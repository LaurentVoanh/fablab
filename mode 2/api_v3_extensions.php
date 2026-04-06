<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', '0');

/**
 * Extensions API V3 pour GENESIS-ULTRA
 * Nouveaux endpoints pour la conscience de l'IA, validation, et rapports
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config_v3_ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

db();

function send(array $d): void
{
    while (ob_get_level()) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if (!$action) throw new Exception('Missing action');

    switch ($action) {

        // ── OPTIMISATION DE REQUÊTE PAR L'IA ────────────────────────────
        case 'optimize_query':
            $source = $_POST['source'] ?? '';
            $topic = $_POST['topic'] ?? '';
            $previous_term = $_POST['previous_term'] ?? '';
            $previous_hits = (int)($_POST['previous_hits'] ?? 0);
            $success_rate = (float)($_POST['success_rate'] ?? 0.0);

            if (!$source || !$topic) throw new Exception('Missing source or topic');

            app_log("Optimizing query for {$source} with term: {$previous_term}", 'optimize');

            $prompt = str_replace(
                ['{SOURCE}', '{TOPIC}', '{PREVIOUS_TERM}', '{PREVIOUS_HITS}', '{SUCCESS_RATE}'],
                [$source, $topic, $previous_term, $previous_hits, $success_rate],
                PROMPT_QUERY_OPTIMIZER
            );

            $raw = mistral($prompt, 'Expert en optimisation de requêtes scientifiques.', 1000);
            $optimization = @json_decode(trim($raw), true);

            if (!is_array($optimization)) {
                throw new Exception('Failed to parse optimization response');
            }

            // Stocker la stratégie optimisée
            $stmt = db()->prepare(
                "INSERT INTO query_strategies (source, topic_type, optimized_term_template, effectiveness_score, created_at) 
                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );
            $stmt->execute([$source, 'general', $optimization['optimized_term'] ?? '', 0.0]);

            send(['success' => true, 'data' => $optimization]);

        // ── VALIDATION D'ARTICLE ────────────────────────────────────────
        case 'validate_article':
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            app_log("Validating article {$article_id}", 'validate');

            // Récupérer les sources utilisées
            $stmt2 = db()->prepare(
                "SELECT COUNT(*) as cnt FROM findings WHERE session_id = ?"
            );
            $stmt2->execute([$article['session_id']]);
            $sources_count = $stmt2->fetch()['cnt'];

            $prompt = str_replace(
                ['{ARTICLE_CONTENT}', '{SOURCES_COUNT}', '{TOTAL_HITS}'],
                [$article['summary'] ?? '', $sources_count, $article['total_hits'] ?? 0],
                PROMPT_VALIDATION_CHECKER
            );

            $raw = mistral($prompt, 'Validateur scientifique rigoureux.', 2000);
            $validation = @json_decode(trim($raw), true);

            if (!is_array($validation)) {
                throw new Exception('Failed to parse validation response');
            }

            // Stocker les résultats de validation
            $stmt3 = db()->prepare(
                "UPDATE articles SET validation_score = ?, contradiction_feedback = ? WHERE id = ?"
            );
            $stmt3->execute([
                $validation['validation_score'] ?? 0.0,
                json_encode($validation['coherence_issues'] ?? []),
                $article_id
            ]);

            send(['success' => true, 'data' => $validation]);

        // ── DÉTECTION DE CONTRADICTIONS ──────────────────────────────────
        case 'detect_contradictions':
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT session_id FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            app_log("Detecting contradictions in article {$article_id}", 'contradictions');

            // Récupérer tous les findings
            $stmt2 = db()->prepare(
                "SELECT source, title, abstract FROM findings WHERE session_id = ? LIMIT 50"
            );
            $stmt2->execute([$article['session_id']]);
            $findings = $stmt2->fetchAll();

            $findings_json = json_encode($findings);

            $prompt = str_replace(
                '{FINDINGS_JSON}',
                $findings_json,
                PROMPT_CONTRADICTION_DETECTOR
            );

            $raw = mistral($prompt, 'Détecteur de contradictions scientifiques.', 2000);
            $contradictions = @json_decode(trim($raw), true);

            if (!is_array($contradictions)) {
                throw new Exception('Failed to parse contradiction detection response');
            }

            send(['success' => true, 'data' => $contradictions]);

        // ── RECHERCHE APPROFONDIE D'UNE SECTION ──────────────────────────
        case 'deep_research_section':
            $article_id = (int)($_POST['article_id'] ?? 0);
            $section_title = $_POST['section_title'] ?? '';
            if ($article_id <= 0 || !$section_title) throw new Exception('Missing article_id or section_title');

            app_log("Deep research for section: {$section_title} in article {$article_id}", 'deep_section');

            $stmt = db()->prepare("SELECT topic, session_id FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            // Récupérer ou créer la section
            $stmt2 = db()->prepare(
                "SELECT * FROM article_sections WHERE article_id = ? AND section_title = ?"
            );
            $stmt2->execute([$article_id, $section_title]);
            $section = $stmt2->fetch();

            if (!$section) {
                $stmt3 = db()->prepare(
                    "INSERT INTO article_sections (article_id, section_title, deep_research_status) 
                     VALUES (?, ?, 'running')"
                );
                $stmt3->execute([$article_id, $section_title]);
                $section_id = db()->lastInsertId();
            } else {
                $section_id = $section['id'];
                // Mettre à jour le statut
                $stmt3 = db()->prepare("UPDATE article_sections SET deep_research_status = 'running' WHERE id = ?");
                $stmt3->execute([$section_id]);
            }

            // Simuler une recherche approfondie (en production, on relancerait les requêtes)
            $prompt = str_replace(
                ['{SECTION_TITLE}', '{SECTION_CONTENT}', '{TOPIC}', '{NEW_FINDINGS}'],
                [$section_title, $section['content_scientific'] ?? '', $article['topic'], '[]'],
                PROMPT_DEEP_RESEARCH_SECTION
            );

            $raw = mistral($prompt, 'Chercheur scientifique approfondi.', 3000);
            $enhanced = @json_decode(trim($raw), true);

            if (!is_array($enhanced)) {
                throw new Exception('Failed to parse deep research response');
            }

            // Mettre à jour la section
            $stmt4 = db()->prepare(
                "UPDATE article_sections SET content_scientific = ?, content_vulgarized = ?, deep_research_status = 'completed', last_updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $stmt4->execute([
                $enhanced['enhanced_content_scientific'] ?? '',
                $enhanced['enhanced_content_vulgarized'] ?? '',
                $section_id
            ]);

            send(['success' => true, 'data' => ['section_id' => $section_id, 'enhanced' => $enhanced]]);

        // ── GÉNÉRATION DE RAPPORT MÉDECIN ────────────────────────────────
        case 'generate_report_medecin':
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            app_log("Generating medical report for article {$article_id}", 'report_medecin');

            $prompt = str_replace(
                ['{ARTICLE_CONTENT}', '{TOPIC}'],
                [$article['full_content_vulgarized'] ?? $article['summary'] ?? '', $article['topic']],
                PROMPT_REPORT_MEDECIN_TRAITANT
            );

            $report_content = mistral($prompt, 'Rédacteur de rapports médicaux pour praticiens.', 3000);

            // Stocker le rapport
            $stmt2 = db()->prepare(
                "INSERT INTO reports (article_id, report_type, content, generated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            );
            $stmt2->execute([$article_id, 'medecin_traitant', $report_content]);

            send(['success' => true, 'data' => ['report_id' => db()->lastInsertId(), 'content' => $report_content]]);

        // ── GÉNÉRATION DE RAPPORT LABO ───────────────────────────────────
        case 'generate_report_labo':
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            app_log("Generating lab report for article {$article_id}", 'report_labo');

            $prompt = str_replace(
                ['{ARTICLE_CONTENT}', '{TOPIC}'],
                [$article['full_content_scientific'] ?? $article['summary'] ?? '', $article['topic']],
                PROMPT_REPORT_LABO_RECHERCHE
            );

            $report_content = mistral($prompt, 'Rédacteur de rapports techniques pour laboratoires.', 3000);

            // Stocker le rapport
            $stmt2 = db()->prepare(
                "INSERT INTO reports (article_id, report_type, content, generated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            );
            $stmt2->execute([$article_id, 'labo_recherche', $report_content]);

            send(['success' => true, 'data' => ['report_id' => db()->lastInsertId(), 'content' => $report_content]]);

        // ── GÉNÉRATION DE RAPPORT SCIENTIFIQUE ───────────────────────────
        case 'generate_report_scientifique':
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch();
            if (!$article) throw new Exception("Article {$article_id} not found");

            app_log("Generating scientific article report for article {$article_id}", 'report_scientifique');

            $prompt = str_replace(
                ['{ARTICLE_CONTENT}', '{TOPIC}'],
                [$article['full_content_scientific'] ?? $article['summary'] ?? '', $article['topic']],
                PROMPT_REPORT_ARTICLE_SCIENTIFIQUE
            );

            $report_content = mistral($prompt, 'Rédacteur d\'articles scientifiques pour publication.', 3000);

            // Stocker le rapport
            $stmt2 = db()->prepare(
                "INSERT INTO reports (article_id, report_type, content, generated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
            );
            $stmt2->execute([$article_id, 'article_scientifique', $report_content]);

            send(['success' => true, 'data' => ['report_id' => db()->lastInsertId(), 'content' => $report_content]]);

        // ── RÉCUPÉRATION DES RAPPORTS ────────────────────────────────────
        case 'get_reports':
            $article_id = (int)($_GET['article_id'] ?? 0);
            if ($article_id <= 0) throw new Exception('Invalid article_id');

            $stmt = db()->prepare("SELECT * FROM reports WHERE article_id = ? ORDER BY generated_at DESC");
            $stmt->execute([$article_id]);
            $reports = $stmt->fetchAll();

            send(['success' => true, 'data' => $reports]);

        default:
            throw new Exception("Unknown action: {$action}");
    }

} catch (Exception $e) {
    send(['success' => false, 'error' => $e->getMessage()]);
}

?>
