<?php
/**
 * SCIENCE HUB ULTIMATE - Configuration Centrale
 * Compatible Hostinger + Mistral AI + SQLite
 */

// Configuration de la base de données SQLite
define('DB_PATH', __DIR__ . '/data/science_hub.db');
define('DB_JOURNAL', __DIR__ . '/data/science_hub.db-journal');

// Configuration Mistral AI
define('MISTRAL_API_KEYS', [
    'key1' => getenv('MISTRAL_API_KEY_1') ?: 'votre_cle_1',
    'key2' => getenv('MISTRAL_API_KEY_2') ?: 'votre_cle_2',
    'key3' => getenv('MISTRAL_API_KEY_3') ?: 'votre_cle_3'
]);
define('MISTRAL_MODELS', ['mistral-large-latest', 'mistral-medium', 'open-mistral-7b']);
define('MISTRAL_TIMEOUT', 60);
define('MISTRAL_MAX_TOKENS', 4096);

// APIs Scientifiques
define('SCIENCE_APIS', [
    'arxiv' => 'http://export.arxiv.org/api/query',
    'pubmed' => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi',
    'doi' => 'https://api.crossref.org/works',
    'semantic_scholar' => 'https://api.semanticscholar.org/graph/v1/paper/search'
]);

// Paramètres généraux
define('SITE_NAME', 'Science Hub Ultimate');
define('SITE_VERSION', '1.0.0');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('LOG_PATH', __DIR__ . '/logs/');

// Initialisation de la session
session_start();

// Fonction de connexion à SQLite
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            error_log("Erreur DB: " . $e->getMessage());
            die("Erreur de connexion à la base de données");
        }
    }
    return $db;
}

// Initialisation du schema de la base de données
function initDatabase($db) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            email TEXT UNIQUE,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS hypotheses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            domain TEXT,
            confidence_score REAL DEFAULT 0.0,
            status TEXT DEFAULT 'draft',
            generated_by_ai INTEGER DEFAULT 1,
            workflow_mode TEXT DEFAULT 'autonomous',
            steps_completed INTEGER DEFAULT 0,
            total_steps INTEGER DEFAULT 9,
            sources_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            abstract TEXT,
            source TEXT,
            doi TEXT,
            url TEXT,
            published_date DATE,
            relevance_score REAL DEFAULT 0.0,
            processed_by_ai INTEGER DEFAULT 1,
            full_content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS experiments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hypothesis_id INTEGER,
            title TEXT NOT NULL,
            protocol TEXT,
            code_php TEXT,
            code_js TEXT,
            status TEXT DEFAULT 'pending',
            results TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (hypothesis_id) REFERENCES hypotheses(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS ai_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            request_type TEXT,
            model_used TEXT,
            prompt TEXT,
            response TEXT,
            tokens_used INTEGER,
            api_key_index INTEGER,
            execution_time REAL,
            success INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS learning_strategies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            strategy_name TEXT UNIQUE,
            success_rate REAL DEFAULT 0.0,
            times_used INTEGER DEFAULT 0,
            last_optimized DATETIME,
            parameters TEXT
        )",
        
        "CREATE TABLE IF NOT EXISTS rss_feeds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feed_url TEXT UNIQUE,
            category TEXT,
            last_crawl DATETIME,
            articles_imported INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1
        )"
    ];
    
    foreach ($tables as $table) {
        $db->exec($table);
    }
}

// Fonction pour appeler Mistral AI avec rotation de clés
function callMistralAI($prompt, $systemMessage = '', $model = null) {
    $model = $model ?: MISTRAL_MODELS[0];
    $keys = array_values(MISTRAL_API_KEYS);
    $response = null;
    $lastError = null;
    
    foreach ($keys as $index => $apiKey) {
        if (empty($apiKey) || $apiKey === 'votre_cle_' . ($index + 1)) {
            continue;
        }
        
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage ?: 'Tu es un assistant scientifique expert capable de générer des hypothèses innovantes et de conduire des recherches multistep.']
            ],
            'max_tokens' => MISTRAL_MAX_TOKENS,
            'temperature' => 0.7
        ];
        
        if (!empty($prompt)) {
            $payload['messages'][] = ['role' => 'user', 'content' => $prompt];
        }
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => MISTRAL_TIMEOUT
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $result) {
            $data = json_decode($result, true);
            if (isset($data['choices'][0]['message']['content'])) {
                // Logger le succès
                logAIRequest('chat', $model, $prompt, $data['choices'][0]['message']['content'], 
                           $data['usage']['total_tokens'] ?? 0, $index, 0, 1);
                return $data['choices'][0]['message']['content'];
            }
        }
        
        $lastError = $error ?: "HTTP $httpCode";
        logAIRequest('chat', $model, $prompt, $lastError, 0, $index, 0, 0);
    }
    
    throw new Exception("Échec de tous les appels API Mistral. Dernière erreur: $lastError");
}

// Fonction de logging AI
function logAIRequest($type, $model, $prompt, $response, $tokens, $keyIndex, $time, $success) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO ai_logs (request_type, model_used, prompt, response, tokens_used, api_key_index, execution_time, success) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $model, substr($prompt, 0, 1000), substr($response, 0, 1000), $tokens, $keyIndex, $time, $success]);
    } catch (Exception $e) {
        error_log("Erreur logging AI: " . $e->getMessage());
    }
}

// Helper pour les réponses JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Vérification de sécurité
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

?>
