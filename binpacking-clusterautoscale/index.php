<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bin Packing Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="../index.php" style="position:fixed;top:12px;right:12px;padding:8px 12px;border-radius:6px;background:#e53e3e;color:#fff;text-decoration:none;font-weight:600;z-index:9999">Índice</a>
    <button onclick="loadItems()">Recarregar Itens</button>
    <div id="bins"></div>
    <div id="binPackRatio" style="margin-top: 20px; font-size: 18px;"></div>
    <div id="totalVolume" style="margin-top: 20px; font-size: 16px;"></div> <!-- Elemento para mostrar o volume total utilizado -->
    <div id="packList" style="margin-top: 20px; font-size: 16px;"></div> <!-- Elemento para mostrar a lista de packs -->
    <script src="script.js"></script>
</body>
</html>
