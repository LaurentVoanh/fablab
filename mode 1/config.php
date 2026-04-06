<?php
/**
* ╔══════════════════════════════════════════════════════════════════════╗
* ║  GENESIS-ULTRA v9.1-FIXED — CONFIG.PHP                               ║
* ║  Configuration centrale • APIs scientifiques • IA augmentée          ║
* ║  Corrections: ArXiv SimpleXML, SSL activé, 8 sources fiables         ║
* ╚══════════════════════════════════════════════════════════════════════╝
*/
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/storage/php_errors.log');
while(@ob_get_level() > 0) { @ob_end_clean(); }

// ============================================================================
// CONSTANTES GLOBALES
// ============================================================================
defined('GENESIS_VERSION')        or define('GENESIS_VERSION',        '9.1-neuron-fixed');
defined('STORAGE_PATH')           or define('STORAGE_PATH',           __DIR__ . '/storage/');
defined('MAX_STEP_TIME')          or define('MAX_STEP_TIME',          22);
defined('HYPOTHESIS_PER_PAGE')    or define('HYPOTHESIS_PER_PAGE',    12);
defined('MAX_LOGS_IN_RAM')        or define('MAX_LOGS_IN_RAM',        300);
defined('ABSTRACT_MAX_CHARS')     or define('ABSTRACT_MAX_CHARS',     800);
defined('MAX_ABSTRACTS_PER_SOURCE') or define('MAX_ABSTRACTS_PER_SOURCE', 8);
defined('MAX_ERRORS_BEFORE_RESET') or define('MAX_ERRORS_BEFORE_RESET', 5);

// ============================================================================
// CLÉS API MISTRAL — Rotation automatique avec fallback
// ⚠️ REMPLACEZ PAR VOS CLÉS RÉELLES EN PRODUCTION
// ============================================================================
$MISTRAL_KEYS = [
    'api key',
    'api key',
    'api key'
];
$MISTRAL_KEY_INDEX = 0;
$MISTRAL_CONFIG = [
    'keys'              => $MISTRAL_KEYS,
    'current_index'     => 0,
    'emergency_model'   => 'mistral-small',
    'models_available'  => [
        'fast'    => ['name' => 'mistral-small',  'tokens_max' => 32000, 'use_for' => 'target_selection,quick_tasks'],
        'medium'  => ['name' => 'mistral-medium', 'tokens_max' => 32000, 'use_for' => 'synthesis,article'],
        'deep'    => ['name' => 'mistral-large',  'tokens_max' => 32000, 'use_for' => 'deep_research,critique'],
    ]
];

// ============================================================================
// SOURCES SCIENTIFIQUES — 8 bases de données (Semantic Scholar retiré)
// ============================================================================
$SCIENTIFIC_APIS = [
    'pubmed' => [
        'name'         => 'PubMed',
        'emoji'        => '📗',
        'color'        => '#0066cc',
        'base'         => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/',
        'max_standard' => 8,
        'max_deep'     => 20,
        'timeout'      => 35,
        'type'         => 'biomedical',
        'weight'       => 1.5,
    ],
    'uniprot' => [
        'name'         => 'UniProt',
        'emoji'        => '🔵',
        'color'        => '#00aa55',
        'base'         => 'https://rest.uniprot.org/uniprotkb/',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 35,
        'type'         => 'protein',
        'weight'       => 1.2,
    ],
    'clinvar' => [
        'name'         => 'ClinVar',
        'emoji'        => '🧬',
        'color'        => '#cc2200',
        'base'         => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/',
        'max_standard' => 6,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'genetics',
        'weight'       => 1.3,
    ],
    'arxiv' => [
        'name'         => 'ArXiv',
        'emoji'        => '📐',
        'color'        => '#ff6600',
        'base'         => 'https://export.arxiv.org/api/query',
        'max_standard' => 6,
        'max_deep'     => 12,
        'timeout'      => 60,
        'type'         => 'preprint',
        'weight'       => 0.9,
    ],
    'europepmc' => [
        'name'         => 'EuropePMC',
        'emoji'        => '🌍',
        'color'        => '#0077bb',
        'base'         => 'https://www.ebi.ac.uk/europepmc/webservices/rest/search',
        'max_standard' => 6,
        'max_deep'     => 12,
        'timeout'      => 40,
        'type'         => 'biomedical',
        'weight'       => 1.2,
    ],
    'openalex' => [
        'name'         => 'OpenAlex',
        'emoji'        => '🌐',
        'color'        => '#8b5cf6',
        'base'         => 'https://api.openalex.org/works',
        'max_standard' => 6,
        'max_deep'     => 12,
        'timeout'      => 40,
        'type'         => 'cross-domain',
        'weight'       => 1.0,
    ],
    'chembl' => [
        'name'         => 'ChEMBL',
        'emoji'        => '⚗️',
        'color'        => '#d97706',
        'base'         => 'https://www.ebi.ac.uk/chembl/api/data/',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 40,
        'type'         => 'chemistry',
        'weight'       => 1.1,
    ],
    'wikidata' => [
        'name'         => 'Wikidata',
        'emoji'        => '📚',
        'color'        => '#666',
        'base'         => 'https://www.wikidata.org/w/api.php',
        'max_standard' => 5,
        'timeout'      => 30,
        'type'         => 'knowledge',
        'weight'       => 0.7,
    ],
];

