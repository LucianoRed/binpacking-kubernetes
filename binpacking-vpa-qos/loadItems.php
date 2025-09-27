<?php
// Similar to binpacking-vpa but with QoS added randomly per pod
$num_items = isset($_GET['numItems']) ? (int)$_GET['numItems'] : 20;
$max_item_size = isset($_GET['maxItemSize']) ? (int)$_GET['maxItemSize'] : 10;
$max_bin_pack_ratio = isset($_GET['maxRatio']) ? (float)$_GET['maxRatio'] : 0.8;
$max_bins = isset($_GET['maxBins']) ? (int)$_GET['maxBins'] : 5;
$apply_vpa = isset($_GET['vpa']) ? (int)$_GET['vpa'] : 0;
$vpaLevel = isset($_GET['vpaLevel']) ? (int)$_GET['vpaLevel'] : 30;
$includeQoS = isset($_GET['qos']) ? (int)$_GET['qos'] : 1;

$items = [];
$total_item_requested = 0;
$origSizes = [];

$qosOptions = ['BestEffort','Burstable','Guaranteed'];

for ($i=0;$i<$num_items;$i++){
    $size = mt_rand(1,$max_item_size); // treat as 'limit' nominal
    $id = 'app-'.($i+1);
    $q = $includeQoS ? $qosOptions[array_rand($qosOptions)] : null;
    // derive request/limit based on QoS
    if ($q === 'BestEffort') {
        $request = 0; $limit = $size; // BE: no request, has limit
    } elseif ($q === 'Burstable') {
        $limit = $size; $request = max(1, (int) floor($limit * 0.6));
    } else { // Guaranteed
        $limit = $size; $request = $size;
    }
    $items[] = ['id'=>$id,'request'=>$request,'limit'=>$limit,'qos'=>$q,'size'=>$limit];
    $origSizes[$id] = $size;
    $total_item_requested += $request; // track requested volume
}

$original_total_item_volume = $total_item_requested;

$bin_capacity = 10;
$bins = [];
$used_bins = 0;
$used_volume = 0; // sum of requests placed
$unallocated = [];

$per_bin_allowed = (int) floor($bin_capacity * $max_bin_pack_ratio);
$total_allowed_volume = $max_bins * $bin_capacity * $max_bin_pack_ratio;

// VPA suggestions map from originals (operate on limits)
$suggestions = [];
if ($apply_vpa && !empty($items)) {
    $sizes = array_column($items,'size');
    $avg = array_sum($sizes)/count($sizes);
    $reduceFactor = max(1, min(95, $vpaLevel));
    $suggestMap = [];
    foreach ($items as $it) {
        // compare against original limit
        if ($it['limit'] > $avg*1.15) {
            $newLimit = max(1, (int) floor($it['limit'] * (100 - $reduceFactor) / 100));
            $suggestions[] = ['id'=>$it['id'],'from'=>$it['limit'],'to'=>$newLimit];
            $suggestMap[$it['id']] = $newLimit;
        }
    }
    // apply suggestions and recompute request according to QoS
    $adjusted = [];
    foreach ($items as $it) {
        if (isset($suggestMap[$it['id']])) {
            $newLimit = $suggestMap[$it['id']];
            if ($it['qos'] === 'BestEffort') {
                $newRequest = 0;
            } elseif ($it['qos'] === 'Burstable') {
                $newRequest = max(1, (int) floor($newLimit * 0.6));
            } else {
                $newRequest = $newLimit;
            }
            $adjusted[] = ['id'=>$it['id'],'request'=>$newRequest,'limit'=>$newLimit,'qos'=>$it['qos'],'size'=>$newLimit];
        } else {
            $adjusted[] = $it;
        }
    }
    $items = $adjusted;
    $total_item_requested = array_sum(array_column($items,'request'));
}

// packing first-fit with per-bin allowed (use request for scheduling)
foreach ($items as $it) {
    $placed = false;
    if (($used_volume + $it['request']) <= $total_allowed_volume) {
        foreach ($bins as &$b) {
            if (array_sum(array_column($b,'request')) + $it['request'] <= $per_bin_allowed) {
                $b[] = $it; $placed = true; $used_volume += $it['request']; break;
            }
        }
        if (!$placed) {
            if ($used_bins < $max_bins && $it['size'] <= $per_bin_allowed) {
                $bins[] = [$it]; $used_bins++; $used_volume += $it['request']; $placed=true;
            } else { $unallocated[]=$it; }
        }
    } else { $unallocated[]=$it; }
}

