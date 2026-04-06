<?php
declare(strict_types=1);

/**
 * Moteur de Conscience et d'Auto-Correction pour GENESIS-ULTRA V3
 * Gère l'apprentissage de l'IA, le scoring des requêtes, et l'optimisation progressive
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config_v3_ai.php';

class ConsciousnessEngine
{
    private PDO $db;
    private array $source_config;

    public function __construct(PDO $db, array $source_config)
    {
        $this->db = $db;
        $this->source_config = $source_config;
    }

    /**
     * Évalue et score une requête basée sur ses résultats
     */
    public function scoreQuery(int $query_id): float
    {
        $stmt = $this->db->prepare(
            "SELECT source, http_code, duration_ms, hits FROM queries WHERE id = ?"
        );
        $stmt->execute([$query_id]);
        $query = $stmt->fetch();

        if (!$query) {
            return 0.0;
        }

        $score = 0.0;

        // 1. Score basé sur le code HTTP (0.3 de poids)
        if ($query['http_code'] >= 200 && $query['http_code'] < 300) {
            $score += 0.3;
        } elseif ($query['http_code'] >= 400 && $query['http_code'] < 500) {
            $score += 0.05; // Faible score pour les erreurs client
        } else {
            $score += 0.0; // Pas de score pour les erreurs serveur
        }

        // 2. Score basé sur le nombre de résultats (0.4 de poids)
        // Normaliser entre 0 et 1, avec un maximum de 50 hits considéré comme optimal
        $hits_score = min(1.0, $query['hits'] / 50.0);
        $score += $hits_score * 0.4;

        // 3. Score basé sur la durée (0.2 de poids)
        // Pénaliser les requêtes très lentes (> 5000ms)
        if ($query['duration_ms'] > 5000) {
            $score += 0.0;
        } elseif ($query['duration_ms'] > 2000) {
            $score += 0.1;
        } else {
            $score += 0.2;
        }

        // 4. Bonus pour les sources spécialisées qui répondent bien
        $specialized_sources = ['UniProt', 'Ensembl', 'PDB', 'ChEMBL', 'PubMed'];
        if (in_array($query['source'], $specialized_sources) && $query['hits'] > 0) {
            $score += 0.1; // Bonus de 0.1
        }

        // Clamper le score entre 0 et 1
        return min(1.0, max(0.0, $score));
    }

    /**
     * Calcule le taux de réussite global pour une source sur les dernières requêtes
     */
    public function getSourceSuccessRate(string $source, int $limit = 20): float
    {
        $stmt = $this->db->prepare(
            "SELECT AVG(CASE WHEN http_code >= 200 AND http_code < 300 THEN 1 ELSE 0 END) as success_rate
             FROM queries WHERE source = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$source, $limit]);
        $result = $stmt->fetch();

        return $result['success_rate'] ?? 0.0;
    }

    /**
     * Génère une requête optimisée pour une source basée sur l'historique
     */
    public function generateOptimizedQuery(string $source, string $topic, string $previous_term = ''): array
    {
        // Récupérer les stratégies existantes pour cette source
        $stmt = $this->db->prepare(
            "SELECT * FROM query_strategies WHERE source = ? ORDER BY effectiveness_score DESC LIMIT 1"
        );
        $stmt->execute([$source]);
        $strategy = $stmt->fetch();

        // Si une bonne stratégie existe, l'utiliser comme base
        if ($strategy && $strategy['effectiveness_score'] > 0.6) {
            return [
                'optimized_term' => $strategy['optimized_term_template'],
                'source' => 'strategy',
                'confidence' => $strategy['effectiveness_score'],
            ];
        }

        // Sinon, demander à Mistral d'optimiser
        $success_rate = $this->getSourceSuccessRate($source);
        $prompt = str_replace(
            ['{SOURCE}', '{TOPIC}', '{PREVIOUS_TERM}', '{PREVIOUS_HITS}', '{SUCCESS_RATE}'],
            [$source, $topic, $previous_term, 0, $success_rate * 100],
            PROMPT_QUERY_OPTIMIZER
        );

        $raw = mistral($prompt, 'Expert en optimisation de requêtes scientifiques.', 1000);
        $optimization = @json_decode(trim($raw), true);

        if (!is_array($optimization)) {
            return [
                'optimized_term' => $previous_term,
                'source' => 'fallback',
                'confidence' => 0.3,
            ];
        }

        // Stocker la nouvelle stratégie
        $stmt2 = $this->db->prepare(
            "INSERT INTO query_strategies (source, topic_type, optimized_term_template, effectiveness_score, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt2->execute([$source, 'general', $optimization['optimized_term'] ?? '', 0.5]);

        return [
            'optimized_term' => $optimization['optimized_term'] ?? $previous_term,
            'source' => 'ai_generated',
            'confidence' => 0.7,
            'reasoning' => $optimization['reasoning'] ?? '',
        ];
    }

    /**
     * Analyse les findings et évalue leur pertinence pour le sujet
     */
    public function evaluateFindingsRelevance(string $session_id, string $topic): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, source, title, abstract FROM findings WHERE session_id = ? LIMIT 100"
        );
        $stmt->execute([$session_id]);
        $findings = $stmt->fetchAll();

        $findings_json = json_encode($findings);

        $prompt = str_replace(
            ['{TOPIC}', '{SOURCE_NAME}', '{SOURCE_FINDINGS}'],
            [$topic, 'mixed', $findings_json],
            PROMPT_SOURCE_RELEVANCE_EVALUATOR
        );

        $raw = mistral($prompt, 'Évaluateur de pertinence des sources scientifiques.', 1500);
        $evaluation = @json_decode(trim($raw), true);

        if (!is_array($evaluation)) {
            return ['error' => 'Failed to parse evaluation'];
        }

        return $evaluation;
    }

    /**
     * Évalue la qualité globale d'une session de recherche
     */
    public function evaluateSessionQuality(string $session_id): array
    {
        // Récupérer les statistiques de la session
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as query_count, 
                    SUM(CASE WHEN http_code >= 200 AND http_code < 300 THEN 1 ELSE 0 END) as successful_queries,
                    SUM(hits) as total_hits,
                    AVG(duration_ms) as avg_duration
             FROM queries WHERE session_id = ?"
        );
        $stmt->execute([$session_id]);
        $stats = $stmt->fetch();

        $success_rate = $stats['query_count'] > 0 ? $stats['successful_queries'] / $stats['query_count'] : 0.0;

        return [
            'query_count' => $stats['query_count'],
            'successful_queries' => $stats['successful_queries'],
            'success_rate' => $success_rate,
            'total_hits' => $stats['total_hits'],
            'avg_duration_ms' => $stats['avg_duration'],
            'quality_score' => $this->calculateSessionQualityScore($stats, $success_rate),
        ];
    }

    /**
     * Calcule un score de qualité global pour une session
     */
    private function calculateSessionQualityScore(array $stats, float $success_rate): float
    {
        $score = 0.0;

        // 1. Score basé sur le taux de réussite (0.4 de poids)
        $score += $success_rate * 0.4;

        // 2. Score basé sur le nombre de résultats (0.3 de poids)
        $hits_score = min(1.0, $stats['total_hits'] / 100.0);
        $score += $hits_score * 0.3;

        // 3. Score basé sur l'efficacité temporelle (0.3 de poids)
        if ($stats['avg_duration'] > 5000) {
            $score += 0.0;
        } elseif ($stats['avg_duration'] > 2000) {
            $score += 0.15;
        } else {
            $score += 0.3;
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Recommande des améliorations pour la prochaine session
     */
    public function recommendImprovements(string $session_id): array
    {
        $quality = $this->evaluateSessionQuality($session_id);
        $recommendations = [];

        if ($quality['success_rate'] < 0.5) {
            $recommendations[] = [
                'issue' => 'Taux de réussite faible',
                'recommendation' => 'Optimiser les termes de recherche pour les sources qui échouent',
                'priority' => 'high',
            ];
        }

        if ($quality['total_hits'] < 20) {
            $recommendations[] = [
                'issue' => 'Peu de résultats trouvés',
                'recommendation' => 'Élargir les critères de recherche ou utiliser des termes plus génériques',
                'priority' => 'high',
            ];
        }

        if ($quality['avg_duration_ms'] > 3000) {
            $recommendations[] = [
                'issue' => 'Requêtes lentes',
                'recommendation' => 'Certaines sources sont lentes, considérer les ignorer ou les interroger en dernier',
                'priority' => 'medium',
            ];
        }

        // Recommandations spécifiques par source
        $stmt = $this->db->prepare(
            "SELECT source, COUNT(*) as count, 
                    SUM(CASE WHEN http_code >= 200 AND http_code < 300 THEN 1 ELSE 0 END) as successful
             FROM queries WHERE session_id = ? GROUP BY source"
        );
        $stmt->execute([$session_id]);
        $sources = $stmt->fetchAll();

        foreach ($sources as $source) {
            $source_success_rate = $source['successful'] / $source['count'];
            if ($source_success_rate < 0.3) {
                $recommendations[] = [
                    'issue' => "Source {$source['source']} a un faible taux de réussite",
                    'recommendation' => "Optimiser les requêtes pour {$source['source']}",
                    'priority' => 'medium',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Applique les améliorations recommandées pour la prochaine session
     */
    public function applyLessonsLearned(string $new_session_id, string $previous_session_id): void
    {
        $recommendations = $this->recommendImprovements($previous_session_id);

        // Stocker les recommandations pour cette nouvelle session
        foreach ($recommendations as $rec) {
            app_log("Applying lesson: {$rec['recommendation']}", 'consciousness');
        }
    }
}

/**
 * Fonction utilitaire pour créer une instance du moteur de conscience
 */
function get_consciousness_engine(): ConsciousnessEngine
{
    return new ConsciousnessEngine(db(), SOURCES_CONFIG);
}

?>
