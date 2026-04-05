<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║  RECHERCHE.PHP — GÉNÉRATEUR D'EXPÉRIENCES PAR IA                  ║
 * ║  Lit les fichiers etape1, utilise Mistral pour générer du code    ║
 * ║  Crée dossiers + index.php + expériences                          ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 */

define('SP_VERSION',        '1.0.0');
define('STORAGE_DIR',       __DIR__ . '/storage');
define('PROCESSED_DIR',     STORAGE_DIR . '/processed');
define('MISTRAL_ENDPOINT',  'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL',     'mistral-small-latest');

$GLOBALS['MISTRAL_KEYS'] = array_values(array_filter(explode(',',
 getenv('MISTRAL_KEYS') ?: 'apikeyhere,apikeyhere,apikeyhere'
)));






// Création des dossiers si nécessaire
foreach ([STORAGE_DIR, PROCESSED_DIR] as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

function sp_curl(string $url, ?array $post = null, array $headers = [], int $timeout = 60): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,   CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,    CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,    CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_USERAGENT      => 'SciencePulse-Recherche/'.SP_VERSION,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $data = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['success'=>($data&&!$err&&$code>=200&&$code<300),'data'=>$data,'error'=>$err?:null,'code'=>$code];
}

function json_out(array $d, int $s = 200): never {
    while (ob_get_level()>0) ob_end_clean();
    http_response_code($s); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
}

