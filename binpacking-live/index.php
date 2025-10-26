<?php
// Exibe informações básicas de conexão vindas de variáveis de ambiente
$apiUrl = getenv('K8S_API_URL') ?: '';
$maskedUrl = $apiUrl ? htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') : 'não configurada';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Binpacking — Cluster (Live)</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../binpacking-vpa/style.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .conn{font-size:0.9rem;color:var(--muted)}
    .conn strong{color:#fff}
    .field small{opacity:.8}
  </style>
  </head>
<body>
  <a href="../index.php" style="position:fixed;top:12px;right:12px;padding:8px 12px;border-radius:6px;background:#e53e3e;color:#fff;text-decoration:none;font-weight:600;z-index:9999">Índice</a>
  <main class="container">
    <header class="site-header">
      <img class="rh-logo" src="https://www.redhat.com/rhdc/managed-files/rhb-logos-red_hat_logo-hero_image_1.svg" alt="Red Hat"/>
      <div>
        <h1>Binpacking — Cluster (Live)</h1>
        <p class="subtitle">Visualização em tempo quase real dos pods por nó, com base em requests (CPU/Mem) do cluster Kubernetes.</p>
        <div class="conn">API: <strong><?php echo $maskedUrl; ?></strong> — configure via variáveis de ambiente K8S_API_URL e K8S_BEARER_TOKEN.</div>
      </div>
    </header>

    <section class="controls">
      <div class="field">
        <label>Recurso</label>
        <select id="resource">
          <option value="cpu" selected>CPU (millicores)</option>
          <option value="memory">Memória (MiB)</option>
        </select>
      </div>
      <div class="field">
        <label>Namespaces (opcional)</label>
        <input id="namespaces" type="text" placeholder="ex: default,kube-system" />
        <small>Deixe em branco para todos</small>
      </div>
      <div class="field">
        <label>Intervalo (segundos)</label>
        <input id="interval" type="number" min="2" value="10" />
      </div>
      <div class="actions">
        <button id="btnRefresh" class="btn primary">Atualizar agora</button>
        <button id="btnToggle" class="btn">Iniciar Auto-Atualização</button>
      </div>
    </section>

    <section class="summary">
      <div id="stats" class="stats">—</div>
    </section>

    <section id="visual" class="visual">
      <div id="bins" class="bins"></div>
      <aside id="side" class="suggestions">
        <h3>Observações</h3>
        <div id="notes" class="unallocated-list">Pods sem requests são contados como 0 na métrica escolhida.</div>
        <div style="height:10px"></div>
        <h3 id="pendingTitle">Pending</h3>
        <div id="pendingGrid" class="pending-grid">Nenhum pod em Pending.</div>
      </aside>
    </section>

    <footer class="site-footer">Fonte: API do cluster — não altera recursos. Apenas leitura.</footer>
  </main>

  <script src="script.js"></script>
  
  <!-- Overlay de carregamento -->
  <div id="loadingOverlay" class="loading hidden">
    <div class="loader" aria-hidden="true"></div>
    <div class="msg" id="loadingMsg">Carregando…</div>
  </div>
  </body>
  </html>