// ============================================================================
// STRATÉGIES DE RECHERCHE AUTOMATIQUE
// ============================================================================
$RESEARCH_STRATEGIES = [
    'broad'      => ['depth' => 3, 'sources' => 5, 'boost' => 1.0, 'desc' => 'Exploration large spectre'],
    'focused'    => ['depth' => 6, 'sources' => 7, 'boost' => 1.3, 'desc' => 'Analyse ciblée'],
    'deep'       => ['depth' => 8, 'sources' => 8, 'boost' => 1.6, 'desc' => 'Recherche approfondie toutes sources'],
    'adaptive'   => ['depth' => 0, 'sources' => 0, 'boost' => 1.0, 'desc' => 'Adaptatif selon disponibilité'],
];

// Domaines scientifiques avec leurs sources préférées
$DOMAIN_SOURCE_MAP = [
    'genetics'    => ['pubmed', 'clinvar', 'uniprot', 'europepmc'],
    'oncology'    => ['pubmed', 'europepmc', 'openalex'],
    'neurology'   => ['pubmed', 'arxiv', 'europepmc'],
    'biochem'     => ['uniprot', 'chembl', 'pubmed', 'europepmc'],
    'pharmacology'=> ['chembl', 'pubmed', 'clinvar', 'europepmc'],
    'general'     => ['pubmed', 'uniprot', 'arxiv', 'europepmc'],
];

// ============================================================================
// INITIALISATION DES DOSSIERS
// ============================================================================
foreach(['logs','knowledge','articles','deep_research','cache','ai_learning','graph','exports','auto_queue'] as $dir) {
    $path = STORAGE_PATH . $dir;
    if(!is_dir($path)) @mkdir($path, 0755, true);
}

// Fichiers index JSONL
$index_files = [
    'logs/index.jsonl',
    'knowledge/index.jsonl',
    'ai_learning/feedback.jsonl',
    'graph/nodes.json',
    'graph/edges.json',
    'auto_queue/queue.json',
];
foreach($index_files as $file) {
    $path = STORAGE_PATH . $file;
    if(!file_exists($path)) {
        $dir = dirname($path);
        if(!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($path, '');
    }
}

// ============================================================================
// RÉSEAU — CURL Optimized avec retry exponentiel & SSL
// ============================================================================
function genesis_curl($url, $post_data = null, $custom_headers = [], $timeout = 45, $max_retries = 3) {
    $attempt    = 0;
    $last_error = null;
    $http_code  = 0;
    while($attempt < $max_retries) {
        $attempt++;
        $ch = @curl_init($url);
        if(!$ch) { $last_error = 'curl_init failed'; continue; }
        
        $headers = array_merge(
            ['Accept: application/json', 'Content-Type: application/json'],
            $custom_headers
        );
        
        @curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,  // ✅ SÉCURITÉ ACTIVÉE
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'GENESIS-ULTRA/' . GENESIS_VERSION . ' (Scientific Research Engine; research@genesis.local)',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => 'gzip,deflate',
        ]);
        
        if($post_data) {
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($post_data) ? $post_data : @json_encode($post_data));
        }
        
        $result        = @curl_exec($ch);
        $error         = @curl_error($ch);
        $http_code     = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_time = @curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        @curl_close($ch);
        
        if($result && !$error && $http_code >= 200 && $http_code < 300) {
            return [
                'success'         => true,
                'data'            => $result,
                'error'           => null,
                'http_code'       => $http_code,
                'attempts'        => $attempt,
                'response_time_ms'=> round($response_time * 1000),
            ];
        }
        $last_error = $error ?: "HTTP $http_code";
        $wait = ($http_code === 429) ? 1500000 : pow(2, $attempt) * 150000;
        if($attempt < $max_retries) @usleep($wait);
    }
    return [
        'success'   => false,
        'data'      => null,
        'error'     => $last_error,
        'http_code' => $http_code,
        'attempts'  => $attempt,
    ];
}

