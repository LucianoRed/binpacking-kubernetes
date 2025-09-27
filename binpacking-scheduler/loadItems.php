<?php
// Simulador de scheduler
$numNodes = isset($_GET['numNodes']) ? (int)$_GET['numNodes'] : 4;
$nodeCap = isset($_GET['nodeCap']) ? (int)$_GET['nodeCap'] : 30;
$numApps = isset($_GET['numApps']) ? (int)$_GET['numApps'] : 8; // apps que chegam nesta rodada
$maxReq = isset($_GET['maxReq']) ? (int)$_GET['maxReq'] : 8;

// Nós persistidos em arquivo temporário por sessão simples (não ideal, mas serve para demo local)
$stateFile = sys_get_temp_dir() . '/binpack_sched_state.json';

// Estrutura de estado com histórico
$defaultState = ['history'=>[], 'pos'=>-1, 'nextPodId'=>1];
$state = $defaultState;
if (file_exists($stateFile)) {
    $s = @file_get_contents($stateFile);
    if ($s) $state = json_decode($s, true) ?: $defaultState;
}

// Optional seed for deterministic runs (useful to reproduce cases)
if (isset($_GET['seed'])) {
    mt_srand((int)$_GET['seed']);
}

// Action control: next | back | forward | reset
$action = isset($_GET['action']) ? $_GET['action'] : 'next';

if ($action === 'reset' || isset($_GET['reset']) && $_GET['reset']=='1') {
    // apagar histórico e re-iniciar
    @unlink($stateFile);
    $state = $defaultState;
}

// Helper: cria snapshot inicial com nós vazios
$create_empty_snapshot = function() use ($numNodes, $nodeCap) {
    $nodes = [];
    for ($i=0;$i<$numNodes;$i++) {
        $nodes[] = ['id'=>'node-'.($i+1),'capacity'=>$nodeCap,'pods'=>[]];
    }
    return ['nodes'=>$nodes,'decisions'=>[], 'issues'=>['evicted'=>[], 'unscheduled'=>[]],'round'=>0];
};

// Se ainda não há histórico, inicializa com snapshot vazio
if (empty($state['history'])) {
    $state['history'][] = $create_empty_snapshot();
    $state['pos'] = 0;
}

// Se o número de nós mudou em relação ao snapshot atual e action é reset/next, reinit
// Atualiza referência ao snapshot corrente
$currentSnapshot = $state['history'][$state['pos']];

if ($action === 'back') {
    if ($state['pos'] > 0) $state['pos']--;
    $out = $state['history'][$state['pos']];
    $out['pos'] = $state['pos']; $out['historyLen'] = count($state['history']);
    file_put_contents($stateFile, json_encode($state));
    header('Content-Type: application/json'); echo json_encode($out); exit;
}

if ($action === 'forward') {
    if ($state['pos'] < count($state['history'])-1) $state['pos']++;
    $out = $state['history'][$state['pos']];
    $out['pos'] = $state['pos']; $out['historyLen'] = count($state['history']);
    file_put_contents($stateFile, json_encode($state));
    header('Content-Type: application/json'); echo json_encode($out); exit;
}

