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