// ============================================================================
// IA MISTRAL — Appel avec rotation de clé
// ============================================================================
function genesis_mistral($messages, $model = 'mistral-small', $max_tokens = 1500, $temperature = 0.4, $require_json = true) {
    global $MISTRAL_KEYS, $MISTRAL_KEY_INDEX;
    $key = $MISTRAL_KEYS[$MISTRAL_KEY_INDEX % count($MISTRAL_KEYS)];
    $MISTRAL_KEY_INDEX++;
    
    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
        'top_p'       => 0.95,
        'safe_prompt' => true,
    ];
    if($require_json) {
        $payload['response_format'] = ['type' => 'json_object'];
    }
    
    $response = genesis_curl(
        'https://api.mistral.ai/v1/chat/completions',
        @json_encode($payload),
        ['Authorization: Bearer ' . $key],
        90,
        2
    );
    
    if(!$response['success']) {
        if(in_array($response['http_code'], [401, 403, 429])) {
            $MISTRAL_KEY_INDEX++;
            $key = $MISTRAL_KEYS[$MISTRAL_KEY_INDEX % count($MISTRAL_KEYS)];
            $response = genesis_curl(
                'https://api.mistral.ai/v1/chat/completions',
                @json_encode($payload),
                ['Authorization: Bearer ' . $key],
                90,
                1
            );
        }
        if(!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'http_code' => $response['http_code']];
        }
    }
    
    $json = @json_decode($response['data'], true);
    if(!isset($json['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Response structure invalide', 'raw' => substr($response['data'] ?? '', 0, 300)];
    }
    
    $content = trim($json['choices'][0]['message']['content']);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);
    $content = trim($content);
    
    if($require_json && !str_starts_with($content, '{') && !str_starts_with($content, '[')) {
        if(preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }
    }
    
    $parsed = $content;
    if($require_json) {
        $parsed = @json_decode($content, true);
        if(!is_array($parsed)) {
            $fixed = preg_replace('/,\s*([\}\]])/', '$1', $content);
            $parsed = @json_decode($fixed, true);
            if(!is_array($parsed)) {
                return ['success' => false, 'error' => 'JSON parse error', 'content' => substr($content, 0, 400)];
            }
        }
    }
    
    return [
        'success'         => true,
        'data'            => $parsed,
        'raw'             => $content,
        'model_used'      => $model,
        'tokens_used'     => $json['usage']['total_tokens'] ?? 0,
        'response_time_ms'=> $response['response_time_ms'] ?? 0,
    ];
}

// ============================================================================
// APIs SCIENTIFIQUES — 8 sources CORRIGÉES
// ============================================================================

function genesis_pubmed($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'PubMed', 'abstracts' => '', 'error' => 'Empty query'];
    $query = preg_replace('/["\']/', '', $query);
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=" 
         . urlencode($query) . "&retmode=json&retmax=$max&sort=relevance";
    $r = genesis_curl($url, null, [], 35);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'PubMed', 'abstracts' => '', 'error' => $r['error']];
    
    $d   = @json_decode($r['data'], true);
    $ids = $d['esearchresult']['idlist'] ?? [];
    $items = []; $abstracts = [];
    
    foreach(array_slice($ids, 0, min(5, count($ids))) as $id) {
        $f  = genesis_curl("https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=$id&retmode=json", null, [], 25);
        $fd = @json_decode($f['data'], true);
        $info   = $fd['result'][$id] ?? [];
        $title  = $info['title'] ?? 'N/A';
        $items[] = [
            'pmid'    => $id,
            'title'   => substr($title, 0, 200),
            'journal' => $info['fulljournalname'] ?? 'N/A',
            'year'    => substr($info['pubdate'] ?? '', 0, 4),
            'url'     => "https://pubmed.ncbi.nlm.nih.gov/$id/",
        ];
        $abstracts[] = substr($title, 0, ABSTRACT_MAX_CHARS);
    }
    return ['count' => count($ids), 'items' => $items, 'source' => 'PubMed', 'abstracts' => implode("\n---\n", array_filter($abstracts))];
}

