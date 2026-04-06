<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  SCIENCE HUB ULTIMATE — AUTONOMOUS.PHP                               ║
 * ║  GENESIS-ULTRA v9.1 — Mode Autonome • 9 étapes • IA multi-couches   ║
 * ║  Compatible Hostinger • SQLite • Mistral AI • 8 sources             ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/config.php';

// Gestion état session
$state_file = STORAGE_PATH . 'auto_queue/state_' . $SESSION_ID . '.json';
$state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : null;

if(!$state) {
    $state = [
        'session_id' => $SESSION_ID,
        'step' => 0,
        'target' => null,
        'memory' => [],
        'hypotheses' => [],
        'searched_targets' => [],
        'sources_this_run' => [],
        'error_count' => 0,
        'current_phase' => 'idle',
        'started_at' => time(),
    ];
}

// Action: Démarrer/Continuer recherche
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if($action === 'start' || $action === 'continue') {
        // Exécution étape par étape
        execute_step($state);
        save_state($state_file, $state);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'state' => $state]);
        exit;
    }
    
    if($action === 'reset') {
        unlink($state_file);
        header('Location: autonomous.php');
        exit;
    }
}

// Fonction exécution étape
function execute_step(&$state) {
    global $PROMPT_LIBRARY, $MISTRAL_CONFIG;
    
    $step = $state['step'];
    
    // Étape 0: Sélection de cible
    if($step === 0) {
        $already = array_slice($state['searched_targets'], -5);
        $domain_hint = '';
        
        if(count($already) > 3) {
            $domains = ['génétique rare','oncologie','neurologie','maladies métaboliques','immunologie','biologie synthétique','pharmacologie','maladies infectieuses'];
            $domain_hint = "Explore le domaine: " . $domains[array_rand($domains)] . ". ";
        }
        
        $result = shu_mistral([
            ['role' => 'system', 'content' => $PROMPT_LIBRARY['target_selection']],
            ['role' => 'user', 'content' => $domain_hint . "Cibles déjà explorées (ÉVITE-LES ABSOLUMENT): [" . implode(', ', $already) . "]"]
        ], $MISTRAL_CONFIG['default_model'], 1200, 0.8);
        
        if($result['success'] && !empty($result['data']['next_target'])) {
            $target = trim($result['data']['next_target']);
            $invalid = ['array','object','null','json','test','example','sample','target','disease','gene','protein'];
            
            if(strlen($target) < 3 || in_array(strtolower($target), $invalid) || in_array($target, $already)) {
                $fallbacks = ['Syndrome de Rett','Ataxie de Friedreich','Maladie de Menkes','Syndrome de Angelman','Maladie de Wilson','Amyotrophie spinale'];
                $target = $fallbacks[array_rand($fallbacks)];
            }
            
            $state['target'] = $target;
            $state['target_domain'] = $result['data']['domain'] ?? 'biomed';
            $state['target_angle'] = $result['data']['research_angle'] ?? '';
            $state['target_queries'] = $result['data']['suggested_queries'] ?? [$target];
            $state['searched_targets'][] = $target;
            $state['memory'] = [];
            $state['step'] = 1;
            $state['sources_this_run'] = [];
            
            add_to_log($state['session_id'], 0, 'target_selection', '🎯 Cible sélectionnée: ' . $target, 
                'Domaine: ' . ($result['data']['domain'] ?? 'N/A'), 'success');
        } else {
            $fallbacks = ['Progeria','Maladie de Huntington','SLA','Syndrome de Dravet'];
            $state['target'] = $fallbacks[array_rand($fallbacks)];
            $state['step'] = 1;
            $state['target_queries'] = [$state['target']];
            $state['error_count']++;
            
            add_to_log($state['session_id'], 0, 'target_selection', '⚠️ Fallback cible aléatoire', $result['error'] ?? '', 'warning');
        }
    }
    
    // Étapes 1-8: Collecte des 8 sources principales
    elseif($step >= 1 && $step <= 8) {
        $sources_map = [
            1 => ['fn' => 'genesis_pubmed', 'name' => 'PubMed'],
            2 => ['fn' => 'genesis_uniprot', 'name' => 'UniProt'],
            3 => ['fn' => 'genesis_clinvar', 'name' => 'ClinVar'],
            4 => ['fn' => 'genesis_arxiv', 'name' => 'ArXiv'],
            5 => ['fn' => 'genesis_europepmc', 'name' => 'EuropePMC'],
            6 => ['fn' => 'genesis_openalex', 'name' => 'OpenAlex'],
            7 => ['fn' => 'genesis_chembl', 'name' => 'ChEMBL'],
            8 => ['fn' => 'genesis_wikidata', 'name' => 'Wikidata'],
        ];
        
        $src = $sources_map[$step];
        $queries = $state['target_queries'] ?? [$state['target']];
        $query = $queries[($step - 1) % count($queries)];
        
        // Nettoyage requête
        $clean_query = preg_replace('/[^A-Za-z0-9\-_\+ ]/', '', $query);
        
        $result = call_api_source($src['fn'], $clean_query);
        
        $count = $result['count'] ?? 0;
        $type = $count > 0 ? 'success' : 'warning';
        $emoji = $count > 3 ? '✅' : ($count > 0 ? '⚡' : '⚠️');
        
        add_to_log($state['session_id'], $step, 'data_harvest', 
            "$emoji {$src['name']}: $count résultats", 
            "Requête: \"$clean_query\"", $type);
        
        $state['memory'][] = [
            'source' => $src['name'],
            'query' => $clean_query,
            'count' => $count,
            'items' => array_slice($result['items'] ?? [], 0, 5),
            'abstracts' => $result['abstracts'] ?? '',
        ];
        
        if($count > 0) {
            $state['sources_this_run'][] = $src['name'];
        }
        
        $state['step']++;
    }
    
    // Étape 9: Synthèse IA + génération hypothèse
    elseif($step === 9) {
        $valid_sources = array_filter($state['memory'], fn($m) => ($m['count'] ?? 0) > 0);
        
        if(count($valid_sources) < 2) {
            add_to_log($state['session_id'], 9, 'synthesis', '⚠️ Sources insuffisantes', 'Skip vers nouvelle cible', 'warning');
            $state['step'] = 0;
            $state['error_count']++;
        } else {
            // Construction contexte
            $ctx = "CIBLE: {$state['target']}\n";
            $ctx .= "DOMAINE: " . ($state['target_domain'] ?? 'biomed') . "\n";
            $ctx .= "DONNÉES COLLECTÉES (" . count($valid_sources) . " sources):\n\n";
            
            foreach($valid_sources as $m) {
                $ctx .= "[{$m['source']} — {$m['count']} résultats]\n";
                if(!empty($m['abstracts'])) {
                    $ctx .= substr($m['abstracts'], 0, 500) . "\n\n";
                }
            }
            
            add_to_log($state['session_id'], 9, 'synthesis', '🧠 Synthèse IA en cours...', count($valid_sources) . ' sources', 'info');
            
            $result = shu_mistral([
                ['role' => 'system', 'content' => $PROMPT_LIBRARY['hypothesis_generation']],
                ['role' => 'user', 'content' => $ctx]
            ], $MISTRAL_CONFIG['deep_model'], 3000, 0.5);
            
            if($result['success'] && isset($result['data']['hypothesis'])) {
                $d = $result['data'];
                $hyp_text = $d['hypothesis'] ?? '';
                
                if(strlen($hyp_text) < 30 || in_array(strtolower($hyp_text), ['n/a','null','unknown'])) {
                    add_to_log($state['session_id'], 9, 'synthesis', '⚠️ Hypothèse invalide', 'Skip', 'warning');
                    $state['step'] = 0;
                    $state['error_count']++;
                } else {
                    // Sauvegarde hypothèse
                    $hypothesis_data = [
                        'title' => $state['target'],
                        'hypothesis' => $d['hypothesis'],
                        'vulgarized' => $d['vulgarized'] ?? '',
                        'novelty_score' => $d['novelty_score'] ?? 0.5,
                        'confidence' => $d['confidence'] ?? 0.5,
                        'mechanism' => $d['mechanism'] ?? '',
                        'therapeutic_target' => $d['therapeutic_target'] ?? '',
                        'evidence_strength' => $d['evidence_strength'] ?? 'moderate',
                        'research_gaps' => $d['research_gaps'] ?? '',
                        'keywords' => $d['keywords'] ?? [],
                        'domain' => $state['target_domain'],
                        'target_name' => $state['target'],
                        'session_id' => $state['session_id'],
                        'step_completed' => 9,
                        'sources_used' => array_column($valid_sources, 'source'),
                    ];
                    
                    save_hypothesis($hypothesis_data);
                    $state['hypotheses'][] = $hypothesis_data;
                    
                    add_to_log($state['session_id'], 9, 'synthesis', '💡 Hypothèse générée!', 
                        substr($d['hypothesis'], 0, 100) . '...', 'success');
                    
                    $state['step'] = 0;
                }
            } else {
                add_to_log($state['session_id'], 9, 'synthesis', '❌ Erreur synthèse IA', $result['error'] ?? '', 'error');
                $state['step'] = 0;
                $state['error_count']++;
            }
        }
    }
    
    // Reset si trop d'erreurs
    if($state['error_count'] >= MAX_ERRORS_BEFORE_RESET) {
        $state['error_count'] = 0;
        $state['step'] = 0;
        add_to_log($state['session_id'], -1, 'reset', '🔄 Reset après erreurs multiples', '', 'warning');
    }
}

