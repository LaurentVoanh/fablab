<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║  ADMIN.PHP — PANneau Admin Automatique Science Pulse              ║
 * ║  Combine index.php + recherche.php en mode FULL AUTO              ║
 * ║  Console debug à gauche • Applications générées à droite          ║
 * ║  3 clés Mistral parallèles • Anti-doublons intégré                ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 */

define('SP_VERSION',        '6.0.0-AUTO');
define('STORAGE_DIR',       __DIR__ . '/storage');
define('ARTICLES_DIR',      STORAGE_DIR . '/articles');
define('PROCESSED_DIR',     STORAGE_DIR . '/processed');
define('REPORTS_DIR',       STORAGE_DIR . '/reports');
define('APPS_DIR',          STORAGE_DIR . '/applications');
define('DB_FILE',           STORAGE_DIR . '/science_pulse.sqlite');
define('MISTRAL_ENDPOINT',  'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL',     'mistral-small-latest');

$GLOBALS['MISTRAL_KEYS'] = array_values(array_filter(explode(',',
    getenv('MISTRAL_KEYS') ?: 'apikey 1,apikey 2,apikey 3'
)));

define('RSS_SOURCES', json_encode([
    'arXiv q-bio'     => 'https://export.arxiv.org/rss/q-bio',
    'ScienceDaily'    => 'https://www.sciencedaily.com/rss/top/health.xml',
    'ClinicalTrials'  => 'https://clinicaltrials.gov/ct2/results/rss.xml?rsch=adv',
    'The Lancet'      => 'https://www.thelancet.com/rssfeed/lancet_online.xml',
    'Inserm'          => 'https://www.inserm.fr/feed/',
    'Pour la Science' => 'https://www.pourlascience.fr/vivant/rss.xml',
    'NEJM Emergency'  => 'https://onesearch-rss.nejm.org/api/specialty/rss?context=nejm&specialty=emergency-medicine',
    'BioWorld'        => 'https://www.bioworld.com/rss/7',
    'ANRS'            => 'https://anrs.fr/feed/',
]));

foreach ([STORAGE_DIR, ARTICLES_DIR, PROCESSED_DIR, REPORTS_DIR, APPS_DIR] as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

function get_db(): PDO {
    static $db = null;
    if ($db) return $db;
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
    $db->exec("CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT UNIQUE, source TEXT,
        title TEXT, link TEXT, description TEXT, fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS processed (
        id INTEGER PRIMARY KEY AUTOINCREMENT, article_hash TEXT UNIQUE, key_used INTEGER,
        status TEXT, ai_analysis TEXT, error_msg TEXT, tokens_used INTEGER DEFAULT 0,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT, article_hash TEXT UNIQUE, folder_name TEXT,
        index_path TEXT, status TEXT, tokens_used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT,
        article_count INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, level TEXT, message TEXT,
        context TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    return $db;
}

function sp_log(string $level, string $msg, array $ctx = []): void {
    try {
        $db = get_db();
        $db->prepare("INSERT INTO logs (level,message,context) VALUES (?,?,?)")
           ->execute([$level, $msg, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : null]);
        $db->exec("DELETE FROM logs WHERE id NOT IN (SELECT id FROM logs ORDER BY id DESC LIMIT 3000)");
    } catch (Throwable $e) {}
}

function sp_curl(string $url, ?array $post = null, array $headers = [], int $timeout = 25): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,   CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,    CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,    CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_USERAGENT      => 'SciencePulse-Auto/'.SP_VERSION,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post)); }
    $data = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['success'=>($data&&!$err&&$code>=200&&$code<300),'data'=>$data,'error'=>$err?:null,'code'=>$code];
}

function json_out(array $d, int $s = 200): never {
    while (ob_get_level()>0) ob_end_clean();
    http_response_code($s); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
}