function genesis_uniprot($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'UniProt', 'abstracts' => '', 'error' => 'Empty query'];
    $gene = preg_replace('/[^A-Za-z0-9\-_]/', '', $query);
    $url = "https://rest.uniprot.org/uniprotkb/search?query=gene_name:" 
         . urlencode($gene) . "+AND+reviewed:true&format=json&size=$max"
         . "&fields=primaryAccession,uniProtkbId,genes,comments,function";
    $r = genesis_curl($url, null, [], 35);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'UniProt', 'abstracts' => '', 'error' => $r['error']];
    
    $d = @json_decode($r['data'], true);
    $results = $d['results'] ?? [];
    $items = []; $abstracts = [];
    
    foreach(array_slice($results, 0, min(5, count($results))) as $p) {
        $func = '';
        foreach($p['comments'] ?? [] as $comment) {
            if($comment['commentType'] === 'FUNCTION' && !empty($comment['texts'][0]['value'])) {
                $func = substr(strip_tags($comment['texts'][0]['value']), 0, 400);
                break;
            }
        }
        $items[] = [
            'id'       => $p['primaryAccession'] ?? 'N/A',
            'name'     => $p['uniProtkbId'] ?? 'N/A',
            'function' => $func ?: 'N/A',
            'gene'     => $p['genes'][0]['geneName']['value'] ?? ($p['genes'][0]['name']['value'] ?? 'N/A'),
            'url'      => "https://www.uniprot.org/uniprotkb/" . ($p['primaryAccession'] ?? ''),
        ];
        if($func) $abstracts[] = $func;
    }
    return ['count' => count($results), 'items' => $items, 'source' => 'UniProt', 'abstracts' => implode("\n---\n", array_filter($abstracts))];
}

function genesis_clinvar($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'ClinVar', 'abstracts' => '', 'error' => 'Empty query'];
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=clinvar&term=" 
         . urlencode($query) . "&retmode=json&retmax=$max";
    $r = genesis_curl($url, null, [], 35);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'ClinVar', 'abstracts' => '', 'error' => $r['error']];
    
    $d   = @json_decode($r['data'], true);
    $ids = $d['esearchresult']['idlist'] ?? [];
    $items = array_map(fn($id) => [
        'vid'  => $id,
        'url'  => "https://www.ncbi.nlm.nih.gov/clinvar/variation/$id/",
    ], array_slice($ids, 0, 5));
    return ['count' => count($ids), 'items' => $items, 'source' => 'ClinVar', 'abstracts' => 'Variants: ' . implode(', ', array_column($items, 'vid'))];
}

// ✅ ARXIV CORRIGÉ — SimpleXML au lieu de regex fragile
function genesis_arxiv($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'ArXiv', 'abstracts' => '', 'error' => 'Empty query'];
    
    // ✅ HTTPS + User-Agent spécifique ArXiv + Accept Atom
    $url = "https://export.arxiv.org/api/query?search_query=all:" 
         . urlencode($query) 
         . "&max_results=$max&sortBy=relevance&sortOrder=descending";
    
    $headers = ['Accept: application/atom+xml', 'User-Agent: GENESIS-ULTRA/9.1 (research@genesis.local)'];
    $r = genesis_curl($url, null, $headers, 60);
    
    if(!$r['success']) {
        return ['count' => 0, 'items' => [], 'source' => 'ArXiv', 'abstracts' => '', 'error' => $r['error']];
    }
    
    // ✅ Parsing XML robuste avec SimpleXML (gestion des namespaces Atom)
    $xml = @simplexml_load_string($r['data'], 'SimpleXMLElement', LIBXML_NOCDATA);
    if(!$xml) {
        return ['count' => 0, 'items' => [], 'source' => 'ArXiv', 'abstracts' => '', 'error' => 'XML parse failed'];
    }
    
    $items = []; 
    $abstracts = [];
    
    foreach($xml->entry as $entry) {
        if(count($items) >= 5) break;
        
        $title = isset($entry->title) ? trim((string)$entry->title) : 'N/A';
        $summary = isset($entry->summary) ? trim((string)$entry->summary) : '';
        $id_url = isset($entry->id) ? trim((string)$entry->id) : '#';
        
        // Nettoyer le résumé (ArXiv inclut des sauts de ligne)
        $summary = preg_replace('/\s+/', ' ', $summary);
        
        $items[] = [
            'id'      => basename($id_url),
            'title'   => substr($title, 0, 200),
            'summary' => substr($summary, 0, 300),
            'url'     => str_replace('http://', 'https://', $id_url),
        ];
        
        if(!empty($summary)) {
            $abstracts[] = substr($summary, 0, ABSTRACT_MAX_CHARS);
        } elseif(!empty($title)) {
            $abstracts[] = $title;
        }
    }
    
    return [
        'count'     => count($items),
        'items'     => $items,
        'source'    => 'ArXiv',
        'abstracts' => implode("\n---\n", array_filter($abstracts)),
    ];
}