// Appel API source
function call_api_source($fn_name, $query) {
    if(!function_exists($fn_name)) {
        return ['count' => 0, 'items' => [], 'abstracts' => ''];
    }
    return $fn_name($query, 5);
}

// Fonctions API scientifiques (versions simplifiées)
function genesis_pubmed($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=" . urlencode($query) . "&retmode=json&retmax=$max";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $ids = $d['esearchresult']['idlist'] ?? [];
    $items = [];
    
    foreach(array_slice($ids, 0, min(3, count($ids))) as $id) {
        $items[] = ['pmid' => $id, 'title' => 'PMID: ' . $id, 'url' => "https://pubmed.ncbi.nlm.nih.gov/$id/"];
    }
    
    return ['count' => count($ids), 'items' => $items, 'abstracts' => 'PubMed results for: ' . $query];
}

function genesis_uniprot($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $gene = preg_replace('/[^A-Za-z0-9\-_]/', '', $query);
    $url = "https://rest.uniprot.org/uniprotkb/search?query=gene_name:" . urlencode($gene) . "+AND+reviewed:true&format=json&size=3";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $results = $d['results'] ?? [];
    $items = array_map(fn($p) => ['id' => $p['primaryAccession'] ?? 'N/A', 'name' => $p['uniProtkbId'] ?? 'N/A'], array_slice($results, 0, 3));
    
    return ['count' => count($results), 'items' => $items, 'abstracts' => 'UniProt results for: ' . $gene];
}