function find_etape1_files(): array {
    $files = [];
    if (!is_dir(PROCESSED_DIR)) return $files;
    $iterator = new DirectoryIterator(PROCESSED_DIR);
    foreach ($iterator as $file) {
        if ($file->isFile() && strpos($file->getFilename(), 'etape1') === 0 && $file->getExtension() === 'json') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function generate_experiment_code(array $data): array {
    $keys = $GLOBALS['MISTRAL_KEYS'];
    if (empty($keys)) {
        return ['success' => false, 'error' => 'Aucune clé API Mistral configurée'];
    }

    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $prompt = "Tu es un expert en développement PHP et en création d'expériences scientifiques interactives.

À partir des données JSON suivantes, tu dois générer DU VRAI CODE PHP fonctionnel pour créer une expérience scientifique interactive.

DONNÉES DE L'EXPÉRIENCE:
$content

TA MISSION:
1. Analyse les données pour comprendre le type d'expérience à réaliser
2. Génère un fichier index.php COMPLET et FONCTIONNEL qui contient:
   - Une interface utilisateur HTML/CSS moderne et responsive
   - Du code PHP backend pour traiter les données
   - Du JavaScript pour l'interactivité
   - L'expérience doit être RÉELLEMENT EXÉCUTABLE quand on lance index.php

3. Le code doit inclure:
   - En-tête PHP avec configuration
   - Structure HTML complète avec DOCTYPE
   - Styles CSS intégrés (design moderne, couleurs science/tech)
   - Logique PHP pour traiter les données de l'expérience
   - JavaScript pour interactions dynamiques
   - Gestion des formulaires si nécessaire
   - Affichage des résultats de l'expérience

CONTRAINTES:
- Retourne UNIQUEMENT le code PHP complet, AUCUNE explication, AUCUN backtick
- Le code doit être prêt à l'emploi (copier-coller dans index.php)
- Utilise des bonnes pratiques PHP 8+
- Interface moderne et professionnelle
- L'expérience doit fonctionner immédiatement

Génère maintenant le code PHP complet:";

    $res = sp_curl(MISTRAL_ENDPOINT, [
        'model' => MISTRAL_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 54096,
        'temperature' => 0.3
    ], ['Authorization: Bearer ' . $keys[0]], 120);

    if (!$res['success']) {
        return ['success' => false, 'error' => 'Erreur API Mistral: ' . ($res['error'] ?? 'HTTP ' . $res['code'])];
    }

    $d = json_decode($res['data'], true);
    $code = trim($d['choices'][0]['message']['content'] ?? '');
    $tokens = $d['usage']['total_tokens'] ?? 0;

    // Nettoyage du code (suppression des backticks s'ils existent)
    $code = preg_replace('/^```(?:php|html)?\s*/i', '', $code);
    $code = preg_replace('/\s*```$/i', '', $code);
    $code = trim($code);

    if (empty($code)) {
        return ['success' => false, 'error' => 'Réponse vide de l\'IA'];
    }

    return ['success' => true, 'code' => $code, 'tokens' => $tokens];
}

// ═══════════════════════════════════════════════════════════════════
// GESTION DES ACTIONS
// ═══════════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';

if ($action === 'list_etape1') {
    $files = find_etape1_files();
    $result = [];
    foreach ($files as $f) {
        $basename = basename($f);
        $data = json_decode(file_get_contents($f), true) ?? [];
        $result[] = [
            'filename' => $basename,
            'path' => $f,
            'size' => filesize($f),
            'modified' => date('Y-m-d H:i:s', filemtime($f)),
            'preview' => isset($data['title']) ? $data['title'] : (isset($data['ai_analysis']) ? substr($data['ai_analysis'], 0, 100) : 'Voir contenu')
        ];
    }
    json_out(['status' => 'ok', 'files' => $result, 'count' => count($result)]);
}

if ($action === 'process_file') {
    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';

    if (empty($filename)) {
        json_out(['status' => 'error', 'message' => 'Nom de fichier requis'], 400);
    }

    $sourcePath = PROCESSED_DIR . '/' . $filename;
    if (!file_exists($sourcePath)) {
        json_out(['status' => 'error', 'message' => 'Fichier introuvable: ' . $filename], 404);
    }

    // Charger les données du fichier
    $data = json_decode(file_get_contents($sourcePath), true);
    if (!is_array($data)) {
        json_out(['status' => 'error', 'message' => 'JSON invalide'], 400);
    }

    // Nom du dossier à créer (sans le prefix etape1-)
    $folderName = preg_replace('/^etape1[-_]?/', '', pathinfo($filename, PATHINFO_FILENAME));
    $folderName = preg_replace('/\.json$/', '', $folderName);
    $folderName = uniqid($folderName . '_'); // Ajout d'un identifiant unique

    $targetDir = PROCESSED_DIR . '/' . $folderName;

    // Créer le dossier
    if (!mkdir($targetDir, 0755, true)) {
        json_out(['status' => 'error', 'message' => 'Impossible de créer le dossier: ' . $targetDir], 500);
    }

    // Renommer etape1 -> etape2
    $newFilename = preg_replace('/^etape1/', 'etape2', $filename);
    $targetEtape2 = $targetDir . '/' . $newFilename;

    if (!copy($sourcePath, $targetEtape2)) {
        json_out(['status' => 'error', 'message' => 'Erreur lors de la copie du fichier etape2'], 500);
    }

    // Supprimer l'original (optionnel, on peut commenter cette ligne)
    // unlink($sourcePath);

    // Appel à l'IA pour générer le code
    $result = generate_experiment_code($data);

    if (!$result['success']) {
        // Nettoyer le dossier créé en cas d'erreur
        @unlink($targetEtape2);
        @rmdir($targetDir);
        json_out(['status' => 'error', 'message' => $result['error']], 500);
    }

    // Écrire le fichier index.php généré par l'IA
    $indexPath = $targetDir . '/index.php';
    if (file_put_contents($indexPath, $result['code']) === false) {
        @unlink($targetEtape2);
        @rmdir($targetDir);
        json_out(['status' => 'error', 'message' => 'Erreur lors de l\'écriture de index.php'], 500);
    }

    // Générer un fichier README explicatif
    $readmeContent = "# Expérience générée par IA\n\n"
                   . "**Dossier créé:** {$folderName}\n"
                   . "**Date:** " . date('Y-m-d H:i:s') . "\n"
                   . "**Fichier source:** {$filename}\n"
                   . "**Tokens utilisés:** {$result['tokens']}\n\n"
                   . "## Fichiers contenus:\n"
                   . "- `index.php` : Page principale avec l'expérience interactive\n"
                   . "- `{$newFilename}` : Données originales de l'expérience\n\n"
                   . "## Utilisation:\n"
                   . "Ouvrez `index.php` dans votre navigateur pour lancer l'expérience.\n";

    file_put_contents($targetDir . '/README.md', $readmeContent);

    json_out([
        'status' => 'ok',
        'folder_created' => $folderName,
        'folder_path' => $targetDir,
        'etape2_file' => $newFilename,
        'index_generated' => true,
        'tokens_used' => $result['tokens'],
        'message' => "Expérience générée avec succès dans: $folderName"
    ]);
}

if ($action === 'get_folder_contents') {
    $folder = $_GET['folder'] ?? '';
    if (empty($folder)) {
        json_out(['status' => 'error', 'message' => 'Dossier requis'], 400);
    }

    $targetDir = PROCESSED_DIR . '/' . basename($folder);
    if (!is_dir($targetDir)) {
        json_out(['status' => 'error', 'message' => 'Dossier introuvable'], 404);
    }

    $files = [];
    $iterator = new DirectoryIterator($targetDir);
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'type' => $file->getExtension()
            ];
        }
    }

    json_out(['status' => 'ok', 'folder' => $folder, 'files' => $files]);
}

