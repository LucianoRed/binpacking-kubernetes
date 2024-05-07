<?php
$num_items = isset($_GET['numItems']) ? (int)$_GET['numItems'] : 20; // Lê o valor enviado ou usa 20 como padrão
$max_item_size = isset($_GET['maxItemSize']) ? (int)$_GET['maxItemSize'] : 10;
$max_bin_pack_ratio = isset($_GET['maxRatio']) ? (float)$_GET['maxRatio'] : 0.8;
$max_bins = isset($_GET['maxBins']) ? (int)$_GET['maxBins'] : 5;
$items = [];
$total_item_volume = 0;
$unallocatedItems = [];

for ($i = 0; $i < $num_items; $i++) {
    $item_size = rand(1, $max_item_size);
    $items[] = $item_size;
    $total_item_volume += $item_size;
}

$bins = [];
$bin_capacity = 10;
$used_volume = 0; // Volume total dos itens já alocados
$used_bins = 0; // Contador de bins usados

foreach ($items as $item) {
    $placed = false;
    // Verifica se adicionar um novo item não excederá o rácio máximo permitido
    if (($used_volume + $item) / (($used_bins + 1) * $bin_capacity) <= $max_bin_pack_ratio) {
        if ($used_bins < $max_bins) {
            foreach ($bins as &$bin) {
                // Verifica se o item pode ser alocado ao bin atual
                if (array_sum($bin) + $item <= $bin_capacity) {
                    $bin[] = $item;
                    $placed = true;
                    $used_volume += $item;
                    break;
                }
            }
            // Se não houver espaço no bin atual, cria um novo bin
            if (!$placed) {
                $bins[] = [$item];
                $used_bins++;
                $used_volume += $item;
            }
        } else {
            $unallocatedItems[] = $item;
        }
    } else {
        $unallocatedItems[] = $item;
    }
}

$available_volume = $used_bins * $bin_capacity;
$bin_pack_ratio = $total_item_volume / $available_volume;

$response = [
    'bins' => $bins,
    'totalUsedVolume' => $total_item_volume,
    'totalAvailableVolume' => $available_volume,
    'binPackRatio' => number_format($bin_pack_ratio, 2),
    'unallocatedItems' => $unallocatedItems
];

echo json_encode($response);
?>
