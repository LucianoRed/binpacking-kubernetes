#!/usr/bin/env python3
import os, sys, time, threading, signal

# ====== Config via env ======
MEM_TARGET_MB       = int(os.getenv("MEM_TARGET_MB", "200"))          # alvo de memória
MEM_RAMP_SECONDS    = float(os.getenv("MEM_RAMP_SECONDS", "60"))      # tempo p/ atingir alvo de memória
CPU_TARGET_PERCENT  = float(os.getenv("CPU_TARGET_PERCENT", "0"))     # 0 desativa CPU load
CPU_RAMP_SECONDS    = float(os.getenv("CPU_RAMP_SECONDS", str(MEM_RAMP_SECONDS)))
CPU_THREADS         = int(os.getenv("CPU_THREADS", str(os.cpu_count() or 1)))
PRINT_EVERY_SEC     = float(os.getenv("PRINT_EVERY_SEC", "2"))        # logs periódicos
TOUCH_MEMORY        = os.getenv("TOUCH_MEMORY", "1") != "0"           # escrever nos blocos (garante commit)
SLICE_MS            = int(os.getenv("CPU_SLICE_MS", "100"))           # granularidade do duty-cycle
MEM_LOG_STEP_MB     = int(os.getenv("MEM_LOG_STEP_MB", "50"))         # marcos de log de memória
PRINT_ON_ALLOC      = os.getenv("PRINT_ON_ALLOC", "0") == "1"         # log a cada alocação (verboso)

# ====== Estado global ======
stop_flag = False
allocations = []  # mantém referências para não coletar

def handle_sigterm(signum, frame):
    global stop_flag
    stop_flag = True

signal.signal(signal.SIGTERM, handle_sigterm)
signal.signal(signal.SIGINT, handle_sigterm)

def human(n_bytes):
    return f"{n_bytes/1024/1024:.1f}MB"

def get_rss_bytes():
    """RSS do processo via /proc/self/statm * page_size."""
    try:
        with open("/proc/self/statm", "r") as f:
            parts = f.read().split()
        rss_pages = int(parts[1])
        page_size = os.sysconf("SC_PAGE_SIZE")
        return rss_pages * page_size
    except Exception:
        return 0

def get_cgroup_mem_current():
    """Uso atual de memória do cgroup (v2) ou retorna 0 se indisponível."""
    paths = [
        "/sys/fs/cgroup/memory.current",               # cgroup v2
        "/sys/fs/cgroup/memory/memory.usage_in_bytes"  # cgroup v1
    ]
    for p in paths:
        try:
            if os.path.exists(p):
                with open(p, "r") as f:
                    return int(f.read().strip())
        except Exception:
            pass
    return 0

# ====== Ramp de Memória ======
def memory_ramp():
    if MEM_TARGET_MB <= 0 or MEM_RAMP_SECONDS <= 0:
        return
    target_bytes = MEM_TARGET_MB * 1024 * 1024
    start = time.time()
    allocated = 0
    chunk_size = 1024 * 1024  # ~1MB por iteração

    # limiar para logs de marcos (50MB por padrão)
    step_bytes = max(1, MEM_LOG_STEP_MB) * 1024 * 1024
    next_mark = step_bytes

    while not stop_flag and allocated < target_bytes:
        elapsed = time.time() - start
        ramp_target = min(1.0, elapsed / MEM_RAMP_SECONDS) * target_bytes
        need = int(ramp_target - allocated)
        if need <= 0:
            time.sleep(0.02)
            continue

        to_alloc = min(need, chunk_size)
        try:
            block = bytearray(to_alloc)
            if TOUCH_MEMORY:
                step = 4096
                for i in range(0, to_alloc, step):
                    block[i] = 1
            allocations.append(block)
            allocated += to_alloc
        except MemoryError:
            print("[mem] MemoryError: cgroup/limite de memória atingido.", flush=True)
            break

        if PRINT_ON_ALLOC or allocated >= next_mark or allocated >= target_bytes:
            rss = get_rss_bytes()
            cgroup_now = get_cgroup_mem_current()
            msg = (f"[mem] allocated={human(allocated)} "
                   f"rss={human(rss)} "
                   f"cgroup_current={(human(cgroup_now) if cgroup_now else 'n/a')} "
                   f"target={MEM_TARGET_MB}MB")
            print(msg, flush=True)
            while allocated >= next_mark:
                next_mark += step_bytes

        time.sleep(0.005)

# ====== Carga de CPU com duty cycle e rampa ======
def cpu_worker(thread_idx, start_time):
    if CPU_TARGET_PERCENT <= 0:
        return
    slice_s = SLICE_MS / 1000.0
    while not stop_flag:
        elapsed = time.time() - start_time
        total_target = max(0.0, min(1000.0, CPU_TARGET_PERCENT))  # permite até 1000% se quiser
        current_total = total_target if CPU_RAMP_SECONDS <= 0 else min(total_target, total_target * (elapsed / CPU_RAMP_SECONDS))
        per_thread = current_total / max(1, CPU_THREADS)
        per_thread = max(0.0, min(100.0, per_thread))
        if per_thread <= 0.0:
            time.sleep(slice_s)
            continue
        busy = slice_s * (per_thread / 100.0)
        idle = max(0.0, slice_s - busy)
        t0 = time.perf_counter()
        while (time.perf_counter() - t0) < busy and not stop_flag:
            pass
        if idle > 0:
            time.sleep(idle)

# ====== Logger periódico ======
def logger(start):
    last = 0
    while not stop_flag:
        now = time.time()
        if now - last >= PRINT_EVERY_SEC:
            last = now
            mem = sum(len(b) for b in allocations)
            rss = get_rss_bytes()
            cgroup_now = get_cgroup_mem_current()
            print(
                f"[stats] t={now-start:.1f}s  "
                f"allocated={human(mem)}/{MEM_TARGET_MB}MB  "
                f"rss={human(rss)}  "
                f"cgroup_current={(human(cgroup_now) if cgroup_now else 'n/a')}  "
                f"cpu_target={CPU_TARGET_PERCENT}%  threads={CPU_THREADS}",
                flush=True
            )
        time.sleep(0.1)

def main():
    print("=== stress container ===", flush=True)
    print(f"MEM_TARGET_MB={MEM_TARGET_MB}  MEM_RAMP_SECONDS={MEM_RAMP_SECONDS}", flush=True)
    print(f"CPU_TARGET_PERCENT={CPU_TARGET_PERCENT}  CPU_RAMP_SECONDS={CPU_RAMP_SECONDS}  CPU_THREADS={CPU_THREADS}", flush=True)
    print(f"MEM_LOG_STEP_MB={MEM_LOG_STEP_MB}  PRINT_ON_ALLOC={int(PRINT_ON_ALLOC)}", flush=True)

    start = time.time()

    threads = []
    t_mem = threading.Thread(target=memory_ramp, daemon=True)
    t_mem.start()
    threads.append(t_mem)

    for i in range(max(0, CPU_THREADS)):
        t = threading.Thread(target=cpu_worker, args=(i, start), daemon=True)
        t.start()
        threads.append(t)

    t_log = threading.Thread(target=logger, args=(start,), daemon=True)
    t_log.start()

    try:
        while not stop_flag:
            time.sleep(0.2)
    finally:
        print("Encerrando...", flush=True)

if __name__ == "__main__":
    main()