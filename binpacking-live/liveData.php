<?php
header('Content-Type: application/json');

// Config via ambiente
$api = getenv('K8S_API_URL');
$token = getenv('K8S_BEARER_TOKEN');
$insecure = getenv('K8S_SKIP_TLS_VERIFY');

if (!$api || !$token) {
  http_response_code(500);
  echo json_encode(['error' => 'Defina K8S_API_URL e K8S_BEARER_TOKEN no ambiente do contêiner.']);
  exit;
}

// Params
$resource = isset($_GET['resource']) && $_GET['resource'] === 'memory' ? 'memory' : 'cpu';
$nsFilter = [];
if (!empty($_GET['ns'])) {
  $nsFilter = array_filter(array_map('trim', explode(',', $_GET['ns'])));
}

// Helpers de parse
function parse_cpu_m($v) {
  if ($v === null || $v === '') return 0;
  // exemplos: "100m", "2"
  if (str_ends_with($v, 'm')) return (int) rtrim($v, 'm');
  if (str_ends_with($v, 'n')) { // nanos -> millicores
    $n = (float) rtrim($v, 'n');
    return (int) round($n / 1_000_000.0); // 1e6 n = 1 m
  }
  // núcleos -> millicores
  if (is_numeric($v)) return (int) ((float)$v * 1000);
  return 0;
}

function parse_mem_bytes($v) {
  if ($v === null || $v === '') return 0;
  // Kubernetes usa potências de 2 (Ki, Mi, Gi) e às vezes sem sufixo (bytes)
  $v = trim($v);
  $map = [
    'Ki' => 1024,
    'Mi' => 1024*1024,
    'Gi' => 1024*1024*1024,
    'Ti' => 1024*1024*1024*1024,
    'Pi' => 1024*1024*1024*1024*1024,
    'k'  => 1000,
    'M'  => 1000*1000,
    'G'  => 1000*1000*1000,
  ];
  foreach ($map as $suf => $mul) {
    if (str_ends_with($v, $suf)) {
      $num = (float) substr($v, 0, -strlen($suf));
      return (int) ($num * $mul);
    }
  }
  if (is_numeric($v)) return (int) $v;
  return 0;
}

function bytes_to_mib($b){ return (int) round($b / (1024*1024)); }

// HTTP helper
function k8s_get($base, $path, $token, $insecure=false) {
  $url = rtrim($base, '/') . '/' . ltrim($path, '/');
  $ch = curl_init($url);
  $headers = [
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
  ];
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
  ]);
  if ($insecure) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  }
  $resp = curl_exec($ch);
  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    echo json_encode(['error' => 'Erro ao consultar API: '.$err]);
    exit;
  }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code < 200 || $code >= 300) {
    http_response_code($code);
    echo json_encode(['error' => 'Falha HTTP '.$code.' em '.$path]);
    exit;
  }
  $data = json_decode($resp, true);
  if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Resposta inválida da API Kubernetes.']);
    exit;
  }
  return $data;
}

// tentativa: não falha o request, retorna null em caso de erro
function k8s_try_get($base, $path, $token, $insecure=false) {
  $url = rtrim($base, '/') . '/' . ltrim($path, '/');
  $ch = curl_init($url);
  $headers = [
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
  ];
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
  ]);
  if ($insecure) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  }
  $resp = curl_exec($ch);
  if ($resp === false) { curl_close($ch); return null; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code < 200 || $code >= 300) return null;
  $data = json_decode($resp, true);
  if ($data === null) return null;
  return $data;
}

