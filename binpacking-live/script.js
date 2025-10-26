function q(id){return document.getElementById(id)}

let timer = null;
let prevPods = new Set();
let tooltipEl = null;
let loadingTimer = null;

function clearEl(el){el.innerHTML=''}

async function fetchLive(){
  const resource = q('resource').value;
  const namespaces = q('namespaces').value.trim();
  const params = new URLSearchParams();
  params.set('resource', resource);
  if (namespaces) params.set('ns', namespaces);
  const res = await fetch(`liveData.php?${params.toString()}`);
  if(!res.ok){
    const text = await res.text();
    throw new Error(text || `HTTP ${res.status}`);
  }
  return res.json();
}

// hash estável para embaralhar dentro de cada grupo sem flicker entre atualizações
function stableHash(s){
  if(!s) return 0;
  let h = 5381;
  for(let i=0;i<s.length;i++){
    h = ((h << 5) + h) + s.charCodeAt(i);
    h = h | 0; // força 32-bit
  }
  return h >>> 0; // unsigned
}

function renderBins(data){
  const bins = q('bins');
  clearEl(bins);
  const nextPods = new Set();
  // montar pares (node/bin) para poder ordenar por grupos de função
  const entries = (data.bins || []).map((b, i)=>{
    const node = (data.nodes || [])[i] || {};
    const role = node.role || 'Worker';
    const roleGroup = (role === 'Worker') ? 0 : (role === 'Master' ? 1 : 2);
    // hash estável por nome/ip para pseudo-aleatoriedade estável dentro do grupo
    const key = (node.name || '') + '|' + (node.ip || '') + '|' + i;
    const weight = stableHash(key);
    return { b, i, node, role, roleGroup, weight };
  });

  // ordenar: Workers (0), Masters (1), Infra (2); dentro do grupo, por peso estável
  entries.sort((a, b)=>{
    if(a.roleGroup !== b.roleGroup) return a.roleGroup - b.roleGroup;
    if(a.weight !== b.weight) return a.weight - b.weight;
    return a.i - b.i; // fallback estável
  });

  entries.forEach(({ b, i, node, role })=>{
    const col = document.createElement('div');
    // Classe base + classe por função do nó (Worker/Master/InfraNode)
    const title = document.createElement('div');
    title.className='col-title';
  const roleClass = (role === 'Worker') ? 'col-worker' : (role === 'Master' ? 'col-master' : 'col-infra');
  const badgeClass = roleClass.replace('col-','role-');
  col.className = `col ${roleClass}`;
  const ip = node?.ip || 'N/A';
  const usedPct = (node?.usedPct ?? null);
  const usedEffPct = (node?.usedEffPct ?? null);
  const rp = usedPct!==null ? `<span class="ratio">${usedPct}%</span>` : '';
  const ep = usedEffPct!==null ? `<span class="eff">${usedEffPct}%</span>` : '<span class="eff">N/A</span>';
  const roleBadge = `<span class="role-badge ${badgeClass}">${role}</span>`;
  title.innerHTML = `${roleBadge} • ${ip} ${rp?('• '+rp):''} • ${ep}`;
    col.appendChild(title);

    const grid = document.createElement('div');
  grid.className = 'pods-grid';
    b.forEach(it=>{
      const units = Math.max(0, it.sizeUnits||0);
      const pod = document.createElement('div');
      let cls = 'pod';
      if (it.terminating) cls += ' terminating';
      else if (it.creating) cls += ' creating';
      else if ((it.phase||'').toLowerCase() === 'running') cls += ' running';
      pod.className = cls;
      // lado do quadrado: escala sublinear para evitar exageros
      const side = units===0 ? 12 : Math.max(14, Math.min(60, Math.round(Math.sqrt(units)*10)));
      pod.style.width = side + 'px';
      pod.style.height = side + 'px';
      // montar tooltip rico (nome, CPU e Memória)
  const tt = `${it.id}\nFase: ${(it.phase||'')}\nCPU: ${it.cpuHuman ?? (it.cpu_m?it.cpu_m+'m':'0m')}\nMem: ${it.memHuman ?? '0 Mi'}`;
      pod.title = tt; // fallback nativo
      // custom tooltip (mais bonito)
      pod.addEventListener('mouseenter', (e)=> showTooltip(tt, e.pageX, e.pageY));
      pod.addEventListener('mousemove', (e)=> moveTooltip(e.pageX, e.pageY));
      pod.addEventListener('mouseleave', hideTooltip);

      // animação para pods novos
      nextPods.add(it.id);
      if(!prevPods.has(it.id)){
        pod.classList.add('new');
      }
      grid.appendChild(pod);
    });
    col.appendChild(grid);
    bins.appendChild(col);
  });
  // atualizar referência de pods para próxima rodada
  prevPods = nextPods;
}