// Semantic Scholar RETIRÉ (Nécessite API Key depuis 2024)

function genesis_europepmc($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'EuropePMC', 'abstracts' => '', 'error' => 'Empty query'];
    $url = "https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=" 
         . urlencode($query) . "&resultType=lite&pageSize=$max&format=json&sort=CITED";
    $r = genesis_curl($url, null, [], 40);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'EuropePMC', 'abstracts' => '', 'error' => $r['error']];
    
    $d = @json_decode($r['data'], true);
    $results = $d['resultList']['result'] ?? [];
    $items = []; $abstracts = [];
    foreach(array_slice($results, 0, 5) as $p) {
        $abs = $p['abstractText'] ?? '';
        $items[] = [
            'id'      => $p['pmid'] ?? $p['id'] ?? 'N/A',
            'title'   => substr($p['title'] ?? 'N/A', 0, 200),
            'journal' => $p['journalTitle'] ?? 'N/A',
            'year'    => $p['pubYear'] ?? 'N/A',
            'cited'   => $p['citedByCount'] ?? 0,
            'url'     => "https://europepmc.org/article/MED/" . ($p['pmid'] ?? $p['id'] ?? ''),
        ];
        if($abs) $abstracts[] = substr($abs, 0, ABSTRACT_MAX_CHARS);
        elseif(!empty($p['title'])) $abstracts[] = $p['title'];
    }
    return ['count' => count($results), 'items' => $items, 'source' => 'EuropePMC', 'abstracts' => implode("\n---\n", array_filter($abstracts))];
}

function genesis_openalex($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'OpenAlex', 'abstracts' => '', 'error' => 'Empty query'];
    $url = "https://api.openalex.org/works?search=" 
         . urlencode($query) . "&per-page=$max&filter=has_abstract:true&sort=cited_by_count:desc"
         . "&mailto=research@genesis.local"; // mailto OBLIGATOIRE
    $r = genesis_curl($url, null, [], 40);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'OpenAlex', 'abstracts' => '', 'error' => $r['error']];
    
    $d = @json_decode($r['data'], true);
    $results = $d['results'] ?? [];
    $items = []; $abstracts = [];
    foreach(array_slice($results, 0, 5) as $p) {
        $abs = '';
        if(!empty($p['abstract_inverted_index'])) {
            $words = [];
            foreach($p['abstract_inverted_index'] as $word => $positions) {
                foreach($positions as $pos) { $words[$pos] = $word; }
            }
            ksort($words);
            $abs = implode(' ', $words);
        }
        $items[] = [
            'id'      => $p['id'] ?? 'N/A',
            'title'   => substr($p['display_name'] ?? 'N/A', 0, 200),
            'journal' => $p['primary_location']['source']['display_name'] ?? 'N/A',
            'year'    => $p['publication_year'] ?? 'N/A',
            'cited'   => $p['cited_by_count'] ?? 0,
            'url'     => $p['primary_location']['landing_page_url'] ?? ($p['id'] ?? '#'),
        ];
        if($abs) $abstracts[] = substr($abs, 0, ABSTRACT_MAX_CHARS);
    }
    return ['count' => $d['meta']['count'] ?? count($results), 'items' => $items, 'source' => 'OpenAlex', 'abstracts' => implode("\n---\n", array_filter($abstracts))];
}