// Buscar nós e pods
$nodes = k8s_get($api, '/api/v1/nodes', $token, (bool)$insecure);
$pods = k8s_get($api, '/api/v1/pods', $token, (bool)$insecure);
// métricas efetivas (se metrics-server disponível)
$podMetrics = k8s_try_get($api, '/apis/metrics.k8s.io/v1beta1/pods', $token, (bool)$insecure);
// mapear uso atual por pod
$podUsage = [];
if ($podMetrics && isset($podMetrics['items'])) {
  foreach ($podMetrics['items'] as $m) {
    $ns = $m['metadata']['namespace'] ?? '';
    $name = $m['metadata']['name'] ?? '';
    if (!$ns || !$name) continue;
    $cpu_m = 0; $mem_b = 0;
    foreach (($m['containers'] ?? []) as $c) {
      $cpu_m += parse_cpu_m($c['usage']['cpu'] ?? '0');
      $mem_b += parse_mem_bytes($c['usage']['memory'] ?? '0');
    }
    $podUsage[$ns . '/' . $name] = ['cpu_m' => $cpu_m, 'mem_b' => $mem_b];
  }
}

// Index de nós
$nodeOrder = [];
$nodeInfo = [];
$pendingPods = [];
foreach (($nodes['items'] ?? []) as $n) {
  $name = $n['metadata']['name'] ?? 'unknown';
  $labels = $n['metadata']['labels'] ?? [];
  $alloc = $n['status']['allocatable'] ?? [];
  $addresses = $n['status']['addresses'] ?? [];
  $cpu_m = parse_cpu_m($alloc['cpu'] ?? '0');
  $mem_b = parse_mem_bytes($alloc['memory'] ?? '0');
  // papel do nó
  $role = 'Worker';
  if (isset($labels['node-role.kubernetes.io/master']) || isset($labels['node-role.kubernetes.io/control-plane']) || isset($labels['node-role.kubernetes.io/controlplane'])) {
    $role = 'Master';
  } elseif ((isset($labels['machine-type']) && $labels['machine-type'] === 'infra-node') || isset($labels['node-role.kubernetes.io/infra'])) {
    // quando label machine-type=infra-node, tratar como InfraNode; também cobre o label comum node-role.kubernetes.io/infra
    $role = 'InfraNode';
  }
  // IP interno preferencial
  $ip = 'N/A';
  foreach ($addresses as $addr) {
    if (($addr['type'] ?? '') === 'InternalIP') { $ip = $addr['address'] ?? 'N/A'; break; }
  }
  if ($ip === 'N/A') {
    foreach ($addresses as $addr) { if (($addr['type'] ?? '') === 'ExternalIP') { $ip = $addr['address'] ?? 'N/A'; break; } }
  }
  $nodeOrder[] = $name;
  $nodeInfo[$name] = [
    'name' => $name,
    'role' => $role,
    'ip' => $ip,
    'alloc_cpu_m' => $cpu_m,
    'alloc_mem_b' => $mem_b,
    'used_cpu_m' => 0,
    'used_mem_b' => 0,
    'used_eff_cpu_m' => 0,
    'used_eff_mem_b' => 0,
    'pods' => []
  ];
}

