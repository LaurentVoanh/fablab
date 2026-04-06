<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Accueil</title>
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #7c3aed;
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
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }
        
        .nav-links a {
            color: var(--light);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(37, 99, 235, 0.2);
            color: var(--accent);
        }
        
        .hero {
            padding: 8rem 2rem 4rem;
            text-align: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero p {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 3rem;
            line-height: 1.8;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }
        
        .feature-card p {
            opacity: 0.8;
            line-height: 1.6;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
        }
        
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-secondary:hover {
            background: var(--accent);
            color: var(--dark);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            background: rgba(30, 41, 59, 0.4);
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            opacity: 0.8;
            font-size: 1.1rem;
        }
        
        footer {
            text-align: center;
            padding: 3rem 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 4rem;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .nav-links { display: none; }
            .features { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <div class="logo">🔬 Science Hub Ultimate</div>
            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="autonomous.php">Mode Autonome</a></li>
                <li><a href="guided.php">Mode Guidé</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="hypotheses.php">Hypothèses</a></li>
                <li><a href="articles.php">Articles</a></li>
                <li><a href="lab.php">Laboratoire</a></li>
                <li><a href="api.php">API</a></li>
                <li><a href="settings.php">Settings</a></li>
            </ul>
        </div>
    </nav>

    <section class="hero">
        <h1>Plateforme de Recherche Scientifique IA Multistep</h1>
        <p>
            Générez des hypothèses scientifiques inédites, analysez des milliers d'articles, 
            et conduisez des recherches autonomes grâce à l'IA Mistral en workflow multi-étapes.
            Compatible avec 36 APIs scientifiques et optimisé pour Hostinger.
        </p>
        <div class="cta-buttons">
            <a href="autonomous.php" class="btn btn-primary">🚀 Lancer Mode Autonome</a>
            <a href="guided.php" class="btn btn-secondary">📋 Mode Guidé</a>
            <a href="dashboard.php" class="btn btn-secondary">📊 Voir Dashboard</a>
        </div>
    </section>

    <section class="features">
        <div class="feature-card" onclick="location.href='autonomous.php'">
            <div class="feature-icon">🤖</div>
            <h3>GENESIS-ULTRA v9.1</h3>
            <p>Agent autonome en 9 étapes générant des hypothèses scientifiques inédites avec validation automatique et scoring de confiance.</p>
        </div>
        
        <div class="feature-card" onclick="location.href='guided.php'">
            <div class="feature-icon">🎯</div>
            <h3>Workflow Guidé V3</h3>
            <p>Processus interactif en 6 étapes avec accès à 36 sources scientifiques et prompts IA spécialisés multi-couches.</p>
        </div>
        
        <div class="feature-card" onclick="location.href='dashboard.php'">
            <div class="feature-icon">🧠</div>
            <h3>Moteur de Conscience IA</h3>
            <p>Apprentissage continu, optimisation automatique des stratégies et amélioration progressive des résultats.</p>
        </div>
        
        <div class="feature-card" onclick="location.href='articles.php'">
            <div class="feature-icon">📰</div>
            <h3>Science Pulse Admin</h3>
            <p>Crawl RSS automatisé, traitement IA d'articles scientifiques et versioning intelligent de contenu.</p>
        </div>
        
        <div class="feature-card" onclick="location.href='lab.php'">
            <div class="feature-icon">⚗️</div>
            <h3>Générateur d'Expériences</h3>
            <p>Transformation automatique d'articles en code PHP/JS interactif pour simulations et tests.</p>
        </div>
        
        <div class="feature-card" onclick="location.href='api.php'">
            <div class="feature-icon">🔌</div>
            <h3>API Unifiée</h3>
            <p>Accès programmatique à toutes les fonctionnalités avec rotation de clés Mistral et caching intelligent.</p>
        </div>
    </section>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-number" id="stat-hypotheses">0</div>
            <div class="stat-label">Hypothèses Générées</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="stat-articles">0</div>
            <div class="stat-label">Articles Analysés</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="stat-experiments">0</div>
            <div class="stat-label">Expériences Créées</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="stat-apis">36</div>
            <div class="stat-label">APIs Connectées</div>
        </div>
    </section>

    <footer>
        <p>© 2025 Science Hub Ultimate v<?= SITE_VERSION ?> | Propulsé par Mistral AI + SQLite | Optimisé pour Hostinger</p>
        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
            Recherche scientifique augmentée par IA multistep • Workflows autonomes • Apprentissage continu
        </p>
    </footer>

    <script>
        // Charger les statistiques depuis la base de données
        async function loadStats() {
            try {
                const response = await fetch('api.php?action=getStats');
                const data = await response.json();
                
                animateNumber('stat-hypotheses', data.hypotheses || 0);
                animateNumber('stat-articles', data.articles || 0);
                animateNumber('stat-experiments', data.experiments || 0);
            } catch (error) {
                console.log('Stats non disponibles');
            }
        }
        
        function animateNumber(elementId, target) {
            const element = document.getElementById(elementId);
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 30);
        }
        
        loadStats();
    </script>
</body>
</html>
