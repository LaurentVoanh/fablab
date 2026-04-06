<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  SCIENCE HUB ULTIMATE — INDEX.PHP                                    ║
 * ║  Page d'accueil • Tableau de bord • Navigation principale            ║
 * ║  Compatible Hostinger • SQLite • Mistral AI                          ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/config.php';

// Récupération des statistiques
$pdo = get_db();
$total_hypotheses = $pdo->query("SELECT COUNT(*) FROM hypotheses")->fetchColumn();
$pending_hypotheses = $pdo->query("SELECT COUNT(*) FROM hypotheses WHERE status = 'pending'")->fetchColumn();
$validated_hypotheses = $pdo->query("SELECT COUNT(*) FROM hypotheses WHERE validation_score > 0.7")->fetchColumn();
$total_articles = $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$total_rss = $pdo->query("SELECT COUNT(*) FROM rss_items")->fetchColumn();
$recent_logs = get_recent_logs($SESSION_ID, 10);

// Traitement RSS si demandé
if(isset($_GET['refresh_rss'])) {
    process_rss_feeds();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCIENCE HUB ULTIMATE — Plateforme de Recherche Scientifique IA</title>
    <meta name="description" content="Plateforme unifiée de recherche scientifique assistée par IA — 36 sources • Mistral AI • SQLite">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #080c10;
            --surface: #0d1319;
            --surface2: #111820;
            --surface3: #161f2a;
            --border: rgba(0, 180, 255, 0.12);
            --border2: rgba(0, 180, 255, 0.25);
            --accent: #00c8ff;
            --accent2: #0affb0;
            --accent3: #ff3d6b;
            --accent4: #ffd700;
            --text: #c8dff0;
            --text-dim: #5a7a95;
            --text-bright: #e8f4ff;
            --mono: 'Space Mono', 'Courier New', monospace;
            --display: 'Syne', sans-serif;
        }
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--mono); font-size: 13px; }
        
        body::before {
            content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: 0.6;
        }
        
        #root { position: relative; z-index: 1; display: grid; grid-template-rows: auto auto 1fr auto; min-height: 100vh; }
        
        /* HEADER */
        #header {
            display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
            padding: 16px 24px; border-bottom: 1px solid var(--border2);
            background: linear-gradient(180deg, rgba(0,200,255,0.05) 0%, transparent 100%);
        }
        
        .header-brand {
            font-family: var(--display); font-size: 22px; font-weight: 800;
            color: var(--text-bright); letter-spacing: -0.5px;
        }
        .header-brand span { color: var(--accent); }
        .header-sub { font-size: 9px; color: var(--text-dim); letter-spacing: 2px; text-transform: uppercase; }
        
        .nav-center { display: flex; gap: 8px; }
        .nav-btn {
            padding: 8px 16px; border: 1px solid var(--border);
            background: var(--surface2); color: var(--text);
            font-family: var(--mono); font-size: 11px; cursor: pointer;
            transition: all 0.2s; text-decoration: none; display: inline-block;
        }
        .nav-btn:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 15px rgba(0,200,255,0.2); }
        .nav-btn.active { border-color: var(--accent2); color: var(--accent2); }
        
        .header-stats { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; font-size: 10px; color: var(--text-dim); }
        
        /* MAIN CONTENT */
        #main { padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; align-content: start; }
        
        .card {
            background: var(--surface); border: 1px solid var(--border);
            padding: 20px; border-radius: 4px; position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
        }
        .card-title {
            font-family: var(--display); font-size: 16px; font-weight: 700;
            color: var(--text-bright); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .card-icon { font-size: 18px; }
        
        .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .stat-item { text-align: center; padding: 12px; background: var(--surface2); border-radius: 4px; }
        .stat-value { font-size: 24px; font-weight: 700; color: var(--accent); font-family: var(--display); }
        .stat-label { font-size: 9px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        
        .action-grid { display: grid; gap: 12px; }
        .action-btn {
            display: flex; align-items: center; gap: 12px; padding: 16px;
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--text); text-decoration: none; font-size: 12px;
            transition: all 0.2s; cursor: pointer;
        }
        .action-btn:hover { border-color: var(--accent); background: var(--surface3); }
        .action-icon { font-size: 20px; width: 32px; text-align: center; }
        .action-desc { flex: 1; }
        .action-title { font-weight: 700; color: var(--text-bright); margin-bottom: 4px; }
        .action-sub { font-size: 10px; color: var(--text-dim); }
        
        .log-list { max-height: 300px; overflow-y: auto; }
        .log-item {
            padding: 8px 0; border-bottom: 1px solid var(--border);
            font-size: 11px; display: flex; gap: 8px;
        }
        .log-time { color: var(--text-dim); min-width: 80px; }
        .log-msg { flex: 1; }
        .log-type.info { color: var(--accent); }
        .log-type.success { color: var(--accent2); }
        .log-type.warning { color: var(--accent4); }
        .log-type.error { color: var(--accent3); }
        
        .rss-section { margin-top: 20px; }
        .rss-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .rss-list { display: grid; gap: 8px; max-height: 400px; overflow-y: auto; }
        .rss-item {
            padding: 12px; background: var(--surface2); border-left: 3px solid var(--accent);
            font-size: 11px;
        }
        .rss-title { font-weight: 700; color: var(--text-bright); margin-bottom: 4px; }
        .rss-meta { font-size: 9px; color: var(--text-dim); }
        
        /* FOOTER */
        #footer {
            padding: 16px 24px; border-top: 1px solid var(--border);
            text-align: center; font-size: 10px; color: var(--text-dim);
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            #header { grid-template-columns: 1fr; gap: 12px; text-align: center; }
            .header-stats { align-items: center; }
            #main { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div id="root">
        <!-- HEADER -->
        <header id="header">
            <div class="header-left">
                <div class="header-brand">SCIENCE HUB <span>ULTIMATE</span></div>
                <div class="header-sub">Plateforme de Recherche Scientifique IA v<?= SCIENCE_HUB_VERSION ?></div>
            </div>
            
            <nav class="nav-center">
                <a href="index.php" class="nav-btn active">🏠 Accueil</a>
                <a href="autonomous.php" class="nav-btn">🤖 Mode Autonome</a>
                <a href="guided.php" class="nav-btn">📋 Mode Guidé</a>
                <a href="dashboard.php" class="nav-btn">📊 Dashboard</a>
                <a href="hypotheses.php" class="nav-btn">💡 Hypothèses</a>
                <a href="articles.php" class="nav-btn">📄 Articles</a>
                <a href="lab.php" class="nav-btn">🧪 Lab</a>
                <a href="settings.php" class="nav-btn">⚙️ Settings</a>
            </nav>
            
            <div class="header-stats">
                <div>Session: <?= substr($SESSION_ID, 0, 8) ?>...</div>
                <div><?= $total_hypotheses ?> hypothèses • <?= $total_articles ?> articles</div>
            </div>
        </header>
        
        <!-- MAIN CONTENT -->
        <main id="main">
            <!-- STATISTIQUES -->
            <div class="card">
                <div class="card-title"><span class="card-icon">📊</span> Statistiques Globales</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?= $total_hypotheses ?></div>
                        <div class="stat-label">Hypothèses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $pending_hypotheses ?></div>
                        <div class="stat-label">En Attente</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $validated_hypotheses ?></div>
                        <div class="stat-label">Validées</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $total_articles ?></div>
                        <div class="stat-label">Articles</div>
                    </div>
                </div>
            </div>
            
            <!-- MODES DE RECHERCHE -->
            <div class="card">
                <div class="card-title"><span class="card-icon">🚀</span> Modes de Recherche</div>
                <div class="action-grid">
                    <a href="autonomous.php" class="action-btn">
                        <span class="action-icon">🤖</span>
                        <div class="action-desc">
                            <div class="action-title">Mode Autonome (GENESIS-ULTRA v9.1)</div>
                            <div class="action-sub">Agent IA en 9 étapes • Sélection automatique de cibles • 8 sources scientifiques</div>
                        </div>
                    </a>
                    <a href="guided.php" class="action-btn">
                        <span class="action-icon">📋</span>
                        <div class="action-desc">
                            <div class="action-title">Mode Guidé (GENESIS-ULTRA V3)</div>
                            <div class="action-sub">Workflow interactif • 36 sources • Contrôle manuel des étapes</div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- DERNIERS LOGS -->
            <div class="card">
                <div class="card-title"><span class="card-icon">📝</span> Activité Récente</div>
                <div class="log-list">
                    <?php if(empty($recent_logs)): ?>
                        <div class="log-item"><span class="log-msg" style="color: var(--text-dim);">Aucune activité récente</span></div>
                    <?php else: ?>
                        <?php foreach($recent_logs as $log): ?>
                            <div class="log-item">
                                <span class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                <span class="log-msg log-type <?= $log['log_type'] ?>">[<?= $log['phase'] ?>] <?= htmlspecialchars($log['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- FLUX RSS -->
            <div class="card rss-section" style="grid-column: 1 / -1;">
                <div class="rss-header">
                    <div class="card-title"><span class="card-icon">📡</span> Flux RSS Scientifiques (24 sources)</div>
                    <a href="?refresh_rss=1" class="nav-btn">🔄 Actualiser</a>
                </div>
                <div class="rss-list">
                    <?php
                    $rss_items = get_rss_items(12);
                    if(empty($rss_items)):
                    ?>
                        <div class="rss-item" style="color: var(--text-dim);">Aucun flux RSS. Cliquez sur "Actualiser" pour charger les dernières news scientifiques.</div>
                    <?php else: ?>
                        <?php foreach($rss_items as $item): ?>
                            <div class="rss-item">
                                <div class="rss-title"><?= htmlspecialchars($item['title']) ?></div>
                                <div class="rss-meta">
                                    <?= htmlspecialchars($item['feed_name']) ?> • 
                                    <?php if($item['pub_date']): ?><?= date('d/m/Y', strtotime($item['pub_date'])) ?><?php endif; ?> • 
                                    Catégorie: <?= htmlspecialchars($item['category']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <!-- FOOTER -->
        <footer id="footer">
            SCIENCE HUB ULTIMATE v<?= SCIENCE_HUB_VERSION ?> • Compatible Hostinger • Mistral AI • SQLite • 36 Sources Scientifiques • 24 Flux RSS
        </footer>
    </div>
</body>
</html>