// Distribuir pods por nó, somando requests
foreach (($pods['items'] ?? []) as $p) {
  $phase = $p['status']['phase'] ?? '';
  if (in_array($phase, ['Succeeded','Failed'])) continue;
  $ns = $p['metadata']['namespace'] ?? 'default';
  if (!empty($nsFilter) && !in_array($ns, $nsFilter)) continue;
  $podName = $p['metadata']['name'] ?? 'pod';
  $nodeName = $p['spec']['nodeName'] ?? null;
  $isTerminating = !empty($p['metadata']['deletionTimestamp']);
  // Detectar "creating" via estados de containers
  $creating = false;
  $statuses = $p['status']['containerStatuses'] ?? [];
  foreach ($statuses as $cs) {
    $reason = $cs['state']['waiting']['reason'] ?? '';
    if ($reason === 'ContainerCreating') { $creating = true; break; }
  }
  if (!$creating) {
    $initStatuses = $p['status']['initContainerStatuses'] ?? [];
    foreach ($initStatuses as $cs) {
      $reason = $cs['state']['waiting']['reason'] ?? '';
      if ($reason === 'PodInitializing' || $reason === 'ContainerCreating') { $creating = true; break; }
    }
  }

  $containers = $p['spec']['containers'] ?? [];
  $req_cpu_m = 0; $req_mem_b = 0;
  foreach ($containers as $c) {
    $req = $c['resources']['requests'] ?? [];
    $req_cpu_m += parse_cpu_m($req['cpu'] ?? '0');
    $req_mem_b += parse_mem_bytes($req['memory'] ?? '0');
  }

  $pod = [
    'id' => $ns . '/' . $podName,
    'ns' => $ns,
    'name' => $podName,
    'cpu_m' => $req_cpu_m,
    'mem_b' => $req_mem_b,
    'terminating' => $isTerminating,
    'phase' => $phase,
    'creating' => $creating,
  'eff_cpu_m' => $podUsage[$ns.'/'.$podName]['cpu_m'] ?? 0,
  'eff_mem_b' => $podUsage[$ns.'/'.$podName]['mem_b'] ?? 0,
  ];

  if (!$nodeName || !isset($nodeInfo[$nodeName])) {
    // Sem nó atribuído -> Pending (não alocado)
    $pendingPods[] = $pod;
  } else {
    $nodeInfo[$nodeName]['pods'][] = $pod;
    $nodeInfo[$nodeName]['used_cpu_m'] += $req_cpu_m;
    $nodeInfo[$nodeName]['used_mem_b'] += $req_mem_b;
    $nodeInfo[$nodeName]['used_eff_cpu_m'] += ($pod['eff_cpu_m'] ?? 0);
    $nodeInfo[$nodeName]['used_eff_mem_b'] += ($pod['eff_mem_b'] ?? 0);
  }
}

// Montar bins conforme recurso
$bins = [];
$totalAvailUnits = 0; $totalUsedUnits = 0; $perBinAllowedUnits = 0;
$nodesOut = [];

foreach ($nodeOrder as $n) {
  $info = $nodeInfo[$n];
  if ($resource === 'cpu') {
    $capUnits = (int) ceil($info['alloc_cpu_m'] / 100); // 100m = 0.1 core
    $usedUnits= (int) ceil($info['used_cpu_m'] / 100);
    $usedPct  = $capUnits > 0 ? (int) round(($usedUnits / $capUnits) * 100) : 0;
    // efetivo
    $effUnits = (int) ceil(($info['used_eff_cpu_m'] ?? 0) / 100);
    $usedEffPct  = $capUnits > 0 ? (int) round(($effUnits / $capUnits) * 100) : null;
    $nodesOut[] = [
      'name' => $n,
      'role' => $info['role'] ?? 'Worker',
      'ip' => $info['ip'] ?? 'N/A',
      'capacityHuman' => sprintf('CPU %.2f cores', $info['alloc_cpu_m']/1000),
      'usedPct' => $usedPct,
      'usedEffPct' => $usedEffPct,
    ];
    $perBinAllowedUnits = $capUnits; // por nó pode variar; usamos último só para exibir unidade
    $totalAvailUnits += $capUnits;
    $totalUsedUnits  += $usedUnits;
    $items = [];
    foreach ($info['pods'] as $pod) {
      $units = max(0, (int) ceil($pod['cpu_m'] / 100));
      $cpuHuman = sprintf('%dm (%.2f cores)', $pod['cpu_m'] ?: 0, ($pod['cpu_m'] ?: 0)/1000);
      $memHuman = bytes_to_mib($pod['mem_b']) . ' Mi';
      $items[] = [
        'id' => $pod['id'],
        'shortId' => $pod['name'],
        'sizeUnits' => $units,
        'sizeHuman' => ($pod['cpu_m']?:0) . 'm',
        'cpu_m' => (int)($pod['cpu_m'] ?: 0),
        'mem_b' => (int)($pod['mem_b'] ?: 0),
        'cpuHuman' => $cpuHuman,
        'memHuman' => $memHuman,
        'terminating' => !empty($pod['terminating']),
        'phase' => $pod['phase'] ?? '',
        'creating' => !empty($pod['creating']),
      ];
    }
    $bins[] = $items;
  } else {
    $unitSize = 256 * 1024 * 1024; // 256Mi para manter escala
    $capUnits = (int) ceil($info['alloc_mem_b'] / $unitSize);
    $usedUnits= (int) ceil($info['used_mem_b'] / $unitSize);
    $usedPct  = $capUnits > 0 ? (int) round(($usedUnits / $capUnits) * 100) : 0;
    // efetivo
    $unitSize = 256 * 1024 * 1024;
    $effUnits = (int) ceil(($info['used_eff_mem_b'] ?? 0) / $unitSize);
    $usedEffPct  = $capUnits > 0 ? (int) round(($effUnits / $capUnits) * 100) : null;
    $nodesOut[] = [
      'name' => $n,
      'role' => $info['role'] ?? 'Worker',
      'ip' => $info['ip'] ?? 'N/A',
      'capacityHuman' => sprintf('Mem %.0f Mi', bytes_to_mib($info['alloc_mem_b'])),
      'usedPct' => $usedPct,
      'usedEffPct' => $usedEffPct,
    ];
    $perBinAllowedUnits = $capUnits;
    $totalAvailUnits += $capUnits;
    $totalUsedUnits  += $usedUnits;
    $items = [];
    foreach ($info['pods'] as $pod) {
      $units = max(0, (int) ceil($pod['mem_b'] / $unitSize));
      $cpuHuman = sprintf('%dm (%.2f cores)', $pod['cpu_m'] ?: 0, ($pod['cpu_m'] ?: 0)/1000);
      $memHuman = bytes_to_mib($pod['mem_b']) . ' Mi';
      $items[] = [
        'id' => $pod['id'],
        'shortId' => $pod['name'],
        'sizeUnits' => $units,
        'sizeHuman' => bytes_to_mib($pod['mem_b']) . ' Mi',
        'cpu_m' => (int)($pod['cpu_m'] ?: 0),
        'mem_b' => (int)($pod['mem_b'] ?: 0),
        'cpuHuman' => $cpuHuman,
        'memHuman' => $memHuman,
        'terminating' => !empty($pod['terminating']),
        'phase' => $pod['phase'] ?? '',
        'creating' => !empty($pod['creating']),
      ];
    }
    $bins[] = $items;
  }
}