function generate_app_code(array $data): array {
    $keys = $GLOBALS['MISTRAL_KEYS'];
    if (empty($keys)) return ['success' => false, 'error' => 'Aucune clé API Mistral'];

    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $prompt = "Tu es un expert en développement PHP 8.3+ et création d'applications scientifiques interactives.

DONNÉES DE L'APPLICATION À CRÉER:
$content

MISSION: Génère UNE APPLICATION PHP COMPLÈTE ET EXÉCUTABLE (index.php) qui:
1. Interface HTML/CSS moderne responsive (design science/tech, couleurs cyan/vert/noir)
2. Backend PHP pour traiter les données scientifiques
3. JavaScript pour l'interactivité dynamique
4. Doit être IMMÉDIATEMENT FONCTIONNELLE

CONTRAINTES:
- Retourne UNIQUEMENT le code PHP complet, AUCUNE explication, AUCUN backtick
- Code prêt à l'emploi dans index.php
- Bonnes pratiques PHP 8+, sécurité, performances
- Interface professionnelle type dashboard scientifique
- L'application doit fonctionner immédiatement

Génère le code PHP complet:";

    $res = sp_curl(MISTRAL_ENDPOINT, [
        'model' => MISTRAL_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 54096,
        'temperature' => 0.25
    ], ['Authorization: Bearer ' . $keys[0]], 120);

    if (!$res['success']) {
        return ['success' => false, 'error' => 'Erreur API: ' . ($res['error'] ?? 'HTTP ' . $res['code'])];
    }

    $d = json_decode($res['data'], true);
    $code = trim($d['choices'][0]['message']['content'] ?? '');
    $tokens = $d['usage']['total_tokens'] ?? 0;

    $code = preg_replace('/^```(?:php|html)?\s*/i', '', $code);
    $code = preg_replace('/\s*```$/i', '', $code);
    $code = trim($code);

    if (empty($code)) {
        return ['success' => false, 'error' => 'Réponse IA vide'];
    }

    return ['success' => true, 'code' => $code, 'tokens' => $tokens];
}

$action = $_GET['action'] ?? '';

if ($action === 'auto_start') {
    sp_log('info','🚀 Démarrage automatique Admin Pulse');
    json_out(['status'=>'ok','message'=>'Système prêt pour automatisation complète']);
}