function genesis_clinvar($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=clinvar&term=" . urlencode($query) . "&retmode=json&retmax=$max";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $ids = $d['esearchresult']['idlist'] ?? [];
    $items = array_map(fn($id) => ['vid' => $id, 'url' => "https://www.ncbi.nlm.nih.gov/clinvar/variation/$id/"], array_slice($ids, 0, 3));
    
    return ['count' => count($ids), 'items' => $items, 'abstracts' => 'ClinVar variants: ' . implode(', ', array_column($items, 'vid'))];
}

function genesis_arxiv($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://export.arxiv.org/api/query?search_query=all:" . urlencode(str_replace(' ', '+', $query)) . "&max_results=3";
    $r = shu_curl($url, null, [], 50);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    @preg_match_all('/<entry>(.*?)<\/entry>/s', $r['data'], $entries);
    $items = [];
    foreach(array_slice($entries[1] ?? [], 0, 3) as $entry) {
        @preg_match('/<title>(.*?)<\/title>/s', $entry, $t);
        @preg_match('/<id>(.*?)<\/id>/s', $entry, $idm);
        $items[] = ['id' => basename($idm[1] ?? '#'), 'title' => substr(trim($t[1] ?? ''), 0, 100), 'url' => $idm[1] ?? '#'];
    }
    
    return ['count' => count($items), 'items' => $items, 'abstracts' => 'ArXiv preprints for: ' . $query];
}