if ($action === 'list_folders') {
    $folders = [];
    if (is_dir(PROCESSED_DIR)) {
        $iterator = new DirectoryIterator(PROCESSED_DIR);
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $name = $item->getFilename();
                // Vérifier si c'est un dossier d'expérience (contient index.php)
                if (file_exists($item->getPathname() . '/index.php')) {
                    $files = [];
                    $subIterator = new DirectoryIterator($item->getPathname());
                    foreach ($subIterator as $f) {
                        if ($f->isFile()) {
                            $files[] = $f->getFilename();
                        }
                    }
                    $folders[] = [
                        'name' => $name,
                        'path' => $item->getPathname(),
                        'files' => $files,
                        'created' => date('Y-m-d H:i:s', filemtime($item->getPathname()))
                    ];
                }
            }
        }
    }
    // Trier par date de création décroissante
    usort($folders, fn($a, $b) => strcmp($b['created'], $a['created']));
    json_out(['status' => 'ok', 'folders' => $folders, 'count' => count($folders)]);
}

// ═══════════════════════════════════════════════════════════════════
// INTERFACE UTILISATEUR
// ═══════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔬 Recherche & Génération d'Expériences - Science Pulse</title>
<style>
:root{--bg:#0a0e1a;--bg1:#111827;--bg2:#1f2937;--primary:#6366f1;--primary2:#8b5cf6;--cyan:#06b6d4;--green:#10b981;--amber:#f59e0b;--red:#ef4444;--t0:#f9fafb;--t1:#9ca3af;--t2:#6b7280;--mono:'JetBrains Mono','Fira Code',Consolas,monospace}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,var(--bg) 0%,#0f172a 100%);color:var(--t0);min-height:100vh;line-height:1.6}
.container{max-width:1400px;margin:0 auto;padding:24px}
header{background:linear-gradient(135deg,var(--bg1) 0%,var(--bg2) 100%);border-bottom:1px solid rgba(99,102,241,0.2);padding:24px;margin-bottom:32px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3)}
h1{font-size:28px;font-weight:800;background:linear-gradient(135deg,var(--primary) 0%,var(--cyan) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px}
.subtitle{color:var(--t1);font-size:14px}
.card{background:var(--bg1);border:1px solid rgba(99,102,241,0.15);border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,0.2)}
.card h2{font-size:18px;color:var(--primary);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.file-list{display:grid;gap:12px}
.file-item{background:var(--bg2);border:1px solid rgba(99,102,241,0.1);border-radius:8px;padding:16px;transition:all 0.2s;cursor:pointer}
.file-item:hover{border-color:var(--primary);transform:translateX(4px);box-shadow:0 4px 12px rgba(99,102,241,0.2)}
.file-name{font-family:var(--mono);font-size:13px;font-weight:700;color:var(--cyan);margin-bottom:4px}
.file-meta{font-size:12px;color:var(--t2);display:flex;gap:16px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;transition:all 0.2s}
.btn-primary{background:linear-gradient(135deg,var(--primary) 0%,var(--primary2) 100%);color:white}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(99,102,241,0.4)}
.btn-primary:disabled{opacity:0.5;cursor:not-allowed;transform:none}
.btn-secondary{background:var(--bg2);color:var(--t0);border:1px solid var(--t2)}
.btn-secondary:hover{background:var(--bg2);border-color:var(--primary)}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
.status-success{background:rgba(16,185,129,0.2);color:var(--green)}
.status-processing{background:rgba(245,158,11,0.2);color:var(--amber)}
.status-error{background:rgba(239,68,68,0.2);color:var(--red)}
.progress-bar{height:4px;background:var(--bg2);border-radius:2px;overflow:hidden;margin-top:12px}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--primary) 0%,var(--cyan) 100%);width:0%;transition:width 0.3s}
.log-console{background:#0d1117;border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:16px;font-family:var(--mono);font-size:12px;max-height:300px;overflow-y:auto}
.log-entry{padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.log-info{color:var(--cyan)}
.log-success{color:var(--green)}
.log-error{color:var(--red)}
.log-warn{color:var(--amber)}
.folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:20px}
.folder-card{background:var(--bg2);border:1px solid rgba(99,102,241,0.1);border-radius:8px;padding:16px}
.folder-name{font-family:var(--mono);font-size:14px;font-weight:700;color:var(--primary);margin-bottom:8px}
.folder-files{font-size:12px;color:var(--t2)}
.hidden{display:none}
.loading-spinner{display:inline-block;width:16px;height:16px;border:2px solid var(--t2);border-top-color:var(--primary);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.alert{padding:16px;border-radius:8px;margin-bottom:20px}
.alert-info{background:rgba(6,182,212,0.1);border:1px solid rgba(6,182,212,0.3);color:var(--cyan)}
</style>
</head>
<body>
<div class="container">
<header>
<h1>🔬 Recherche & Génération d'Expériences</h1>
<p class="subtitle">Recherche les fichiers etape1, utilise Mistral IA pour générer du code PHP exécutable</p>
</header>

<div class="alert alert-info">
<strong>💡 Comment ça marche:</strong> Cette page recherche automatiquement les fichiers JSON commençant par <code>etape1</code> dans <code>storage/processed</code>,
utilise l'IA Mistral pour analyser le contenu et générer du vrai code PHP fonctionnel, crée un dossier dédié avec le fichier renommé en <code>etape2</code>
et un <code>index.php</code> contenant l'expérience interactive prête à être exécutée.
</div>

<!-- Section: Liste des fichiers etape1 -->
<div class="card">
<h2>📁 Fichiers etape1 trouvés</h2>
<button class="btn btn-primary" onclick="loadEtape1Files()" id="btn-refresh">
<span>⟳</span> Actualiser la liste
</button>
<div id="file-list" class="file-list" style="margin-top:20px"></div>
</div>

<!-- Section: Progression -->
<div class="card hidden" id="progress-card">
<h2>⚙️ Traitement en cours</h2>
<div id="progress-status" style="margin-bottom:12px;color:var(--t1)">Initialisation...</div>
<div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
<div class="log-console" id="log-console" style="margin-top:16px"></div>
</div>

<!-- Section: Dossiers créés -->
<div class="card">
<h2>📂 Dossiers d'expériences créés</h2>
<button class="btn btn-secondary" onclick="loadFolders()" id="btn-folders">
<span>⟳</span> Actualiser
</button>
<div id="folder-grid" class="folder-grid"></div>
</div>
</div>

<script>
const API_BASE = '?action=';

function log(msg, type='info'){
    const console = document.getElementById('log-console');
    if(!console) return;
    const cls = type==='success'?'log-success':type==='error'?'log-error':type==='warn'?'log-warn':'log-info';
    const time = new Date().toLocaleTimeString('fr-FR');
    console.innerHTML += `<div class="log-entry ${cls}">[${time}] ${msg}</div>`;
    console.scrollTop = console.scrollHeight;
}

function clearLog(){
    const console = document.getElementById('log-console');
    if(console) console.innerHTML = '';
}

async function loadEtape1Files(){
    const container = document.getElementById('file-list');
    const btn = document.getElementById('btn-refresh');
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Chargement...';

    try{
        const r = await fetch(API_BASE + 'list_etape1').then(res=>res.json());
        if(r.status === 'ok' && r.files.length > 0){
            container.innerHTML = r.files.map(f => `
                <div class="file-item" onclick="processFile('${f.filename}')">
                    <div class="file-name">📄 ${escapeHtml(f.filename)}</div>
                    <div class="file-meta">
                        <span>📏 ${(f.size/1024).toFixed(2)} KB</span>
                        <span>🕐 ${f.modified}</span>
                        <span>📝 ${escapeHtml(f.preview.substring(0,80))}${f.preview.length>80?'...':''}</span>
                    </div>
                    <div style="margin-top:10px">
                        <span class="status-badge status-success">Prêt à traiter</span>
                        <button class="btn btn-primary" style="float:right;padding:6px 12px;font-size:11px" onclick="event.stopPropagation();processFile('${f.filename}')">
                            🚀 Traiter avec IA
                        </button>
                    </div>
                </div>
            `).join('');
            log(`✅ ${r.count} fichier(s) etape1 trouvé(s)`, 'success');
        }else{
            container.innerHTML = '<div style="padding:24px;text-align:center;color:var(--t2)">Aucun fichier etape1 trouvé dans storage/processed</div>';
            log('ℹ️ Aucun fichier etape1 trouvé', 'info');
        }
    }catch(e){
        container.innerHTML = `<div style="padding:24px;text-align:center;color:var(--red)">Erreur: ${escapeHtml(e.message)}</div>`;
        log('❌ Erreur chargement: '+e.message, 'error');
    }finally{
        btn.disabled = false;
        btn.innerHTML = '<span>⟳</span> Actualiser la liste';
    }
}

async function processFile(filename){
    if(!confirm(`Traiter le fichier "${filename}" avec l'IA Mistral?\n\nCela va:\n1. Créer un dossier dédié\n2. Renommer le fichier en etape2\n3. Générer un index.php avec l'expérience\n\nContinuer?`)) return;

    document.getElementById('progress-card').classList.remove('hidden');
    clearLog();

    const statusEl = document.getElementById('progress-status');
    const fillEl = document.getElementById('progress-fill');

    log(`🎯 Démarrage traitement: ${filename}`, 'info');
    statusEl.textContent = 'Analyse du fichier...';
    fillEl.style.width = '10%';

    try{
        // Étape 1: Lecture et analyse
        log('📖 Lecture du fichier JSON...', 'info');
        await sleep(500);
        fillEl.style.width = '20%';

        // Étape 2: Appel IA
        log('🤖 Envoi à Mistral IA pour analyse...', 'info');
        statusEl.textContent = 'Analyse IA en cours...';
        fillEl.style.width = '40%';
        await sleep(300);

        log('⏳ Génération du code PHP par IA...', 'warn');
        fillEl.style.width = '60%';

        const r = await fetch(API_BASE + 'process_file', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({filename: filename})
        }).then(res => res.json());

        fillEl.style.width = '90%';

        if(r.status === 'ok'){
            log(`✅ Dossier créé: ${r.folder_created}`, 'success');
            log(`📄 Fichier renommé: ${r.etape2_file}`, 'success');
            log(`💻 index.php généré (${r.tokens_used} tokens)`, 'success');
            log(`📍 Chemin: ${r.folder_path}`, 'info');
            statusEl.textContent = 'Traitement terminé avec succès!';
            fillEl.style.width = '100%';

            setTimeout(() => {
                document.getElementById('progress-card').classList.add('hidden');
                loadFolders();
                loadEtape1Files(); // Rafraîchir la liste
            }, 2000);

            alert(`✅ Expérience générée avec succès!\n\nDossier: ${r.folder_created}\nFichiers créés:\n- ${r.etape2_file}\n- index.php\n\nL'expérience est prête à être exécutée!`);
        }else{
            throw new Error(r.message || 'Erreur inconnue');
        }
    }catch(e){
        log(`❌ Erreur: ${e.message}`, 'error');
        statusEl.textContent = 'Échec du traitement';
        fillEl.style.width = '0%';
        alert('❌ Erreur: ' + e.message);
    }
}

async function loadFolders(){
    const grid = document.getElementById('folder-grid');
    const btn = document.getElementById('btn-folders');
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Chargement...';

    try{
        // On scanne le dossier processed pour trouver les sous-dossiers
        const r = await fetch(API_BASE + 'list_folders').then(res => res.json());
        if(r.status === 'ok' && r.folders.length > 0){
            grid.innerHTML = r.folders.map(f => `
                <div class="folder-card">
                    <div class="folder-name">📁 ${escapeHtml(f.name)}</div>
                    <div class="folder-files">${f.files.join(', ')}</div>
                    <div style="margin-top:12px">
                        <a href="storage/processed/${escapeHtml(f.name)}/index.php" target="_blank" class="btn btn-primary" style="padding:6px 12px;font-size:11px">
                            🚀 Lancer l'expérience
                        </a>
                    </div>
                </div>
            `).join('');
            log(`✅ ${r.folders.length} dossier(s) d'expérience trouvé(s)`, 'success');
        }else{
            grid.innerHTML = '<div style="padding:24px;text-align:center;color:var(--t2)">Aucun dossier d\'expérience créé</div>';
        }
    }catch(e){
        grid.innerHTML = `<div style="padding:24px;text-align:center;color:var(--red)">Erreur: ${escapeHtml(e.message)}</div>`;
    }finally{
        btn.disabled = false;
        btn.innerHTML = '<span>⟳</span> Actualiser';
    }
}

function sleep(ms){return new Promise(resolve=>setTimeout(resolve,ms))}
function escapeHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    loadEtape1Files();
    loadFolders();
});
</script>
</body>
</html>
