<?php
require_once 'config.php';

// Mode autonome GENESIS-ULTRA v9.1 - 9 étapes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    
    if ($action === 'start_autonomous') {
        $domain = sanitizeInput($_POST['domain'] ?? 'sciences generales');
        $topic = sanitizeInput($_POST['topic'] ?? '');
        
        try {
            // Étape 1: Génération d'hypothèse initiale
            $step1Prompt = "Tu es GENESIS-ULTRA v9.1, un agent de recherche scientifique autonome.
            
Domaine: $domain
Sujet: $topic

ÉTAPE 1/9: Génère une hypothèse scientifique révolutionnaire et testable dans ce domaine.
L'hypothèse doit être:
- Innovante et non triviale
- Falsifiable et testable expérimentalement
- Basée sur des principes scientifiques solides
- Potentiellement disruptive pour le domaine

Format JSON attendu:
{
  \"hypothesis\": \"...\",
  \"rationale\": \"...\",
  \"novelty_score\": 0-10,
  \"testability_score\": 0-10,
  \"potential_impact\": \"...\" 
}";

            $step1Response = callMistralAI($step1Prompt, "Expert en génération d'hypothèses scientifiques révolutionnaires.");
            $hypothesisData = json_decode($step1Response, true) ?? ['hypothesis' => $step1Response];
            
            // Sauvegarder l'hypothèse
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO hypotheses (title, description, domain, confidence_score, status, workflow_mode, steps_completed, total_steps) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $hypothesisData['hypothesis'] ?? 'Hypothèse générée',
                $hypothesisData['rationale'] ?? '',
                $domain,
                ($hypothesisData['novelty_score'] ?? 5 + $hypothesisData['testability_score'] ?? 5) / 20,
                'in_progress',
                'autonomous',
                1,
                9
            ]);
            $hypothesisId = $db->lastInsertId();
            
            jsonResponse([
                'success' => true,
                'hypothesis_id' => $hypothesisId,
                'step' => 1,
                'data' => $hypothesisData,
                'message' => 'Étape 1/9 complétée: Hypothèse générée'
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    if ($action === 'continue_workflow') {
        $hypothesisId = (int)$_POST['hypothesis_id'];
        $currentStep = (int)$_POST['current_step'];
        
        try {
            $db = getDB();
            $hypothesis = $db->query("SELECT * FROM hypotheses WHERE id = $hypothesisId")->fetch();
            
            if (!$hypothesis) {
                throw new Exception("Hypothèse non trouvée");
            }
            
            $nextStep = $currentStep + 1;
            $response = null;
            $stepData = [];
            
            // Workflow en 9 étapes
            switch ($nextStep) {
                case 2:
                    // Recherche bibliographique
                    $prompt = "ÉTAPE 2/9: Pour l'hypothèse suivante, identifie 10-15 articles scientifiques pertinents.
                    
Hypothèse: {$hypothesis['title']}
Domaine: {$hypothesis['domain']}

Pour chaque article, fournis:
- Titre
- Résumé court
- DOI ou URL
- Pertinence (0-10)
- Année de publication

Format JSON array.";
                    $response = callMistralAI($prompt, "Expert en revue bibliographique scientifique.");
                    $stepData['articles'] = json_decode($response, true) ?? [];
                    $sourcesCount = count($stepData['articles']);
                    break;
                    
                case 3:
                    // Analyse critique
                    $prompt = "ÉTAPE 3/9: Analyse critique de l'hypothèse et des sources.
                    
Hypothèse: {$hypothesis['title']}

Fournis:
- Points forts de l'hypothèse
- Faiblesses potentielles
- Contre-arguments possibles
- Biais identifiés
- Recommandations d'amélioration

Format JSON structuré.";
                    $response = callMistralAI($prompt, "Expert en analyse critique scientifique.");
                    $stepData['analysis'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 4:
                    // Conception expérimentale
                    $prompt = "ÉTAPE 4/9: Conçois un protocole expérimental complet pour tester l'hypothèse.
                    
Hypothèse: {$hypothesis['title']}

Inclus:
- Objectifs expérimentaux
- Matériel nécessaire
- Protocole étape par étape
- Contrôles requis
- Méthodes d'analyse
- Critères de succès

Format JSON détaillé.";
                    $response = callMistralAI($prompt, "Expert en méthodologie expérimentale.");
                    $stepData['protocol'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 5:
                    // Simulation prédictive
                    $prompt = "ÉTAPE 5/9: Génère des prédictions quantitatives basées sur l'hypothèse.
                    
Hypothèse: {$hypothesis['title']}

Fournis:
- Prédictions principales avec valeurs numériques
- Intervalles de confiance
- Scénarios alternatifs
- Signatures expérimentales attendues

Format JSON avec données structurées.";
                    $response = callMistralAI($prompt, "Expert en modélisation prédictive.");
                    $stepData['predictions'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 6:
                    // Validation croisée
                    $prompt = "ÉTAPE 6/9: Validation croisée avec les connaissances actuelles.
                    
Hypothèse: {$hypothesis['title']}

Vérifie:
- Compatibilité avec théories établies
- Incohérences potentielles
- Domaines d'application
- Limites de validité

Format JSON analytique.";
                    $response = callMistralAI($prompt, "Expert en validation scientifique.");
                    $stepData['validation'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 7:
                    // Optimisation
                    $prompt = "ÉTAPE 7/9: Optimise l'hypothèse et le protocole.
                    
Hypothèse: {$hypothesis['title']}

Propose:
- Version optimisée de l'hypothèse
- Améliorations du protocole
- Réduction des coûts/temps
- Augmentation de la précision

Format JSON avec comparaisons avant/après.";
                    $response = callMistralAI($prompt, "Expert en optimisation de recherche.");
                    $stepData['optimization'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 8:
                    // Génération de code
                    $prompt = "ÉTAPE 8/9: Génère du code PHP/JS pour une simulation interactive de l'expérience.
                    
Hypothèse: {$hypothesis['title']}
Protocole: " . json_encode($hypothesis['description'] ?? '') . "

Fournis:
- Code PHP pour backend
- Code JS pour visualisation
- Interface utilisateur simple
- Paramètres ajustables

Format JSON avec champs 'php_code' et 'js_code'.";
                    $response = callMistralAI($prompt, "Expert en développement scientifique.");
                    $stepData['code'] = json_decode($response, true);
                    $sourcesCount = $hypothesis['sources_count'];
                    break;
                    
                case 9:
                    // Rapport final
                    $prompt = "ÉTAPE 9/9: Génère un rapport scientifique complet.
                    
Hypothèse: {$hypothesis['title']}
Domaine: {$hypothesis['domain']}

Inclus:
- Résumé exécutif
- Contexte scientifique
- Méthodologie
- Résultats attendus
- Implications
- Recommandations
- Références

Format Markdown structuré.";
                    $response = callMistralAI($prompt, "Rédacteur scientifique expert.");
                    $stepData['report'] = $response;
                    $sourcesCount = $hypothesis['sources_count'];
                    
                    // Mettre à jour le statut
                    $update = $db->prepare("UPDATE hypotheses SET status = 'completed', steps_completed = ? WHERE id = ?");
                    $update->execute([9, $hypothesisId]);
                    break;
                    
                default:
                    throw new Exception("Étape invalide");
            }
            
            // Mettre à jour la progression
            if ($nextStep < 9) {
                $update = $db->prepare("UPDATE hypotheses SET steps_completed = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$nextStep, $hypothesisId]);
            }
            
            jsonResponse([
                'success' => true,
                'step' => $nextStep,
                'data' => $stepData,
                'message' => "Étape $nextStep/9 complétée"
            ]);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Autonome - GENESIS-ULTRA v9.1</title>
    <style>
        :root {
            --primary: #2563eb;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, #0f172a 100%);
            color: var(--light);
            min-height: 100vh;
            padding-top: 80px;
        }
        
        nav {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        input, textarea, select {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.5);
            color: var(--light);
            font-size: 1rem;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
        }
        
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.4);
        }
        
        .progress-container {
            margin: 2rem 0;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transition: width 0.5s ease;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .step-indicator {
            padding: 0.8rem;
            text-align: center;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            font-size: 0.9rem;
        }
        
        .step-indicator.active {
            background: var(--primary);
            color: white;
        }
        
        .step-indicator.completed {
            background: var(--success);
            color: white;
        }
        
        .results-area {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            min-height: 300px;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-y: auto;
            max-height: 600px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            border: 4px solid rgba(255,255,255,0.1);
            border-top: 4px solid var(--accent);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <a href="index.php" class="logo">🔬 Science Hub Ultimate</a>
            <div style="color: var(--accent);">Mode Autonome - 9 Étapes</div>
        </div>
    </nav>

    <div class="container">
        <h1>🤖 GENESIS-ULTRA v9.1 - Mode Autonome</h1>
        <p style="opacity: 0.8; margin-bottom: 2rem;">Agent de recherche scientifique autonome en 9 étapes</p>

        <div class="form-card" id="initForm">
            <form id="autonomousForm">
                <div class="form-group">
                    <label for="domain">Domaine Scientifique</label>
                    <select id="domain" name="domain" required>
                        <option value="physique">Physique</option>
                        <option value="biologie">Biologie</option>
                        <option value="chimie">Chimie</option>
                        <option value="neurosciences">Neurosciences</option>
                        <option value="ia">Intelligence Artificielle</option>
                        <option value="climat">Science du Climat</option>
                        <option value="medecine">Médecine</option>
                        <option value="sciences_generales">Sciences Générales</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="topic">Sujet Spécifique (optionnel)</label>
                    <textarea id="topic" name="topic" placeholder="Décrivez un sujet ou une question de recherche spécifique..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">🚀 Lancer la Recherche Autonome</button>
            </form>
        </div>

        <div id="workflowArea" style="display: none;">
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: 11%"></div>
                </div>
                <div class="steps-grid" id="stepsGrid">
                    <div class="step-indicator active">1</div>
                    <div class="step-indicator">2</div>
                    <div class="step-indicator">3</div>
                    <div class="step-indicator">4</div>
                    <div class="step-indicator">5</div>
                    <div class="step-indicator">6</div>
                    <div class="step-indicator">7</div>
                    <div class="step-indicator">8</div>
                    <div class="step-indicator">9</div>
                </div>
                <div style="text-align: center; margin-top: 1rem; color: var(--accent);" id="stepStatus">
                    Étape 1/9: Génération d'hypothèse
                </div>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <div id="loadingText">Traitement en cours...</div>
            </div>

            <div class="results-area" id="results"></div>

            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn btn-primary" id="continueBtn" style="display: none;" onclick="continueWorkflow()">
                    Continuer vers l'étape suivante →
                </button>
            </div>
        </div>
    </div>

    <script>
        let hypothesisId = null;
        let currentStep = 0;
        const stepNames = [
            'Génération d\'hypothèse',
            'Recherche bibliographique',
            'Analyse critique',
            'Conception expérimentale',
            'Simulation prédictive',
            'Validation croisée',
            'Optimisation',
            'Génération de code',
            'Rapport final'
        ];

        document.getElementById('autonomousForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const domain = document.getElementById('domain').value;
            const topic = document.getElementById('topic').value;
            
            document.getElementById('initForm').style.display = 'none';
            document.getElementById('workflowArea').style.display = 'block';
            showLoading('Génération de l\'hypothèse initiale...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'start_autonomous');
                formData.append('domain', domain);
                formData.append('topic', topic);
                
                const response = await fetch('autonomous.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    hypothesisId = data.hypothesis_id;
                    currentStep = 1;
                    updateProgress();
                    displayResults(data.data);
                    document.getElementById('continueBtn').style.display = 'inline-block';
                } else {
                    alert('Erreur: ' + data.error);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
            
            hideLoading();
        });

        async function continueWorkflow() {
            if (!hypothesisId || currentStep >= 9) return;
            
            showLoading(`Exécution de l'étape ${currentStep + 1}/9...`);
            
            try {
                const formData = new FormData();
                formData.append('action', 'continue_workflow');
                formData.append('hypothesis_id', hypothesisId);
                formData.append('current_step', currentStep);
                
                const response = await fetch('autonomous.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentStep = data.step;
                    updateProgress();
                    displayResults(data.data);
                    
                    if (currentStep < 9) {
                        document.getElementById('continueBtn').style.display = 'inline-block';
                    } else {
                        document.getElementById('continueBtn').textContent = '✅ Recherche Terminée!';
                        document.getElementById('continueBtn').disabled = true;
                    }
                } else {
                    alert('Erreur: ' + data.error);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
            
            hideLoading();
        }

        function updateProgress() {
            const percentage = (currentStep / 9) * 100;
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('stepStatus').textContent = `Étape ${currentStep}/9: ${stepNames[currentStep - 1]}`;
            
            const indicators = document.querySelectorAll('.step-indicator');
            indicators.forEach((indicator, index) => {
                indicator.classList.remove('active', 'completed');
                if (index + 1 < currentStep) {
                    indicator.classList.add('completed');
                    indicator.textContent = '✓';
                } else if (index + 1 === currentStep) {
                    indicator.classList.add('active');
                    indicator.textContent = index + 1;
                } else {
                    indicator.textContent = index + 1;
                }
            });
        }

        function displayResults(data) {
            const results = document.getElementById('results');
            results.textContent = JSON.stringify(data, null, 2);
        }

        function showLoading(text) {
            document.getElementById('loadingText').textContent = text;
            document.getElementById('loading').style.display = 'block';
            document.getElementById('continueBtn').style.display = 'none';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
    </script>
</body>
</html>
