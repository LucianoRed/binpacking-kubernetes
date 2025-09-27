function q(id){return document.getElementById(id)}

async function fetchData(vpa=0){
  const maxRatio = q('maxRatio').value;
  const maxBins = q('maxBins').value;
  const maxItemSize = q('maxItemSize').value;
  const vpaLevel = q('vpaLevel') ? q('vpaLevel').value : 30;
  const numItems = q('numItems').value;
  const includeQoS = 1; // always include QoS in this demo
  const res = await fetch(`loadItems.php?maxRatio=${maxRatio}&maxBins=${maxBins}&maxItemSize=${maxItemSize}&numItems=${numItems}&vpa=${vpa}&vpaLevel=${vpaLevel}&qos=${includeQoS}`);
  return res.json();
}

function clear(el){el.innerHTML=''}

function renderBins(data){
  const bins = q('bins');
  clear(bins);
  data.bins.forEach((b, i)=>{
    const col = document.createElement('div');
    col.className='col';
    const title = document.createElement('div'); title.className='col-title'; title.textContent=`Node ${i+1}`;
    col.appendChild(title);
    b.forEach(it=>{
      const item = document.createElement('div');
      item.className='item';
      item.style.height = `${Math.max(20, it.limit*14)}px`;
      // show request/limit explicitly
      const req = typeof it.request !== 'undefined' ? it.request : '?';
      const lim = typeof it.limit !== 'undefined' ? it.limit : it.size;
      item.innerHTML = `<span>${it.id} • req:${req} / lim:${lim}</span> <span class="qos-badge qos-${it.qos}">${it.qos}</span>`;
      col.appendChild(item);
    });
    bins.appendChild(col);
  });
}

function renderVPA(data){
  const el = q('vpaLine');
  if(!data.vpa || data.vpa.length===0){ el.textContent='Nenhuma sugestão.'; return }
  el.innerHTML = '';
  const row = document.createElement('div'); row.className='vpa-inline';
  data.vpa.forEach(s=>{
    const pill = document.createElement('span'); pill.className='vpa-pill'; pill.textContent = `${s.id}: ${s.from}→${s.to}`;
    row.appendChild(pill);
  });
  el.appendChild(row);
}

function renderUnallocated(data){
  const el = q('unallocatedList');
  if(!data.unallocated || data.unallocated.length===0){ el.textContent='Nenhum app desalocado.'; return }
  el.innerHTML = '';
  const grid = document.createElement('div'); grid.className='unallocated-grid';
  data.unallocated.forEach(it=>{
    const item = document.createElement('div'); item.className='item unalloc';
    // show original size if available
    const orig = it.orig_size ? ` (orig ${it.orig_size})` : '';
    const qos = it.qos ? ` <span class="qos-badge qos-${it.qos}">${it.qos}</span>` : '';
    const req = typeof it.request !== 'undefined' ? `req:${it.request} / ` : '';
    item.innerHTML = `<span>${it.id} • ${req}${it.size}${orig}</span>${qos}`;
    grid.appendChild(item);
  });
  el.appendChild(grid);
}

function renderEvicted(data){
  const el = q('evictedList');
  if(!data.evicted || data.evicted.length===0){ el.textContent='Nenhum pod evicted.'; return }
  el.innerHTML = '';
  const ul = document.createElement('div'); ul.className='evicted-grid';
  data.evicted.forEach(e=>{
    const it = document.createElement('div'); it.className='item evicted';
    it.innerHTML = `<span>${e.id} • qos:${e.qos} • orig:${e.orig_size} • node:${e.node}</span>`;
    ul.appendChild(it);
  });
  el.appendChild(ul);
}

function renderStats(data){
  const orig = data.originalTotalVolume ? `Original: ${data.originalTotalVolume} — ` : '';
  const perBin = data.perBinAllowed ? `(per nó permitido ${data.perBinAllowed}) ` : '';
  q('stats').textContent = `${orig}BinPack Ratio: ${data.binPackRatio} — Used: ${data.totalUsedVolume} / Available: ${data.totalAvailableVolume} ${perBin}— Nodes: ${data.bins.length}`;
}

// Chart state
let chart = null;
const historyLabels = [];
const historyValues = [];

function ensureChart(){
  const ctx = document.getElementById('unallocatedChart').getContext('2d');
  if(chart) return chart;
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: historyLabels,
      datasets: [{
        label: 'Não alocados',
        data: historyValues,
        backgroundColor: 'rgba(229,62,62,0.2)',
        borderColor: 'rgba(229,62,62,0.9)',
        fill: true,
        tension: 0.3,
        pointRadius: 5,
      }]
    },
    options: {
      scales: {
        x: { title: { display: true, text: 'Rodada / Máx Request' } },
        y: { title: { display: true, text: 'Pods desalocados' }, beginAtZero: true, precision:0 }
      },
      plugins: { legend: { display: false } }
    }
  });
  return chart;
}

async function load(vpa=0){
  q('btnLoad').disabled = true; q('btnVPA').disabled = true;
  try{
    const data = await fetchData(vpa);
    renderBins(data);
    renderStats(data);
    renderVPA(data);
    renderUnallocated(data);
    renderEvicted(data);
    const vpaLevel = q('vpaLevel') ? q('vpaLevel').value : '';
    const label = `R${historyLabels.length+1} / M=${q('maxItemSize').value}${vpa?(' / VPA-'+vpaLevel+'%'):''}`;
    const val = (data.unallocated && data.unallocated.length) ? data.unallocated.length : 0;
    historyLabels.push(label);
    historyValues.push(val);
    ensureChart();
    chart.update();
  }finally{
    q('btnLoad').disabled = false; q('btnVPA').disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', ()=>{
  q('btnLoad').addEventListener('click', ()=>load(0));
  q('btnVPA').addEventListener('click', ()=>load(1));
  load(0);
});