// Se action é next, vamos gerar uma nova rodada a partir do snapshot atual
if ($action === 'next') {
    // iniciar novo snapshot copiando o atual
    $snapshot = $state['history'][$state['pos']];
    // atualizar round
    $snapshot['round'] = ($snapshot['round'] ?? 0) + 1;
    // gerar novos pods
    $newPods = [];
    $numApps = isset($_GET['numApps']) ? (int)$_GET['numApps'] : 8;
    $maxReq = isset($_GET['maxReq']) ? (int)$_GET['maxReq'] : 8;
    for ($i=0;$i<$numApps;$i++){
        $req = mt_rand(1,$maxReq);
        $r = mt_rand(1,100);
        if ($r<=25) $q='BestEffort'; elseif ($r<=75) $q='Burstable'; else $q='Guaranteed';
        $id = 'pod-'.$state['nextPodId']++;
        $newPods[] = ['id'=>$id,'request'=>$req,'qos'=>$q,'usage'=>$req];
    }

    // Vamos simular duas ordens: descending (FFD) e ascending (FFI), aplicar best-fit por item,
    // e escolher a que resultar em menos apps não alocados (ou menor volume não alocado).
    $schedule_try = function($baseSnapshot, $newPods, $order) {
        // copiar snapshot deeply
        $snap = $baseSnapshot;
        // sort
        if ($order === 'desc') {
            usort($newPods, function($a,$b){ return $b['request'] <=> $a['request']; });
        } else {
            usort($newPods, function($a,$b){ return $a['request'] <=> $b['request']; });
        }
        $decisions = [];
        $decisions_detailed = [];
        $unscheduled = [];
        foreach ($newPods as $pod) {
            $candidates = [];
            foreach ($snap['nodes'] as $node) {
                $used = array_sum(array_column($node['pods'],'request'));
                $residual = $node['capacity'] - $used;
                if ($pod['request'] <= $residual) $candidates[] = ['node'=>$node,'residual'=>$residual];
            }
            if (empty($candidates)) { $unscheduled[]=$pod; $decisions[] = "{$pod['id']} ... sem fit"; continue; }
            usort($candidates, function($a,$b) use ($pod){
                $ra = $a['residual'] - $pod['request'];
                $rb = $b['residual'] - $pod['request'];
                if ($ra === $rb) return count($a['node']['pods']) <=> count($b['node']['pods']);
                return $ra <=> $rb;
            });
            // build readable candidates for explanation
            $candReadable = [];
            foreach ($candidates as $c) {
                $candReadable[] = ['node'=>$c['node']['id'],'residual'=>$c['residual'],'numPods'=>count($c['node']['pods'])];
            }
            $chosen = $candidates[0]['node']['id'];
            // find and push into snap nodes
            foreach ($snap['nodes'] as &$n) { if ($n['id']==$chosen) { $n['pods'][] = $pod; break; } }
            $decisions[] = "{$pod['id']} agendado em {$chosen}";
            $decisions_detailed[] = ['pod'=>$pod['id'],'pod_request'=>$pod['request'],'pod_qos'=>$pod['qos'],'candidates'=>$candReadable,'chosen'=>$chosen];
        }
        // compute total unallocated volume to compare
        $unallocatedVolume = array_sum(array_map(function($p){return $p['request'];}, $unscheduled));
        return ['snapshot'=>$snap,'decisions'=>$decisions,'decision_details'=>$decisions_detailed,'unscheduled'=>$unscheduled,'unallocatedVolume'=>$unallocatedVolume];
    };

    $tryDesc = $schedule_try($snapshot, $newPods, 'desc');
    $tryAsc = $schedule_try($snapshot, $newPods, 'asc');
    // escolher o melhor: menos itens não alocados, desempate por volume não alocado
    $chosenTry = null;
    if (count($tryDesc['unscheduled']) < count($tryAsc['unscheduled'])) $chosenTry = $tryDesc; elseif (count($tryAsc['unscheduled']) < count($tryDesc['unscheduled'])) $chosenTry = $tryAsc; else {
        $chosenTry = ($tryDesc['unallocatedVolume'] <= $tryAsc['unallocatedVolume']) ? $tryDesc : $tryAsc;
    }

    // aplicar o snapshot escolhido
    $snapshot = $chosenTry['snapshot'];
    $decisions = $chosenTry['decisions'];
    $decision_details = $chosenTry['decision_details'] ?? [];
    $unscheduled = $chosenTry['unscheduled'];

    // simular uso real
    foreach ($snapshot['nodes'] as &$node) {
        foreach ($node['pods'] as &$p) { $factor = mt_rand(80,140)/100; $p['usage'] = max(1,(int)floor($p['request']*$factor)); }
    }

    // evictions
    $evicted = [];
    foreach ($snapshot['nodes'] as &$node) {
        $totalUsage = array_sum(array_column($node['pods'],'usage'));
        if ($totalUsage > $node['capacity']) {
            $toFree = $totalUsage - $node['capacity'];
            usort($node['pods'], function($a,$b){ $prio=['BestEffort'=>0,'Burstable'=>1,'Guaranteed'=>2]; if ($prio[$a['qos']] !== $prio[$b['qos']]) return $prio[$a['qos']] <=> $prio[$b['qos']]; return $b['usage'] <=> $a['usage']; });
            $freed=0; $rem=[];
            foreach ($node['pods'] as $pod) {
                if ($freed < $toFree) { $evicted[]=['pod'=>$pod,'from'=>$node['id']]; $freed += $pod['usage']; } else { $rem[]=$pod; }
            }
            $node['pods'] = $rem;
        }
    }

    $snapshot['decisions'] = $decisions;
    if (!empty($decision_details)) $snapshot['decision_details'] = $decision_details;
    $snapshot['issues'] = ['evicted'=>$evicted,'unscheduled'=>$unscheduled];

    // Se está no meio do histórico (pos < end), truncar adiante
    if ($state['pos'] < count($state['history'])-1) {
        $state['history'] = array_slice($state['history'],0,$state['pos']+1);
    }
    // anexar snapshot e avançar pos
    $state['history'][] = $snapshot;
    $state['pos'] = count($state['history'])-1;

    // persistir e retornar
    file_put_contents($stateFile, json_encode($state));
    $out = $snapshot; $out['pos']=$state['pos']; $out['historyLen']=count($state['history']);
    header('Content-Type: application/json'); echo json_encode($out); exit;
}

