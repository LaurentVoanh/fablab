<?php

require_once __DIR__ . '/config.php';

function migrate_db_v3(): void
{
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "Migration de la base de données vers la V3...\n";

        // 1. Modifier la table 'queries'
        echo "Ajout des colonnes à la table 'queries'...\n";
        $pdo->exec("ALTER TABLE queries ADD COLUMN score REAL");
        $pdo->exec("ALTER TABLE queries ADD COLUMN feedback_ai TEXT");
        $pdo->exec("ALTER TABLE queries ADD COLUMN is_optimized BOOLEAN DEFAULT 0");
        $pdo->exec("ALTER TABLE queries ADD COLUMN parent_query_id INTEGER");
        echo "Colonnes ajoutées à 'queries'.\n";

        // 2. Créer la table 'query_strategies'
        echo "Création de la table 'query_strategies'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS query_strategies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source TEXT NOT NULL,
                topic_type TEXT NOT NULL,
                optimized_term_template TEXT NOT NULL,
                effectiveness_score REAL,
                last_used_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "Table 'query_strategies' créée.\n";

        // 3. Modifier la table 'articles'
        echo "Ajout des colonnes à la table 'articles'...\n";
        $pdo->exec("ALTER TABLE articles ADD COLUMN full_content_scientific TEXT");
        $pdo->exec("ALTER TABLE articles ADD COLUMN full_content_vulgarized TEXT");
        $pdo->exec("ALTER TABLE articles ADD COLUMN validation_score REAL");
        $pdo->exec("ALTER TABLE articles ADD COLUMN contradiction_feedback TEXT");
        echo "Colonnes ajoutées à 'articles'.\n";

        // 4. Créer la table 'article_sections'
        echo "Création de la table 'article_sections'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS article_sections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                section_title TEXT NOT NULL,
                content_scientific TEXT,
                content_vulgarized TEXT,
                deep_research_status TEXT DEFAULT 'pending',
                last_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (article_id) REFERENCES articles(id)
            );
        ");
        echo "Table 'article_sections' créée.\n";

        // 5. Créer la table 'reports'
        echo "Création de la table 'reports'...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                report_type TEXT NOT NULL,
                content TEXT,
                generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (article_id) REFERENCES articles(id)
            );
        ");
        echo "Table 'reports' créée.\n";

        echo "Migration de la base de données V3 terminée avec succès.\n";

    } catch (PDOException $e) {
        echo "Erreur de migration de la base de données : " . $e->getMessage() . "\n";
    }
}

migrate_db_v3();

?>
