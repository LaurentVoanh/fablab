<?php
require_once 'config.php';

// API unifiée pour toutes les opérations
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = getDB();
    
    switch ($action) {
        case 'getStats':
            $hypotheses = $db->query("SELECT COUNT(*) FROM hypotheses")->fetchColumn();
            $articles = $db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
            $experiments = $db->query("SELECT COUNT(*) FROM experiments")->fetchColumn();
            $aiCalls = $db->query("SELECT COUNT(*) FROM ai_logs WHERE success = 1")->fetchColumn();
            
            echo json_encode([
                'hypotheses' => (int)$hypotheses,
                'articles' => (int)$articles,
                'experiments' => (int)$experiments,
                'ai_calls' => (int)$aiCalls,
                'apis_connected' => 36
            ]);
            break;
            
        case 'getHypotheses':
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $status = $_GET['status'] ?? null;
            
            $sql = "SELECT * FROM hypotheses";
            if ($status) {
                $sql .= " WHERE status = :status";
            }
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $db->prepare($sql);
            if ($status) {
                $stmt->bindValue(':status', $status);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'getHypothesis':
            $id = (int)$_GET['id'];
            $hypothesis = $db->query("SELECT * FROM hypotheses WHERE id = $id")->fetch();
            echo json_encode($hypothesis ?: ['error' => 'Not found']);
            break;
            
        case 'getArticles':
            $limit = (int)($_GET['limit'] ?? 20);
            $articles = $db->query("SELECT * FROM articles ORDER BY created_at DESC LIMIT $limit")->fetchAll();
            echo json_encode($articles);
            break;
            
        case 'searchArticles':
            $query = sanitizeInput($_GET['q'] ?? '');
            $stmt = $db->prepare("SELECT * FROM articles WHERE title LIKE :q OR abstract LIKE :q ORDER BY relevance_score DESC LIMIT 20");
            $stmt->execute(['q' => "%$query%"]);
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'getExperiments':
            $hypothesisId = (int)($_GET['hypothesis_id'] ?? 0);
            if ($hypothesisId) {
                $stmt = $db->prepare("SELECT * FROM experiments WHERE hypothesis_id = ?");
                $stmt->execute([$hypothesisId]);
            } else {
                $experiments = $db->query("SELECT * FROM experiments ORDER BY created_at DESC LIMIT 20")->fetchAll();
            }
            echo json_encode($experiments ?? []);
            break;
            
        case 'getAILogs':
            $limit = (int)($_GET['limit'] ?? 50);
            $logs = $db->query("SELECT * FROM ai_logs ORDER BY created_at DESC LIMIT $limit")->fetchAll();
            echo json_encode($logs);
            break;
            
        case 'getStrategies':
            $strategies = $db->query("SELECT * FROM learning_strategies ORDER BY success_rate DESC")->fetchAll();
            echo json_encode($strategies);
            break;
            
        case 'updateStrategy':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $name = sanitizeInput($_POST['name']);
            $successRate = (float)$_POST['success_rate'];
            $parameters = $_POST['parameters'] ?? '';
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO learning_strategies (strategy_name, success_rate, times_used, last_optimized, parameters) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)");
            $stmt->execute([$name, $successRate, 1, $parameters]);
            
            echo json_encode(['success' => true, 'message' => 'Strategy updated']);
            break;
            
        case 'addRSSFeed':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $feedUrl = sanitizeInput($_POST['feed_url']);
            $category = sanitizeInput($_POST['category'] ?? 'general');
            
            $stmt = $db->prepare("INSERT OR IGNORE INTO rss_feeds (feed_url, category, last_crawl) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$feedUrl, $category]);
            
            echo json_encode(['success' => true, 'message' => 'RSS feed added']);
            break;
            
        case 'getRSSFeeds':
            $feeds = $db->query("SELECT * FROM rss_feeds ORDER BY category")->fetchAll();
            echo json_encode($feeds);
            break;
            
        case 'crawlRSS':
            // Simulation de crawl RSS (à implémenter avec SimplePie ou similaire)
            $feedId = (int)$_GET['feed_id'];
            $feed = $db->query("SELECT * FROM rss_feeds WHERE id = $feedId")->fetch();
            
            if (!$feed) {
                throw new Exception('Feed not found');
            }
            
            // Ici, on appellerait Mistral pour traiter les articles
            echo json_encode([
                'success' => true,
                'message' => 'RSS crawl initiated',
                'feed' => $feed
            ]);
            break;
            
        case 'generateExperiment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            $hypothesisId = (int)$_POST['hypothesis_id'];
            $title = sanitizeInput($_POST['title']);
            $protocol = $_POST['protocol'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO experiments (hypothesis_id, title, protocol, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$hypothesisId, $title, $protocol]);
            
            echo json_encode(['success' => true, 'experiment_id' => $db->lastInsertId()]);
            break;
            
        case 'testMistral':
            // Test de connexion à Mistral AI
            $response = callMistralAI("Réponds simplement 'OK' si tu reçois ce message.", "Test de connexion");
            echo json_encode(['success' => true, 'response' => $response]);
            break;
            
        default:
            echo json_encode(['error' => 'Action not found', 'available_actions' => [
                'getStats', 'getHypotheses', 'getHypothesis', 'getArticles', 
                'searchArticles', 'getExperiments', 'getAILogs', 'getStrategies',
                'updateStrategy', 'addRSSFeed', 'getRSSFeeds', 'crawlRSS',
                'generateExperiment', 'testMistral'
            ]]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
