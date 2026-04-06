<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hypothèses - Science Hub Ultimate</title>
    <style>
        :root { --primary: #2563eb; --accent: #06b6d4; --dark: #1e293b; --light: #f8fafc; --success: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, var(--dark) 0%, #0f172a 100%); color: var(--light); min-height: 100vh; padding-top: 80px; }
        nav { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(10px); padding: 1rem 2rem; position: fixed; width: 100%; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        h1 { font-size: 2.5rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.5rem 1.5rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: var(--light); cursor: pointer; transition: all 0.3s; }
        .filter-btn.active, .filter-btn:hover { background: var(--primary); border-color: var(--primary); }
        .hypotheses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .hypothesis-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; transition: all 0.3s; cursor: pointer; }
        .hypothesis-card:hover { transform: translateY(-3px); border-color: var(--accent); }
        .hypothesis-title { font-size: 1.2rem; margin-bottom: 0.5rem; color: var(--accent); }
        .hypothesis-meta { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .badge-success { background: var(--success); color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-info { background: var(--primary); color: white; }
        .progress-bar { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden; margin: 1rem 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); }
    </style>
</head>
<body>
    <nav><div class="nav-container"><a href="index.php" class="logo">🔬 Science Hub Ultimate</a><div style="color: var(--accent);">Gestion des Hypothèses</div></div></nav>
    <div class="container">
        <h1>💡 Hypothèses de Recherche</h1>
        <div class="filters">
            <button class="filter-btn active" onclick="filterStatus('all')">Toutes</button>
            <button class="filter-btn" onclick="filterStatus('draft')">Brouillons</button>
            <button class="filter-btn" onclick="filterStatus('in_progress')">En cours</button>
            <button class="filter-btn" onclick="filterStatus('completed')">Complétées</button>
        </div>
        <div class="hypotheses-grid" id="hypothesesGrid"><p>Chargement...</p></div>
    </div>
    <script>
        let currentFilter = 'all';
        async function loadHypotheses() {
            const url = currentFilter === 'all' ? 'api.php?action=getHypotheses&limit=50' : `api.php?action=getHypotheses&status=${currentFilter}&limit=50`;
            const response = await fetch(url);
            const hypotheses = await response.json();
            const grid = document.getElementById('hypothesesGrid');
            if (hypotheses && hypotheses.length > 0) {
                grid.innerHTML = hypotheses.map(h => `
                    <div class="hypothesis-card" onclick="viewHypothesis(${h.id})">
                        <div class="hypothesis-title">${escapeHtml(h.title.substring(0, 80))}${h.title.length > 80 ? '...' : ''}</div>
                        <div class="hypothesis-meta">
                            <span class="badge badge-info">${h.domain}</span>
                            <span class="badge badge-${h.status === 'completed' ? 'success' : 'warning'}">${h.status}</span>
                            <span class="badge badge-info">${h.workflow_mode}</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: ${(h.steps_completed / h.total_steps) * 100}%"></div></div>
                        <div style="font-size: 0.85rem; opacity: 0.7;">Étape ${h.steps_completed}/${h.total_steps} • Score: ${(h.confidence_score * 100).toFixed(0)}%</div>
                        <div style="font-size: 0.8rem; opacity: 0.5; margin-top: 0.5rem;">${h.created_at}</div>
                    </div>
                `).join('');
            } else {
                grid.innerHTML = '<p>Aucune hypothèse trouvée</p>';
            }
        }
        function filterStatus(status) {
            currentFilter = status;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            loadHypotheses();
        }
        function viewHypothesis(id) { window.location.href = `hypotheses.php?id=${id}`; }
        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        loadHypotheses();
    </script>
</body>
</html>