function genesis_chembl($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'ChEMBL', 'abstracts' => '', 'error' => 'Empty query'];
    $url = "https://www.ebi.ac.uk/chembl/api/data/target/search?q=" 
         . urlencode($query) . "&limit=$max&format=json";
    $r = genesis_curl($url, null, [], 40);
    if(!$r['success']) return ['count' => 0, 'items' => [], 'source' => 'ChEMBL', 'abstracts' => '', 'error' => $r['error']];
    
    $d = @json_decode($r['data'], true);
    $results = $d['targets'] ?? [];
    $items = []; $abstracts = [];
    foreach(array_slice($results, 0, 5) as $t) {
        $desc = $t['pref_name'] ?? 'N/A';
        $items[] = [
            'id'   => $t['target_chembl_id'] ?? 'N/A',
            'name' => $desc,
            'type' => $t['target_type'] ?? 'N/A',
            'url'  => "https://www.ebi.ac.uk/chembl/target_report_card/" . ($t['target_chembl_id'] ?? ''),
        ];
        $abstracts[] = "Target: $desc";
    }
    return ['count' => $d['page_meta']['total_count'] ?? count($results), 'items' => $items, 'source' => 'ChEMBL', 'abstracts' => implode("\n---\n", array_filter($abstracts))];
}

function genesis_wikidata($query, $max = 5) {
    if(empty($query)) return ['count' => 0, 'items' => [], 'source' => 'Wikidata', 'abstracts' => '', 'error' => 'Empty query'];
    // Fallback API texte (plus fiable que SPARQL strict)
    $url = "https://www.wikidata.org/w/api.php?action=wbsearchentities&search=" 
         . urlencode($query) . "&language=en&format=json&limit=$max";
    $r = genesis_curl($url, null, ['Accept: application/json'], 30);
    $items = []; $abstracts = [];
    
    if($r['success']) {
        $d = @json_decode($r['data'], true);
        $results = $d['search'] ?? [];
        foreach(array_slice($results, 0, 5) as $b) {
            $label = $b['label'] ?? 'N/A';
            $desc  = $b['description'] ?? '';
            $items[] = [
                'id'          => $b['id'] ?? 'N/A',
                'label'       => $label,
                'description' => $desc,
                'url'         => "https://www.wikidata.org/wiki/" . ($b['id'] ?? '')
            ];
            if($desc) $abstracts[] = "$label: $desc";
        }
    }
    return ['count' => count($items), 'items' => $items, 'source' => 'Wikidata', 'abstracts' => implode(", ", array_filter($abstracts))];
}

