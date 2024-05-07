<?php
$num_items = 20; // Número de itens
$max_item_size = 10; // Tamanho máximo de cada item
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
$used_bins = 0; // Contador para o número de bins utilizados

foreach ($items as $item) {
    $placed = false;
    foreach ($bins as &$bin) {
        if (array_sum($bin) + $item <= $bin_capacity) {
            $bin[] = $item;
            $placed = true;
            break;
        }
    }
    if (!$placed) {
        $bins[] = [$item];
        $used_bins++; // Incrementa apenas quando um novo bin é utilizado
    }
}

$available_volume = $used_bins * $bin_capacity; // Calcula o volume disponível com base nos bins utilizados
$bin_pack_ratio = $total_item_volume / $available_volume; // Correção para calcular o Bin Pack Ratio

$response = [
    'bins' => $bins,
    'totalUsedVolume' => $total_item_volume, // Soma total dos volumes dos itens
    'totalAvailableVolume' => $available_volume, // Volume total disponível nos bins utilizados
    'binPackRatio' => number_format($bin_pack_ratio, 2) // Formata para duas casas decimais
];

echo json_encode($response);
?>