function renderStats(data){
  const res = q('resource').value;
  const unit = res==='cpu' ? 'unid(100m)' : 'unid(256Mi)';
  const perBin = data.perBinAllowedUnits ? `(por nó ${data.perBinAllowedUnits} ${unit}) ` : '';
  q('stats').textContent = `Recurso: ${res.toUpperCase()} — Used: ${data.totalUsedUnits} / Available: ${data.totalAvailableUnits} ${unit} ${perBin}— Nodes: ${data.bins.length} — PackRatio: ${data.binPackRatio}`;
}

function renderPending(data){
  const el = q('pendingGrid');
  if(!el) return;
  el.innerHTML = '';
  const list = data.pending || [];
  const titleEl = document.getElementById('pendingTitle');
  if (titleEl) {
    titleEl.textContent = `Pending (${list.length})`;
  }
  if(list.length === 0){
    el.textContent = 'Nenhum pod em Pending.';
    return;
  }
  list.forEach(it=>{
    const units = Math.max(0, it.sizeUnits||0);
    const pod = document.createElement('div');
    pod.className = 'pod pending' + (units===0 ? ' zero':'');
    const side = units===0 ? 12 : Math.max(14, Math.min(60, Math.round(Math.sqrt(units)*10)));
    pod.style.width = side + 'px';
    pod.style.height = side + 'px';
    const tt = `${it.id}\nCPU: ${it.cpuHuman ?? (it.cpu_m?it.cpu_m+'m':'0m')}\nMem: ${it.memHuman ?? '0 Mi'}`;
    pod.title = tt;
    pod.addEventListener('mouseenter', (e)=> showTooltip(tt, e.pageX, e.pageY));
    pod.addEventListener('mousemove', (e)=> moveTooltip(e.pageX, e.pageY));
    pod.addEventListener('mouseleave', hideTooltip);
    el.appendChild(pod);
  });
}

async function load(){
  q('btnRefresh').disabled = true; q('btnToggle').disabled = true;
  try{
    const resType = q('resource').value === 'cpu' ? 'CPU' : 'Memória';
    showLoading(`Sincronizando ${resType}…`);
    const data = await fetchLive();
    renderBins(data);
    renderStats(data);
    renderPending(data);
  }catch(e){
    const bins = q('bins');
    clearEl(bins);
    const d = document.createElement('div');
    d.className='suggest';
    d.textContent = `Erro ao carregar: ${e.message}`;
    bins.appendChild(d);
  }finally{
    q('btnRefresh').disabled = false; q('btnToggle').disabled = false;
    hideLoading();
  }
}

function start(){
  const iv = Math.max(2, parseInt(q('interval').value || '10', 10));
  if(timer) clearInterval(timer);
  timer = setInterval(load, iv*1000);
  q('btnToggle').textContent = 'Parar Auto-Atualização';
}

function stop(){
  if(timer){ clearInterval(timer); timer = null; }
  q('btnToggle').textContent = 'Iniciar Auto-Atualização';
}

document.addEventListener('DOMContentLoaded', ()=>{
  // criar tooltip dinâmico
  tooltipEl = document.createElement('div');
  tooltipEl.id = 'tooltip';
  document.body.appendChild(tooltipEl);
  q('btnRefresh').addEventListener('click', ()=> load());
  q('btnToggle').addEventListener('click', ()=>{ if(timer) stop(); else start(); });
  q('resource').addEventListener('change', ()=> load());
  load();
});

function showTooltip(text, x, y){
  if(!tooltipEl) return;
  tooltipEl.textContent = text;
  tooltipEl.style.opacity = '1';
  moveTooltip(x, y);
}
function moveTooltip(x, y){
  if(!tooltipEl) return;
  const pad = 10;
  tooltipEl.style.left = (x + pad) + 'px';
  tooltipEl.style.top  = (y + pad) + 'px';
}
function hideTooltip(){
  if(!tooltipEl) return;
  tooltipEl.style.opacity = '0';
}

// Loading helpers (evitam flicker em cargas rápidas)
function showLoading(msg='Carregando…'){
  const overlay = document.getElementById('loadingOverlay');
  const msgEl = document.getElementById('loadingMsg');
  if(!overlay||!msgEl) return;
  msgEl.textContent = msg;
  clearTimeout(loadingTimer);
  loadingTimer = setTimeout(()=>{
    overlay.classList.remove('hidden');
  }, 120); // aparece somente se demorar um pouco
}
function hideLoading(){
  const overlay = document.getElementById('loadingOverlay');
  if(!overlay) return;
  clearTimeout(loadingTimer);
  overlay.classList.add('hidden');
}