// Gerar novos pods
$newPods = [];
for ($i=0;$i<$numApps;$i++){
    $req = mt_rand(1,$maxReq);
    // atribuir QoS aleatório com probabilidade: BE 25%, Burstable 50%, Guaranteed 25%
    $r = mt_rand(1,100);
    if ($r<=25) $q='BestEffort'; elseif ($r<=75) $q='Burstable'; else $q='Guaranteed';
    $newPods[] = ['id'=>'pod-'.(count($state['pods'])+$i+1),'request'=>$req,'qos'=>$q,'usage'=>$req];
}

// Função para sum requests em node
function node_sum($n){
    return array_sum(array_column($n['pods'],'request'));
}

// Para cada novo pod: selecionar nó candidato e alocar se possível
$decisions = [];
$unscheduled = [];
foreach ($newPods as $pod) {
    // encontrar nós com suficiente capacity residual (considerando requests)
    $candidates = [];
    foreach ($state['nodes'] as &$node) {
        $used = node_sum($node);
        $residual = $node['capacity'] - $used;
        if ($pod['request'] <= $residual) {
            $candidates[] = ['node'=>$node,'residual'=>$residual];
        }
    }

    if (empty($candidates)) {
        // no fit: unscheduled for now
        $unscheduled[] = $pod;
        $decisions[] = "{$pod['id']} (req={$pod['request']}, qos={$pod['qos']}): não cabe em nenhum nó — ficará pendente";
        continue;
    }

    // escolher nó que minimiza residual após alocação (fit tight)
    usort($candidates, function($a,$b) use ($pod){
        $ra = $a['residual'] - $pod['request'];
        $rb = $b['residual'] - $pod['request'];
        return $ra <=> $rb;
    });

    $chosenId = $candidates[0]['node']['id'];
    // alocar no estado
    foreach ($state['nodes'] as &$node) {
        if ($node['id']==$chosenId) {
            $node['pods'][] = $pod;
            break;
        }
    }
    $state['pods'][] = $pod;
    $decisions[] = "{$pod['id']} (req={$pod['request']}, qos={$pod['qos']}): agendado em {$chosenId} — escolheu nó com residual {$candidates[0]['residual']}";
}

// Simular aumento de uso real: alguns pods aumentam consumo aleatoriamente
foreach ($state['nodes'] as &$node) {
    foreach ($node['pods'] as &$p) {
        // uso real pode variar entre 80% e 140% do request
        $factor = mt_rand(80,140)/100;
        $p['usage'] = max(1, (int)floor($p['request'] * $factor));
    }
}

// Detectar pressão e evict se necessário: para cada node, se soma usage > capacity
$evicted = [];
foreach ($state['nodes'] as &$node) {
    $totalUsage = array_sum(array_column($node['pods'],'usage'));
    if ($totalUsage > $node['capacity']) {
        // determinar quanto precisamos liberar
        $toFree = $totalUsage - $node['capacity'];
        // ordenar pods por QoS (BestEffort first) e por usage desc
        usort($node['pods'], function($a,$b){
            $prio = ['BestEffort'=>0,'Burstable'=>1,'Guaranteed'=>2];
            if ($prio[$a['qos']] !== $prio[$b['qos']]) return $prio[$a['qos']] <=> $prio[$b['qos']];
            return $b['usage'] <=> $a['usage'];
        });
        $freed = 0;
        $remainingPods = [];
        foreach ($node['pods'] as $pod) {
            if ($freed < $toFree) {
                $evicted[] = ['pod'=>$pod,'from'=>$node['id']];
                $freed += $pod['usage'];
            } else {
                $remainingPods[] = $pod;
            }
        }
        $node['pods'] = $remainingPods;
    }
}

// Rebuild issues (evicted + unscheduled)
$issues = ['evicted'=>$evicted,'unscheduled'=>$unscheduled];

$state['round'] += 1;
// Persistir estado
file_put_contents($stateFile, json_encode($state));

header('Content-Type: application/json');
echo json_encode(['nodes'=>$state['nodes'],'decisions'=>$decisions,'issues'=>$issues,'round'=>$state['round']]);

?>