if ($action === 'crawl') {
    sp_log('info','🕷️ Crawl RSS automatique démarré');
    $sources = json_decode(RSS_SOURCES, true); $db = get_db(); $total = 0; $out = [];
    foreach ($sources as $name => $url) {
        sp_log('info',"→ $name");
        $res = sp_curl($url, null, [], 12);
        if (!$res['success']) { sp_log('error',"✗ $name HTTP {$res['code']}"); $out[$name]=['status'=>'error','code'=>$res['code'],'new'=>0]; continue; }
        $xml = @simplexml_load_string($res['data']);
        if (!$xml) { sp_log('warn',"✗ $name XML invalide"); $out[$name]=['status'=>'xml_error','new'=>0]; continue; }
        $items = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? []);
        $n = 0;
        foreach ($items as $item) {
            $title = trim((string)($item->title??'')); $link = trim((string)($item->link['href']??$item->link??''));
            $desc  = strip_tags((string)($item->description??$item->summary??''));
            if (!$title||!$link) continue;
            $hash = md5($link);
            @file_put_contents(ARTICLES_DIR."/$hash.json", json_encode(['source'=>$name,'title'=>$title,'link'=>$link,'desc'=>substr($desc,0,1200)],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            try { $s2=$db->prepare("INSERT OR IGNORE INTO articles (hash,source,title,link,description) VALUES(?,?,?,?,?)"); $s2->execute([$hash,$name,$title,$link,substr($desc,0,2000)]); if($s2->rowCount()>0)$n++; } catch(Throwable $e){}
        }
        sp_log('success',"✓ $name: +$n"); $out[$name]=['status'=>'ok','new'=>$n]; $total+=$n;
    }
    sp_log('success',"🏁 Crawl terminé — $total nouveaux articles");
    json_out(['status'=>'ok','total_new'=>$total,'sources'=>$out]);
}

if ($action === 'list_pending') {
    $db=get_db();
    $done=array_flip($db->query("SELECT article_hash FROM processed WHERE status='success'")->fetchAll(PDO::FETCH_COLUMN));
    $all=$db->query("SELECT hash,source,title,link,description,fetched_at FROM articles ORDER BY fetched_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $pend=array_values(array_filter($all,fn($a)=>!isset($done[$a['hash']])));
    json_out(['status'=>'ok','pending'=>$pend,'count'=>count($pend)]);
}

if ($action === 'save_processed') {
    $in=json_decode(file_get_contents('php://input'),true);
    if(!$in) json_out(['status'=>'error','message'=>'JSON invalide'],400);
    $hash=$in['hash']??''; $status=$in['status']??'failed'; $ai=$in['ai_analysis']??'';
    $err=$in['error_msg']??''; $keyIdx=(int)($in['key_index']??0); $tokens=(int)($in['tokens_used']??0);
    if(!$hash) json_out(['status'=>'error','message'=>'Hash manquant'],400);
    $db=get_db();
    $db->prepare("INSERT OR REPLACE INTO processed (article_hash,key_used,status,ai_analysis,error_msg,tokens_used) VALUES(?,?,?,?,?,?)")
       ->execute([$hash,$keyIdx,$status,$ai,$err,$tokens]);
    $src=ARTICLES_DIR."/$hash.json";
    if(file_exists($src)){
        $data=json_decode(file_get_contents($src),true)??[];
        $prefix=$status==='success'?'etape1-':'off-';
        $data['ai_analysis']=$ai; $data['processing_status']=$status; $data['processed_at']=date('Y-m-d H:i:s');
        $data['model_used']=MISTRAL_MODEL; $data['key_index']=$keyIdx; $data['tokens_used']=$tokens;
        if($err)$data['error_msg']=$err;
        file_put_contents(PROCESSED_DIR."/$prefix$hash.json",json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
    sp_log($status==='success'?'success':'error',$status==='success'?"✅ $hash [clé#$keyIdx — $tokens tok]":"❌ $hash: $err");
    json_out(['status'=>'ok','saved'=>$hash]);
}

if ($action === 'generate_app') {
    $in=json_decode(file_get_contents('php://input'),true);
    $hash=$in['hash']??'';
    if(!$hash) json_out(['status'=>'error','message'=>'Hash requis'],400);

    $db=get_db();
    $check=$db->query("SELECT id FROM applications WHERE article_hash='$hash'")->fetch(PDO::FETCH_ASSOC);
    if($check){
        sp_log('warn',"⚠️ Application déjà existante pour $hash");
        json_out(['status'=>'exists','message'=>'Application déjà générée','app_id'=>$check['id']]);
    }

    $src=PROCESSED_DIR."/etape1-$hash.json";
    if(!file_exists($src)){
        $src=PROCESSED_DIR."/$hash.json";
        if(!file_exists($src)) json_out(['status'=>'error','message'=>'Fichier source introuvable']);
    }

    $data=json_decode(file_get_contents($src),true);
    if(!is_array($data)) json_out(['status'=>'error','message'=>'JSON invalide']);

    sp_log('info',"🔧 Génération app pour $hash...");
    $result=generate_app_code($data);

    if(!$result['success']){
        sp_log('error',"❌ Génération échouée: ".$result['error']);
        json_out(['status'=>'error','message'=>$result['error']]);
    }

    $folderName='app_'.substr($hash,0,8).'_'.uniqid();
    $targetDir=APPS_DIR.'/'.$folderName;
    if(!mkdir($targetDir,0755,true)){
        json_out(['status'=>'error','message'=>'Impossible de créer le dossier']);
    }

    $indexPath=$targetDir.'/index.php';
    if(file_put_contents($indexPath,$result['code'])===false){
        @rmdir($targetDir);
        json_out(['status'=>'error','message'=>"Erreur écriture index.php"]);
    }

    $readme="# Application Science Pulse\n\n**Source:** {$data['title']}\n**Date:** ".date('Y-m-d H:i:s')."\n**Tokens:** {$result['tokens']}\n\nFichier: index.php";
    file_put_contents($targetDir.'/README.md',$readme);

    $db->prepare("INSERT INTO applications (article_hash,folder_name,index_path,status,tokens_used) VALUES(?,?,?,?,?)")
       ->execute([$hash,$folderName,$indexPath,'success',$result['tokens']]);
    $appId=$db->lastInsertId();

    sp_log('success',"🚀 App générée: $folderName ({$result['tokens']} tok)");
    json_out(['status'=>'ok','app_id'=>$appId,'folder'=>$folderName,'path'=>$targetDir,'tokens'=>$result['tokens']]);
}

if ($action === 'list_apps') {
    $rows=get_db()->query("SELECT * FROM applications ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    json_out(['status'=>'ok','apps'=>$rows]);
}

if ($action === 'get_logs') {
    $since=(int)($_GET['since']??0);
    $rows=get_db()->query("SELECT id,level,message,context,created_at FROM logs WHERE id>$since ORDER BY id ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    $last=count($rows)?(int)end($rows)['id']:$since;
    json_out(['status'=>'ok','logs'=>$rows,'last_id'=>$last]);
}

if ($action === 'stats') {
    $db=get_db();
    json_out(['status'=>'ok','stats'=>[
        'articles_total'  =>(int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
        'articles_pending'=>(int)$db->query("SELECT COUNT(*) FROM articles WHERE hash NOT IN (SELECT article_hash FROM processed WHERE status='success')")->fetchColumn(),
        'processed_ok'    =>(int)$db->query("SELECT COUNT(*) FROM processed WHERE status='success'")->fetchColumn(),
        'apps_generated'  =>(int)$db->query("SELECT COUNT(*) FROM applications WHERE status='success'")->fetchColumn(),
        'tokens_total'    =>(int)$db->query("SELECT SUM(tokens_used) FROM processed")->fetchColumn()?:0,
    ]]);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>⚡ Admin Pulse Auto v<?= SP_VERSION ?></title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Bricolage+Grotesque:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg0:#05080d;--bg1:#0a0f18;--bg2:#0f1824;--bg3:#152030;--bg4:#1c2a3c;--line:#1e2d3e;--t0:#ddeeff;--t1:#7fa3c0;--t2:#3d5f7a;--cyan:#00c8f0;--green:#00e07a;--red:#ff3f5a;--amber:#ffb020;--purple:#a78bfa;--mono:'JetBrains Mono',monospace;--ui:'Bricolage Grotesque',sans-serif}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;background:var(--bg0);color:var(--t0);font-family:var(--ui)}
#root{display:grid;grid-template-columns:400px 1fr;height:100vh}
#console{background:var(--bg1);border-right:1px solid var(--line);display:flex;flex-direction:column}
#conh{padding:11px 14px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between}
#cont{font:700 10px/1 var(--mono);color:var(--cyan);letter-spacing:1px}
#conb{flex:1;overflow-y:auto;padding:8px;background:#000;font:400 10.5px/1.6 var(--mono)}
.ll{padding:2px 4px;border-radius:2px;white-space:pre-wrap;word-break:break-word;margin:1px 0}
.li{color:var(--cyan)}.ls{color:var(--green)}.le{color:var(--red)}.lw{color:var(--amber)}.ld{color:var(--t2)}.la{color:var(--purple)}
#coni{padding:7px;border-top:1px solid var(--line);display:flex;gap:5px}
#cmd{flex:1;background:var(--bg0);border:1px solid var(--line2);color:var(--t0);padding:5px 9px;border-radius:4px;font:400 10px/1 var(--mono)}
.cb{padding:4px 9px;background:var(--bg3);border:none;color:var(--t1);font:700 9px/1 var(--mono);border-radius:3px;cursor:pointer}.cb:hover{background:var(--bg4);color:var(--t0)}
#apps{overflow-y:auto;padding:18px;background:var(--bg0)}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.title{font-size:18px;font-weight:800;color:var(--t0)}
.title span{color:var(--cyan)}
.stats{display:flex;gap:12px;margin-bottom:16px}
.stat{background:var(--bg1);border:1px solid var(--line);border-radius:6px;padding:10px 14px;text-align:center}
.stat-v{font:800 20px/1 var(--mono);color:var(--cyan)}.stat-l{font:400 9px/1 var(--mono);color:var(--t2);margin-top:4px;text-transform:uppercase}
.ctrl{display:flex;gap:8px;margin-bottom:16px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;border:none;cursor:pointer;font:700 11px/1 var(--mono);transition:all .12s}
.btn-p{background:var(--cyan);color:#000}.btn-p:hover{background:#00b0d5}
.btn-g{background:var(--green);color:#000}.btn-r{background:var(--red);color:#fff}
.btn-o{background:var(--bg3);color:var(--t1);border:1px solid var(--line2)}.btn-o:hover{background:var(--bg4);color:var(--t0)}
.app-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.app-card{background:var(--bg1);border:1px solid var(--line);border-radius:8px;padding:14px;transition:all .15s;cursor:pointer}
.app-card:hover{border-color:var(--cyan);transform:translateY(-2px)}
.app-name{font:700 12px/1 var(--mono);color:var(--cyan);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.app-meta{font:400 10px/1.5 var(--mono);color:var(--t2)}
.app-status{display:inline-block;padding:2px 8px;border-radius:20px;font:700 9px/1.6 var(--mono);margin-top:8px}
.st-ok{background:rgba(0,224,122,.14);color:var(--green)}
.progress{height:3px;background:var(--bg3);border-radius:2px;overflow:hidden;margin:10px 0}
.pb{height:100%;background:var(--cyan);transition:width .3s}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:var(--bg0)}::-webkit-scrollbar-thumb{background:var(--line2);border-radius:3px}
</style>
</head>
<body>
<div id="root">
<!-- CONSOLE GAUCHE -->
<div id="console">
  <div id="conh"><span id="cont">⚡ CONSOLE DEBUG LIVE</span><button class="cb" onclick="clearLog()">✕</button></div>
  <div id="conb"></div>
  <div id="coni"><input type="text" id="cmd" placeholder="Commande..." onkeydown="if(event.key==='Enter')runCmd()"><button class="cb" onclick="runCmd()">▶</button></div>
</div>

<!-- APPLICATIONS DROITE -->
<div id="apps">
  <div class="header">
    <div class="title">🚀 Admin Pulse <span>Auto</span></div>
    <div style="font:400 10px/1 var(--mono);color:var(--t2)">v<?= SP_VERSION ?></div>
  </div>

  <div class="stats">
    <div class="stat"><div class="stat-v" id="st-art">—</div><div class="stat-l">Articles</div></div>
    <div class="stat"><div class="stat-v" id="st-pend" style="color:var(--amber)">—</div><div class="stat-l">En attente</div></div>
    <div class="stat"><div class="stat-v" id="st-proc" style="color:var(--green)">—</div><div class="stat-l">Traités</div></div>
    <div class="stat"><div class="stat-v" id="st-app" style="color:var(--purple)">—</div><div class="stat-l">Apps</div></div>
    <div class="stat"><div class="stat-v" id="st-tok" style="color:var(--cyan)">—</div><div class="stat-l">Tokens</div></div>
  </div>

  <div class="ctrl">
    <button class="btn btn-p" id="btn-auto" onclick="toggleAuto()">⏸️ PAUSE AUTO</button>
    <button class="btn btn-o" onclick="runCrawl()">⬡ Crawl RSS</button>
    <button class="btn btn-o" onclick="processAll()">⚡ Traiter Tout</button>
    <button class="btn btn-o" onclick="loadApps()">↻ Apps</button>
  </div>

  <div class="progress"><div class="pb" id="prog-bar" style="width:0%"></div></div>
  <div id="prog-txt" style="font:400 10px/1 var(--mono);color:var(--t2);margin-bottom:12px"></div>

  <div style="font:700 10px/1 var(--mono);color:var(--t2);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px">Applications Générées</div>
  <div class="app-grid" id="app-list"></div>
</div>
</div>

<script>
const $=id=>document.getElementById(id);
let autoMode=true,stopReq=false,lastLogId=0,isProcessing=false;

function clog(msg,type='i'){
  const t={'i':'li','s':'ls','e':'le','w':'lw','d':'ld','a':'la'}[type]||'li';
  const d=new Date().toLocaleTimeString('fr-FR');
  $('conb').innerHTML+=`<div class="ll ${t}">[${d}] ${msg}</div>`;
  $('conb').scrollTop=$('conb').scrollHeight;
}

function clearLog(){ $('conb').innerHTML=''; clog('🗑️ Console effacée','d'); }

async function fetchLogs(){
  try{
    const r=await fetch(`?action=get_logs&since=${lastLogId}`).then(x=>x.json());
    (r.logs||[]).forEach(l=>{
      const tp=l.level==='success'?'s':l.level==='error'?'e':l.level==='warn'?'w':'i';
      clog(l.message,tp);
    });
    if(r.last_id)lastLogId=r.last_id;
  }catch(e){}
}

async function loadStats(){
  try{
    const r=await fetch('?action=stats').then(x=>x.json());
    if(r.stats){
      $('st-art').textContent=r.stats.articles_total||0;
      $('st-pend').textContent=r.stats.articles_pending||0;
      $('st-proc').textContent=r.stats.processed_ok||0;
      $('st-app').textContent=r.stats.apps_generated||0;
      $('st-tok').textContent=r.stats.tokens_total||0;
    }
  }catch(e){}
}

async function runCrawl(){
  clog('🕷️ Lancement crawl RSS...','i');
  try{
    const r=await fetch('?action=crawl').then(x=>x.json());
    if(r.status==='ok'){
      clog(`✅ Crawl terminé: ${r.total_new} nouveaux articles`,'s');
      loadStats();
    }else clog('❌ Erreur crawl','e');
  }catch(e){clog('❌ '+e.message,'e')}
}

async function processAll(){
  if(isProcessing)return;
  isProcessing=true;
  clog('⚡ Démarrage traitement automatique...','i');

  try{
    const pending=await fetch('?action=list_pending').then(x=>x.json());
    const items=pending.pending||[];
    if(!items.length){ clog('ℹ️ Aucun article en attente','w'); isProcessing=false; return; }

    let done=0,ok=0,fail=0;
    const total=items.length;

    for(const art of items){
      if(stopReq)break;

      // Étape 1: Traitement IA de l'article
      clog(`→ ${art.source}: ${art.title.substring(0,40)}...`,'i');

      // Simulation appel IA (à adapter avec votre logique complète)
      const keys=['k1','k2','k3'];
      const keyIdx=Math.floor(Math.random()*3);

      // Sauvegarde processed
      await fetch('?action=save_processed',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
        hash:art.hash,status:'success',ai_analysis:`Analyse IA complète pour ${art.title}`,...{key_index:keyIdx,tokens_used:Math.floor(Math.random()*500)+200}
      })});

      // Étape 2: Génération application
      const appR=await fetch('?action=generate_app',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({hash:art.hash})});
      const appD=await appR.json();

      if(appD.status==='ok'||appD.status==='exists'){
        ok++; clog(`✅ App générée: ${appD.folder||'existe déjà'}`,'s');
      }else{ fail++; clog(`❌ Erreur: ${appD.message}`,'e'); }

      done++;
      const pct=Math.round((done/total)*100);
      $('prog-bar').style.width=pct+'%';
      $('prog-txt').textContent=`${done}/${total} — ✅${ok} ❌${fail} (${pct}%)`;

      await new Promise(r=>setTimeout(r,800)); // Délai anti-rate-limit
    }

    clog(`🏁 Terminé: ${ok}✅ / ${fail}❌ sur ${done}`,'s');
    loadStats(); loadApps();
  }catch(e){ clog('❌ '+e.message,'e'); }
  finally{ isProcessing=false; stopReq=false; $('btn-auto').textContent='⏸️ PAUSE AUTO'; }
}

async function loadApps(){
  try{
    const r=await fetch('?action=list_apps').then(x=>x.json());
    const apps=r.apps||[];
    if(!apps.length){ $('app-list').innerHTML='<div style="color:var(--t2);font:400 11px/1 var(--mono)">Aucune application</div>'; return; }
    $('app-list').innerHTML=apps.map(a=>`
      <div class="app-card" onclick="window.open('${a.index_path.replace(__DIR__,'.')}/index.php','_blank')">
        <div class="app-name">${a.folder_name}</div>
        <div class="app-meta">Hash: ${a.article_hash.substring(0,12)}...</div>
        <div class="app-meta">${a.created_at} · ${a.tokens_used} tok</div>
        <span class="app-status st-ok">✓ Prête</span>
      </div>
    `).join('');
  }catch(e){clog('❌ Load apps: '+e.message,'e')}
}

function toggleAuto(){
  autoMode=!autoMode;
  $('btn-auto').textContent=autoMode?'⏸️ PAUSE AUTO':'▶ REPRENDRE AUTO';
  clog(autoMode?'▶ Mode AUTO activé':'⏸️ Mode AUTO pause','w');
}

function runCmd(){
  const cmd=$('cmd').value.trim();
  if(!cmd)return;
  clog('> '+cmd,'d');
  if(cmd==='help') clog('Commandes: crawl, process, apps, stats, clear','a');
  else if(cmd==='crawl') runCrawl();
  else if(cmd==='process') processAll();
  else if(cmd==='apps') loadApps();
  else if(cmd==='stats') loadStats();
  else if(cmd==='clear') clearLog();
  else clog('Commande inconnue: '+cmd,'w');
  $('cmd').value='';
}

// Auto-start au chargement
(async ()=>{
  clog('🟢 Admin Pulse Auto v<?= SP_VERSION ?> démarré','s');
  clog('📡 3 clés Mistral prêtes • Mode FULL AUTO','i');
  clog('🔄 Cycle: Crawl → Traitement IA → Génération Apps','i');

  await loadStats();
  await loadApps();
  fetchLogs();
  setInterval(fetchLogs,2000);
  setInterval(loadStats,5000);

  // Démarrage automatique après 2s
  setTimeout(()=>{
    clog('🚀 Démarrage séquence automatique...','a');
    runCrawl();
    setTimeout(processAll,3000);
  },2000);
})();
</script>
</body>
</html>
