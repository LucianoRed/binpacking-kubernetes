function q(id){return document.getElementById(id)}

async function fetchData(vpa=0){
  const maxRatio = q('maxRatio').value;
  const maxBins = q('maxBins').value;
  const maxItemSize = q('maxItemSize').value;
  const vpaLevel = q('vpaLevel') ? q('vpaLevel').value : 30;
  const numItems = q('numItems').value;
  const res = await fetch(`loadItems.php?maxRatio=${maxRatio}&maxBins=${maxBins}&maxItemSize=${maxItemSize}&numItems=${numItems}&vpa=${vpa}&vpaLevel=${vpaLevel}`);
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
      item.style.height = `${Math.max(20, it.size*18)}px`;
      item.textContent = `${it.id} • ${it.size}`;
      col.appendChild(item);
    });
    bins.appendChild(col);
  });

  // unallocated: grid com várias colunas
  // nota: a lista de não alocados agora aparece na coluna direita (renderUnallocated)
}

function renderStats(data){
  const orig = data.originalTotalVolume ? `Original: ${data.originalTotalVolume} — ` : '';
  const perBin = data.perBinAllowed ? `(per nó permitido ${data.perBinAllowed}) ` : '';
  q('stats').textContent = `${orig}BinPack Ratio: ${data.binPackRatio} — Used: ${data.totalUsedVolume} / Available: ${data.totalAvailableVolume} ${perBin}— Nodes: ${data.bins.length}`;
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
    const item = document.createElement('div'); item.className='item unalloc'; item.textContent = `${it.id} • ${it.size}`;
    grid.appendChild(item);
  });
  el.appendChild(grid);
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
    // update chart history: label by maxItemSize (request cap) and count of unallocated
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
