function q(id){return document.getElementById(id)}

async function fetchState(action='next'){
  const numNodes = q('numNodes').value;
  const nodeCap = q('nodeCap').value;
  const numApps = q('numApps').value;
  const maxReq = q('maxReq').value;
  const seed = q('seed').value;
  const url = `loadItems.php?action=${action}&numNodes=${numNodes}&nodeCap=${nodeCap}&numApps=${numApps}&maxReq=${maxReq}${seed?('&seed='+encodeURIComponent(seed)):''}`;
  const res = await fetch(url);
  return res.json();
}

function render(state){
  const nodesEl = q('nodes'); nodesEl.innerHTML = '';
  state.nodes.forEach(n=>{
    const div = document.createElement('div'); div.className='node';
    const title = document.createElement('div'); title.className='node-title'; title.textContent = `${n.id} — cap ${n.capacity}`;
    div.appendChild(title);
    const used = n.pods.reduce((s,p)=>s+p.usage,0);
    const bar = document.createElement('div'); bar.className='node-bar';
    const usedEl = document.createElement('div'); usedEl.className='node-used'; usedEl.style.width = Math.min(100, (used/n.capacity)*100)+'%'; usedEl.textContent = `${used}/${n.capacity}`;
    bar.appendChild(usedEl);
    div.appendChild(bar);
    const list = document.createElement('div'); list.className='pod-list';
    n.pods.forEach(p=>{
      const pod = document.createElement('div'); pod.className='pod'; pod.innerHTML = `<span class="pod-id">${p.id}</span> <span class="pod-qos ${p.qos}">${p.qos}</span> <span class="pod-req">req:${p.request}</span> <span class="pod-usage">use:${p.usage}</span>`;
      list.appendChild(pod);
    });
    div.appendChild(list);
    nodesEl.appendChild(div);
  });
  // issues
  const issuesEl = q('issues'); issuesEl.innerHTML='';
  if(state.issues.evicted.length===0 && state.issues.unscheduled.length===0) issuesEl.textContent='Nenhuma ação';
  state.issues.evicted.forEach(e=>{
    const el = document.createElement('div'); el.className='issue evicted'; el.textContent = `${e.pod.id} evicted from ${e.from} (usage ${e.pod.usage}, qos ${e.pod.qos})`;
    issuesEl.appendChild(el);
  });
  state.issues.unscheduled.forEach(u=>{ const el = document.createElement('div'); el.className='issue unsched'; el.textContent = `${u.id} unscheduled (req ${u.request}, qos ${u.qos})`; issuesEl.appendChild(el); });

  // decisions
  const decEl = q('decisions'); decEl.innerHTML='';
  state.decisions.forEach(d=>{ const p = document.createElement('div'); p.className='decision'; p.textContent = d; decEl.appendChild(p); });
  // detailed candidates if available
  if (state.decision_details) {
    const hdr = document.createElement('div'); hdr.className='decision-hdr'; hdr.textContent='Detalhes (candidatos)'; decEl.appendChild(hdr);
    state.decision_details.forEach(dd=>{
      const p = document.createElement('div'); p.className='decision-d';
      p.innerHTML = `<strong>${dd.pod}</strong> req=${dd.pod_request} qos=${dd.pod_qos} — escolhido: ${dd.chosen}`;
      const list = document.createElement('div'); list.className='cand-list';
      dd.candidates.forEach(c=>{ const cdiv = document.createElement('div'); cdiv.className='cand'; cdiv.textContent = `${c.node} (residual ${c.residual}, pods ${c.numPods})`; list.appendChild(cdiv); });
      p.appendChild(list);
      decEl.appendChild(p);
    });
  }
}

document.addEventListener('DOMContentLoaded', ()=>{
  q('btnLoad').addEventListener('click', async ()=>{ const s = await fetchState('next'); render(s); updateNav(s); });
  q('btnReset').addEventListener('click', async ()=>{ const s = await fetchState('reset'); render(s); updateNav(s); });
  q('btnBack').addEventListener('click', async ()=>{ const s = await fetchState('back'); render(s); updateNav(s); });
  q('btnForward').addEventListener('click', async ()=>{ const s = await fetchState('forward'); render(s); updateNav(s); });
  // initial load
  fetchState('next').then(s=>{ render(s); updateNav(s); });
});

function updateNav(s){
  const pos = s.pos ?? 0; const len = s.historyLen ?? 1;
  q('btnBack').disabled = pos<=0;
  q('btnForward').disabled = pos>=len-1;
}
