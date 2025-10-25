<?php
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Binpacking + VPA — Demonstração</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <a href="../index.php" style="position:fixed;top:12px;right:12px;padding:8px 12px;border-radius:6px;background:#e53e3e;color:#fff;text-decoration:none;font-weight:600;z-index:9999">Índice</a>
  <main class="container">
    <header class="site-header">
      <img class="rh-logo" src="https://www.redhat.com/rhdc/managed-files/rhb-logos-red_hat_logo-hero_image_1.svg" alt="Red Hat"/>
      <div>
        <h1>Binpacking — Exemplo com VPA</h1>
        <p class="subtitle">Exemplo interativo que mostra como um ajuste vertical (VPA) pode melhorar o empacotamento de pods em nós.</p>
      </div>
    </header>

    <section class="controls">
      <div class="field">
        <label>Máx. Bin Pack Ratio</label>
        <input id="maxRatio" type="number" step="0.05" min="0" max="1" value="0.8" />
      </div>
      <div class="field">
        <label>Máx. de Nodes</label>
        <input id="maxBins" type="number" min="1" value="5" />
      </div>
      <div class="field">
        <label>Máx. Request (unit)</label>
        <input id="maxItemSize" type="number" min="1" value="10" />
      </div>
      <div class="field">
        <label>Número de Apps</label>
        <input id="numItems" type="number" min="1" value="20" />
      </div>
      <div class="field">
        <label>Nível VPA</label>
        <select id="vpaLevel">
          <option value="30">Conservador (−30%)</option>
          <option value="50">Agressivo (−50%)</option>
          <option value="70">Muito agressivo (−70%)</option>
        </select>
      </div>
      <div class="actions">
        <button id="btnLoad" class="btn primary">Recarregar</button>
        <button id="btnVPA" class="btn">Simular VPA</button>
      </div>
    </section>

    <section class="summary">
      <div id="stats" class="stats">—</div>
    </section>

    <section class="vpa-row">
      <div class="vpa-line">
        <h3>Sugestões VPA</h3>
        <div id="vpaLine" class="vpa-line-content">Nenhuma sugestão ainda.</div>
      </div>
    </section>

    <section id="visual" class="visual">
      <div id="bins" class="bins"></div>
      <aside id="suggestions" class="suggestions">
        <h3>Não alocados</h3>
        <div id="unallocatedList" class="unallocated-list">Nenhum app desalocado.</div>
      </aside>
      <aside id="chartPanel" class="chartPanel">
        <h3>Não alocados por rodada</h3>
        <canvas id="unallocatedChart" width="300" height="300"></canvas>
        <div class="chart-legend">Cada ponto = uma rodada. Reduza Máx. Request e observe queda nos desalocados.</div>
      </aside>
    </section>

    <footer class="site-footer">Exemplo didático — não para produção. Gerado localmente.</footer>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="script.js"></script>
</body>
</html>
