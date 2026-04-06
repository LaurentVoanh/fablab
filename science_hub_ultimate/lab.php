<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratoire - Science Hub Ultimate</title>
    <style>
        :root { --primary: #2563eb; --accent: #06b6d4; --dark: #1e293b; --light: #f8fafc; --success: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, var(--dark) 0%, #0f172a 100%); color: var(--light); min-height: 100vh; padding-top: 80px; }
        nav { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(10px); padding: 1rem 2rem; position: fixed; width: 100%; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        h1 { font-size: 2.5rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .experiments-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .experiment-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        .experiment-title { font-size: 1.2rem; color: var(--accent); margin-bottom: 0.5rem; }
        .badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: bold; display: inline-block; margin-bottom: 1rem; }
        .badge-success { background: var(--success); color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .code-preview { background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.85rem; overflow-x: auto; margin-top: 1rem; max-height: 200px; overflow-y: auto; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); color: white; margin-top: 1rem; }
    </style>
</head>
<body>
    <nav><div class="nav-container"><a href="index.php" class="logo">🔬 Science Hub Ultimate</a><div style="color: var(--accent);">Laboratoire d'Expérimentation</div></div></nav>
    <div class="container">
        <h1>⚗️ Laboratoire d'Expériences</h1>
        <div class="experiments-grid" id="experimentsGrid"><p>Chargement...</p></div>
    </div>
    <script>
        async function loadExperiments() {
            const response = await fetch('api.php?action=getExperiments');
            const experiments = await response.json();
            const grid = document.getElementById('experimentsGrid');
            if (experiments && experiments.length > 0) {
                grid.innerHTML = experiments.map(e => `
                    <div class="experiment-card">
                        <div class="experiment-title">${escapeHtml(e.title)}</div>
                        <span class="badge badge-${e.status === 'completed' ? 'success' : 'warning'}">${e.status}</span>
                        ${e.code_php ? `<div class="code-preview">${escapeHtml(e.code_php.substring(0, 300))}...</div>` : ''}
                        <button class="btn" onclick="runExperiment(${e.id})">Lancer l'expérience</button>
                    </div>
                `).join('');
            } else { grid.innerHTML = '<p>Aucune expérience. Générez-en depuis le mode autonome ou guidé.</p>'; }
        }
        function runExperiment(id) { alert('Fonctionnalité de simulation à implémenter pour l\'expérience #' + id); }
        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        loadExperiments();
    </script>
</body>
</html>
