<?php
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Binpacking — Simulador de Scheduler</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="container">
    <header class="site-header">
      <img class="rh-logo" src="https://www.redhat.com/rhdc/managed-files/rhb-logos-red_hat_logo-hero_image_1.svg" alt="Red Hat"/>
      <div>
        <h1>Simulador: Como o Scheduler do Kubernetes Decide</h1>
        <p class="subtitle">Clique em "Recarregar" para avançar uma rodada — novos pods entram, consumo sobe, o scheduler atribui nós e o sistema pode evictar pods conforme QoS.</p>
      </div>
    </header>

    <section class="controls">
      <div class="field"><label>Número de nós</label><input id="numNodes" type="number" min="1" value="4"></div>
      <div class="field"><label>Capacidade por nó (units)</label><input id="nodeCap" type="number" min="5" value="30"></div>
      <div class="field"><label>Número de apps por rodada</label><input id="numApps" type="number" min="1" value="8"></div>
      <div class="field"><label>Variabilidade máx. pedido</label><input id="maxReq" type="number" min="1" value="8"></div>
  <div class="field"><label>Seed (opcional)</label><input id="seed" type="text" placeholder="ex: 12345"></div>
      <div class="actions">
        <button id="btnBack" class="btn">◀ Voltar</button>
        <button id="btnLoad" class="btn primary">Recarregar ▶</button>
        <button id="btnForward" class="btn">Avançar ▶</button>
        <button id="btnReset" class="btn">Resetar</button>
      </div>
    </section>

    <section class="explain">
      <h3>Como funciona (resumo)</h3>
      <ul>
        <li>Scheduler valida se o pod cabe no nó (requests vs capacidade).</li>
        <li>Entre candidatos, escolhe o nó que deixa menor espaço livre residual (fit tight) — simula otimização de binpacking.</li>
        <li>Se o consumo real dos pods ultrapassar a capacidade do nó, acontece pressão e o sistema pode evictar pods.</li>
        <li>Evicções seguem QoS: BestEffort → Burstable → Guaranteed. Pods sem requests são os primeiros a serem removidos.</li>
      </ul>
    </section>

    <section class="visual">
      <div id="nodes" class="nodes"></div>
      <aside class="side">
        <div class="panel">
          <h4>Evicted / Unscheduled</h4>
          <div id="issues"></div>
        </div>
        <div class="panel">
          <h4>Decisões (por pod)</h4>
          <div id="decisions" class="decisions"></div>
        </div>
      </aside>
    </section>

    <footer class="site-footer">Demonstração educativa — não substitui a leitura da documentação oficial do Kubernetes.</footer>
  </main>

  <script src="script.js"></script>
</body>
</html>
