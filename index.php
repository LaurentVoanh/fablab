<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║  SCIENCE PULSE — ADMIN PANEL v5.0                                 ║
 * ║  Prompt bio-informatique académique • 3 clés parallèles           ║
 * ║  Console totale • Rapports • Auto-Rewrite IA • Versions           ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 */

define('SP_VERSION',        '5.0.0');
define('STORAGE_DIR',       __DIR__ . '/storage');
define('ARTICLES_DIR',      STORAGE_DIR . '/articles');
define('PROCESSED_DIR',     STORAGE_DIR . '/processed');
define('REPORTS_DIR',       STORAGE_DIR . '/reports');
define('DB_FILE',           STORAGE_DIR . '/science_pulse.sqlite');
define('MISTRAL_ENDPOINT',  'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL',     'mistral-small-latest');

$GLOBALS['MISTRAL_KEYS'] = array_values(array_filter(explode(',',
    getenv('MISTRAL_KEYS') ?: 'apikeyhere,apikeyhere,apikeyhere'
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

foreach ([STORAGE_DIR, ARTICLES_DIR, PROCESSED_DIR, REPORTS_DIR, STORAGE_DIR.'/backups'] as $d) {
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
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT,
        article_count INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS code_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT, version_label TEXT,
        code TEXT, evaluation TEXT, score REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
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
        CURLOPT_USERAGENT      => 'SciencePulse/'.SP_VERSION,
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

$action = $_GET['action'] ?? '';
if ($action) {

    if ($action === 'crawl') {
        sp_log('info','🕷️ Crawl RSS démarré');
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
        $db=get_db(); $done=array_flip($db->query("SELECT article_hash FROM processed WHERE status='success'")->fetchAll(PDO::FETCH_COLUMN));
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

    if ($action === 'generate_report') {
        $keys=$GLOBALS['MISTRAL_KEYS']; $db=get_db();
        $rows=$db->query("SELECT a.title,a.source,a.link,p.ai_analysis FROM processed p JOIN articles a ON a.hash=p.article_hash WHERE p.status='success' ORDER BY p.processed_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows) json_out(['status'=>'error','message'=>'Aucun article traité']);
        $bloc=''; foreach($rows as $i=>$r) $bloc.="\n[".($i+1)."] SOURCE: {$r['source']}\nTITRE: {$r['title']}\nÉTUDE IA:\n".substr($r['ai_analysis'],0,500)."\n---";
        $res=sp_curl(MISTRAL_ENDPOINT,['model'=>MISTRAL_MODEL,'messages'=>[['role'=>'user','content'=>"Tu es éditeur en chef d'une revue scientifique. Génère un rapport de veille bio-informatique complet en français à partir de ".count($rows)." études.\n\nÉTUDES:\n$bloc\n\nRAPPORT (Markdown: ## Résumé Exécutif, ## Tendances Majeures, ## Découvertes Clés, ## Recommandations API PHP, ## Conclusion):"]],'max_tokens'=>2500,'temperature'=>0.45],['Authorization: Bearer '.$keys[0]],120);
        if(!$res['success']){sp_log('error','Rapport err: '.$res['error']);json_out(['status'=>'error','message'=>$res['error']]);}
        $d2=json_decode($res['data'],true); $report=$d2['choices'][0]['message']['content']??'';
        if(!$report) json_out(['status'=>'error','message'=>'IA vide']);
        $title='Rapport — '.date('d/m/Y H:i');
        $db->prepare("INSERT INTO reports (title,content,article_count) VALUES(?,?,?)")->execute([$title,$report,count($rows)]);
        $rid=$db->lastInsertId(); sp_log('success',"📄 Rapport #$rid généré");
        json_out(['status'=>'ok','report_id'=>$rid,'title'=>$title,'content'=>$report,'article_count'=>count($rows)]);
    }

    if ($action === 'get_reports') {
        $rows=get_db()->query("SELECT id,title,article_count,substr(content,1,400) as preview,created_at FROM reports ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        json_out(['status'=>'ok','reports'=>$rows]);
    }
    if ($action === 'get_report') {
        $row=get_db()->query("SELECT * FROM reports WHERE id=".(int)($_GET['id']??0))->fetch(PDO::FETCH_ASSOC);
        if(!$row) json_out(['status'=>'error','message'=>'Introuvable'],404);
        json_out(['status'=>'ok','report'=>$row]);
    }

    if ($action === 'ai_rewrite') {
        $keys=$GLOBALS['MISTRAL_KEYS']; $in=json_decode(file_get_contents('php://input'),true);
        $file=basename($in['filename']??''); $task=$in['task']??'optimize'; $notes=$in['notes']??'';
        $path=__DIR__."/$file"; if(!$file||!file_exists($path)) json_out(['status'=>'error','message'=>"Fichier $file introuvable"]);
        $code=file_get_contents($path);
        $descs=['optimize'=>'Optimise pour performances, latence cURL, mémoire.','fix'=>'Corrige tous les bugs, race conditions, parsing JSON.','refactor'=>'Refactorise SOLID/DRY/Clean Code sans changer le comportement.','add_feature'=>'Enrichis avec retry exponentiel, cache, meilleure gestion 429.'];
        $td=($descs[$task]??$descs['optimize']).($notes?"\n\nInstructions: $notes":'');
        $prompt="Tu es expert PHP 8.3 senior. $td\n\nRetourne UNIQUEMENT le code PHP complet, aucune explication, aucun backtick.\n\nCODE ACTUEL ($file):\n\n$code";
        sp_log('info',"🔧 Rewrite: $file ($task)");
        $res=sp_curl(MISTRAL_ENDPOINT,['model'=>MISTRAL_MODEL,'messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>4096,'temperature'=>0.15],['Authorization: Bearer '.($keys[1]??$keys[0])],120);
        if(!$res['success']) json_out(['status'=>'error','message'=>$res['error']]);
        $d3=json_decode($res['data'],true); $new=trim($d3['choices'][0]['message']['content']??''); $tok=$d3['usage']['total_tokens']??0;
        $new=preg_replace('/^```(?:php|html|js)?\s*/i','',$new); $new=preg_replace('/\s*```$/i','',$new); $new=trim($new);
        if(!$new) json_out(['status'=>'error','message'=>'Réponse vide']);
        $label='v'.date('Ymd-His').'-'.$task; $db=get_db();
        $db->prepare("INSERT INTO code_versions (filename,version_label,code) VALUES(?,?,?)")->execute([$file,$label,$new]);
        $vid=$db->lastInsertId(); sp_log('success',"✅ Version $label créée ($tok tok)");
        json_out(['status'=>'ok','version_id'=>$vid,'version_label'=>$label,'new_code'=>$new,'tokens_used'=>$tok,'filename'=>$file]);
    }

    if ($action === 'ai_evaluate') {
        $keys=$GLOBALS['MISTRAL_KEYS']; $in=json_decode(file_get_contents('php://input'),true);
        $vid=(int)($in['version_id']??0); $db=get_db();
        $row=$db->query("SELECT * FROM code_versions WHERE id=$vid")->fetch(PDO::FETCH_ASSOC);
        if(!$row) json_out(['status'=>'error','message'=>'Version introuvable']);
        $prompt="Expert revue de code PHP. Évalue et retourne UNIQUEMENT JSON:\n{\"score\":0.0-1.0,\"quality\":\"...\",\"performance\":\"...\",\"security\":\"...\",\"maintainability\":\"...\",\"bugs\":\"...\",\"summary\":\"...\",\"recommendation\":\"deploy|review|reject\"}\n\nCODE ({$row['filename']}):\n\n".substr($row['code'],0,4000);
        $res=sp_curl(MISTRAL_ENDPOINT,['model'=>MISTRAL_MODEL,'messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>800,'temperature'=>0.05,'response_format'=>['type'=>'json_object']],['Authorization: Bearer '.($keys[2]??$keys[0])],60);
        if(!$res['success']) json_out(['status'=>'error','message'=>$res['error']]);
        $d4=json_decode($res['data'],true); $evs=$d4['choices'][0]['message']['content']??'{}';
        $ev=json_decode($evs,true)??['score'=>0.5,'summary'=>'Partiel']; $sc=(float)($ev['score']??0.5);
        $db->prepare("UPDATE code_versions SET evaluation=?,score=? WHERE id=?")->execute([json_encode($ev,JSON_UNESCAPED_UNICODE),$sc,$vid]);
        sp_log('success',"🔍 #$vid: ".round($sc*100).'%');
        json_out(['status'=>'ok','evaluation'=>$ev,'score'=>$sc,'version_id'=>$vid]);
    }

    if ($action === 'deploy_version') {
        $in=json_decode(file_get_contents('php://input'),true); $vid=(int)($in['version_id']??0);
        $db=get_db(); $row=$db->query("SELECT * FROM code_versions WHERE id=$vid")->fetch(PDO::FETCH_ASSOC);
        if(!$row) json_out(['status'=>'error','message'=>'Version introuvable']);
        $target=__DIR__.'/'.basename($row['filename']);
        if(file_exists($target)) copy($target,STORAGE_DIR.'/backups/backup-'.date('YmdHis').'-'.basename($row['filename']));
        file_put_contents($target,$row['code']); sp_log('success',"🚀 {$row['version_label']} → {$row['filename']}");
        json_out(['status'=>'ok','deployed'=>$row['version_label'],'file'=>basename($target)]);
    }

    if ($action === 'get_versions') {
        $fn=$_GET['filename']??''; $db=get_db(); $w=$fn?"WHERE filename=".$db->quote($fn):'';
        $rows=$db->query("SELECT id,filename,version_label,score,substr(evaluation,1,300) as eval_preview,created_at FROM code_versions $w ORDER BY id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
        json_out(['status'=>'ok','versions'=>$rows]);
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
            'processed_fail'  =>(int)$db->query("SELECT COUNT(*) FROM processed WHERE status='failed'")->fetchColumn(),
            'reports_count'   =>(int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn(),
            'code_versions'   =>(int)$db->query("SELECT COUNT(*) FROM code_versions")->fetchColumn(),
            'tokens_total'    =>(int)$db->query("SELECT SUM(tokens_used) FROM processed")->fetchColumn(),
        ]]);
    }

    if ($action === 'clear_logs') { get_db()->exec("DELETE FROM logs"); json_out(['status'=>'ok']); }
    json_out(['status'=>'error','message'=>"Action inconnue: $action"],404);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Science Pulse Admin v<?= SP_VERSION ?></title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Bricolage+Grotesque:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg0:#05080d;--bg1:#0a0f18;--bg2:#0f1824;--bg3:#152030;--bg4:#1c2a3c;
  --line:#1e2d3e;--line2:#263648;
  --t0:#ddeeff;--t1:#7fa3c0;--t2:#3d5f7a;--t3:#1e3347;
  --cyan:#00c8f0;--green:#00e07a;--red:#ff3f5a;--amber:#ffb020;--purple:#a78bfa;
  --mono:'JetBrains Mono',monospace;--ui:'Bricolage Grotesque',sans-serif;--r:6px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;background:var(--bg0);color:var(--t0);font-family:var(--ui)}
#root{display:grid;grid-template-rows:48px 1fr;grid-template-columns:200px 1fr 360px;height:100vh}

/* TOP BAR */
#bar{grid-column:1/-1;background:var(--bg1);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:16px;padding:0 18px}
.logo{font-size:13px;font-weight:800;letter-spacing:3px;color:var(--cyan);text-transform:uppercase}
.logo em{color:var(--t2);font-style:normal;font-weight:400}
.bsep{width:1px;height:20px;background:var(--line);flex-shrink:0}
#bstats{display:flex;gap:18px;margin-left:auto}
.bs{font:400 10px/1 var(--mono);color:var(--t2)}.bs b{color:var(--t0);font-weight:700}
#live{display:flex;align-items:center;gap:5px;font:700 10px/1 var(--mono);color:var(--green)}
.dot{width:7px;height:7px;border-radius:50%;background:var(--green);animation:blink 1.8s ease infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* SIDEBAR */
#nav{background:var(--bg1);border-right:1px solid var(--line);padding:12px 0;display:flex;flex-direction:column;gap:1px;overflow-y:auto}
.ns{padding:10px 14px 3px;font:700 9px/1 var(--mono);color:var(--t3);letter-spacing:2px;text-transform:uppercase}
.ni{display:flex;align-items:center;gap:9px;padding:8px 14px;cursor:pointer;font-size:12px;color:var(--t1);border-left:2px solid transparent;transition:all .12s;white-space:nowrap}
.ni:hover{background:var(--bg2);color:var(--t0)}.ni.on{background:rgba(0,200,240,.06);color:var(--cyan);border-left-color:var(--cyan)}
.nic{font-size:13px;width:16px;text-align:center;flex-shrink:0}

/* MAIN */
#main{overflow-y:auto;padding:18px;background:var(--bg0)}
.page{display:none}.page.on{display:block}
.ptitle{font-size:18px;font-weight:800;margin-bottom:16px;color:var(--t0)}
.ptitle span{color:var(--cyan)}

/* CARDS */
.card{background:var(--bg1);border:1px solid var(--line);border-radius:var(--r);padding:14px;margin-bottom:12px}
.chead{font:700 10px/1 var(--mono);color:var(--t2);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:12px}

/* STAT GRID */
.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:14px}
.sb{background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);padding:12px;text-align:center}
.sv{font:800 26px/1 var(--mono);color:var(--cyan)}.sl{font:400 9px/1 var(--mono);color:var(--t2);margin-top:5px;text-transform:uppercase;letter-spacing:1px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:var(--r);border:none;cursor:pointer;font:700 11px/1 var(--mono);letter-spacing:.5px;transition:all .12s}
.btn:disabled{opacity:.35;cursor:not-allowed}
.btn-p{background:var(--cyan);color:#000}.btn-p:hover:not(:disabled){background:#00b0d5}
.btn-g{background:var(--green);color:#000}
.btn-r{background:var(--red);color:#fff}
.btn-o{background:var(--bg3);color:var(--t1);border:1px solid var(--line2)}.btn-o:hover:not(:disabled){background:var(--bg4);color:var(--t0)}
.btn-sm{padding:5px 10px;font-size:10px}
.fx{display:flex}.g6{gap:6px}.g10{gap:10px}.g14{gap:14px}.ac{align-items:center}.jb{justify-content:space-between}.wr{flex-wrap:wrap}
.mt8{margin-top:8px}.mt12{margin-top:12px}.mt16{margin-top:16px}.mb8{margin-bottom:8px}.mb12{margin-bottom:12px}.w100{width:100%}

/* PROGRESS */
.pw{background:var(--bg3);height:5px;border-radius:3px;overflow:hidden;margin:6px 0}
.pb{height:100%;border-radius:3px;transition:width .35s ease;background:var(--cyan)}

/* KEY MONITOR */
.kg{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px}
.kb{background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);padding:10px}
.kb.active{border-color:var(--cyan);background:rgba(0,200,240,.06)}
.kb.ok{border-color:var(--green);background:rgba(0,224,122,.06)}
.kb.err{border-color:var(--red);background:rgba(255,63,90,.06)}
.kb.wait{border-color:var(--amber)}
.kl{font:400 9px/1 var(--mono);color:var(--t2);margin-bottom:5px}
.ks{font:700 11px/1 var(--mono);color:var(--t1)}
.kt{font:400 10px/1.3 var(--mono);color:var(--t2);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ARTICLE ROW */
.alist{max-height:420px;overflow-y:auto}
.arow{padding:9px 12px;border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:flex-start}.arow:last-child{border-bottom:none}
.asrc{font:700 9px/1 var(--mono);color:var(--cyan);text-transform:uppercase;letter-spacing:1px;min-width:90px;padding-top:1px;white-space:nowrap}
.atit{flex:1;font-size:12px;line-height:1.4;color:var(--t0)}
.ast{font:700 10px/1 var(--mono);white-space:nowrap}
.ok{color:var(--green)}.fail{color:var(--red)}.pend{color:var(--amber)}

/* ANALYSIS BOX (étude académique) */
.abox{background:var(--bg0);border:1px solid var(--line);border-radius:var(--r);padding:14px;font-size:12px;line-height:1.75;color:var(--t1);max-height:450px;overflow-y:auto;font-family:var(--ui)}
.abox h2{color:var(--cyan);font-size:13px;font-family:var(--mono);margin:14px 0 6px;border-bottom:1px solid var(--line);padding-bottom:4px}
.abox h3{color:var(--t0);font-size:12px;margin:10px 0 4px;font-family:var(--mono)}
.abox strong{color:var(--t0)}.abox em{color:var(--amber)}
.abox .api-chip{display:inline-block;background:rgba(0,200,240,.1);color:var(--cyan);border:1px solid rgba(0,200,240,.2);border-radius:3px;padding:1px 6px;font:700 9px/1.6 var(--mono);margin:1px}

/* PROMPT BOX */
.pbox{background:var(--bg0);border:1px solid var(--line2);border-radius:var(--r);padding:12px;font:400 10px/1.7 var(--mono);color:var(--t1);max-height:240px;overflow-y:auto;white-space:pre-wrap}

/* CODE BOX */
.cbox{background:#000;border:1px solid var(--line);border-radius:var(--r);padding:12px;font:400 11px/1.6 var(--mono);color:#b0c8e0;max-height:380px;overflow-y:auto;white-space:pre}

/* VERSIONS */
.vrow{padding:9px 12px;border-bottom:1px solid var(--line);display:grid;grid-template-columns:1fr auto auto auto;align-items:center;gap:10px}.vrow:last-child{border-bottom:none}
.vl{font:700 11px/1 var(--mono);color:var(--cyan)}
.vsw{width:70px;background:var(--bg3);height:3px;border-radius:2px}
.vsb{height:100%;border-radius:2px}

/* REPORT CARD */
.rcard{background:var(--bg1);border:1px solid var(--line);border-radius:var(--r);padding:14px;margin-bottom:10px;cursor:pointer;transition:border-color .15s}.rcard:hover{border-color:var(--cyan)}

/* INPUTS */
.si,.ss,.sta{background:var(--bg2);border:1px solid var(--line2);color:var(--t0);padding:7px 11px;border-radius:var(--r);font:400 11px/1 var(--mono)}
.si:focus,.ss:focus,.sta:focus{outline:none;border-color:var(--cyan)}.sta{resize:vertical;min-height:60px;line-height:1.5}

/* BADGE */
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font:700 9px/1.6 var(--mono)}
.bb{background:rgba(0,200,240,.14);color:var(--cyan)}.bg{background:rgba(0,224,122,.14);color:var(--green)}
.br{background:rgba(255,63,90,.14);color:var(--red)}.ba{background:rgba(255,176,32,.14);color:var(--amber)}

/* MODAL */
.mbg{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;display:none;align-items:center;justify-content:center}
.mbg.open{display:flex}
.modal{background:var(--bg1);border:1px solid var(--line2);border-radius:8px;padding:20px;max-width:780px;width:92%;max-height:84vh;overflow-y:auto}
.mt{font:700 13px/1 var(--mono);color:var(--cyan);margin-bottom:14px}

/* CONSOLE */
#con{background:var(--bg1);border-left:1px solid var(--line);display:flex;flex-direction:column}
#conh{padding:11px 14px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between}
#cont{font:700 10px/1 var(--mono);color:var(--cyan);letter-spacing:1px}
#conb{flex:1;overflow-y:auto;padding:8px;background:#000;font:400 10.5px/1.6 var(--mono)}
.ll{padding:1px 4px;border-radius:2px;white-space:pre-wrap;word-break:break-word}
.li{color:var(--cyan)}.ls{color:var(--green)}.le{color:var(--red)}.lw{color:var(--amber)}.ld{color:var(--t2)}.la{color:var(--purple)}
#coni{padding:7px;border-top:1px solid var(--line);display:flex;gap:5px}
#cmd{flex:1;background:var(--bg0);border:1px solid var(--line2);color:var(--t0);padding:5px 9px;border-radius:4px;font:400 10px/1 var(--mono)}
#cmd:focus{outline:none;border-color:var(--cyan)}
.cb{padding:4px 9px;background:var(--bg3);border:none;color:var(--t1);font:700 9px/1 var(--mono);border-radius:3px;cursor:pointer}.cb:hover{background:var(--bg4);color:var(--t0)}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:var(--bg0)}::-webkit-scrollbar-thumb{background:var(--line2);border-radius:3px}
</style>
</head>
<body>
<div id="root">

<!-- TOP BAR -->
<div id="bar">
  <div class="logo">Science <em>·</em> Pulse <em style="font-size:9px;color:var(--t3)">v<?= SP_VERSION ?></em></div>
  <div class="bsep"></div>
  <div id="live"><div class="dot"></div>LIVE</div>
  <div id="bstats">
    <div class="bs">Articles: <b id="ba">—</b></div>
    <div class="bs">Traités: <b id="bok">—</b></div>
    <div class="bs">Tokens: <b id="btok">—</b></div>
    <div class="bs">Rapports: <b id="brep">—</b></div>
    <div class="bs">Versions: <b id="bver">—</b></div>
  </div>
</div>

<!-- SIDEBAR -->
<div id="nav">
  <div class="ns">Système</div>
  <div class="ni on" data-p="dashboard"><span class="nic">◈</span>Dashboard</div>
  <div class="ni" data-p="crawler"><span class="nic">⬡</span>Crawler RSS</div>
  <div class="ns">Traitement IA</div>
  <div class="ni" data-p="processor"><span class="nic">⚡</span>Processeur IA</div>
  <div class="ni" data-p="prompt"><span class="nic">◻</span>Prompt Actif</div>
  <div class="ns">Résultats</div>
  <div class="ni" data-p="articles"><span class="nic">▤</span>Articles</div>
  <div class="ni" data-p="reports"><span class="nic">◈</span>Rapports</div>
  <div class="ns">Développement</div>
  <div class="ni" data-p="rewrite"><span class="nic">⟳</span>Auto-Rewrite IA</div>
  <div class="ni" data-p="versions"><span class="nic">▣</span>Versions Code</div>
</div>

<!-- MAIN -->
<div id="main">

  <!-- DASHBOARD -->
  <div id="page-dashboard" class="page on">
    <div class="ptitle">Dashboard <span>Science Pulse</span></div>
    <div class="sg">
      <div class="sb"><div class="sv" id="s-art">—</div><div class="sl">Articles</div></div>
      <div class="sb"><div class="sv" id="s-pend" style="color:var(--amber)">—</div><div class="sl">En attente</div></div>
      <div class="sb"><div class="sv" id="s-ok" style="color:var(--green)">—</div><div class="sl">Traités OK</div></div>
      <div class="sb"><div class="sv" id="s-fail" style="color:var(--red)">—</div><div class="sl">Échecs</div></div>
      <div class="sb"><div class="sv" id="s-tok" style="color:var(--purple)">—</div><div class="sl">Tokens IA</div></div>
      <div class="sb"><div class="sv" id="s-rep" style="color:var(--cyan)">—</div><div class="sl">Rapports</div></div>
    </div>
    <div class="card">
      <div class="chead">Actions rapides</div>
      <div class="fx g10 wr">
        <button class="btn btn-p" onclick="goAndRun('crawler',runCrawl)">⬡ Crawl RSS</button>
        <button class="btn btn-o" onclick="go('processor')">⚡ Lancer traitement IA</button>
        <button class="btn btn-o" onclick="doReport()">◈ Générer rapport</button>
        <button class="btn btn-o" onclick="go('rewrite')">⟳ Auto-Rewrite</button>
      </div>
    </div>
    <div class="card">
      <div class="chead">Prompt actif — Bio-Informatique Académique</div>
      <div style="font:400 10px/1.6 var(--mono);color:var(--t1)">
        Chaque article reçoit une <b style="color:var(--cyan)">étude académique complète</b> en 5 sections :<br>
        <span style="color:var(--green)">①</span> Analyse critique &nbsp;
        <span style="color:var(--green)">②</span> Protocole PHP expérimental &nbsp;
        <span style="color:var(--green)">③</span> 20+ APIs stratégiques (PubMed · UniProt · ChEMBL · StringDB · KEGG…) &nbsp;
        <span style="color:var(--green)">④</span> Architecture SQLite &nbsp;
        <span style="color:var(--green)">⑤</span> Impact scientifique
      </div>
    </div>
    <div class="card" id="dash-last" style="display:none">
      <div class="chead">Dernier rapport généré</div>
      <div id="dash-prev" class="abox" style="max-height:200px"></div>
    </div>
  </div>

  <!-- CRAWLER -->
  <div id="page-crawler" class="page">
    <div class="ptitle">Crawler <span>RSS</span></div>
    <div class="card">
      <div class="chead">Sources (<?= count(json_decode(RSS_SOURCES,true)) ?>)</div>
      <?php foreach(json_decode(RSS_SOURCES,true) as $n=>$u): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid var(--line)">
        <span style="font:700 10px/1 var(--mono);color:var(--cyan);min-width:140px"><?=htmlspecialchars($n)?></span>
        <span style="font:400 10px/1 var(--mono);color:var(--t2)"><?=htmlspecialchars($u)?></span>
      </div>
      <?php endforeach;?>
      <div class="mt12"><button class="btn btn-p" id="btn-crawl" onclick="runCrawl()">⬡ Lancer le crawl</button></div>
    </div>
    <div class="card" id="crawl-res" style="display:none">
      <div class="chead">Résultats</div><div id="crawl-res-body"></div>
    </div>
  </div>

  <!-- PROCESSEUR IA -->
  <div id="page-processor" class="page">
    <div class="ptitle">Processeur <span>IA</span> — 3 Clés Parallèles</div>
    <div class="card">
      <div class="chead">Moniteur des 3 clés Mistral freemium</div>
      <div class="kg">
        <div class="kb" id="kb0"><div class="kl">CLÉ #1 — 5qaRTj…</div><div class="ks" id="ks0">EN ATTENTE</div><div class="kt" id="kt0"></div></div>
        <div class="kb" id="kb1"><div class="kl">CLÉ #2 — o3rG1z…</div><div class="ks" id="ks1">EN ATTENTE</div><div class="kt" id="kt1"></div></div>
        <div class="kb" id="kb2"><div class="kl">CLÉ #3 — vEzQMK…</div><div class="ks" id="ks2">EN ATTENTE</div><div class="kt" id="kt2"></div></div>
      </div>
    </div>
    <div class="card">
      <div class="chead">Progression</div>
      <div class="fx ac jb mb8">
        <span id="proc-st" style="font:400 12px/1 var(--mono);color:var(--t1)">Prêt — batches de 3 articles en parallèle</span>
        <span id="proc-cnt" style="font:700 11px/1 var(--mono);color:var(--t2)"></span>
      </div>
      <div class="pw"><div class="pb" id="proc-bar" style="width:0%"></div></div>
      <div id="proc-det" style="font:400 10px/1 var(--mono);color:var(--t2);margin-top:4px"></div>
      <div id="proc-cd" style="font:700 11px/1 var(--mono);color:var(--amber);margin-top:8px;min-height:14px"></div>
      <div class="fx g10 mt12">
        <button class="btn btn-p" id="btn-ps" onclick="startProc()">⚡ LANCER LE TRAITEMENT PARALLÈLE</button>
        <button class="btn btn-r" id="btn-pp" onclick="stopProc()" style="display:none">⬛ ARRÊTER</button>
      </div>
    </div>
    <div class="card">
      <div class="chead">Aperçu du prompt bio-informatique (début)</div>
      <div class="pbox" id="proc-prompt-prev"></div>
    </div>
  </div>

  <!-- PROMPT COMPLET -->
  <div id="page-prompt" class="page">
    <div class="ptitle">Prompt <span>Bio-Informatique Académique</span></div>
    <div class="card">
      <div class="chead">Prompt complet — injecté à chaque traitement d'article</div>
      <div class="pbox" style="max-height:580px" id="full-prompt"></div>
    </div>
    <div class="card">
      <div class="chead">Groupes d'API couverts (20+ sources)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font:400 11px/1.7 var(--mono);color:var(--t1)">
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">LITTÉRATURE</div>PubMed · EuropePMC · OpenAlex<br>CrossRef · arXiv · SemanticScholar</div>
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">GÉNOMIQUE & PROTÉINES</div>UniProt · Ensembl · ClinVar<br>NCBI_Gene · NCBI_Protein · PDB</div>
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">BIOLOGIE DES SYSTÈMES</div>StringDB · Reactome<br>GeneOntology · KEGG</div>
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">CLINIQUE & PHARMACOLOGIE</div>ClinicalTrials · OpenFDA · ChEMBL<br>PubChem · RxNorm · DisGeNET</div>
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">ÉCOLOGIE & BIODIVERSITÉ</div>GBIF · WorldBank · WHOGHO</div>
        <div><div style="color:var(--cyan);font-weight:700;margin-bottom:4px">IA & MODÈLES</div>HuggingFace · PapersWithCode</div>
      </div>
    </div>
  </div>

  <!-- ARTICLES -->
  <div id="page-articles" class="page">
    <div class="ptitle">Articles <span>Collectés</span></div>
    <div class="fx g6 mb12">
      <button class="btn btn-o btn-sm" onclick="loadArticles()">↻ Actualiser (pending)</button>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <div class="alist" id="art-body"><div style="padding:16px;text-align:center;font:400 11px/1 var(--mono);color:var(--t2)">Cliquez ↻ ou lancez un crawl.</div></div>
    </div>
  </div>

  <!-- RAPPORTS -->
  <div id="page-reports" class="page">
    <div class="ptitle">Rapports <span>de Veille</span></div>
    <div class="fx g10 mb12">
      <button class="btn btn-p" onclick="doReport()">◈ Générer nouveau rapport</button>
      <button class="btn btn-o" onclick="loadReports()">↻ Actualiser</button>
    </div>
    <div id="rep-body"></div>
  </div>

  <!-- AUTO-REWRITE -->
  <div id="page-rewrite" class="page">
    <div class="ptitle">Auto-Rewrite <span>IA</span></div>
    <div class="card">
      <div class="chead">Paramètres de réécriture</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px">
        <div>
          <div style="font:700 9px/1 var(--mono);color:var(--t2);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px">Fichier cible</div>
          <select class="ss w100" id="rw-file">
            <option value="index.php">index.php (ce fichier)</option>
            <option value="1.php">1.php (crawler)</option>
            <option value="2.php">2.php (processeur)</option>
            <option value="agent.php">agent.php (agent)</option>
          </select>
        </div>
        <div>
          <div style="font:700 9px/1 var(--mono);color:var(--t2);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px">Tâche IA</div>
          <select class="ss w100" id="rw-task">
            <option value="optimize">Optimiser les performances</option>
            <option value="fix">Corriger les bugs</option>
            <option value="refactor">Refactoriser (SOLID/Clean Code)</option>
            <option value="add_feature">Ajouter des fonctionnalités</option>
          </select>
        </div>
      </div>
      <div style="font:700 9px/1 var(--mono);color:var(--t2);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px">Instructions spécifiques</div>
      <textarea class="sta w100" id="rw-notes" placeholder="Ex: Ajouter retry exponentiel sur 429, optimiser les boucles cURL, améliorer le parsing JSON…"></textarea>
      <div class="mt12"><button class="btn btn-p" id="btn-rw" onclick="runRewrite()">⟳ LANCER LA RÉÉCRITURE IA</button></div>
    </div>
    <div class="card" id="rw-res" style="display:none">
      <div class="chead fx jb ac">
        <span>Nouvelle version générée</span>
        <div class="fx g6">
          <button class="btn btn-o btn-sm" onclick="evalLatest()">🔍 Évaluer</button>
          <button class="btn btn-g btn-sm" onclick="deployLatest()">🚀 Déployer</button>
        </div>
      </div>
      <div id="rw-vl" style="font:700 11px/1 var(--mono);color:var(--cyan);margin-bottom:8px"></div>
      <div id="rw-code" class="cbox"></div>
      <div id="rw-eval" style="margin-top:10px;display:none"></div>
    </div>
  </div>

  <!-- VERSIONS -->
  <div id="page-versions" class="page">
    <div class="ptitle">Versions <span>du Code</span></div>
    <div class="fx g10 mb12 ac">
      <select class="ss" id="ver-fn" onchange="loadVersions()">
        <option value="">Tous les fichiers</option>
        <option value="index.php">index.php</option>
        <option value="1.php">1.php</option>
        <option value="2.php">2.php</option>
        <option value="agent.php">agent.php</option>
      </select>
      <button class="btn btn-o btn-sm" onclick="loadVersions()">↻</button>
    </div>
    <div class="card" style="padding:0;overflow:hidden" id="ver-body">
      <div style="padding:16px;text-align:center;font:400 11px/1 var(--mono);color:var(--t2)">Chargement…</div>
    </div>
  </div>

</div><!-- /main -->

<!-- CONSOLE -->
<div id="con">
  <div id="conh">
    <span id="cont">▶ CONSOLE SYSTÈME</span>
    <div class="fx g6">
      <button class="cb" onclick="clearCon()">CLR</button>
      <button class="cb" onclick="AS=!AS;clog('Auto-scroll '+(AS?'ON':'OFF'),'d')">AS</button>
    </div>
  </div>
  <div id="conb"></div>
  <div id="coni">
    <input id="cmd" class="si" placeholder="> commande…" onkeydown="if(event.key==='Enter')execCmd()">
    <button class="cb" onclick="execCmd()">RUN</button>
  </div>
</div>

</div><!-- /root -->

<!-- MODAL -->
<div class="mbg" id="modal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mt" id="mod-t"></div>
    <div id="mod-b"></div>
    <div class="fx jb mt16"><div id="mod-a"></div><button class="btn btn-o btn-sm" onclick="closeModal()">Fermer</button></div>
  </div>
</div>

<script>
// ═══════════════════════════════════════
// ÉTAT
// ═══════════════════════════════════════
let AS=true, lastLogId=0;
let isProc=false, stopReq=false;
let queue=[], pDone=0, pOK=0, pFail=0;
let cdTimer=null, lastVid=null, lastVl=null;

// ═══════════════════════════════════════
// PROMPT BIO-INFORMATIQUE ACADÉMIQUE
// Identique au define PHP PROMPT_TEMPLATE
// ═══════════════════════════════════════
const PROMPT_BASE = `Tu es une IA experte en bio-informatique et en ingénierie logicielle. Ton rôle est de transformer l'article scientifique fourni en un modèle de recherche actionnable où l'on utilisera des fonctions PHP et des APIs de données.

CONTEXTE DE L'ARTICLE :
{ARTICLE_CONTENT}

MISSION :
Rédige une étude approfondie expliquant comment une infrastructure basée sur PHP 8.3 et une base de données SQLite/Vectorielle peut faire avancer la science sur le sujet de l'article. Tu dois détailler des expériences numériques concrètes, expérimentales et révolutionnaires.

STRUCTURE DE TA RÉPONSE (FORMAT ACADÉMIQUE) :

1. ANALYSE CRITIQUE : Résume les points clés de l'article et identifie une lacune de données ou un besoin de corrélation spécifique.

2. PROTOCOLE D'EXPÉRIMENTATION PHP : Détermine comment un script PHP pourrait automatiser la collecte de données via les API ci-dessous pour valider ou infirmer les hypothèses de l'article.

3. UTILISATION STRATÉGIQUE DES API (OBLIGATOIRE) :
Pour chaque groupe d'API suivant dont tu connais le fonctionnement des endpoints, explique précisément quelle donnée extraire (en remplaçant {TERM} par un mot-clé pertinent de l'article) et comment l'intégrer dans l'étude :
   - RECHERCHE GÉNÉRALE & LITTÉRATURE : PubMed, EuropePMC, OpenAlex, CrossRef, arXiv, SemanticScholar.
   - DONNÉES GÉNOMIQUES & PROTÉINES : UniProt, Ensembl, ClinVar, NCBI_Gene, NCBI_Protein, PDB.
   - BIOLOGIE DES SYSTÈMES & VOIES : StringDB, Reactome, GeneOntology, KEGG.
   - CLINIQUE & PHARMACOLOGIE : ClinicalTrials, OpenFDA, ChEMBL, PubChem, RxNorm, DisGeNET.
   - ÉCOLOGIE & BIODIVERSITÉ : GBIF, WorldBank, WHOGHO.
   - IA & MODÈLES : HuggingFace, PapersWithCode.

4. ARCHITECTURE BDD & ALGORITHME :
   - Propose un schéma de table SQLite pour stocker ces résultats corrélés.
   - Explique comment PHP pourrait traiter ces volumes (multi-threading, parsing JSON) et surtout comment PHP pourrait grâce à ses fonctions faire des calculs et expériences réelles.

5. IMPACT SCIENTIFIQUE ATTENDU : En quoi cette automatisation par le code apporte-t-elle une valeur que l'article original n'avait pas ?

CONSIGNES DE RÉDACTION :
- Style : Professionnel, technique, académique.
- Langue : Français.
- Pas d'introduction inutile du type "Voici mon analyse". Entre directement dans le vif du sujet.
- Utilise des termes techniques (exemple : API, Endpoints, Parsing, Corrélation, Ontologie).`;

const MISTRAL = {
  endpoint:'https://api.mistral.ai/v1/chat/completions',
  model:'mistral-small-latest',
  keys:['5qaRTjWUjGJpAk5z35XcdEP5ZbH8Rake','o3rG1zvdq1yDOvjb7Z4J3J3eHXRShytu','vEzQMKN74Ez8RIwJ6y8J30ENDjFruXkF'],
  batchDelay:63000,  // 63s entre batches (freemium safety)
  timeout:95000,     // 95s par requête (étude longue)
  maxTokens:2000,    // étude académique complète
};

// ═══════════════════════════════════════
// CONSOLE
// ═══════════════════════════════════════
const LMAP={i:'li',s:'ls',e:'le',w:'lw',d:'ld',a:'la',info:'li',success:'ls',error:'le',warn:'lw',debug:'ld',ai:'la'};
function clog(msg,lvl='i'){
  const b=document.getElementById('conb');
  const ts=new Date().toLocaleTimeString('fr-FR',{hour12:false});
  const el=document.createElement('div');
  el.className='ll '+(LMAP[lvl]||'li');
  el.textContent=`[${ts}] ${msg}`;
  b.appendChild(el);
  if(AS) b.scrollTop=99999;
}
function clearCon(){document.getElementById('conb').innerHTML='';fetch('?action=clear_logs');clog('Console effacée.','d')}
function execCmd(){
  const inp=document.getElementById('cmd'); const cmd=inp.value.trim(); inp.value='';
  if(!cmd)return; clog('> '+cmd,'d');
  ({stats:refreshStats,crawl:runCrawl,process:startProc,report:doReport,clear:clearCon,
    help:()=>clog('stats | crawl | process | report | clear | pending | help','i'),
    pending:async()=>{const d=await fetch('?action=list_pending').then(r=>r.json());clog(`En attente: ${d.count}`,'i');}
  }[cmd]||(() =>clog(`"${cmd}" inconnu. Tapez help.`,'w')))();
}

// ═══════════════════════════════════════
// POLL LOGS SERVEUR
// ═══════════════════════════════════════
async function pollLogs(){
  try{
    const d=await fetch(`?action=get_logs&since=${lastLogId}`).then(r=>r.json());
    if(d.logs?.length){
      const b=document.getElementById('conb');
      d.logs.forEach(l=>{
        const el=document.createElement('div');
        el.className='ll '+(LMAP[l.level]||'li');
        el.textContent=`[${(l.created_at||'').split(' ')[1]||''}][SRV] ${l.message}${l.context?' '+l.context:''}`;
        b.appendChild(el);
      });
      lastLogId=d.last_id;
      if(AS) b.scrollTop=99999;
    }
  }catch(e){}
}
setInterval(pollLogs,2500);

// ═══════════════════════════════════════
// STATS
// ═══════════════════════════════════════
function fmtN(n){return n>=1000?(n/1000).toFixed(1)+'k':(n||0)}
async function refreshStats(){
  try{
    const d=await fetch('?action=stats').then(r=>r.json()); const s=d.stats||{};
    document.getElementById('s-art').textContent=s.articles_total??'—';
    document.getElementById('s-pend').textContent=s.articles_pending??'—';
    document.getElementById('s-ok').textContent=s.processed_ok??'—';
    document.getElementById('s-fail').textContent=s.processed_fail??'—';
    document.getElementById('s-tok').textContent=fmtN(s.tokens_total);
    document.getElementById('s-rep').textContent=s.reports_count??'—';
    document.getElementById('ba').textContent=s.articles_total??'—';
    document.getElementById('bok').textContent=s.processed_ok??'—';
    document.getElementById('btok').textContent=fmtN(s.tokens_total);
    document.getElementById('brep').textContent=s.reports_count??'—';
    document.getElementById('bver').textContent=s.code_versions??'—';
  }catch(e){clog('Stats err: '+e.message,'e')}
}
setInterval(refreshStats,12000); refreshStats();

// ═══════════════════════════════════════
// NAV
// ═══════════════════════════════════════
function go(p){
  document.querySelectorAll('.page').forEach(x=>x.classList.remove('on'));
  document.querySelectorAll('.ni').forEach(x=>x.classList.remove('on'));
  document.getElementById('page-'+p)?.classList.add('on');
  document.querySelector(`[data-p="${p}"]`)?.classList.add('on');
  if(p==='articles') loadArticles();
  if(p==='reports')  loadReports();
  if(p==='versions') loadVersions();
  if(p==='prompt')   {document.getElementById('full-prompt').textContent=PROMPT_BASE;}
  if(p==='processor'){document.getElementById('proc-prompt-prev').textContent=PROMPT_BASE.substring(0,480)+'…';}
}
function goAndRun(p,fn){go(p);setTimeout(fn,300)}
document.querySelectorAll('.ni').forEach(ni=>ni.addEventListener('click',()=>go(ni.dataset.p)));

// ═══════════════════════════════════════
// CRAWLER
// ═══════════════════════════════════════
async function runCrawl(){
  const btn=document.getElementById('btn-crawl');
  if(btn){btn.disabled=true;btn.textContent='⟳ Crawl en cours…';}
  clog('🕷️ Crawl RSS démarré…','i');
  try{
    const d=await fetch('?action=crawl').then(r=>r.json());
    if(d.status==='ok'){
      clog(`✅ Crawl terminé — ${d.total_new} nouveaux articles`,'s');
      const card=document.getElementById('crawl-res'); const body=document.getElementById('crawl-res-body');
      if(card&&body){
        card.style.display='block';
        body.innerHTML=Object.entries(d.sources).map(([n,r])=>
          `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--line);font:400 11px/1 var(--mono)">
            <span style="color:var(--t1)">${esc(n)}</span>
            <span class="${r.status==='ok'?'ok':'fail'}">${r.status==='ok'?'+'+r.new+' articles':'ERR '+r.code}</span>
          </div>`
        ).join('');
      }
      refreshStats();
    }else clog('❌ Crawl err: '+(d.message||'?'),'e');
  }catch(e){clog('❌ Exception: '+e.message,'e')}
  finally{if(btn){btn.disabled=false;btn.textContent='⬡ Lancer le crawl';}}
}

// ═══════════════════════════════════════
// PROCESSEUR — 3 CLÉS PARALLÈLES
// ═══════════════════════════════════════
function setKey(i,state,title=''){
  const box=document.getElementById('kb'+i);
  const ks=document.getElementById('ks'+i);
  const kt=document.getElementById('kt'+i);
  if(!box)return;
  box.className='kb'+(state?' '+state:'');
  const L={active:'⚡ EN COURS…',ok:'✅ SUCCÈS',err:'❌ ÉCHEC',wait:'⏳ EN ATTENTE','':'EN ATTENTE'};
  const C={active:'var(--cyan)',ok:'var(--green)',err:'var(--red)',wait:'var(--amber)'};
  ks.textContent=L[state]||state; ks.style.color=C[state]||'var(--t1)';
  if(kt) kt.textContent=title?title.substring(0,52)+(title.length>52?'…':''):'';
}
function resetKeys(){[0,1,2].forEach(i=>setKey(i,'',''))}

function buildPrompt(art){
  const content=`SOURCE: ${art.source}\nTITRE: ${art.title}\nCONTENU: ${(art.description||'').substring(0,1200)}`;
  return PROMPT_BASE.replace('{ARTICLE_CONTENT}',content);
}

async function callMistral(ki,art){
  const key=MISTRAL.keys[ki];
  clog(`🔑 Clé #${ki+1} → "${art.title.substring(0,36)}…"`, 'i');
  setKey(ki,'active',art.title);
  const ctrl=new AbortController();
  const tid=setTimeout(()=>ctrl.abort(),MISTRAL.timeout);
  try{
    const res=await fetch(MISTRAL.endpoint,{
      method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'Bearer '+key},
      body:JSON.stringify({model:MISTRAL.model,messages:[{role:'user',content:buildPrompt(art)}],max_tokens:MISTRAL.maxTokens,temperature:0.4}),
      signal:ctrl.signal,
    });
    clearTimeout(tid);
    if(res.status===429){clog(`⚠️ Clé #${ki+1}: Rate limit 429 (quota freemium)`,'w');setKey(ki,'err','429 Rate Limit');return{success:false,error:'429'};}
    if(!res.ok){const t=await res.text().catch(()=>'');throw new Error(`HTTP ${res.status}: ${t.substring(0,100)}`);}
    const data=await res.json();
    const content=data?.choices?.[0]?.message?.content?.trim();
    if(!content) throw new Error('Réponse Mistral vide');
    const tokens=data?.usage?.total_tokens||0;
    clog(`✅ Clé #${ki+1} — ${tokens} tokens — "${art.title.substring(0,28)}…"`,'s');
    // Affiche l'extrait de l'étude dans la console
    const lines=content.split('\n').filter(l=>l.trim().length>2).slice(0,3);
    lines.forEach(l=>clog('   '+l.substring(0,80),'a'));
    setKey(ki,'ok',art.title);
    await fetch('?action=save_processed',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({hash:art.hash,status:'success',ai_analysis:content,key_index:ki,tokens_used:tokens})});
    return{success:true,text:content,tokens};
  }catch(err){
    clearTimeout(tid);
    const msg=err.name==='AbortError'?'Timeout (95s)':(err.message||String(err));
    clog(`❌ Clé #${ki+1}: ${msg.substring(0,80)}`,'e');
    setKey(ki,'err',msg.substring(0,40));
    await fetch('?action=save_processed',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({hash:art.hash,status:'failed',error_msg:msg,key_index:ki})}).catch(()=>{});
    return{success:false,error:msg};
  }
}

function sleep(ms){return new Promise(r=>setTimeout(r,ms))}

function startCd(ms){
  if(cdTimer)clearInterval(cdTimer);
  const end=Date.now()+ms;
  const el=document.getElementById('proc-cd');
  cdTimer=setInterval(()=>{
    const rem=Math.max(0,end-Date.now());
    if(el) el.textContent=rem>0?`⏳ Prochain batch dans ${Math.ceil(rem/1000)}s (rate limit freemium Mistral)…`:'';
    if(rem<=0) clearInterval(cdTimer);
  },400);
}

async function startProc(){
  if(isProc)return;
  clog('📋 Chargement articles en attente…','i');
  const d=await fetch('?action=list_pending').then(r=>r.json());
  if(!d.pending?.length){clog('ℹ️ Aucun article en attente. Lancez le crawl.','w');return;}
  queue=[...d.pending]; isProc=true; stopReq=false; pDone=0; pOK=0; pFail=0;
  const total=queue.length;
  clog(`⚡ DÉMARRAGE — ${total} articles | 3 clés parallèles | Délai batch: ${MISTRAL.batchDelay/1000}s`,'s');
  clog(`   Prompt académique — jusqu'à ${MISTRAL.maxTokens} tokens/article`,'d');
  document.getElementById('btn-ps').style.display='none';
  document.getElementById('btn-pp').style.display='inline-flex';
  resetKeys();

  while(queue.length>0&&!stopReq){
    const batch=queue.splice(0,3);
    clog(`\n📦 BATCH [${pDone+1}–${pDone+batch.length}/${total}] — ${queue.length} restants`,'i');
    batch.forEach((_,i)=>setKey(i,'wait',batch[i]?.title||''));
    // PARALLÈLE — 1 clé par article simultanément
    const results=await Promise.allSettled(batch.map((art,i)=>callMistral(i,art)));
    results.forEach(r=>{pDone++;if(r.status==='fulfilled'&&r.value?.success)pOK++;else pFail++;});
    const pct=Math.round((pDone/total)*100);
    document.getElementById('proc-bar').style.width=pct+'%';
    document.getElementById('proc-cnt').textContent=`${pDone}/${total} — ✅${pOK} ❌${pFail}`;
    document.getElementById('proc-det').textContent=`Progression: ${pct}%`;
    document.getElementById('proc-st').textContent=`Traitement — ${queue.length} restants`;
    refreshStats();
    if(queue.length>0&&!stopReq){
      clog(`⏳ Pause ${MISTRAL.batchDelay/1000}s (quota freemium Mistral)…`,'w');
      startCd(MISTRAL.batchDelay); await sleep(MISTRAL.batchDelay);
      document.getElementById('proc-cd').textContent='';
    }
  }
  isProc=false;
  const reason=stopReq?'⏸️ Arrêté':'🏁 Terminé';
  clog(`${reason}: ${pOK}✅ / ${pFail}❌ sur ${pDone} articles (${pDone?Math.round(pOK/pDone*100):0}% succès)`,'s');
  document.getElementById('proc-st').textContent=`${reason} — ${pOK}✅ / ${pFail}❌`;
  document.getElementById('btn-ps').style.display='inline-flex';
  document.getElementById('btn-pp').style.display='none';
  resetKeys(); if(cdTimer)clearInterval(cdTimer);
  document.getElementById('proc-cd').textContent='';
  refreshStats();
}
function stopProc(){stopReq=true;clog('⏸️ Arrêt demandé…','w')}

// ═══════════════════════════════════════
// ARTICLES
// ═══════════════════════════════════════
async function loadArticles(){
  const el=document.getElementById('art-body'); if(!el)return;
  el.innerHTML='<div style="padding:14px;text-align:center;font:400 11px/1 var(--mono);color:var(--t2)">Chargement…</div>';
  try{
    const d=await fetch('?action=list_pending').then(r=>r.json());
    const items=d.pending||[];
    if(!items.length){el.innerHTML='<div style="padding:14px;text-align:center;font:400 11px/1 var(--mono);color:var(--t2)">Aucun article en attente.</div>';return;}
    el.innerHTML=items.map(a=>`
      <div class="arow">
        <div class="asrc">${esc(a.source||'')}</div>
        <div><div class="atit">${esc(a.title||'')}</div><div style="font:400 9px/1 var(--mono);color:var(--t2);margin-top:3px">${a.fetched_at||''}</div></div>
        <div class="ast pend">⏳</div>
      </div>`).join('');
  }catch(e){el.innerHTML='<div style="padding:14px;color:var(--red)">Erreur: '+esc(e.message)+'</div>';}
}

// ═══════════════════════════════════════
// RAPPORTS
// ═══════════════════════════════════════
async function loadReports(){
  const el=document.getElementById('rep-body'); if(!el)return;
  try{
    const d=await fetch('?action=get_reports').then(r=>r.json());
    if(!d.reports?.length){el.innerHTML='<div class="card"><p style="font:400 12px/1 var(--mono);color:var(--t2)">Aucun rapport. Cliquez Générer.</p></div>';return;}
    el.innerHTML=d.reports.map(r=>`
      <div class="rcard" onclick="viewRep(${r.id})">
        <div class="fx jb ac">
          <div><div style="font-size:13px;font-weight:700">${esc(r.title)}</div>
          <div style="font:400 10px/1 var(--mono);color:var(--t2);margin-top:4px">${r.created_at} · ${r.article_count} études</div></div>
          <span class="badge bb">Voir →</span>
        </div>
        <div style="font:400 11px/1.5 var(--mono);color:var(--t1);margin-top:8px">${esc((r.preview||'').substring(0,150))}…</div>
      </div>`).join('');
  }catch(e){clog('Rapports err: '+e.message,'e')}
}
async function viewRep(id){
  const d=await fetch(`?action=get_report&id=${id}`).then(r=>r.json());
  if(!d.report)return;
  openModal(d.report.title,`<div class="abox" style="max-height:55vh">${mdR(d.report.content)}</div>`);
}
async function doReport(){
  clog('📄 Génération rapport de veille…','i');
  try{
    const d=await fetch('?action=generate_report').then(r=>r.json());
    if(d.status==='ok'){
      clog(`✅ Rapport "${d.title}" — ${d.article_count} études`,'s');
      document.getElementById('dash-last').style.display='block';
      document.getElementById('dash-prev').innerHTML=mdR(d.content.substring(0,800)+'…');
      loadReports(); refreshStats();
    }else clog('❌ Rapport err: '+(d.message||'?'),'e');
  }catch(e){clog('❌ Exception rapport: '+e.message,'e')}
}

// ═══════════════════════════════════════
// AUTO-REWRITE
// ═══════════════════════════════════════
async function runRewrite(){
  const file=document.getElementById('rw-file').value;
  const task=document.getElementById('rw-task').value;
  const notes=document.getElementById('rw-notes').value;
  const btn=document.getElementById('btn-rw');
  if(!file){clog('Sélectionnez un fichier.','w');return;}
  btn.disabled=true; btn.textContent='⟳ Réécriture en cours…';
  clog(`🔧 Réécriture IA: ${file} (${task})…`,'i');
  try{
    const d=await fetch('?action=ai_rewrite',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({filename:file,task,notes})}).then(r=>r.json());
    if(d.status==='ok'){
      clog(`✅ Version ${d.version_label} — ${d.tokens_used} tokens`,'s');
      lastVid=d.version_id; lastVl=d.version_label;
      document.getElementById('rw-res').style.display='block';
      document.getElementById('rw-vl').textContent=`${d.version_label} · ${d.tokens_used} tokens · ${file}`;
      document.getElementById('rw-code').textContent=(d.new_code||'').substring(0,3500)+((d.new_code?.length||0)>3500?'\n…[tronqué]':'');
      document.getElementById('rw-eval').style.display='none';
      refreshStats();
    }else clog('❌ Rewrite err: '+(d.message||'?'),'e');
  }catch(e){clog('❌ Exception rewrite: '+e.message,'e')}
  finally{btn.disabled=false;btn.textContent='⟳ LANCER LA RÉÉCRITURE IA';}
}
async function evalLatest(){
  if(!lastVid){clog('Aucune version à évaluer.','w');return;}
  clog(`🔍 Évaluation version #${lastVid}…`,'i');
  try{
    const d=await fetch('?action=ai_evaluate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({version_id:lastVid})}).then(r=>r.json());
    if(d.status==='ok'){
      const ev=d.evaluation; const pct=Math.round(d.score*100);
      const col=pct>70?'var(--green)':pct>40?'var(--amber)':'var(--red)';
      const rc={deploy:'bg',review:'ba',reject:'br'}[ev.recommendation]||'bb';
      const el=document.getElementById('rw-eval'); el.style.display='block';
      el.innerHTML=`<div class="card" style="background:var(--bg0)">
        <div class="fx jb ac mb8"><span style="font-size:13px;font-weight:700">Évaluation IA</span>
        <div class="fx g6 ac"><span style="font:800 22px/1 var(--mono);color:${col}">${pct}%</span><span class="badge ${rc}">${(ev.recommendation||'?').toUpperCase()}</span></div></div>
        <div style="font:400 10px/1.7 var(--mono);color:var(--t1)">
          <div><b style="color:var(--t0)">Qualité:</b> ${esc(ev.quality||'—')}</div>
          <div><b style="color:var(--t0)">Performances:</b> ${esc(ev.performance||'—')}</div>
          <div><b style="color:var(--t0)">Sécurité:</b> ${esc(ev.security||'—')}</div>
          <div><b style="color:var(--t0)">Maintenance:</b> ${esc(ev.maintainability||'—')}</div>
          ${ev.bugs?`<div><b style="color:var(--red)">Bugs:</b> ${esc(ev.bugs)}</div>`:''}
          <div style="margin-top:6px;color:var(--t0)"><b>Résumé:</b> ${esc(ev.summary||'—')}</div>
        </div></div>`;
      clog(`🔍 Score: ${pct}% — ${ev.recommendation}`,'s');
      loadVersions();
    }else clog('❌ Éval err: '+(d.message||'?'),'e');
  }catch(e){clog('❌ Exception éval: '+e.message,'e')}
}
async function deployLatest(){
  if(!lastVid){clog('Aucune version à déployer.','w');return;}
  if(!confirm(`Déployer "${lastVl}" ? Le fichier actuel sera sauvegardé en backup.`))return;
  clog(`🚀 Déploiement #${lastVid}…`,'i');
  try{
    const d=await fetch('?action=deploy_version',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({version_id:lastVid})}).then(r=>r.json());
    if(d.status==='ok') clog(`✅ Déployé: ${d.deployed} → ${d.file}`,'s');
    else clog('❌ Deploy err: '+(d.message||'?'),'e');
  }catch(e){clog('❌ Exception deploy: '+e.message,'e')}
}

// ═══════════════════════════════════════
// VERSIONS
// ═══════════════════════════════════════
async function loadVersions(){
  const el=document.getElementById('ver-body'); const fn=document.getElementById('ver-fn')?.value||'';
  if(!el)return;
  try{
    const d=await fetch(fn?`?action=get_versions&filename=${encodeURIComponent(fn)}`:'?action=get_versions').then(r=>r.json());
    if(!d.versions?.length){el.innerHTML='<div style="padding:14px;text-align:center;font:400 11px/1 var(--mono);color:var(--t2)">Aucune version. Utilisez Auto-Rewrite.</div>';return;}
    el.innerHTML=d.versions.map(v=>{
      const pct=Math.round((v.score||0)*100);
      const col=pct>70?'var(--green)':pct>40?'var(--amber)':'var(--red)';
      return `<div class="vrow">
        <div><div class="vl">${esc(v.version_label)}</div><div style="font:400 9px/1 var(--mono);color:var(--t2);margin-top:2px">${esc(v.filename)} · ${v.created_at}</div></div>
        <div><div class="vsw"><div class="vsb" style="width:${pct}%;background:${col}"></div></div><div style="font:700 9px/1 var(--mono);text-align:center;color:${col};margin-top:2px">${pct}%</div></div>
        <button class="btn btn-o btn-sm" onclick="evalById(${v.id})">🔍</button>
        <button class="btn btn-o btn-sm" onclick="depById(${v.id},'${esc(v.version_label)}')">🚀</button>
      </div>`;
    }).join('');
  }catch(e){clog('Versions err: '+e.message,'e')}
}
async function evalById(id){lastVid=id;await evalLatest();}
async function depById(id,label){if(!confirm(`Déployer "${label}" ?`))return;lastVid=id;lastVl=label;await deployLatest();}

// ═══════════════════════════════════════
// MODAL / UTILS
// ═══════════════════════════════════════
function openModal(t,b,acts=''){
  document.getElementById('mod-t').textContent=t;
  document.getElementById('mod-b').innerHTML=b;
  document.getElementById('mod-a').innerHTML=acts;
  document.getElementById('modal').classList.add('open');
}
function closeModal(){document.getElementById('modal').classList.remove('open')}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function mdR(t){
  return esc(t)
    .replace(/^## (.+)$/gm,'<h2>$1</h2>')
    .replace(/^### (.+)$/gm,'<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
    .replace(/\*(.+?)\*/g,'<em>$1</em>')
    .replace(/\n/g,'<br>');
}

// ═══════════════════════════════════════
// INIT
// ═══════════════════════════════════════
clog('🟢 Science Pulse Admin v<?= SP_VERSION ?> démarré','s');
clog(`📡 Mistral: ${MISTRAL.model} · 3 clés parallèles · Batch delay: ${MISTRAL.batchDelay/1000}s · Max tokens/article: ${MISTRAL.maxTokens}`,'i');
clog('📝 Prompt: Bio-Informatique Académique (5 sections · 20+ APIs)','d');
clog('> Tapez "help" pour voir les commandes disponibles','d');
</script>
</body>
</html>
