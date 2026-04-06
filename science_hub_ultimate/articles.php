<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Articles - Science Hub Ultimate</title>
    <style>
        :root { --primary: #2563eb; --accent: #06b6d4; --dark: #1e293b; --light: #f8fafc; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, var(--dark) 0%, #0f172a 100%); color: var(--light); min-height: 100vh; padding-top: 80px; }
        nav { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(10px); padding: 1rem 2rem; position: fixed; width: 100%; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        h1 { font-size: 2.5rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .search-box { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .search-input { flex: 1; padding: 1rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; background: rgba(15, 23, 42, 0.5); color: var(--light); font-size: 1rem; }
        .btn { padding: 1rem 2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; background: linear-gradient(90deg, var(--primary), var(--accent)); color: white; }
        .articles-list { display: flex; flex-direction: column; gap: 1rem; }
        .article-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        .article-title { font-size: 1.2rem; color: var(--accent); margin-bottom: 0.5rem; }
        .article-meta { display: flex; gap: 1rem; margin-bottom: 1rem; font-size: 0.85rem; opacity: 0.7; }
        .article-abstract { opacity: 0.8; line-height: 1.6; }
    </style>
</head>
<body>
    <nav><div class="nav-container"><a href="index.php" class="logo">🔬 Science Hub Ultimate</a><div style="color: var(--accent);">Base de Articles Scientifiques</div></div></nav>
    <div class="container">
        <h1>📰 Articles Scientifiques</h1>
        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="Rechercher dans les articles...">
            <button class="btn" onclick="searchArticles()">Rechercher</button>
        </div>
        <div class="articles-list" id="articlesList"><p>Chargement...</p></div>
    </div>
    <script>
        async function loadArticles() {
            const response = await fetch('api.php?action=getArticles&limit=50');
            const articles = await response.json();
            displayArticles(articles);
        }
        async function searchArticles() {
            const query = document.getElementById('searchInput').value;
            if (!query) { loadArticles(); return; }
            const response = await fetch(`api.php?action=searchArticles&q=${encodeURIComponent(query)}`);
            const articles = await response.json();
            displayArticles(articles);
        }
        function displayArticles(articles) {
            const list = document.getElementById('articlesList');
            if (articles && articles.length > 0) {
                list.innerHTML = articles.map(a => `
                    <div class="article-card">
                        <div class="article-title">${escapeHtml(a.title)}</div>
                        <div class="article-meta"><span>${a.source || 'Inconnu'}</span><span>${a.published_date || ''}</span><span>Score: ${(a.relevance_score * 100).toFixed(0)}%</span></div>
                        <div class="article-abstract">${escapeHtml(a.abstract || 'Pas de résumé disponible')}</div>
                    </div>
                `).join('');
            } else { list.innerHTML = '<p>Aucun article trouvé</p>'; }
        }
        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        loadArticles();
    </script>
</body>
</html>
