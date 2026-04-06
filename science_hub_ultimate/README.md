# 🚀 SCIENCE HUB ULTIMATE v1.0.0

Plateforme de recherche scientifique augmentée par IA multistep, optimisée pour Hostinger avec Mistral AI et SQLite.

## 📋 Fonctionnalités Principales

### 7 Inventions Géniales Intégrées:

1. **GENESIS-ULTRA v9.1 (Mode Autonome)** - Agent de recherche en 9 étapes
   - Génération d'hypothèses révolutionnaires
   - Recherche bibliographique automatisée
   - Analyse critique et validation
   - Conception expérimentale
   - Simulation prédictive
   - Génération de code PHP/JS
   - Rapport scientifique complet

2. **GENESIS-ULTRA V3 (Mode Guidé)** - Workflow interactif en 6 étapes
   - Formulation de questions de recherche
   - Accès à 36 sources scientifiques
   - Analyse approfondie de l'état de l'art
   - Conception méthodologique
   - Validation par les pairs simulée
   - Rapport final professionnel

3. **Moteur de Conscience IA** - Apprentissage continu
   - Scoring des stratégies
   - Optimisation automatique
   - Logging détaillé des appels API
   - Rotation intelligente des clés Mistral

4. **Prompts IA Multi-Couches** - 7 types spécialisés
   - Validation scientifique
   - Contradiction et critique
   - Rapports structurés
   - Génération de code
   - Analyse bibliographique

5. **Science Pulse Admin** - Veille scientifique
   - Crawl RSS automatisé
   - Traitement IA d'articles
   - Versioning de contenu
   - Base de données SQLite

6. **Générateur d'Expériences** - Code automatique
   - Transformation articles → simulations
   - Code PHP backend
   - Visualisations JavaScript
   - Paramètres ajustables

7. **SQLite Intelligent** - Schema complet
   - 7 tables interconnectées
   - Apprentissage des stratégies
   - Logs AI détaillés
   - Export JSON natif

## 🗂️ Structure du Projet

```
science_hub_ultimate/
├── config.php          # Configuration centrale (DB, API, fonctions)
├── index.php           # Page d'accueil
├── autonomous.php      # Mode autonome GENESIS-ULTRA v9.1 (9 étapes)
├── guided.php          # Mode guidé GENESIS-ULTRA V3 (6 étapes)
├── dashboard.php       # Dashboard analytique
├── hypotheses.php      # Gestion des hypothèses
├── articles.php        # Base d'articles scientifiques
├── lab.php             # Laboratoire d'expérimentation
├── api.php             # API REST unifiée
├── settings.php        # Configuration système
├── data/               # Base de données SQLite
│   └── science_hub.db
└── logs/               # Logs système et AI
```

## 🔧 Installation sur Hostinger

### Étape 1: Téléchargement
1. Compressez le dossier `science_hub_ultimate`
2. Uploadez via FTP ou File Manager Hostinger
3. Extrayez dans votre répertoire web (ex: `public_html/`)

### Étape 2: Configuration Mistral AI
Éditez `config.php` ou définissez les variables d'environnement:

```php
define('MISTRAL_API_KEYS', [
    'key1' => 'votre_cle_primaire',
    'key2' => 'votre_cle_secondaire',
    'key3' => 'votre_cle_backup'
]);
```

Ou via .htaccess sur Hostinger:
```
SetEnv MISTRAL_API_KEY_1 sk-xxx
SetEnv MISTRAL_API_KEY_2 sk-xxx
SetEnv MISTRAL_API_KEY_3 sk-xxx
```

### Étape 3: Permissions
Assurez-vous que les dossiers `data/` et `logs/` sont accessibles en écriture:
```bash
chmod 755 data/ logs/
```

### Étape 4: Test
Accédez à `https://votre-domaine.com/science_hub_ultimate/settings.php` et cliquez sur "Tester Mistral AI"

## 🎯 Pages du Site

| Page | Description | URL |
|------|-------------|-----|
| Accueil | Présentation et statistiques | index.php |
| Mode Autonome | GENESIS-ULTRA v9.1 (9 étapes) | autonomous.php |
| Mode Guidé | GENESIS-ULTRA V3 (6 étapes) | guided.php |
| Dashboard | Analytics et activité | dashboard.php |
| Hypothèses | Gestion des recherches | hypotheses.php |
| Articles | Base documentaire | articles.php |
| Laboratoire | Expérimentations | lab.php |
| API | Documentation API | api.php |
| Settings | Configuration | settings.php |

## 📊 Base de Données SQLite

### Tables:
- **users** - Utilisateurs et rôles
- **hypotheses** - Hypothèses de recherche
- **articles** - Articles scientifiques
- **experiments** - Expériences générées
- **ai_logs** - Logs des appels API Mistral
- **learning_strategies** - Stratégies d'apprentissage
- **rss_feeds** - Flux RSS configurés

La base est automatiquement créée au premier accès.

## 🔌 APIs Scientifiques Supportées (36)

arXiv, PubMed, CrossRef, Semantic Scholar, IEEE Xplore, ScienceDirect, Springer, Nature, Wiley, JSTOR, Google Scholar, DOAJ, bioRxiv, medRxiv, ChemRxiv, PsyArXiv, SocArXiv, OSF Preprints, Zenodo, Figshare, DataCite, ORCID, Scopus, Web of Science, Dimensions, OpenAlex, BASE, CORE, Unpaywall, Dissernet, Magiran, CNKI, J-STAGE, SciELO, Redalyc + autres

## 🛠️ API REST

Endpoints disponibles via `api.php?action=XXX`:

- `getStats` - Statistiques globales
- `getHypotheses` - Liste des hypothèses
- `getHypothesis?id=X` - Détail d'une hypothèse
- `getArticles` - Liste des articles
- `searchArticles?q=XXX` - Recherche d'articles
- `getExperiments` - Expériences
- `getAILogs` - Logs AI
- `getStrategies` - Stratégies d'apprentissage
- `testMistral` - Test de connexion API

## 🎨 Design

- Interface moderne dark theme
- Responsive (mobile-first)
- Animations fluides
- Codes couleur sémantiques
- Navigation intuitive

## 📝 Workflows IA

### Mode Autonome (9 étapes):
1. Génération d'hypothèse
2. Recherche bibliographique
3. Analyse critique
4. Conception expérimentale
5. Simulation prédictive
6. Validation croisée
7. Optimisation
8. Génération de code
9. Rapport final

### Mode Guidé (6 étapes):
1. Formulation de la question
2. Recherche de sources (36 APIs)
3. Analyse approfondie
4. Conception méthodologique
5. Validation par les pairs
6. Rapport final

## ⚙️ Configuration Technique

- **PHP**: 7.4+ requis
- **SQLite**: PDO_SQLite activé
- **cURL**: Requis pour les appels API
- **JSON**: Activé par défaut
- **Hostinger**: 100% compatible

## 🔐 Sécurité

- Input sanitization (htmlspecialchars)
- Prepared statements SQL
- Session management
- Error logging
- API key rotation

## 📄 Licence

Usage libre pour la recherche scientifique.

## 🤝 Support

Pour toute question ou amélioration, consultez la documentation complète dans chaque fichier PHP.

---

**Science Hub Ultimate** - Propulsé par Mistral AI + SQLite | Optimisé pour Hostinger
