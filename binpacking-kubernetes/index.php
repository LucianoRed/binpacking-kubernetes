<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bin Packing Visualization</title>
    <!-- <link rel="stylesheet" href="style.css"> -->
    <style>
.bin {
    width: 100px;
    height: 300px;
    border: 2px solid black;
    margin: 10px;
    float: left;
    position: relative;
    overflow: hidden;
}

.item {
    width: 100%;
    position: relative;
    border: 1px solid red;
    text-align: center;
    background-color: lightblue;
}

.unallocated {
    border: 1px solid red; /* borda vermelha para destaque */
    background-color: pink; /* cor de fundo rosa para fácil identificação */
    color: black; /* cor do texto */
    text-align: center; /* centralizar o texto */
}

.unallocated-bin {
    width: 100px;
    height: auto; /* Altura automática para acomodar qualquer número de itens */
    border: 2px solid red; /* Borda vermelha para destaque */
    background-color: pink; /* Fundo rosa */
    margin: 10px;
    float: left;
    position: relative;
}

.unallocated-item {
    width: 100%;
    border: 1px solid darkred; /* Borda escura para itens não alocados */
    background-color: lightpink; /* Fundo mais claro para itens dentro do bin não alocado */
    text-align: center; /* Centralizar texto */
    margin-top: 2px; /* Espaço entre itens */
}


    </style>
</head>
<body>
    <label for="maxRatio">Max Bin Pack Ratio:</label>
    <input type="number" id="maxRatio" step="0.1" value="0.8" min="0" max="1" style="margin-right: 10px;">

    <label for="maxBins">Max Number of Nodes:</label>
    <input type="number" id="maxBins" value="5" min="1" style="margin-right: 10px;">

    <label for="maxItemSize">Max Request Size:</label>
    <input type="number" id="maxItemSize" value="10" min="1" style="margin-right: 10px;">

    <label for="numItems">Number of Apps:</label>
    <input type="number" id="numItems" value="20" min="1" style="margin-right: 10px;">

    <button onclick="loadItems()">Recarregar Nodes</button>

    <div id="bins"></div>
    <div id="binPackRatio" style="margin-top: 20px; font-size: 18px;"></div>
    <div id="totalVolume" style="margin-top: 20px; font-size: 16px;"></div>
    <div id="packList" style="margin-top: 20px; font-size: 16px;"></div>
    <div id="unallocatedItems" style="color: red; margin-top: 20px; font-size: 16px;"></div>

    <script>
function loadItems() {
    const maxRatio = document.getElementById('maxRatio').value;
    const maxBins = document.getElementById('maxBins').value;
    const maxItemSize = document.getElementById('maxItemSize').value;
    const numItems = document.getElementById('numItems').value;
    fetch(`loadItems.php?maxRatio=${maxRatio}&maxBins=${maxBins}&maxItemSize=${maxItemSize}&numItems=${numItems}`)
        .then(response => response.json())
        .then(data => {
            const binsElement = document.getElementById('bins');
            const ratioElement = document.getElementById('binPackRatio');
            const totalVolumeElement = document.getElementById('totalVolume');
            binsElement.innerHTML = '';
            data.bins.forEach((bin, index) => {
                const binElement = document.createElement('div');
                binElement.className = 'bin';
                bin.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'item';
                    itemElement.style.height = `${item * 30}px`;
                    itemElement.innerText = item;
                    binElement.appendChild(itemElement);
                });
                binsElement.appendChild(binElement);
            });

            // Create separate bins for each unallocated item
            data.unallocatedItems.forEach(item => {
                const unallocatedBin = document.createElement('div');
                unallocatedBin.className = 'unallocated-bin';
                const itemElement = document.createElement('div');
                itemElement.className = 'unallocated-item';
                itemElement.style.height = `${item * 30}px`;
                itemElement.innerText = item;
                unallocatedBin.appendChild(itemElement);
                binsElement.appendChild(unallocatedBin); // Add each unallocated item in a separate bin
            });

            ratioElement.textContent = `Bin Pack Ratio: ${data.binPackRatio}`;
            totalVolumeElement.textContent = `Total Used Volume: ${data.totalUsedVolume}, Total Available Volume: ${data.totalAvailableVolume}`;
        });
}


    </script>
</body>
</html>
