<?php
// Lista dinamicamente diretórios que contenham 'binpacking' no nome
$dirs = array_values(array_filter(glob('*', GLOB_ONLYDIR), function($d){
    return stripos($d, 'binpacking') !== false;
}));
sort($dirs);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Binpacking — Índice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#0f1724;--card:#0b1220;--accent:#e53e3e;--muted:#9aa4b2}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;background:linear-gradient(180deg,#071025 0%, #07172a 50%, #071a2f 100%);color:#e6eef6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px}
    .wrap{width:100%;max-width:1100px}
  header{display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:28px;text-align:center}
  .logo{display:flex;align-items:center;justify-content:center}
  .logo img{height:96px}
  h1{margin:0;font-size:1.8rem}
  p.lead{margin:4px 0 0;color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
    .card{background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.03);padding:18px;border-radius:12px;transition:transform .15s,box-shadow .15s}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 30px rgba(2,6,23,0.6)}
    .card h3{margin:0 0 8px;font-size:1.05rem}
    .card p{margin:0;color:var(--muted);font-size:0.92rem}
    .actions{display:flex;gap:8px;margin-top:12px}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;text-decoration:none;color:#fff;background:var(--accent);font-weight:600}
    .btn.secondary{background:transparent;border:1px solid rgba(255,255,255,0.06);color:var(--muted)}
    footer{margin-top:26px;color:var(--muted);font-size:0.85rem;text-align:center}
    .empty{padding:28px;border-radius:10px;background:linear-gradient(180deg,rgba(255,255,255,0.01),transparent);text-align:center;color:var(--muted)}
  @media (max-width:520px){header{flex-direction:column;align-items:center} .logo img{height:72px}}
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <div class="logo">
        <a href="https://www.redhat.com/" target="_blank" rel="noopener">
          <img src="https://www.redhat.com/rhdc/managed-files/rhb-logos-red_hat_logo-hero_image_1.svg" alt="Red Hat" />
        </a>
      </div>
      <div>
        <h1>Projetos Binpacking</h1>
        <p class="lead">Links rápidos para cada subdiretório relacionado a binpacking neste repositório.</p>
      </div>
    </header>

    <?php if(empty($dirs)): ?>
      <div class="empty">Nenhum diretório contendo "binpacking" foi encontrado neste nível.</div>
    <?php else: ?>
      <div class="grid">
        <?php foreach($dirs as $d): ?>
          <div class="card">
            <h3><?php echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?></h3>
            <p>Abrir o diretório <strong><?php echo htmlspecialchars($d); ?></strong> para ver a aplicação correspondente.</p>
            <div class="actions">
              <a class="btn" href="<?php echo rawurlencode($d); ?>/">Abrir</a>
              <a class="btn secondary" href="<?php echo rawurlencode($d); ?>/index.php">Índice</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <footer>
      Gerado dinamicamente — <?php echo date('d/m/Y H:i'); ?> — Navegue clicando nos cartões acima.
    </footer>
  </div>
</body>
</html>
