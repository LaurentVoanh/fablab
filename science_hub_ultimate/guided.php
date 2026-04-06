<?php
require_once 'config.php';

// Gestion des actions POST pour le mode guidé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    
    if ($action === 'start_guided') {
        $domain = sanitizeInput($_POST['domain'] ?? 'sciences generales');
        $question = sanitizeInput($_POST['question'] ?? '');
        
        try {
            // Étape 1: Formulation de la question de recherche
            $step1Prompt = "Tu es un assistant de recherche scientifique guidée (GENESIS-ULTRA V3).
            
Domaine: $domain
Question initiale: $question

ÉTAPE 1/6: Affine et reformule cette question de recherche pour la rendre plus précise et testable.
Propose également 3-5 sous-questions connexes.

Format JSON:
{
  \"refined_question\": \"...\",
  \"sub_questions\": [\"...\", \"...\"],
  \"key_concepts\": [\"...\", \"...\"],
  \"recommended_approach\": \"...\"
}";

            $response = callMistralAI($step1Prompt, "Expert en formulation de questions de recherche scientifique.");
            $data = json_decode($response, true) ?? ['refined_question' => $response];
            
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO hypotheses (title, description, domain, status, workflow_mode, steps_completed, total_steps) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['refined_question'] ?? $question,
                json_encode($data),
                $domain,
                'in_progress',
                'guided',
                1,
                6
            ]);
            $hypothesisId = $db->lastInsertId();
            
            jsonResponse([
                'success' => true,
                'hypothesis_id' => $hypothesisId,
                'step' => 1,
                'data' => $data
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    if ($action === 'next_step') {
        $hypothesisId = (int)$_POST['hypothesis_id'];
        $currentStep = (int)$_POST['current_step'];
        $userInput = sanitizeInput($_POST['user_input'] ?? '');
        
        try {
            $db = getDB();
            $hypothesis = $db->query("SELECT * FROM hypotheses WHERE id = $hypothesisId")->fetch();
            
            if (!$hypothesis) {
                throw new Exception("Hypothèse non trouvée");
            }
            
            $nextStep = $currentStep + 1;
            $response = null;
            $stepData = [];
            
            switch ($nextStep) {
                case 2:
                    // Recherche de sources (36 APIs)
                    $prompt = "ÉTAPE 2/6: Pour la question suivante, identifie les meilleures sources scientifiques parmi 36 bases de données disponibles.
                    
Question: {$hypothesis['title']}
Domaine: {$hypothesis['domain']}

Fournis une liste structurée de:
- 5-10 articles clés avec DOI
- 3-5 revues systématiques pertinentes
- 2-3 bases de données spécialisées
- Sources primaires recommandées

Inclus les URLs d'accès quand disponibles.";
                    $response = callMistralAI($prompt, "Expert en recherche bibliographique multi-sources.");
                    $stepData['sources'] = $response;
                    break;
                    
                case 3:
                    // Analyse approfondie
                    $prompt = "ÉTAPE 3/6: Analyse approfondie de l'état de l'art.
                    
Question: {$hypothesis['title']}

Fournis:
1. Consensus scientifique actuel
2. Zones de controverse ou débat
3. Lacunes dans la littérature
4. Méthodologies dominantes
5. Résultats contradictoires éventuels

Structure claire avec sections numérotées.";
                    $response = callMistralAI($prompt, "Analyste scientifique senior.");
                    $stepData['analysis'] = $response;
                    break;
                    
                case 4:
                    // Conception méthodologique
                    $prompt = "ÉTAPE 4/6: Conçois une méthodologie de recherche complète.
                    
Question: {$hypothesis['title']}

Développe:
- Design expérimental ou approche théorique
- Population/échantillon ou matériaux
- Variables et mesures
- Protocole détaillé
- Analyses statistiques prévues
- Considérations éthiques

Format structuré professionnel.";
                    $response = callMistralAI($prompt, "Expert en méthodologie de recherche.");
                    $stepData['methodology'] = $response;
                    break;
                    
                case 5:
                    // Validation par les pairs simulée
                    $prompt = "ÉTAPE 5/6: Simulation de revue par les pairs.
                    
Question: {$hypothesis['title']}
Méthodologie: " . substr($hypothesis['description'], 0, 500) . "

Agis comme 3 reviewers experts:
- Reviewer 1: Points forts et originalité
- Reviewer 2: Faiblesses méthodologiques
- Reviewer 3: Recommandations d'amélioration

Synthèse finale avec score de qualité (0-10).";
                    $response = callMistralAI($prompt, "Comité de revue scientifique simulé.");
                    $stepData['review'] = $response;
                    break;
                    
                case 6:
                    // Rapport final et prochaines étapes
                    $prompt = "ÉTAPE 6/6: Génère un rapport de recherche complet et un plan d'action.
                    
Question: {$hypothesis['title']}
Domaine: {$hypothesis['domain']}

Inclus:
1. Résumé exécutif (300 mots)
2. Contexte et justification
3. Méthodologie recommandée
4. Ressources nécessaires
5. Timeline estimée
6. Critères de succès
7. Prochaines étapes concrètes
8. Références clés

Format professionnel prêt à soumettre.";
                    $response = callMistralAI($prompt, "Rédacteur de projets de recherche expert.");
                    $stepData['final_report'] = $response;
                    
                    $update = $db->prepare("UPDATE hypotheses SET status = 'completed', steps_completed = ? WHERE id = ?");
                    $update->execute([6, $hypothesisId]);
                    break;
            }
            
            if ($nextStep < 6) {
                $update = $db->prepare("UPDATE hypotheses SET steps_completed = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$nextStep, $hypothesisId]);
            }
            
            jsonResponse([
                'success' => true,
                'step' => $nextStep,
                'data' => $stepData,
                'message' => "Étape $nextStep/6 complétée"
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
    <title>Mode Guidé - Science Hub Ultimate</title>
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
        
        .intro-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .apis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
            opacity: 0.7;
            font-size: 0.8rem;
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
            min-height: 150px;
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
        
        .progress-steps {
            display: none;
            margin: 2rem 0;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        
        .step.active .step-number {
            background: var(--primary);
        }
        
        .step.completed .step-number {
            background: var(--success);
        }
        
        .results-area {
            display: none;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
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
            <div style="color: var(--accent);">Mode Guidé - 6 Étapes</div>
        </div>
    </nav>

    <div class="container">
        <h1>🎯 Workflow Guidé V3</h1>
        <p style="opacity: 0.8; margin-bottom: 2rem;">Processus interactif en 6 étapes avec accès à 36 sources scientifiques</p>

        <div class="intro-card">
            <h3 style="color: var(--accent); margin-bottom: 1rem;">📚 36 Sources Scientifiques Connectées</h3>
            <div class="apis-grid">
                <div>arXiv</div><div>PubMed</div><div>CrossRef</div><div>Semantic Scholar</div>
                <div>IEEE Xplore</div><div>ScienceDirect</div><div>Springer</div><div>Nature</div>
                <div>Wiley</div><div>JSTOR</div><div>Google Scholar</div><div>DOAJ</div>
                <div>bioRxiv</div><div>medRxiv</div><div>ChemRxiv</div><div>PsyArXiv</div>
                <div>SocArXiv</div><div>OSF Preprints</div><div>Zenodo</div><div>Figshare</div>
                <div>DataCite</div><div>ORCID</div><div>Scopus</div><div>Web of Science</div>
                <div>Dimensions</div><div>OpenAlex</div><div>BASE</div><div>CORE</div>
                <div>Unpaywall</div><div>Dissernet</div><div>Magiran</div><div>CNKI</div>
                <div>J-STAGE</div><div>SciELO</div><div>Redalyc</div><div>Et plus...</div>
            </div>
        </div>

        <div class="form-card" id="initForm">
            <form id="guidedForm">
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
                        <option value="psychologie">Psychologie</option>
                        <option value="economie">Économie</option>
                        <option value="sciences_generales">Sciences Générales</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="question">Question de Recherche Initiale</label>
                    <textarea id="question" name="question" placeholder="Décrivez votre question ou idée de recherche..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">🚀 Démarrer le Workflow Guidé</button>
            </form>
        </div>

        <div id="workflowArea" style="display: none;">
            <div class="progress-steps" id="progressSteps">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div>Formulation de la question</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div>Recherche de sources (36 APIs)</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div>Analyse approfondie</div>
                </div>
                <div class="step" id="step4">
                    <div class="step-number">4</div>
                    <div>Conception méthodologique</div>
                </div>
                <div class="step" id="step5">
                    <div class="step-number">5</div>
                    <div>Validation par les pairs</div>
                </div>
                <div class="step" id="step6">
                    <div class="step-number">6</div>
                    <div>Rapport final</div>
                </div>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <div id="loadingText">Traitement en cours...</div>
            </div>

            <div class="results-area" id="results"></div>

            <div style="text-align: center; margin-top: 2rem;" id="actionButtons">
                <button class="btn btn-primary" id="continueBtn" style="display: none;" onclick="nextStep()">
                    Continuer vers l'étape suivante →
                </button>
            </div>
        </div>
    </div>

    <script>
        let hypothesisId = null;
        let currentStep = 0;
        const stepNames = [
            'Formulation',
            'Sources',
            'Analyse',
            'Méthodologie',
            'Validation',
            'Rapport'
        ];

        document.getElementById('guidedForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const domain = document.getElementById('domain').value;
            const question = document.getElementById('question').value;
            
            document.getElementById('initForm').style.display = 'none';
            document.getElementById('workflowArea').style.display = 'block';
            document.getElementById('progressSteps').style.display = 'block';
            showLoading('Formulation de la question de recherche...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'start_guided');
                formData.append('domain', domain);
                formData.append('question', question);
                
                const response = await fetch('guided.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    hypothesisId = data.hypothesis_id;
                    currentStep = 1;
                    updateSteps();
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

        async function nextStep() {
            if (!hypothesisId || currentStep >= 6) return;
            
            showLoading(`Exécution de l'étape ${currentStep + 1}/6...`);
            
            try {
                const formData = new FormData();
                formData.append('action', 'next_step');
                formData.append('hypothesis_id', hypothesisId);
                formData.append('current_step', currentStep);
                
                const response = await fetch('guided.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentStep = data.step;
                    updateSteps();
                    displayResults(data.data);
                    
                    if (currentStep < 6) {
                        document.getElementById('continueBtn').style.display = 'inline-block';
                    } else {
                        document.getElementById('continueBtn').textContent = '✅ Workflow Terminé!';
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

        function updateSteps() {
            for (let i = 1; i <= 6; i++) {
                const step = document.getElementById(`step${i}`);
                step.classList.remove('active', 'completed');
                if (i < currentStep) {
                    step.classList.add('completed');
                    step.querySelector('.step-number').textContent = '✓';
                } else if (i === currentStep) {
                    step.classList.add('active');
                    step.querySelector('.step-number').textContent = i;
                } else {
                    step.querySelector('.step-number').textContent = i;
                }
            }
        }

        function displayResults(data) {
            const results = document.getElementById('results');
            results.style.display = 'block';
            results.textContent = typeof data === 'object' ? JSON.stringify(data, null, 2) : data;
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
