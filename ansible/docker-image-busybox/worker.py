#!/usr/bin/env python3
import os
import time
import random
import math
import sys

# Environment variables to influence behavior
REQUEST_M = int(os.getenv('REQUEST_M', '0'))
LIMIT_M = int(os.getenv('LIMIT_M', '0'))
MEMORY_MB = int(os.getenv('MEMORY_MB', '0'))

def busy_work(duration_seconds=0.2):
    # Do some CPU work for duration_seconds
    end = time.time() + duration_seconds
    x = 0.0001
    while time.time() < end:
        x += math.sqrt(x + random.random())

def main():
    print(f"worker start request={REQUEST_M}m limit={LIMIT_M}m memory={MEMORY_MB}Mi")
    # If requested, allocate memory and hold it to simulate memory pressure
    allocated = []
    if MEMORY_MB > 0:
        try:
            print(f"Allocating {MEMORY_MB} MiB of memory")
            # allocate in 1MiB chunks to avoid a single huge allocation
            for _ in range(MEMORY_MB):
                allocated.append(bytearray(1024 * 1024))
            print("Memory allocation complete")
        except MemoryError:
            print("Memory allocation failed (MemoryError)")
        except Exception as e:
            print('Memory allocation error', e)
    # Loop forever: randomly choose a multiplier that may exceed request or limit
    while True:
        # choose a random multiplier between 0.2x and 1.8x
        mult = random.uniform(0.2, 1.8)
        # base seconds to busy for is proportional to perceived CPU consumption
        # map millicpu to seconds of work per loop (heuristic)
        base_cpu = max(1, LIMIT_M if LIMIT_M>0 else max(1, REQUEST_M))
        # duration proportional to cpu fraction
        duration = (base_cpu / 1000.0) * mult
        # cap duration between small bounds to avoid long blocks
        duration = max(0.05, min(1.5, duration))
        busy_work(duration)
        # sleep a bit to allow variability
        time.sleep(random.uniform(0.1, 0.6))

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print('worker error', e, file=sys.stderr)
        sys.exit(1)