function genesis_europepmc($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=" . urlencode($query) . "&resultType=lite&pageSize=3&format=json";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $results = $d['resultList']['result'] ?? [];
    $items = array_map(fn($p) => ['id' => $p['pmid'] ?? $p['id'] ?? 'N/A', 'title' => substr($p['title'] ?? 'N/A', 0, 100)], array_slice($results, 0, 3));
    
    return ['count' => count($results), 'items' => $items, 'abstracts' => 'EuropePMC results for: ' . $query];
}

function genesis_openalex($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://api.openalex.org/works?search=" . urlencode($query) . "&per-page=3&mailto=research@sciencehub.local";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $results = $d['results'] ?? [];
    $items = array_map(fn($p) => ['id' => $p['id'] ?? 'N/A', 'title' => substr($p['title'] ?? 'N/A', 0, 100)], array_slice($results, 0, 3));
    
    return ['count' => count($results), 'items' => $items, 'abstracts' => 'OpenAlex results for: ' . $query];
}

function genesis_chembl($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://www.ebi.ac.uk/chembl/api/data/molecule.json?pref_name__icontains=" . urlencode($query) . "&limit=3";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $results = $d['molecules'] ?? [];
    $items = array_map(fn($m) => ['id' => $m['molecule_chembl_id'] ?? 'N/A', 'name' => $m['pref_name'] ?? 'N/A'], array_slice($results, 0, 3));
    
    return ['count' => count($results), 'items' => $items, 'abstracts' => 'ChEMBL molecules for: ' . $query];
}

function genesis_wikidata($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    $url = "https://www.wikidata.org/w/api.php?action=wbsearchentities&search=" . urlencode($query) . "&language=en&format=json&limit=3";
    $r = shu_curl($url);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'abstracts' => ''];
    
    $d = @json_decode($r['data'], true);
    $results = $d['search'] ?? [];
    $items = array_map(fn($e) => ['id' => $e['id'] ?? 'N/A', 'label' => $e['label'] ?? 'N/A'], array_slice($results, 0, 3));
    
    return ['count' => count($results), 'items' => $items, 'abstracts' => 'Wikidata entities for: ' . $query];
}

// Sauvegarde état
function save_state($file, $state) {
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
}

