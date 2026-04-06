<?php

require_once __DIR__ . '/config.php';

function init_db_v2(): void
{
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "Initialisation de la base de données V2...\n";

        // Créer la table 'articles'
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic TEXT NOT NULL,
                title TEXT NOT NULL,
                summary TEXT,
                sources_ok INTEGER,
                total_hits INTEGER,
                word_count INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                session_id TEXT NOT NULL
            );
        ");
        echo "Table 'articles' créée ou déjà existante.\n";

        // Créer la table 'sessions'
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                topic TEXT NOT NULL,
                status TEXT NOT NULL,
                mode TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "Table 'sessions' créée ou déjà existante.\n";

        // Créer la table 'queries'
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS queries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                source TEXT NOT NULL,
                url TEXT NOT NULL,
                term TEXT NOT NULL,
                status TEXT NOT NULL,
                http_code INTEGER,
                duration_ms INTEGER,
                hits INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES sessions(id)
            );
        ");
        echo "Table 'queries' créée ou déjà existante.\n";

        // Créer la table 'findings'
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS findings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                source TEXT NOT NULL,
                title TEXT,
                abstract TEXT,
                year TEXT,
                url TEXT,
                source_url TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES sessions(id)
            );
        ");
        echo "Table 'findings' créée ou déjà existante.\n";

        echo "Initialisation de la base de données V2 terminée avec succès.\n";

    } catch (PDOException $e) {
        echo "Erreur d'initialisation de la base de données : " . $e->getMessage() . "\n";
    }
}

init_db_v2();

?>