// Montar lista de pendentes conforme recurso escolhido
$pendingOut = [];
if ($resource === 'cpu') {
  foreach ($pendingPods as $pod) {
    $units = max(0, (int) ceil(($pod['cpu_m'] ?: 0) / 100));
    $pendingOut[] = [
      'id' => $pod['id'],
      'sizeUnits' => $units,
      'cpu_m' => (int)($pod['cpu_m'] ?: 0),
      'mem_b' => (int)($pod['mem_b'] ?: 0),
      'cpuHuman' => sprintf('%dm (%.2f cores)', $pod['cpu_m'] ?: 0, ($pod['cpu_m'] ?: 0)/1000),
      'memHuman' => bytes_to_mib($pod['mem_b']) . ' Mi',
    ];
  }
} else {
  $unitSize = 256 * 1024 * 1024;
  foreach ($pendingPods as $pod) {
    $units = max(0, (int) ceil(($pod['mem_b'] ?: 0) / $unitSize));
    $pendingOut[] = [
      'id' => $pod['id'],
      'sizeUnits' => $units,
      'cpu_m' => (int)($pod['cpu_m'] ?: 0),
      'mem_b' => (int)($pod['mem_b'] ?: 0),
      'cpuHuman' => sprintf('%dm (%.2f cores)', $pod['cpu_m'] ?: 0, ($pod['cpu_m'] ?: 0)/1000),
      'memHuman' => bytes_to_mib($pod['mem_b']) . ' Mi',
    ];
  }
}

$ratio = $totalAvailUnits > 0 ? round($totalUsedUnits / $totalAvailUnits, 2) : 0;

echo json_encode([
  'nodes' => $nodesOut,
  'bins' => $bins,
  'perBinAllowedUnits' => $perBinAllowedUnits,
  'totalUsedUnits' => $totalUsedUnits,
  'totalAvailableUnits' => $totalAvailUnits,
  'binPackRatio' => $ratio,
  'pending' => $pendingOut,
]);

?>
