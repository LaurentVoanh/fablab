<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Science Hub Ultimate</title>
    <style>
        :root {
            --primary: #2563eb;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .card h3 {
            color: var(--accent);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .stat-big {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .chart-container {
            height: 200px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .recent-list {
            list-style: none;
        }
        
        .recent-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-success { background: var(--success); color: white; }
        .badge-warning { background: var(--warning); color: white; }
        .badge-info { background: var(--primary); color: white; }
        
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 0.8rem;
            border-left: 3px solid var(--accent);
            margin-bottom: 0.5rem;
            background: rgba(15, 23, 42, 0.3);
            border-radius: 0 8px 8px 0;
        }
        
        .activity-time {
            font-size: 0.8rem;
            opacity: 0.6;
            margin-top: 0.3rem;
        }
        
        .ai-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .ai-stat {
            text-align: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
        }
        
        .ai-stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .ai-stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <a href="index.php" class="logo">🔬 Science Hub Ultimate</a>
            <div style="color: var(--accent);">Dashboard Analytique</div>
        </div>
    </nav>

    <div class="container">
        <h1>📊 Dashboard de Recherche</h1>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>📈 Statistiques Globales</h3>
                <div class="stat-big" id="totalHypotheses">-</div>
                <div>Hypothèses générées</div>
                <div style="margin-top: 1rem;" class="stat-big" id="totalArticles" style="font-size: 2rem;">-</div>
                <div>Articles analysés</div>
            </div>
            
            <div class="card">
                <h3>⚗️ Expériences</h3>
                <div class="stat-big" id="totalExperiments">-</div>
                <div>Expériences créées</div>
                <div style="margin-top: 1rem;">
                    <span class="badge badge-success" id="completedExp">0 complétées</span>
                    <span class="badge badge-warning" id="pendingExp">0 en cours</span>
                </div>
            </div>
            
            <div class="card">
                <h3>🧠 IA & Apprentissage</h3>
                <div class="ai-stats">
                    <div class="ai-stat">
                        <div class="ai-stat-value" id="aiCalls">-</div>
                        <div class="ai-stat-label">Appels API</div>
                    </div>
                    <div class="ai-stat">
                        <div class="ai-stat-value" id="successRate">-%</div>
                        <div class="ai-stat-label">Taux de succès</div>
                    </div>
                    <div class="ai-stat">
                        <div class="ai-stat-value" id="avgTokens">-</div>
                        <div class="ai-stat-label">Tokens moyens</div>
                    </div>
                    <div class="ai-stat">
                        <div class="ai-stat-value" id="strategies">-</div>
                        <div class="ai-stat-label">Stratégies</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>🔌 APIs Connectées</h3>
                <div class="stat-big" style="font-size: 2rem;">36</div>
                <div>Sources scientifiques</div>
                <div style="margin-top: 1rem; opacity: 0.8; font-size: 0.9rem;">
                    • arXiv<br>
                    • PubMed<br>
                    • CrossRef DOI<br>
                    • Semantic Scholar<br>
                    • Et 32 autres...
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card" style="grid-column: span 2;">
                <h3>📝 Hypothèses Récentes</h3>
                <ul class="recent-list" id="recentHypotheses">
                    <li class="recent-item">Chargement...</li>
                </ul>
            </div>
            
            <div class="card">
                <h3>⚡ Activité Récente</h3>
                <div class="activity-feed" id="activityFeed">
                    <div class="activity-item">
                        <div>Initialisation du système</div>
                        <div class="activity-time">À l'instant</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>🎯 Stratégies d'Apprentissage</h3>
            <div id="strategiesList">
                <p style="opacity: 0.7; padding: 1rem;">Les stratégies seront affichées ici après utilisation du système.</p>
            </div>
        </div>
    </div>

    <script>
        async function loadDashboard() {
            try {
                // Charger les statistiques
                const statsResponse = await fetch('api.php?action=getStats');
                const stats = await statsResponse.json();
                
                document.getElementById('totalHypotheses').textContent = stats.hypotheses || 0;
                document.getElementById('totalArticles').textContent = stats.articles || 0;
                document.getElementById('totalExperiments').textContent = stats.experiments || 0;
                document.getElementById('aiCalls').textContent = stats.ai_calls || 0;
                
                // Charger les hypothèses récentes
                const hypothesesResponse = await fetch('api.php?action=getHypotheses&limit=5');
                const hypotheses = await hypothesesResponse.json();
                
                const hypothesesList = document.getElementById('recentHypotheses');
                if (hypotheses && hypotheses.length > 0) {
                    hypothesesList.innerHTML = hypotheses.map(h => `
                        <li class="recent-item">
                            <div>
                                <strong>${escapeHtml(h.title.substring(0, 60))}${h.title.length > 60 ? '...' : ''}</strong>
                                <div style="font-size: 0.8rem; opacity: 0.7;">${h.domain} • ${h.workflow_mode}</div>
                            </div>
                            <span class="badge badge-${h.status === 'completed' ? 'success' : 'warning'}">${h.status}</span>
                        </li>
                    `).join('');
                } else {
                    hypothesesList.innerHTML = '<li class="recent-item">Aucune hypothèse pour le moment</li>';
                }
                
                // Charger les logs AI pour les statistiques avancées
                const logsResponse = await fetch('api.php?action=getAILogs&limit=100');
                const logs = await logsResponse.json();
                
                if (logs && logs.length > 0) {
                    const successful = logs.filter(l => l.success === 1).length;
                    const successRate = Math.round((successful / logs.length) * 100);
                    const avgTokens = Math.round(logs.reduce((sum, l) => sum + (l.tokens_used || 0), 0) / logs.length);
                    
                    document.getElementById('successRate').textContent = successRate + '%';
                    document.getElementById('avgTokens').textContent = avgTokens;
                    
                    // Mettre à jour le feed d'activité
                    const activityFeed = document.getElementById('activityFeed');
                    activityFeed.innerHTML = logs.slice(0, 10).map(log => `
                        <div class="activity-item">
                            <div>API ${log.model_used} - ${log.request_type} (${log.success === 1 ? '✓' : '✗'})</div>
                            <div class="activity-time">${log.created_at}</div>
                        </div>
                    `).join('');
                }
                
                // Charger les stratégies
                const strategiesResponse = await fetch('api.php?action=getStrategies');
                const strategies = await strategiesResponse.json();
                
                const strategiesList = document.getElementById('strategiesList');
                if (strategies && strategies.length > 0) {
                    strategiesList.innerHTML = strategies.map(s => `
                        <div class="recent-item">
                            <div>
                                <strong>${escapeHtml(s.strategy_name)}</strong>
                                <div style="font-size: 0.8rem; opacity: 0.7;">Utilisée ${s.times_used} fois</div>
                            </div>
                            <span class="badge badge-success">${Math.round(s.success_rate * 100)}% succès</span>
                        </div>
                    `).join('');
                }
                
            } catch (error) {
                console.error('Erreur chargement dashboard:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadDashboard();
        setInterval(loadDashboard, 30000); // Rafraîchir toutes les 30 secondes
    </script>
</body>
</html>