// Récupération logs récents
$logs = get_recent_logs($SESSION_ID, 50);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Autonome — GENESIS-ULTRA v9.1</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #080c10; --surface: #0d1319; --surface2: #111820; --surface3: #161f2a;
            --border: rgba(0, 180, 255, 0.12); --border2: rgba(0, 180, 255, 0.25);
            --accent: #00c8ff; --accent2: #0affb0; --accent3: #ff3d6b; --accent4: #ffd700;
            --text: #c8dff0; --text-dim: #5a7a95; --text-bright: #e8f4ff;
            --mono: 'Space Mono', monospace; --display: 'Syne', sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--mono); font-size: 12px; }
        body::before { content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E"); opacity: 0.6; }
        #root { position: relative; z-index: 1; display: grid; grid-template-rows: auto 1fr auto; min-height: 100vh; }
        
        #header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border2); }
        .header-brand { font-family: var(--display); font-size: 20px; font-weight: 800; color: var(--text-bright); }
        .header-brand span { color: var(--accent); }
        .nav-btn { padding: 8px 16px; border: 1px solid var(--border); background: var(--surface2); color: var(--text); text-decoration: none; font-size: 11px; margin-left: 8px; transition: all 0.2s; }
        .nav-btn:hover { border-color: var(--accent); color: var(--accent); }
        
        #main { padding: 24px; display: grid; grid-template-columns: 1fr 400px; gap: 20px; overflow: hidden; }
        
        .panel { background: var(--surface); border: 1px solid var(--border); padding: 20px; overflow-y: auto; max-height: calc(100vh - 200px); }
        .panel-title { font-family: var(--display); font-size: 16px; font-weight: 700; color: var(--text-bright); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        .status-display { background: var(--surface2); padding: 16px; border-radius: 4px; margin-bottom: 20px; }
        .status-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .status-label { color: var(--text-dim); }
        .status-value { color: var(--accent); font-weight: 700; }
        
        .control-panel { display: grid; gap: 12px; margin-top: 20px; }
        .btn { padding: 14px 20px; border: none; border-radius: 4px; font-family: var(--mono); font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #000; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,200,255,0.3); }
        .btn-secondary { background: var(--surface2); border: 1px solid var(--border); color: var(--text); }
        .btn-secondary:hover { border-color: var(--accent3); color: var(--accent3); }
        
        .log-container { max-height: 500px; overflow-y: auto; }
        .log-entry { padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 11px; }
        .log-time { color: var(--text-dim); margin-right: 8px; }
        .log-phase { color: var(--accent4); margin-right: 8px; }
        .log-message.info { color: var(--accent); }
        .log-message.success { color: var(--accent2); }
        .log-message.warning { color: var(--accent4); }
        .log-message.error { color: var(--accent3); }
        
        .target-card { background: var(--surface2); padding: 16px; border-left: 3px solid var(--accent2); margin-bottom: 16px; }
        .target-name { font-family: var(--display); font-size: 14px; font-weight: 700; color: var(--text-bright); }
        .target-domain { font-size: 10px; color: var(--text-dim); margin-top: 4px; }
        
        #footer { padding: 16px 24px; border-top: 1px solid var(--border); text-align: center; font-size: 10px; color: var(--text-dim); }
        
        @media (max-width: 900px) { #main { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div id="root">
        <header id="header">
            <div class="header-brand">GENESIS-ULTRA <span>v9.1</span> — Mode Autonome</div>
            <nav>
                <a href="index.php" class="nav-btn">🏠 Accueil</a>
                <a href="guided.php" class="nav-btn">📋 Mode Guidé</a>
                <a href="dashboard.php" class="nav-btn">📊 Dashboard</a>
            </nav>
        </header>
        
        <main id="main">
            <div class="panel">
                <div class="panel-title">🤖 Agent Autonome de Recherche</div>
                
                <div class="status-display">
                    <div class="status-row"><span class="status-label">Cible Actuelle:</span><span class="status-value"><?= $state['target'] ?? 'En attente...' ?></span></div>
                    <div class="status-row"><span class="status-label">Domaine:</span><span class="status-value"><?= $state['target_domain'] ?? '-' ?></span></div>
                    <div class="status-row"><span class="status-label">Étape:</span><span class="status-value"><?= $state['step'] ?>/9</span></div>
                    <div class="status-row"><span class="status-label">Sources Collectées:</span><span class="status-value"><?= count($state['sources_this_run']) ?>/8</span></div>
                    <div class="status-row"><span class="status-label">Hypothèses Générées:</span><span class="status-value"><?= count($state['hypotheses']) ?></span></div>
                    <div class="status-row"><span class="status-label">Erreurs:</span><span class="status-value"><?= $state['error_count'] ?>/<?= MAX_ERRORS_BEFORE_RESET ?></span></div>
                </div>
                
                <?php if($state['target']): ?>
                <div class="target-card">
                    <div class="target-name">🎯 <?= htmlspecialchars($state['target']) ?></div>
                    <div class="target-domain">Domaine: <?= htmlspecialchars($state['target_domain'] ?? 'N/A') ?> • Angle: <?= htmlspecialchars($state['target_angle'] ?? 'N/A') ?></div>
                </div>
                <?php endif; ?>
                
                <div class="control-panel">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $state['step'] > 0 ? 'continue' : 'start' ?>">
                        <button type="submit" class="btn btn-primary">
                            <?= $state['step'] > 0 ? '▶️ Continuer l\'exécution' : '🚀 Démarrer la recherche autonome' ?>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="btn btn-secondary">🔄 Reset Complet</button>
                    </form>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-title">📝 Logs d'Exécution</div>
                <div class="log-container">
                    <?php if(empty($logs)): ?>
                        <div class="log-entry" style="color: var(--text-dim);">Aucun log. Démarrez la recherche pour voir l'activité.</div>
                    <?php else: ?>
                        <?php foreach(array_reverse($logs) as $log): ?>
                            <div class="log-entry">
                                <span class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                <span class="log-phase">[<?= $log['phase'] ?>]</span>
                                <span class="log-message <?= $log['log_type'] ?>"><?= htmlspecialchars($log['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <footer id="footer">
            GENESIS-ULTRA v9.1 • 9 étapes autonomes • 8 sources scientifiques • Mistral AI • Hostinger Compatible
        </footer>
    </div>
</body>
</html>
