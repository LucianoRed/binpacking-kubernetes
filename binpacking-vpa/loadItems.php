<?php
// Parâmetros
$num_items = isset($_GET['numItems']) ? (int)$_GET['numItems'] : 20;
$max_item_size = isset($_GET['maxItemSize']) ? (int)$_GET['maxItemSize'] : 10;
$max_bin_pack_ratio = isset($_GET['maxRatio']) ? (float)$_GET['maxRatio'] : 0.8;
$max_bins = isset($_GET['maxBins']) ? (int)$_GET['maxBins'] : 5;
$apply_vpa = isset($_GET['vpa']) ? (int)$_GET['vpa'] : 0; // 1 para aplicar sugestão de VPA
$vpaLevel = isset($_GET['vpaLevel']) ? (int)$_GET['vpaLevel'] : 30; // percentual: 30,50,70

$items = [];
$total_item_volume = 0;
// mapa de tamanhos originais para cada app id
$origSizes = [];

for ($i = 0; $i < $num_items; $i++) {
    // Gera pedidos com alguma variabilidade
    $item_size = rand(1, $max_item_size);
    $id = 'app-' . ($i+1);
    $items[] = ['id' => $id, 'size' => $item_size];
    $origSizes[$id] = $item_size;
    $total_item_volume += $item_size;
}

$original_total_item_volume = $total_item_volume;

$bin_capacity = 10;
$bins = [];
$used_bins = 0;
$used_volume = 0;
$unallocated = [];

// Limites derivados do max_bin_pack_ratio
$per_bin_allowed = (int) floor($bin_capacity * $max_bin_pack_ratio); // ex: 10 * 0.8 => 8
$total_allowed_volume = $max_bins * $bin_capacity * $max_bin_pack_ratio; // volume total permitido considerando todos os nós

// Se solicitado aplicar VPA, calcular sugestões a partir dos itens originais e ajustar antes do packing
// Construímos um mapa de sugestões (id -> novo tamanho) e aplicamos todas as alterações antes de empacotar
$suggestions = [];
if ($apply_vpa && !empty($items)) {
    $sizes = array_column($items, 'size');
    $avg = array_sum($sizes)/count($sizes);
    $reduceFactor = max(1, min(95, $vpaLevel));
    $suggestMap = [];
    foreach ($items as $it) {
        if ($it['size'] > $avg*1.15) {
            $new = max(1, floor($it['size'] * (100 - $reduceFactor) / 100));
            $suggestions[] = ['id' => $it['id'], 'from' => $it['size'], 'to' => $new];
            $suggestMap[$it['id']] = $new;
        }
    }
    // aplicar sugestões para construir items ajustados
    $adjusted = [];
    foreach ($items as $it) {
        if (isset($suggestMap[$it['id']])) {
            $adjusted[] = ['id' => $it['id'], 'size' => $suggestMap[$it['id']]];
        } else {
            $adjusted[] = $it;
        }
    }
    $items = $adjusted;
    $total_item_volume = array_sum(array_column($items, 'size'));
}

// Função simples de first-fit com respeito ao max_ratio
foreach ($items as $it) {
    $placed = false;
    // primeiro, verificar se ainda caberia no total permitido
    if (($used_volume + $it['size']) <= $total_allowed_volume) {
        // tentar colocar em bins existentes, respeitando per-bin allowed
        foreach ($bins as &$b) {
            if (array_sum(array_column($b, 'size')) + $it['size'] <= $per_bin_allowed) {
                $b[] = $it;
                $placed = true;
                $used_volume += $it['size'];
                break;
            }
        }
        // se não coube em nenhum bin existente, tentar abrir novo bin se houver slots
        if (!$placed) {
            if ($used_bins < $max_bins && $it['size'] <= $per_bin_allowed) {
                $bins[] = [$it];
                $used_bins++;
                $used_volume += $it['size'];
                $placed = true;
            } else {
                // não cabe (ou por bin ou por limite de bins)
                $unallocated[] = $it;
            }
        }
    } else {
        // já excederia o total permitido
        $unallocated[] = $it;
    }
}

$physical_available_volume = max(1, $used_bins) * $bin_capacity;
$logical_available_volume = max(1, $used_bins) * $per_bin_allowed; // capacidade efetiva considerando max_ratio
$bin_pack_ratio = $total_item_volume / max(1, $logical_available_volume);

// montar resposta
$response = [
    'bins' => $bins,
    'unallocated' => $unallocated,
    'originalTotalVolume' => $original_total_item_volume,
    'totalUsedVolume' => $total_item_volume,
    'totalAvailableVolume' => $logical_available_volume,
    'physicalAvailableVolume' => $physical_available_volume,
    'perBinAllowed' => $per_bin_allowed,
    'binPackRatio' => round($bin_pack_ratio, 2),
    'vpa' => []
];

// Se solicitado, gerar sugestões VPA simples: reduzir requests maiores que a média pelo percentual escolhido
// anexar sugestões (calculadas anteriormente a partir dos originais)
if (!empty($suggestions)) {
    $response['vpa'] = $suggestions;
}

// anexar tamanho original aos desalocados para facilitar depuração (se VPA aplicado)
if (!empty($unallocated)) {
    foreach ($response['unallocated'] as &$u) {
        if (isset($origSizes[$u['id']])) {
            $u['orig_size'] = $origSizes[$u['id']];
        }
    }
    unset($u);
}

header('Content-Type: application/json');
echo json_encode($response);

?>