// ============================================================================
// STOCKAGE — JSONL + JSON
// ============================================================================
function genesis_save($id, $data, $folder = 'knowledge') {
    if(!is_array($data) || empty($id)) return false;
    $data['saved_at'] = time();
    $data['version']  = GENESIS_VERSION;
    $file = STORAGE_PATH . "$folder/$id.json";
    $ok   = @file_put_contents($file, @json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if($ok && $folder === 'knowledge') {
        $index_line = json_encode([
            'id'       => $id,
            'target'   => $data['target'] ?? '',
            'title'    => substr($data['title'] ?? '', 0, 100),
            'novelty'  => $data['novelty'] ?? 0.5,
            'sources'  => $data['sources'] ?? [],
            'saved_at' => time(),
        ]) . "\n";
        @file_put_contents(STORAGE_PATH . "$folder/index.jsonl", $index_line, FILE_APPEND);
    }
    return $ok !== false;
}

function genesis_load($id, $folder = 'knowledge') {
    $file = STORAGE_PATH . "$folder/$id.json";
    if(!file_exists($file)) return null;
    $d = @json_decode(@file_get_contents($file), true);
    return is_array($d) ? $d : null;
}

function genesis_list($folder = 'knowledge', $limit = 50, $page = 1) {
    $files = @glob(STORAGE_PATH . "$folder/*.json");
    if(!$files) return ['items' => [], 'total' => 0, 'pages' => 1];
    rsort($files);
    $items = [];
    foreach($files as $f) {
        if(basename($f) === 'index.json') continue;
        $d = @json_decode(@file_get_contents($f), true);
        if($d && is_array($d)) $items[] = $d;
    }
    $total  = count($items);
    $pages  = max(1, ceil($total / $limit));
    $page   = max(1, min($page, $pages));
    $start  = ($page - 1) * $limit;
    return ['items' => array_slice($items, $start, $limit), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function genesis_search($query, $folder = 'knowledge') {
    $files = @glob(STORAGE_PATH . "$folder/*.json");
    if(!$files) return [];
    $results = [];
    $query   = strtolower($query);
    foreach($files as $f) {
        if(basename($f) === 'index.json') continue;
        $d = @json_decode(@file_get_contents($f), true);
        if(!$d || !is_array($d)) continue;
        if(stripos($d['target'] ?? '', $query) !== false
            || stripos($d['title'] ?? '', $query) !== false
            || stripos($d['vulgarized'] ?? '', $query) !== false) {
            $results[] = $d;
        }
    }
    usort($results, fn($a,$b) => ($b['novelty'] ?? 0) <=> ($a['novelty'] ?? 0));
    return $results;
}

// ============================================================================
// STATISTIQUES
// ============================================================================
function genesis_get_stats() {
    $knowledge = @glob(STORAGE_PATH . 'knowledge/*.json') ?: [];
    $articles  = @glob(STORAGE_PATH . 'articles/*.json') ?: [];
    $deep      = @glob(STORAGE_PATH . 'deep_research/*.json') ?: [];
    $avg_novelty = 0;
    $sources_used = [];
    foreach($knowledge as $f) {
        if(basename($f) === 'index.json') continue;
        $d = @json_decode(@file_get_contents($f), true);
        if($d) {
            $avg_novelty += ($d['novelty'] ?? 0.5);
            foreach($d['sources'] ?? [] as $src) {
                $sources_used[$src] = ($sources_used[$src] ?? 0) + 1;
            }
        }
    }
    $count = max(1, count($knowledge) - (file_exists(STORAGE_PATH . 'knowledge/index.json') ? 1 : 0));
    return [
        'hypotheses'    => $count,
        'articles'      => count($articles),
        'deep_research' => count($deep),
        'avg_novelty'   => round($avg_novelty / $count, 2),
        'sources_used'  => $sources_used,
        'top_source'    => array_key_first(arsort_return($sources_used) ?: []),
    ];
}

function arsort_return($arr) {
    arsort($arr);
    return $arr;
}

// ============================================================================
// LOGS
// ============================================================================
function genesis_add_log(&$st, $msg, $type = 'info', $detail = null, $phase = null) {
    $entry = [
        'time'   => date('H:i:s'),
        'msg'    => is_string($msg) ? $msg : @json_encode($msg),
        'type'   => $type,
        'detail' => $detail,
        'phase'  => $phase ?? ($st['current_phase'] ?? 'core'),
    ];
    $st['logs'][] = $entry;
    if(count($st['logs']) > MAX_LOGS_IN_RAM) array_shift($st['logs']);
    $file = STORAGE_PATH . 'logs/genesis_' . date('Y-m-d') . '.jsonl';
    @file_put_contents($file, json_encode($entry + ['version' => GENESIS_VERSION]) . "\n", FILE_APPEND);
}

// ============================================================================
// EXPORTS
// ============================================================================
function genesis_export_csv($hypos = null) {
    if($hypos === null) { $list = genesis_list('knowledge', 1000); $hypos = $list['items']; }
    @header('Content-Type: text/csv; charset=utf-8');
    @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.csv"');
    $out = @fopen('php://output', 'w');
    if(!$out) return;
    @fputcsv($out, ['ID','Cible','Hypothèse','Vulgarisation','Novelty','Confiance','Sources','Statut','Date','Version']);
    foreach($hypos as $h) {
        @fputcsv($out, [
            $h['id'] ?? '', $h['target'] ?? '', $h['title'] ?? '',
            $h['vulgarized'] ?? '', $h['novelty'] ?? '', $h['validation_score'] ?? '',
            implode(';', $h['sources'] ?? []), $h['status'] ?? '',
            date('Y-m-d H:i', $h['saved_at'] ?? $h['timestamp'] ?? time()),
            $h['version'] ?? GENESIS_VERSION,
        ]);
    }
    @fclose($out); exit;
}

function genesis_export_json($hypos = null) {
    if($hypos === null) { $list = genesis_list('knowledge', 1000); $hypos = $list['items']; }
    @header('Content-Type: application/json; charset=utf-8');
    @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.json"');
    echo @json_encode(['exported' => date('c'), 'version' => GENESIS_VERSION, 'count' => count($hypos), 'hypotheses' => $hypos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function genesis_export_bibtex($hypos = null) {
    if($hypos === null) { $list = genesis_list('knowledge', 1000); $hypos = $list['items']; }
    @header('Content-Type: text/plain; charset=utf-8');
    @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.bib"');
    foreach($hypos as $h) {
        $key = preg_replace('/[^a-zA-Z0-9]/', '_', $h['id'] ?? 'unknown');
        echo "@misc{genesis_$key,\n";
        echo "  author = {GENESIS-ULTRA " . GENESIS_VERSION . " AI},\n";
        echo "  title = {{" . str_replace(['}','{'], '', $h['title'] ?? 'Hypothèse') . "}},\n";
        echo "  year = {" . date('Y', $h['saved_at'] ?? time()) . "},\n";
        echo "  note = {Target: " . ($h['target'] ?? 'N/A') . ", Novelty: " . round(($h['novelty'] ?? 0.5)*100) . "%},\n";
        echo "  howpublished = {\\url{https://genesis-ultra.science/h/" . ($h['id'] ?? '') . "}},\n";
        echo "}\n";
    }
    exit;
}

function genesis_export_ris($hypos = null) {
    if($hypos === null) { $list = genesis_list('knowledge', 1000); $hypos = $list['items']; }
    @header('Content-Type: text/plain; charset=utf-8');
    @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.ris"');
    foreach($hypos as $h) {
        echo "TY  - GEN\n";
        echo "TI  - " . str_replace("\n", ' ', $h['title'] ?? 'Hypothèse') . "\n";
        echo "DA  - " . date('Y/m/d', $h['saved_at'] ?? time()) . "\n";
        echo "KW  - " . ($h['target'] ?? 'N/A') . "\n";
        echo "AB  - " . str_replace("\n", ' ', substr($h['vulgarized'] ?? '', 0, 500)) . "\n";
        echo "N1  - Novelty: " . round(($h['novelty'] ?? 0.5)*100) . "%\n";
        echo "UR  - https://genesis-ultra.science/h/" . ($h['id'] ?? '') . "\n";
        echo "ER  - \n";
    }
    exit;
}

function genesis_export_jsonld($hypos = null) {
    if($hypos === null) { $list = genesis_list('knowledge', 1000); $hypos = $list['items']; }
    @header('Content-Type: application/ld+json; charset=utf-8');
    @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.jsonld"');
    $output = [];
    foreach($hypos as $h) {
        $output[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'ScholarlyArticle',
            'headline' => $h['title'] ?? 'Hypothèse scientifique',
            'about'    => ['@type' => 'MedicalCondition', 'name' => $h['target'] ?? 'N/A'],
            'dateCreated' => date('c', $h['saved_at'] ?? time()),
            'author'   => ['@type' => 'Organization', 'name' => 'GENESIS-ULTRA', 'url' => 'https://genesis-ultra.science'],
            'abstract' => $h['vulgarized'] ?? '',
            'keywords' => implode(', ', $h['sources'] ?? []),
            'url'      => 'https://genesis-ultra.science/h/' . ($h['id'] ?? ''),
        ];
    }
    echo @json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
}

// ============================================================================
// UTILITAIRES
// ============================================================================
function genesis_sanitize($input, $type = 'string', $max_length = 500) {
    if($input === null) return null;
    switch($type) {
        case 'int':   return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : null;
        case 'float': return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : null;
        default:
            if(!is_string($input) && !is_numeric($input)) return null;
            $input = trim(strip_tags((string)$input));
            if(preg_match('/[<>"\'\\\\;]|(--|#|\/\*|\*\/)/', $input)) return null;
            return substr($input, 0, min($max_length, 2000));
    }
}

function genesis_generate_id($prefix = 'GEN') {
    return $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

function genesis_json_out($data, $status = 200) {
    while(@ob_get_level() > 0) @ob_end_clean();
    @ob_start();
    if($status !== 200) http_response_code($status);
    @header('Content-Type: application/json; charset=utf-8');
    @header('Cache-Control: no-cache');
    echo @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================================
// SESSION
// ============================================================================
if(@session_status() === PHP_SESSION_NONE) {
    @session_start();
}
?>