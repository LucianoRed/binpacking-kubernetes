function loadItems() {
    fetch('loadItems.php')
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
            ratioElement.textContent = `Bin Pack Ratio: ${data.binPackRatio}`;
            totalVolumeElement.textContent = `Total Used Volume: ${data.totalUsedVolume}, Total Available Volume: ${data.totalAvailableVolume}`;
        });
}
