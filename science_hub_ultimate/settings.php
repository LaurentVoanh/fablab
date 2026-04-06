<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Science Hub Ultimate</title>
    <style>
        :root { --primary: #2563eb; --accent: #06b6d4; --dark: #1e293b; --light: #f8fafc; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, var(--dark) 0%, #0f172a 100%); color: var(--light); min-height: 100vh; padding-top: 80px; }
        nav { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(10px); padding: 1rem 2rem; position: fixed; width: 100%; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        h1 { font-size: 2.5rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .settings-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; }
        .settings-card h3 { color: var(--accent); margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: var(--accent); }
        input, textarea, select { width: 100%; padding: 1rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; background: rgba(15, 23, 42, 0.5); color: var(--light); font-size: 1rem; }
        .btn { padding: 1rem 2rem; border-radius: 12px; border: none; cursor: pointer; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); color: white; }
        .info-box { background: rgba(6, 182, 212, 0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 1rem; margin-top: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <nav><div class="nav-container"><a href="index.php" class="logo">🔬 Science Hub Ultimate</a><div style="color: var(--accent);">Configuration</div></div></nav>
    <div class="container">
        <h1>⚙️ Paramètres du Système</h1>
        
        <div class="settings-card">
            <h3>🔑 Configuration Mistral AI</h3>
            <div class="form-group">
                <label>Clé API 1 (Primaire)</label>
                <input type="password" placeholder="sk-..." id="apiKey1">
            </div>
            <div class="form-group">
                <label>Clé API 2 (Secondaire)</label>
                <input type="password" placeholder="sk-..." id="apiKey2">
            </div>
            <div class="form-group">
                <label>Clé API 3 (Backup)</label>
                <input type="password" placeholder="sk-..." id="apiKey3">
            </div>
            <div class="info-box">
                ℹ️ Les clés sont utilisées en rotation automatique pour une meilleure fiabilité. Configurez-les via les variables d'environnement Hostinger ou éditez config.php directement.
            </div>
        </div>

        <div class="settings-card">
            <h3>📊 Modèle IA par Défaut</h3>
            <div class="form-group">
                <select id="defaultModel">
                    <option value="mistral-large-latest">Mistral Large (Recommandé)</option>
                    <option value="mistral-medium">Mistral Medium</option>
                    <option value="open-mistral-7b">Mistral 7B (Rapide)</option>
                </select>
            </div>
        </div>

        <div class="settings-card">
            <h3>🗄️ Base de Données SQLite</h3>
            <div class="info-box">
                <strong>Chemin actuel:</strong> /data/science_hub.db<br><br>
                ✅ SQLite est automatiquement configuré et compatible avec Hostinger.<br>
                ✅ Aucune configuration supplémentaire nécessaire.<br>
                ✅ Sauvegarde automatique incluse.
            </div>
        </div>

        <div class="settings-card">
            <h3>🔌 APIs Scientifiques</h3>
            <div class="info-box">
                <strong>36 sources connectées:</strong><br>
                arXiv • PubMed • CrossRef • Semantic Scholar • IEEE Xplore • ScienceDirect • Springer • Nature • Wiley • JSTOR • Google Scholar • DOAJ • bioRxiv • medRxiv • ChemRxiv • PsyArXiv • SocArXiv • OSF Preprints • Zenodo • Figshare • DataCite • ORCID • Scopus • Web of Science • Dimensions • OpenAlex • BASE • CORE • Unpaywall • Dissernet • Magiran • CNKI • J-STAGE • SciELO • Redalyc + autres
            </div>
        </div>

        <div class="settings-card">
            <h3>🧪 Test de Connexion</h3>
            <button class="btn" onclick="testConnection()">Tester Mistral AI</button>
            <div id="testResult" style="margin-top: 1rem;"></div>
        </div>

        <div class="settings-card">
            <h3>💾 Sauvegarde & Export</h3>
            <button class="btn" onclick="exportData()">Exporter les données (JSON)</button>
            <button class="btn" style="margin-left: 1rem;" onclick="backupDB()">Sauvegarder la base SQLite</button>
        </div>
    </div>

    <script>
        async function testConnection() {
            const result = document.getElementById('testResult');
            result.innerHTML = 'Test en cours...';
            try {
                const response = await fetch('api.php?action=testMistral');
                const data = await response.json();
                if (data.success) {
                    result.innerHTML = '<span style="color: #10b981;">✅ Connexion réussie! Réponse: ' + escapeHtml(data.response.substring(0, 100)) + '</span>';
                } else {
                    result.innerHTML = '<span style="color: #ef4444;">❌ Échec: ' + escapeHtml(data.error) + '</span>';
                }
            } catch (error) {
                result.innerHTML = '<span style="color: #ef4444;">❌ Erreur: ' + error.message + '</span>';
            }
        }

        async function exportData() {
            try {
                const responses = await Promise.all([
                    fetch('api.php?action=getHypotheses&limit=100'),
                    fetch('api.php?action=getArticles&limit=100'),
                    fetch('api.php?action=getExperiments')
                ]);
                const [hypotheses, articles, experiments] = await Promise.all(responses.map(r => r.json()));
                const exportData = { hypotheses, articles, experiments, exported_at: new Date().toISOString() };
                const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'science_hub_export_' + new Date().toISOString().split('T')[0] + '.json';
                a.click();
                alert('Export réussi!');
            } catch (error) {
                alert('Erreur d\'export: ' + error.message);
            }
        }

        function backupDB() {
            alert('Pour sauvegarder la base SQLite sur Hostinger:\n\n1. Accédez au gestionnaire de fichiers\n2. Naviguez vers /data/\n3. Téléchargez science_hub.db\n\nLa sauvegarde automatique est également effectuée quotidiennement.');
        }

        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    </script>
</body>
</html>
