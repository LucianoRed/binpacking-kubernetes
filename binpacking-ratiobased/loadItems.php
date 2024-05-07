<?php
$num_items = 20; // Número de itens
$max_item_size = 10; // Tamanho máximo de cada item
$max_bin_pack_ratio = isset($_GET['maxRatio']) ? (float)$_GET['maxRatio'] : 0.8; // Lê o valor enviado ou usa 0.8 como padrão
$items = [];
$total_item_volume = 0;

for ($i = 0; $i < $num_items; $i++) {
    $item_size = rand(1, $max_item_size);
    $items[] = $item_size;
    $total_item_volume += $item_size;
}

$bins = [];
$bin_capacity = 10;
$used_volume = 0;
$used_bins = 0;

foreach ($items as $item) {
    $placed = false;
    foreach ($bins as &$bin) {
        if (array_sum($bin) + $item <= $bin_capacity) {
            $temp_total_volume = $used_volume + $item;
            $temp_available_volume = ($used_bins + (count($bin) == 0 ? 1 : 0)) * $bin_capacity;
            $temp_ratio = $temp_total_volume / $temp_available_volume;
            if ($temp_ratio <= $max_bin_pack_ratio) {
                $bin[] = $item;
                $placed = true;
                $used_volume = $temp_total_volume;
                break;
            }
        }
    }
    if (!$placed) {
        $bins[] = [$item];
        $used_bins++;
        $used_volume += $item;
    }
}

$available_volume = $used_bins * $bin_capacity;
$bin_pack_ratio = $total_item_volume / $available_volume;

$response = [
    'bins' => $bins,
    'totalUsedVolume' => $total_item_volume,
    'totalAvailableVolume' => $available_volume,
    'binPackRatio' => number_format($bin_pack_ratio, 2)
];

echo json_encode($response);
?>
