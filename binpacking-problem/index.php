<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bin Packing Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="../index.php" style="position:fixed;top:12px;right:12px;padding:8px 12px;border-radius:6px;background:#e53e3e;color:#fff;text-decoration:none;font-weight:600;z-index:9999">Índice</a>
    <label for="maxRatio">Max Bin Pack Ratio:</label>
    <input type="number" id="maxRatio" step="0.1" value="0.8" min="0" max="1" style="margin-right: 10px;">
    <label for="maxBins">Max Number of Bins:</label>
    <input type="number" id="maxBins" value="5" min="1" style="margin-right: 10px;">
    <label for="maxItemSize">Max Item Size:</label>
    <input type="number" id="maxItemSize" value="10" min="1" style="margin-right: 10px;">
    <label for="numItems">Number of Items:</label>
    <input type="number" id="numItems" value="20" min="1" style="margin-right: 10px;">
    <button onclick="loadItems()">Recarregar Itens</button>
    <div id="bins"></div>
    <div id="binPackRatio" style="margin-top: 20px; font-size: 18px;"></div>
    <div id="totalVolume" style="margin-top: 20px; font-size: 16px;"></div>
    <div id="packList" style="margin-top: 20px; font-size: 16px;"></div>
    <div id="unallocatedItems" style="color: red; margin-top: 20px; font-size: 16px;"></div>
    <script src="script.js"></script>
</body>
</html>