// At this point we have placed items into $bins. Now simulate runtime usage and evictions
$evicted = [];
// qos priority for eviction: BestEffort (0) -> Burstable (1) -> Guaranteed (2)
$qosPriority = ['BestEffort'=>0,'Burstable'=>1,'Guaranteed'=>2,null=>1];

$total_used_after_eviction = 0;
foreach ($bins as $bi => &$b) {
        // compute runtime per pod: random between 0.8x and 1.4x of a base usage
    $runtimeSum = 0.0;
    $runtimeList = [];
    foreach ($b as $it) {
        // For pods with request==0 (BestEffort) assume they still consume some runtime up to a fraction of their limit.
        if (isset($it['request']) && $it['request'] > 0) {
            $base = (float) $it['request'];
        } else {
            // BestEffort: assume moderate usage of their limit so they can be evicted under pressure
            $base = max(0.1, (float) $it['limit'] * 0.5);
        }
        $mult = mt_rand(80,140)/100.0;
        $runtime = round($base * $mult, 2);
        $runtimeList[] = array_merge($it, ['runtime'=>$runtime]);
        $runtimeSum += $runtime;
    }

    // If node exceeds physical capacity, evict until under capacity
    if ($runtimeSum > $bin_capacity) {
        // sort candidates by qos priority (evict lower priority number first), then by runtime desc
        usort($runtimeList, function($a,$b) use ($qosPriority){
            $pa = isset($qosPriority[$a['qos']]) ? $qosPriority[$a['qos']] : 1;
            $pb = isset($qosPriority[$b['qos']]) ? $qosPriority[$b['qos']] : 1;
            if ($pa === $pb) return ($b['runtime'] <=> $a['runtime']);
            return $pa <=> $pb;
        });

        // evict until runtimeSum <= bin_capacity
        $newList = [];
        foreach ($runtimeList as $cand) {
            if ($runtimeSum <= $bin_capacity) { $newList[] = $cand; continue; }
            // evict this candidate
            $runtimeSum -= $cand['runtime'];
            $evicted[] = [
                'id'=>$cand['id'], 'qos'=>$cand['qos'], 'orig_size'=>isset($origSizes[$cand['id']]) ? $origSizes[$cand['id']] : $cand['size'],
                'runtime'=>$cand['runtime'], 'node'=> $bi+1
            ];
        }

        // remaining (non-evicted) populate bin
        $b = [];
        foreach ($newList as $rem) {
            // strip runtime field when storing back
            $b[] = ['id'=>$rem['id'],'request'=>$rem['request'],'limit'=>$rem['limit'],'qos'=>$rem['qos'],'size'=>$rem['size']];
            $total_used_after_eviction += $rem['runtime'];
        }
    } else {
        // no eviction, keep original items (without runtime)
        $b = [];
        foreach ($runtimeList as $rem) {
            $b[] = ['id'=>$rem['id'],'request'=>$rem['request'],'limit'=>$rem['limit'],'qos'=>$rem['qos'],'size'=>$rem['size']];
            $total_used_after_eviction += $rem['runtime'];
        }
    }
    unset($runtimeList);
}
unset($b);

$physical_available_volume = max(1,$used_bins)*$bin_capacity;
$logical_available_volume = max(1,$used_bins)*$per_bin_allowed;
$bin_pack_ratio = ($total_item_requested > 0) ? ($total_item_requested / max(1,$logical_available_volume)) : 0;

$response = [
    'bins'=>$bins,
    'unallocated'=>$unallocated,
    'evicted'=>$evicted,
    'originalTotalVolume'=>$original_total_item_volume,
    'totalRequestedVolume'=>$total_item_requested,
    'totalPlacedRequests'=>$used_volume,
    'totalUsedAfterEviction'=>round($total_used_after_eviction,2),
    'totalAvailableVolume'=>$logical_available_volume,
    'physicalAvailableVolume'=>$physical_available_volume,
    'perBinAllowed'=>$per_bin_allowed,
    'binPackRatio'=>round($bin_pack_ratio,2),
    'vpa'=>[]
];

if (!empty($suggestions)) $response['vpa']=$suggestions;

// attach orig_size to unallocated
if (!empty($unallocated)){
    foreach ($response['unallocated'] as &$u){ if (isset($origSizes[$u['id']])) $u['orig_size']=$origSizes[$u['id']]; }
    unset($u);
}

// attach orig_size to evicted
if (!empty($evicted)){
    foreach ($response['evicted'] as &$e){ if (!isset($e['orig_size']) && isset($origSizes[$e['id']])) $e['orig_size']=$origSizes[$e['id']]; }
    unset($e);
}

header('Content-Type: application/json');
echo json_encode($response);

?>
