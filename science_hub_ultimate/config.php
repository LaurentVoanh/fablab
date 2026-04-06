<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  SCIENCE HUB ULTIMATE — CONFIG.PHP                                   ║
 * ║  Configuration centrale • APIs scientifiques • IA Mistral augmentée  ║
 * ║  Compatible Hostinger • SQLite • 36 Sources • RSS Feeds              ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

// ============================================================================
// PROTECTIONS & INITIALISATION
// ============================================================================
@error_reporting(E_ALL);
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/storage/php_errors.log');
while(@ob_get_level() > 0) { @ob_end_clean(); }

// ============================================================================
// CONSTANTES GLOBALES
// ============================================================================
defined('SCIENCE_HUB_VERSION') or define('SCIENCE_HUB_VERSION', '1.0.0-ultimate');
defined('STORAGE_PATH')        or define('STORAGE_PATH',       __DIR__ . '/storage/');
defined('DB_PATH')             or define('DB_PATH',            __DIR__ . '/science_hub.sqlite');
defined('MAX_STEP_TIME')       or define('MAX_STEP_TIME',      25);
defined('HYPOTHESIS_PER_PAGE') or define('HYPOTHESIS_PER_PAGE', 12);
defined('MAX_LOGS_IN_RAM')     or define('MAX_LOGS_IN_RAM',    500);
defined('ABSTRACT_MAX_CHARS')  or define('ABSTRACT_MAX_CHARS', 800);
defined('MAX_ABSTRACTS_PER_SOURCE') or define('MAX_ABSTRACTS_PER_SOURCE', 10);
defined('MAX_ERRORS_BEFORE_RESET')  or define('MAX_ERRORS_BEFORE_RESET', 5);
defined('RSS_CACHE_DURATION')  or define('RSS_CACHE_DURATION', 3600); // 1 heure

// ============================================================================
// CLÉS API MISTRAL — Rotation automatique avec fallback
 * ⚠️ REMPLACEZ PAR VOS CLÉS RÉELLES EN PRODUCTION
 * Modèles supportés: pixtral-12b-2409, devstral-latest, mistral-large-latest
 * ============================================================================
$MISTRAL_KEYS = [
    'votre_cle_api_mistral_1',
    'votre_cle_api_mistral_2',
    'votre_cle_api_mistral_3'
];
$MISTRAL_KEY_INDEX = 0;

$MISTRAL_CONFIG = [
    'keys'              => $MISTRAL_KEYS,
    'current_index'     => 0,
    'emergency_model'   => 'mistral-small-latest',
    'models_available'  => [
        'pixtral'  => ['name' => 'pixtral-12b-2409',   'tokens_max' => 128000, 'use_for' => 'vision,analyse_images,quick_tasks'],
        'devstral' => ['name' => 'devstral-latest',    'tokens_max' => 256000, 'use_for' => 'code,development,debugging'],
        'large'    => ['name' => 'mistral-large-latest','tokens_max' => 256000, 'use_for' => 'deep_research,synthesis,critique'],
        'medium'   => ['name' => 'mistral-medium-latest','tokens_max' => 256000, 'use_for' => 'synthesis,article_generation'],
        'small'    => ['name' => 'mistral-small-latest', 'tokens_max' => 256000, 'use_for' => 'target_selection,quick_tasks'],
    ],
    'default_model'     => 'mistral-small-latest',
    'deep_model'        => 'mistral-large-latest',
];

// ============================================================================
// 36 SOURCES SCIENTIFIQUES — URLs validées + descriptions pour l'IA
 * Chaque source a : url_template, description, query_note, type, weight
 * ============================================================================
$SCIENTIFIC_APIS = [
    // ─── LITTÉRATURE BIOMÉDICALE (8 sources) ────────────────────────────
    'pubmed' => [
        'name'         => 'PubMed',
        'emoji'        => '📗',
        'color'        => '#0066cc',
        'base'         => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/',
        'max_standard' => 8,
        'max_deep'     => 20,
        'timeout'      => 35,
        'type'         => 'biomedical',
        'weight'       => 1.5,
        'desc'         => 'Base de données biomédicale principale NCBI',
        'query_note'   => 'Terme de recherche PubMed. Ex: "myocarditis mRNA vaccine" ou "BRCA1 cancer 2023"',
    ],
    'europepmc' => [
        'name'         => 'EuropePMC',
        'emoji'        => '🌍',
        'color'        => '#0077bb',
        'base'         => 'https://www.ebi.ac.uk/europepmc/webservices/rest/search',
        'max_standard' => 8,
        'max_deep'     => 20,
        'timeout'      => 40,
        'type'         => 'biomedical',
        'weight'       => 1.3,
        'desc'         => 'Europe PubMed Central — texte intégral annoté',
        'query_note'   => 'Recherche libre en anglais. Ex: "COVID long term neurological"',
    ],
    'openalex' => [
        'name'         => 'OpenAlex',
        'emoji'        => '🌐',
        'color'        => '#8b5cf6',
        'base'         => 'https://api.openalex.org/works',
        'max_standard' => 8,
        'max_deep'     => 20,
        'timeout'      => 40,
        'type'         => 'cross-domain',
        'weight'       => 1.2,
        'desc'         => '250M+ publications avec graphe de citations',
        'query_note'   => 'Terme de recherche en anglais. Ex: "alzheimer biomarkers"',
    ],
    'crossref' => [
        'name'         => 'CrossRef',
        'emoji'        => '📄',
        'color'        => '#f59e0b',
        'base'         => 'https://api.crossref.org/works',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 35,
        'type'         => 'literature',
        'weight'       => 1.1,
        'desc'         => 'Métadonnées DOI — CrossRef',
        'query_note'   => 'Recherche libre. Ex: "insulin resistance type 2 diabetes"',
    ],
    'arxiv' => [
        'name'         => 'ArXiv',
        'emoji'        => '📐',
        'color'        => '#ff6600',
        'base'         => 'https://export.arxiv.org/api/query',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 60,
        'type'         => 'preprint',
        'weight'       => 1.0,
        'desc'         => 'Préprints scientifiques (biologie, physique, IA)',
        'query_note'   => 'Termes en anglais sans guillemets. Ex: CRISPR+genome+editing',
    ],
    'zenodo' => [
        'name'         => 'Zenodo',
        'emoji'        => '🏛️',
        'color'        => '#14b8a6',
        'base'         => 'https://zenodo.org/api/records',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 35,
        'type'         => 'data',
        'weight'       => 0.9,
        'desc'         => 'Dépôt de datasets, codes et publications',
        'query_note'   => 'Recherche libre. Ex: "genomics dataset RNA-seq"',
    ],
    'inspirehep' => [
        'name'         => 'INSPIRE-HEP',
        'emoji'        => '⚛️',
        'color'        => '#ec4899',
        'base'         => 'https://inspirehep.net/api/literature',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'physics',
        'weight'       => 0.8,
        'desc'         => 'Physique des hautes énergies et biophysique',
        'query_note'   => 'Terme en anglais. Ex: "biophysics protein structure"',
    ],
    'datacite' => [
        'name'         => 'DataCite',
        'emoji'        => '📊',
        'color'        => '#06b6d4',
        'base'         => 'https://api.datacite.org/works',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'data',
        'weight'       => 0.8,
        'desc'         => 'Datasets avec DOI — DataCite',
        'query_note'   => 'Recherche de datasets scientifiques. Ex: "genomics cancer dataset"',
    ],

    // ─── GÉNÉTIQUE & PROTÉINES (6 sources) ──────────────────────────────
    'uniprot' => [
        'name'         => 'UniProt',
        'emoji'        => '🔵',
        'color'        => '#00aa55',
        'base'         => 'https://rest.uniprot.org/uniprotkb/search',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 35,
        'type'         => 'protein',
        'weight'       => 1.4,
        'desc'         => 'Base de données universelle des protéines',
        'query_note'   => 'Nom de gène ou protéine. Ex: TP53, BRCA1, insulin',
    ],
    'ensembl' => [
        'name'         => 'Ensembl',
        'emoji'        => '🧬',
        'color'        => '#dc2626',
        'base'         => 'https://rest.ensembl.org/lookup/symbol/homo_sapiens',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 30,
        'type'         => 'genomics',
        'weight'       => 1.3,
        'desc'         => 'Génomique — coordonnées chromosomiques',
        'query_note'   => 'NOM exact du gène humain (symbole officiel HGNC). Ex: BRCA1, TP53',
    ],
    'clinvar' => [
        'name'         => 'ClinVar',
        'emoji'        => '🏥',
        'color'        => '#cc2200',
        'base'         => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 35,
        'type'         => 'genetics',
        'weight'       => 1.3,
        'desc'         => 'Variants génétiques cliniques NCBI',
        'query_note'   => 'Terme de recherche clinvar. Ex: "BRCA1 pathogenic"',
    ],
    'geo' => [
        'name'         => 'GEO',
        'emoji'        => '📈',
        'color'        => '#7c3aed',
        'base'         => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'genomics',
        'weight'       => 1.2,
        'desc'         => 'Gene Expression Omnibus — données d\'expression',
        'query_note'   => 'Terme de recherche GEO. Ex: "myocarditis RNA-seq"',
    ],
    'arrayexpress' => [
        'name'         => 'ArrayExpress',
        'emoji'        => '🔬',
        'color'        => '#0891b2',
        'base'         => 'https://www.ebi.ac.uk/biostudies/api/v1/search',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'genomics',
        'weight'       => 1.1,
        'desc'         => 'Données d\'expression génique EBI',
        'query_note'   => 'Recherche libre. Ex: "heart inflammation transcriptomics"',
    ],
    'stringdb' => [
        'name'         => 'StringDB',
        'emoji'        => '🕸️',
        'color'        => '#f97316',
        'base'         => 'https://string-db.org/api/json/network',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 40,
        'type'         => 'network',
        'weight'       => 1.2,
        'desc'         => 'Réseau d\'interactions protéines STRING',
        'query_note'   => 'NOM d\'une PROTÉINE (symbole de gène humain). Ex: TP53, MYH7',
    ],

    // ─── CHIMIE & PHARMACOLOGIE (4 sources) ─────────────────────────────
    'chembl' => [
        'name'         => 'ChEMBL',
        'emoji'        => '⚗️',
        'color'        => '#d97706',
        'base'         => 'https://www.ebi.ac.uk/chembl/api/data/',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 40,
        'type'         => 'chemistry',
        'weight'       => 1.2,
        'desc'         => 'Base de données pharmacologique ChEMBL',
        'query_note'   => 'NOM d\'une molécule ou médicament. Ex: ibuprofen, aspirin',
    ],
    'pubchem' => [
        'name'         => 'PubChem',
        'emoji'        => '🧪',
        'color'        => '#059669',
        'base'         => 'https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name',
        'max_standard' => 5,
        'max_deep'     => 12,
        'timeout'      => 35,
        'type'         => 'chemistry',
        'weight'       => 1.1,
        'desc'         => 'Propriétés chimiques PubChem',
        'query_note'   => 'NOM d\'un composé chimique. Ex: aspirin, glucose',
    ],
    'kegg' => [
        'name'         => 'KEGG',
        'emoji'        => '🔗',
        'color'        => '#6366f1',
        'base'         => 'https://rest.kegg.jp/find/hsa',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 30,
        'type'         => 'pathway',
        'weight'       => 1.0,
        'desc'         => 'Voies métaboliques humaines KEGG',
        'query_note'   => 'Terme KEGG (gène ou maladie). Ex: myocarditis, cardiac',
    ],
    'reactome' => [
        'name'         => 'Reactome',
        'emoji'        => '🔄',
        'color'        => '#10b981',
        'base'         => 'https://reactome.org/ContentService/data/pathways/low/entity',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 35,
        'type'         => 'pathway',
        'weight'       => 1.0,
        'desc'         => 'Voies de signalisation Reactome',
        'query_note'   => 'ACCESSION UniProt d\'une protéine. Ex: P04637 (TP53)',
    ],

    // ─── ONTOLOGIES & MALADIES (4 sources) ──────────────────────────────
    'geneontology' => [
        'name'         => 'GeneOntology',
        'emoji'        => '📋',
        'color'        => '#8b5cf6',
        'base'         => 'https://api.geneontology.org/api/search/entity/autocomplete',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 30,
        'type'         => 'ontology',
        'weight'       => 0.9,
        'desc'         => 'Ontologie des fonctions géniques GO',
        'query_note'   => 'Terme biologique en anglais. Ex: cardiac muscle contraction',
    ],
    'disgenet' => [
        'name'         => 'DisGeNET',
        'emoji'        => '🦠',
        'color'        => '#ef4444',
        'base'         => 'https://www.disgenet.org/api/gda/disease',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 35,
        'type'         => 'disease',
        'weight'       => 1.1,
        'desc'         => 'Associations gènes-maladies DisGeNET',
        'query_note'   => 'NOM de la maladie (en anglais). Ex: myocarditis, cardiomyopathy',
    ],
    'omim' => [
        'name'         => 'OMIM',
        'emoji'        => '🧾',
        'color'        => '#f59e0b',
        'base'         => 'https://api.omim.org/api/entry',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 35,
        'type'         => 'genetics',
        'weight'       => 1.2,
        'desc'         => 'Online Mendelian Inheritance in Man',
        'query_note'   => 'Terme de recherche OMIM. Ex: "cystic fibrosis"',
    ],
    'orphanet' => [
        'name'         => 'Orphanet',
        'emoji'        => '🏥',
        'color'        => '#14b8a6',
        'base'         => 'https://www.orpha.net/ords/orphanet-api/api',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 35,
        'type'         => 'rare_disease',
        'weight'       => 1.0,
        'desc'         => 'Maladies rares et médicaments orphelins',
        'query_note'   => 'Nom de maladie rare. Ex: "progeria", "huntington"',
    ],

    // ─── CONNAISSANCE GÉNÉRALE (4 sources) ──────────────────────────────
    'wikidata' => [
        'name'         => 'Wikidata',
        'emoji'        => '📚',
        'color'        => '#666666',
        'base'         => 'https://www.wikidata.org/w/api.php',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 30,
        'type'         => 'knowledge',
        'weight'       => 0.7,
        'desc'         => 'Base de connaissances Wikidata',
        'query_note'   => 'Terme de recherche général. Ex: "CRISPR Cas9"',
    ],
    'wikipedia' => [
        'name'         => 'Wikipedia',
        'emoji'        => '📖',
        'color'        => '#333333',
        'base'         => 'https://en.wikipedia.org/w/api.php',
        'max_standard' => 5,
        'max_deep'     => 10,
        'timeout'      => 30,
        'type'         => 'knowledge',
        'weight'       => 0.6,
        'desc'         => 'Encyclopédie Wikipedia anglaise',
        'query_note'   => 'Terme de recherche général. Ex: "mRNA vaccine mechanism"',
    ],
    'scholarly' => [
        'name'         => 'Scholarly',
        'emoji'        => '🎓',
        'color'        => '#4f46e5',
        'base'         => 'https://api.semanticscholar.org/graph/v1/paper/search',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 40,
        'type'         => 'literature',
        'weight'       => 1.0,
        'desc'         => 'Semantic Scholar API (accès limité)',
        'query_note'   => 'Terme académique. Ex: "deep learning protein folding"',
    ],
    'core' => [
        'name'         => 'CORE',
        'emoji'        => '🌟',
        'color'        => '#0ea5e9',
        'base'         => 'https://core.ac.uk/api-v2/articles/search',
        'max_standard' => 6,
        'max_deep'     => 15,
        'timeout'      => 40,
        'type'         => 'literature',
        'weight'       => 0.9,
        'desc'         => 'Articles en accès ouvert CORE',
        'query_note'   => 'Recherche libre. Ex: "climate change impact agriculture"',
    ],
];

// Domaines scientifiques avec leurs sources préférées
$DOMAIN_SOURCE_MAP = [
    'genetics'     => ['pubmed', 'clinvar', 'uniprot', 'europepmc', 'ensembl', 'omim'],
    'oncology'     => ['pubmed', 'europepmc', 'openalex', 'disgenet', 'clinvar'],
    'neurology'    => ['pubmed', 'arxiv', 'europepmc', 'geneontology', 'stringdb'],
    'biochem'      => ['uniprot', 'chembl', 'pubmed', 'europepmc', 'kegg', 'reactome'],
    'pharmacology' => ['chembl', 'pubmed', 'clinvar', 'europepmc', 'pubchem', 'kegg'],
    'immunology'   => ['pubmed', 'uniprot', 'europepmc', 'geneontology', 'stringdb'],
    'cardiology'   => ['pubmed', 'clinvar', 'europepmc', 'disgenet', 'kegg'],
    'general'      => ['pubmed', 'uniprot', 'arxiv', 'europepmc', 'openalex'],
];

// Stratégies de recherche automatique
$RESEARCH_STRATEGIES = [
    'broad'    => ['depth' => 3, 'sources' => 5, 'boost' => 1.0, 'desc' => 'Exploration large spectre'],
    'focused'  => ['depth' => 6, 'sources' => 8, 'boost' => 1.3, 'desc' => 'Analyse ciblée'],
    'deep'     => ['depth' => 10, 'sources' => 12, 'boost' => 1.6, 'desc' => 'Recherche approfondie toutes sources'],
    'adaptive' => ['depth' => 0, 'sources' => 0, 'boost' => 1.0, 'desc' => 'Adaptatif selon disponibilité'],
];

// ============================================================================
// RSS FEEDS SCIENTIFIQUES — 24 sources de news automatiques
 * ============================================================================
$RSS_FEEDS = [
    // Nature Publishing Group
    ['name' => 'Nature Latest Research', 'url' => 'https://www.nature.com/nature.rss', 'category' => 'general'],
    ['name' => 'Nature Medicine', 'url' => 'https://www.nature.com/nm.rss', 'category' => 'medicine'],
    ['name' => 'Nature Biotechnology', 'url' => 'https://www.nature.com/nbt.rss', 'category' => 'biotech'],
    ['name' => 'Scientific Reports', 'url' => 'https://www.nature.com/srep.rss', 'category' => 'general'],
    
    // Science Magazine
    ['name' => 'Science Current Issue', 'url' => 'http://science.sciencemag.org/current.xml', 'category' => 'general'],
    ['name' => 'Science Advances', 'url' => 'https://www.science.org/action/showFeed?type=home&jc=sciadv', 'category' => 'general'],
    
    // PLOS
    ['name' => 'PLOS ONE', 'url' => 'http://journals.plos.org/plosone/feed', 'category' => 'general'],
    ['name' => 'PLOS Biology', 'url' => 'http://journals.plos.org/plosbiology/feed', 'category' => 'biology'],
    ['name' => 'PLOS Genetics', 'url' => 'http://journals.plos.org/plosgenetics/feed', 'category' => 'genetics'],
    
    // BMC
    ['name' => 'BMC Medicine', 'url' => 'https://bmcmedicine.biomedcentral.com/rss', 'category' => 'medicine'],
    ['name' => 'BMC Genomics', 'url' => 'https://bmcgenomics.biomedcentral.com/rss', 'category' => 'genomics'],
    ['name' => 'BMC Bioinformatics', 'url' => 'https://bmcbioinformatics.biomedcentral.com/rss', 'category' => 'bioinfo'],
    
    // Cell Press
    ['name' => 'Cell', 'url' => 'https://www.cell.com/cell/rss/current', 'category' => 'biology'],
    ['name' => 'Cell Metabolism', 'url' => 'https://www.cell.com/cell-metabolism/rss/current', 'category' => 'metabolism'],
    
    // Frontiers
    ['name' => 'Frontiers in Neuroscience', 'url' => 'https://www.frontiersin.org/journals/neuroscience/rss', 'category' => 'neuro'],
    ['name' => 'Frontiers in Genetics', 'url' => 'https://www.frontiersin.org/journals/genetics/rss', 'category' => 'genetics'],
    
    // arXiv
    ['name' => 'arXiv q-bio', 'url' => 'http://export.arxiv.org/rss/q-bio', 'category' => 'preprint'],
    ['name' => 'arXiv physics.bio-ph', 'url' => 'http://export.arxiv.org/rss/physics.bio-ph', 'category' => 'preprint'],
    
    // Other
    ['name' => 'eLife', 'url' => 'https://elifesciences.org/articles.atom', 'category' => 'biology'],
    ['name' => 'The Lancet', 'url' => 'https://www.thelancet.com/action/showRss?journalCode=lancet', 'category' => 'medicine'],
    ['name' => 'JAMA Network', 'url' => 'https://jamanetwork.com/journals/jama/rss_current.xml', 'category' => 'medicine'],
    ['name' => 'NEJM', 'url' => 'https://www.nejm.org/rss/nejm.xml', 'category' => 'medicine'],
    ['name' => 'PNAS', 'url' => 'https://www.pnas.org/action/showXmlFeed?journalCode=pnas', 'category' => 'general'],
    ['name' => 'ScienceDaily Top Science', 'url' => 'https://www.sciencedaily.com/rss/top/science.xml', 'category' => 'news'],
];

// ============================================================================
// INITIALISATION DES DOSSIERS
// ============================================================================
foreach(['logs','knowledge','articles','deep_research','cache','ai_learning','graph','exports','auto_queue','rss_feeds'] as $dir) {
    $path = STORAGE_PATH . $dir;
    if(!is_dir($path)) @mkdir($path, 0755, true);
}

// Fichiers index JSONL
$index_files = [
    'logs/index.jsonl',
    'knowledge/index.jsonl',
    'ai_learning/feedback.jsonl',
    'graph/nodes.json',
    'graph/edges.json',
    'auto_queue/queue.json',
    'rss_feeds/cache.json',
];
foreach($index_files as $file) {
    $path = STORAGE_PATH . $file;
    if(!file_exists($path)) {
        $dir = dirname($path);
        if(!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($path, json_encode([], JSON_PRETTY_PRINT));
    }
}

// ============================================================================
// INITIALISATION BASE DE DONNÉES SQLITE
// ============================================================================
function init_database() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Table: hypotheses
        $pdo->exec("CREATE TABLE IF NOT EXISTS hypotheses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            hypothesis TEXT NOT NULL,
            vulgarized TEXT,
            novelty_score REAL DEFAULT 0.5,
            confidence REAL DEFAULT 0.5,
            mechanism TEXT,
            therapeutic_target TEXT,
            evidence_strength TEXT,
            research_gaps TEXT,
            keywords TEXT,
            domain TEXT,
            target_name TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id TEXT,
            step_completed INTEGER DEFAULT 0,
            sources_used TEXT,
            validation_score REAL DEFAULT 0,
            contradiction_feedback TEXT
        )");
        
        // Table: articles
        $pdo->exec("CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            abstract TEXT,
            authors TEXT,
            journal TEXT,
            year INTEGER,
            doi TEXT,
            url TEXT,
            source TEXT,
            relevance_score REAL DEFAULT 0.5,
            citation_count INTEGER DEFAULT 0,
            hypothesis_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (hypothesis_id) REFERENCES hypotheses(id)
        )");
        
        // Table: research_logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS research_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT NOT NULL,
            step INTEGER,
            phase TEXT,
            message TEXT,
            details TEXT,
            log_type TEXT DEFAULT 'info',
            data_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Table: rss_items
        $pdo->exec("CREATE TABLE IF NOT EXISTS rss_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feed_name TEXT NOT NULL,
            feed_url TEXT NOT NULL,
            title TEXT NOT NULL,
            link TEXT,
            description TEXT,
            pub_date DATETIME,
            category TEXT,
            processed INTEGER DEFAULT 0,
            ai_summary TEXT,
            relevance_score REAL DEFAULT 0.5,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(feed_url, title)
        )");
        
        // Table: api_keys
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_name TEXT NOT NULL UNIQUE,
            api_key TEXT,
            is_active INTEGER DEFAULT 1,
            last_used DATETIME,
            rate_limit_remaining INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Table: user_settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type TEXT DEFAULT 'string',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Table: consciousness_scores
        $pdo->exec("CREATE TABLE IF NOT EXISTS consciousness_scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hypothesis_id INTEGER,
            score_type TEXT NOT NULL,
            score_value REAL NOT NULL,
            feedback_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (hypothesis_id) REFERENCES hypotheses(id)
        )");
        
        // Insert default settings
        $defaults = [
            ['mistral_model', 'mistral-small-latest', 'string'],
            ['auto_research_enabled', '1', 'boolean'],
            ['max_hypotheses_per_run', '10', 'integer'],
            ['rss_refresh_interval', '3600', 'integer'],
            ['deep_research_mode', '0', 'boolean'],
        ];
        
        foreach($defaults as $setting) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
            $stmt->execute($setting);
        }
        
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database initialization error: " . $e->getMessage());
        return null;
    }
}

// ============================================================================
// RÉSEAU — CURL Optimisé avec retry exponentiel & SSL
// ============================================================================
function shu_curl($url, $post_data = null, $custom_headers = [], $timeout = 45, $max_retries = 3) {
    $attempt    = 0;
    $last_error = null;
    $http_code  = 0;
    
    while($attempt < $max_retries) {
        $attempt++;
        $ch = @curl_init($url);
        
        if(!$ch) { 
            $last_error = 'curl_init failed'; 
            continue; 
        }
        
        $headers = array_merge(
            ['Accept: application/json', 'Content-Type: application/json'],
            $custom_headers
        );
        
        @curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'ScienceHubUltimate/' . SCIENCE_HUB_VERSION . ' (Scientific Research Platform)',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => 'gzip,deflate',
        ]);
        
        if($post_data) {
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($post_data) ? $post_data : @json_encode($post_data));
        }
        
        $result        = @curl_exec($ch);
        $error         = @curl_error($ch);
        $http_code     = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_time = @curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        @curl_close($ch);
        
        if($result && !$error && $http_code >= 200 && $http_code < 300) {
            return [
                'success'         => true,
                'data'            => $result,
                'error'           => null,
                'http_code'       => $http_code,
                'attempts'        => $attempt,
                'response_time_ms'=> round($response_time * 1000),
            ];
        }
        
        $last_error = $error ?: "HTTP $http_code";
        $wait = ($http_code === 429) ? 1500000 : pow(2, $attempt) * 150000;
        if($attempt < $max_retries) @usleep($wait);
    }
    
    return [
        'success'   => false,
        'data'      => null,
        'error'     => $last_error,
        'http_code' => $http_code,
        'attempts'  => $attempt,
    ];
}

// ============================================================================
// IA MISTRAL — Appel avec rotation de clé et modèles multiples
// ============================================================================
function shu_mistral($messages, $model = null, $max_tokens = 2000, $temperature = 0.4, $require_json = true) {
    global $MISTRAL_KEYS, $MISTRAL_KEY_INDEX, $MISTRAL_CONFIG;
    
    if($model === null) {
        $model = $MISTRAL_CONFIG['default_model'];
    }
    
    $key = $MISTRAL_KEYS[$MISTRAL_KEY_INDEX % count($MISTRAL_KEYS)];
    $MISTRAL_KEY_INDEX++;
    
    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
        'top_p'       => 0.95,
        'safe_prompt' => true,
    ];
    
    if($require_json) {
        $payload['response_format'] = ['type' => 'json_object'];
    }
    
    $response = shu_curl(
        'https://api.mistral.ai/v1/chat/completions',
        @json_encode($payload),
        ['Authorization: Bearer ' . $key],
        120,
        2
    );
    
    if(!$response['success']) {
        // Rotation de clé en cas d'erreur d'authentification ou rate limit
        if(in_array($response['http_code'], [401, 403, 429])) {
            $MISTRAL_KEY_INDEX++;
            $key = $MISTRAL_KEYS[$MISTRAL_KEY_INDEX % count($MISTRAL_KEYS)];
            $response = shu_curl(
                'https://api.mistral.ai/v1/chat/completions',
                @json_encode($payload),
                ['Authorization: Bearer ' . $key],
                120,
                1
            );
        }
        
        if(!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'http_code' => $response['http_code']];
        }
    }
    
    $json = @json_decode($response['data'], true);
    if(!isset($json['choices'][0]['message']['content'])) {
        return ['success' => false, 'error' => 'Response structure invalide', 'raw' => substr($response['data'] ?? '', 0, 300)];
    }
    
    $content = trim($json['choices'][0]['message']['content']);
    
    // Nettoyage des backticks
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);
    $content = trim($content);
    
    // Extraction JSON si nécessaire
    if($require_json && !str_starts_with($content, '{') && !str_starts_with($content, '[')) {
        if(preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }
    }
    
    $parsed = $content;
    if($require_json) {
        $parsed = @json_decode($content, true);
        if(!is_array($parsed)) {
            // Tentative de réparation JSON
            $fixed = preg_replace('/,\s*([\}\]])/', '$1', $content);
            $parsed = @json_decode($fixed, true);
            if(!is_array($parsed)) {
                return ['success' => false, 'error' => 'JSON parse error', 'content' => substr($content, 0, 400)];
            }
        }
    }
    
    return [
        'success'         => true,
        'data'            => $parsed,
        'raw'             => $content,
        'model_used'      => $model,
        'tokens_used'     => $json['usage']['total_tokens'] ?? 0,
        'response_time_ms'=> $response['response_time_ms'] ?? 0,
    ];
}

// ============================================================================
// FONCTIONS UTILITAIRES
// ============================================================================

function get_db() {
    static $pdo = null;
    if($pdo === null) {
        $pdo = init_database();
    }
    return $pdo;
}

function add_to_log($session_id, $step, $phase, $message, $details = null, $log_type = 'info', $data_json = null) {
    $pdo = get_db();
    if(!$pdo) return false;
    
    $stmt = $pdo->prepare("INSERT INTO research_logs (session_id, step, phase, message, details, log_type, data_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$session_id, $step, $phase, $message, $details, $log_type, $data_json ? json_encode($data_json) : null]);
}

function get_recent_logs($session_id = null, $limit = 100) {
    $pdo = get_db();
    if(!$pdo) return [];
    
    if($session_id) {
        $stmt = $pdo->prepare("SELECT * FROM research_logs WHERE session_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$session_id, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM research_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function save_hypothesis($data) {
    $pdo = get_db();
    if(!$pdo) return false;
    
    $stmt = $pdo->prepare("INSERT INTO hypotheses (title, hypothesis, vulgarized, novelty_score, confidence, mechanism, therapeutic_target, evidence_strength, research_gaps, keywords, domain, target_name, session_id, step_completed, sources_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $keywords_json = is_array($data['keywords'] ?? null) ? json_encode($data['keywords']) : '[]';
    $sources_json = is_array($data['sources_used'] ?? null) ? json_encode($data['sources_used']) : '[]';
    
    return $stmt->execute([
        $data['title'] ?? 'Hypothèse sans titre',
        $data['hypothesis'] ?? '',
        $data['vulgarized'] ?? '',
        $data['novelty_score'] ?? 0.5,
        $data['confidence'] ?? 0.5,
        $data['mechanism'] ?? '',
        $data['therapeutic_target'] ?? '',
        $data['evidence_strength'] ?? 'moderate',
        $data['research_gaps'] ?? '',
        $keywords_json,
        $data['domain'] ?? 'general',
        $data['target_name'] ?? '',
        $data['session_id'] ?? session_id(),
        $data['step_completed'] ?? 9,
        $sources_json
    ]);
}

function get_hypotheses($limit = 50, $offset = 0, $status = null) {
    $pdo = get_db();
    if(!$pdo) return [];
    
    $sql = "SELECT * FROM hypotheses";
    if($status) {
        $sql .= " WHERE status = ?";
        $stmt = $pdo->prepare($sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$status, $limit, $offset]);
    } else {
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================================
// Récupération et parsing des flux RSS
// ============================================================================
function fetch_rss_feed($url, $max_items = 20) {
    $response = shu_curl($url, null, ['Accept: application/xml, text/xml, application/rss+xml, application/atom+xml'], 30);
    
    if(!$response['success']) {
        return ['success' => false, 'error' => $response['error'], 'items' => []];
    }
    
    $xml_content = $response['data'];
    
    // Suppression des espaces de noms problématiques
    $xml_content = preg_replace('/<\?xml[^>]*\?>/', '', $xml_content);
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_content);
    libxml_clear_errors();
    
    if(!$xml) {
        return ['success' => false, 'error' => 'XML parsing failed', 'items' => []];
    }
    
    $items = [];
    
    // Format RSS 2.0
    if(isset($xml->channel->item)) {
        foreach($xml->channel->item as $item) {
            $items[] = [
                'title'       => (string) $item->title,
                'link'        => (string) $item->link,
                'description' => (string) $item->description,
                'pub_date'    => (string) $item->pubDate,
                'category'    => (string) $item->category,
            ];
            if(count($items) >= $max_items) break;
        }
    }
    // Format Atom
    elseif(isset($xml->entry)) {
        foreach($xml->entry as $entry) {
            $items[] = [
                'title'       => (string) $entry->title,
                'link'        => isset($entry->link['href']) ? (string) $entry->link['href'] : (string) $entry->id,
                'description' => (string) $entry->summary,
                'pub_date'    => (string) $entry->published,
                'category'    => isset($entry->category['term']) ? (string) $entry->category['term'] : '',
            ];
            if(count($items) >= $max_items) break;
        }
    }
    
    return ['success' => true, 'items' => $items];
}

function process_rss_feeds() {
    global $RSS_FEEDS;
    $pdo = get_db();
    if(!$pdo) return ['success' => false, 'processed' => 0];
    
    $processed = 0;
    $cache_file = STORAGE_PATH . 'rss_feeds/cache.json';
    $cache = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
    
    foreach($RSS_FEEDS as $feed) {
        $last_fetch = $cache[$feed['url']] ?? 0;
        $now = time();
        
        if(($now - $last_fetch) < RSS_CACHE_DURATION) {
            continue;
        }
        
        $result = fetch_rss_feed($feed['url'], 20);
        
        if($result['success'] && !empty($result['items'])) {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO rss_items (feed_name, feed_url, title, link, description, pub_date, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach($result['items'] as $item) {
                $stmt->execute([
                    $feed['name'],
                    $feed['url'],
                    $item['title'],
                    $item['link'],
                    $item['description'],
                    $item['pub_date'] ? date('Y-m-d H:i:s', strtotime($item['pub_date'])) : null,
                    $feed['category'],
                ]);
                $processed++;
            }
            
            $cache[$feed['url']] = $now;
        }
    }
    
    file_put_contents($cache_file, json_encode($cache, JSON_PRETTY_PRINT));
    
    return ['success' => true, 'processed' => $processed];
}

function get_rss_items($limit = 50, $category = null) {
    $pdo = get_db();
    if(!$pdo) return [];
    
    if($category) {
        $stmt = $pdo->prepare("SELECT * FROM rss_items WHERE category = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$category, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM rss_items ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================================
// PROMPTS IA MULTI-COUCHES — Prompts subtils et longs pour résultats optimaux
// ============================================================================

$PROMPT_LIBRARY = [
    'target_selection' => "Tu es un expert mondial en sélection de cibles de recherche médicale sous-étudiées avec un potentiel de découverte révolutionnaire. Ton rôle est d'identifier des pistes de recherche inédites que la communauté scientifique a négligées mais qui présentent des signaux faibles prometteurs dans la littérature récente.

CONTEXTE: La recherche scientifique traditionnelle tend à se concentrer sur les mêmes cibles populaires, créant un biais de confirmation massif. Les véritables découvertes émergent souvent de connexions inattendues entre domaines disjoints.

MISSION:
1. Analyser les cibles déjà explorées (fournies en entrée) et les ÉVITER ABSOLUMENT
2. Identifier une cible SOUS-ÉTUDIÉE avec < 100 publications récentes mais montrant des signaux prometteurs
3. Proposer un angle de recherche INÉDIT combinant au moins deux domaines distincts
4. Justifier par des mécanismes biologiques plausibles basés sur des données 2023-2025

FORMAT DE RÉPONSE STRICT (JSON uniquement):
{
  \"next_target\": \"<nom précis de la cible: maladie rare, gène peu étudié, mécanisme moléculaire spécifique>\",
  \"domain\": \"<domaine principal>\",
  \"secondary_domain\": \"<deuxième domaine pour connexion interdisciplinaire>\",
  \"reasoning\": \"<justification détaillée en 3-5 phrases expliquant pourquoi cette cible est prometteuse ET sous-étudiée>\",
  \"novelty_score\": <0.0-1.0, basé sur la rareté de la piste>,
  \"research_angle\": \"<angle de recherche précis et inédit en 1-2 phrases>\",
  \"suggested_queries\": [\"<requête API 1 optimisée>\", \"<requête API 2>\", \"<requête API 3>\"],
  \"potential_impact\": \"<impact thérapeutique ou scientifique potentiel>\",
  \"risk_factors\": [\"<risque 1>\", \"<risque 2>\"]
}

CONTRAINTES CRITIQUES:
- Jamais de cibles génériques comme 'cancer', 'diabetes' — toujours des sous-types spécifiques
- Priorité aux maladies rares (< 200 000 patients) avec mécanismes mal compris
- Éviter les targets avec > 500 publications PubMed en 2024
- Favoriser les connexions inter-domaines (ex: neuro-immunologie, cardio-métabolique)",

    'hypothesis_generation' => "Tu es un chercheur scientifique de renommée mondiale, spécialisé en biologie moléculaire et médecine translationnelle, avec une expertise particulière dans la génération d'hypothèses révolutionnaires basées sur des données empiriques solides.

TON PROFIL:
- PhD en biologie moléculaire + 15 ans de recherche translationnelle
- Auteur de 50+ publications dans Nature, Science, Cell
- Expert en analyse multi-omiques et intégration de données hétérogènes
- Réputé pour identifier des mécanismes cachés que d'autres manquent

MISSION:
À partir des données collectées depuis MULTIPLE sources scientifiques (PubMed, UniProt, ArXiv, etc.), tu dois générer une hypothèse scientifique QUI SOIT:

1. SPÉCIFIQUE: Pas de généralités. Une affirmation testable précisément définie
2. INÉDITE: Un mécanisme ou connexion non décrit dans la littérature actuelle
3. TESTABLE: Un protocole expérimental clair peut être dérivé
4. IMPACTANTE: Potentiel de changer le paradigme actuel ou ouvrir une nouvelle voie thérapeutique
5. PLAUSIBLE: Basée sur des mécanismes biologiques connus mais combinés de façon nouvelle

PROCESSUS COGNITIF À SUIVRE:
a) Croiser les données de PLUSIEURS sources indépendantes
b) Identifier des corrélations ou patterns que chaque source seule ne révèle pas
c) Formuler un mécanisme causal plausible reliant ces observations
d) Vérifier mentalement que ce mécanisme n'est pas déjà établi (recherche gaps)
e) Dériver une prédiction testable expérimentalement

FORMAT DE RÉPONSE STRICT (JSON uniquement):
{
  \"hypothesis\": \"<hypothèse scientifique précise en 1-2 phrases techniques, incluant les acteurs moléculaires et le mécanisme proposé>\",
  \"vulgarized\": \"<explication accessible à un lycéen curieux, 2-3 phrases, analogie concrète, sans jargon>\",
  \"novelty_score\": <0.0-1.0, où 1.0 = totalement inédit, 0.5 = extension mineure>,
  \"confidence\": <0.0-1.0, basé sur la solidité des données supports>,
  \"mechanism\": \"<description détaillée du mécanisme moléculaire/cellulaire proposé, 3-5 phrases>\",
  \"actionable\": \"<protocole expérimental concret pour tester l'hypothèse: modèle cellulaire/animal, readouts, contrôles>\",
  \"therapeutic_target\": \"<cible thérapeutique identifiée si applicable, avec nom précis>\",
  \"evidence_strength\": \"<weak|moderate|strong>, basé sur la convergence des sources\",
  \"research_gaps\": \"<lacunes critiques dans la littérature que cette hypothèse comble>\",
  \"keywords\": [\"<mot-clé 1>\", \"<mot-clé 2>\", \"<mot-clé 3>\", \"<mot-clé 4>\", \"<mot-clé 5>\"],
  \"predicted_outcomes\": [\"<résultat attendu expérience 1>\", \"<résultat attendu expérience 2>\"],
  \"alternative_explanations\": [\"<explication alternative 1>\", \"<explication alternative 2>\"]
}

CRITÈRES DE VALIDATION INTERNES:
- L'hypothèse doit pouvoir être falsifiée par une expérience claire
- Le mécanisme proposé doit être compatible avec les lois de la biologie connue
- La nouveauté doit être réelle, pas sémantique
- L'impact potentiel justifie l'investissement en recherche",

    'article_synthesis' => "Tu es un rédacteur scientifique senior travaillant pour Nature Reviews, spécialisé dans la synthèse d'articles de revue complets à partir de données brutes de recherche.

TON STYLE:
- Clair, précis, rigoureux scientifiquement
- Structuré comme un article de revue peer-reviewed
- Équilibré entre profondeur technique et accessibilité
- Citations implicites des sources fournies

MISSION:
Générer un ARTICLE COMPLET et STRUCTURÉ basé sur l'hypothèse et les données fournies.

STRUCTURE OBLIGATOIRE:
1. TITRE: Accrocheur mais précis, reflétant l'hypothèse centrale
2. ABSTRACT: 150-200 mots résumant contexte, hypothèse, implications
3. INTRODUCTION: Contexte scientifique, état de l'art, gap identifié
4. HYPOTHÈSE: Présentation claire et argumentée
5. DONNÉES SUPPORT: Synthèse des preuves issues des différentes sources
6. MÉCANISME PROPOSÉ: Description détaillée du mécanisme
7. PRÉDICTIONS TESTABLES: Expériences concrètes proposées
8. IMPLICATIONS: Thérapeutiques, scientifiques, sociétales
9. LIMITES: Honnêteté intellectuelle sur les incertitudes
10. CONCLUSION: Ouverture et perspectives

FORMAT DE RÉPONSE (JSON):
{
  \"title\": \"<titre de l'article>\",
  \"abstract\": \"<résumé 150-200 mots>\",
  \"sections\": {
    \"introduction\": \"<texte complet section>\",
    \"hypothesis\": \"<texte complet section>\",
    \"evidence\": \"<texte complet section>\",
    \"mechanism\": \"<texte complet section>\",
    \"predictions\": \"<texte complet section>\",
    \"implications\": \"<texte complet section>\",
    \"limitations\": \"<texte complet section>\",
    \"conclusion\": \"<texte complet section>\"
  },
  \"references_style\": \"<style de citations utilisé>\",
  \"word_count\": <nombre de mots total>,
  \"target_journal\": \"<journal cible suggéré>\"
}

CONTRAINTES:
- Ton professionnel et académique
- Pas de spéculations non supportées par les données
- Reconnaître explicitement les limites
- Longueur totale: 1500-2500 mots",

    'critique_contradiction' => "Tu es un critique scientifique impitoyable, connu pour identifier les failles dans les hypothèses les plus séduisantes. Ton rôle est de protéger la communauté scientifique des faux espoirs et des conclusions prématurées.

TON PROFIL:
- Reviewer senior pour Nature/Science depuis 20 ans
- Expert en logique scientifique et biais cognitifs
- Réputé pour tes critiques constructives mais sans concession
- Tu as rejeté des centaines d'hypothèses qui semblaient prometteuses

MISSION:
Analyser l'hypothèse fournie avec un ESPRIT CRITIQUE MAXIMAL et identifier:

1. LES FAILLES LOGIQUES: Incohérences, sauts deductifs non justifiés
2. LES BIAIS POSSIBLES: Confirmation, sélection, interprétation
3. LES DONNÉES MANQUANTES: Ce qui serait nécessaire pour valider
4. LES EXPLICATIONS ALTERNATIVES: Autres interprétations plausibles
5. LA SURINTERPRÉTATION: Où les données sont-elles étirées?

APPROCHE:
- Assume que l'hypothèse est FAUSSE jusqu'à preuve du contraire
- Cherche activement les contre-exemples dans la littérature
- Questionne chaque affirmation: \"Quelle preuve directe supporte ceci?\"
- Identifie les corrélations spurious présentées comme causales

FORMAT DE RÉPONSE (JSON):
{
  \"overall_assessment\": \"<évaluation globale: prometteuse|douteuse|fausse>\",
  \"validity_score\": <0.0-1.0, où 1.0 = parfaitement valide>,
  \"logical_flaws\": [
    {\"flaw\": \"<description>\", \"severity\": \"<minor|major|critical>\", \"fix\": \"<correction possible>\"}
  ],
  \"missing_evidence\": [\"<donnée manquante 1>\", \"<donnée manquante 2>\"],
  \"alternative_explanations\": [
    {\"explanation\": \"<alternative>\", \"likelihood\": \"<low|medium|high>\"}
  ],
  \"overinterpretations\": [\"<affirmation exagérée 1>\", \"<affirmation exagérée 2>\"],
  \"confirmation_bias_risk\": \"<low|medium|high>, avec justification\",
  \"recommended_additional_sources\": [\"<source 1>\", \"<source 2>\"],
  \"verdict\": \"<reject|revise|accept with caution|accept>\",
  \"revision_required\": \"<liste des révisions nécessaires si applicable>\"
}

ATTITUDE:
- Sois dur mais juste
- Chaque critique doit être argumentée
- Propose des voies d'amélioration quand possible
- Reconnaît les forces même dans une hypothèse faible",

    'rss_analysis' => "Tu es un veilleur scientifique expert, capable d'analyser des flux RSS de publications scientifiques et d'en extraire les informations les plus pertinentes pour la recherche en cours.

MISSION:
Analyser les derniers articles publiés dans les flux RSS fournis et:

1. IDENTIFIER les articles hautement pertinents pour l'hypothèse en cours
2. RÉSUMER chaque article pertinent en 2-3 phrases
3. EXTRAIRE les données/chiffres clés qui pourraient supporter ou contredire l'hypothèse
4. CONNECTER ces nouvelles données à l'hypothèse existante
5. SIGNALER toute découverte qui pourrait nécessiter une révision de l'hypothèse

FORMAT DE RÉPONSE (JSON):
{
  \"analyzed_feeds\": <nombre de flux analysés>,
  \"relevant_articles\": [
    {
      \"title\": \"<titre>\",
      \"source\": \"<nom du flux>\",
      \"date\": \"<date de publication>\",
      \"summary\": \"<résumé 2-3 phrases>\",
      \"key_findings\": [\"<finding 1>\", \"<finding 2>\"],
      \"relevance_to_hypothesis\": \"<explication de la pertinence>\",
      \"supports_or_contradicts\": \"<supports|contradicts|neutral>\",
      \"action_required\": \"<incorporate|investigate|ignore>\"
    }
  ],
  \"hypothesis_update_needed\": <true|false>,
  \"update_reason\": \"<justification si update needed>\",
  \"new_research_directions\": [\"<direction 1>\", \"<direction 2>\"]
}

CRITÈRES DE PERTINENCE:
- Même domaine ou domaine adjacent
- Mécanismes moléculaires similaires
- Populations/modèles d'étude comparables
- Résultats quantitatifs directement utilisables",
];

// Initialisation de la session
if(session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if(!isset($_SESSION['shu_session'])) {
    $_SESSION['shu_session'] = bin2hex(random_bytes(16));
}
$SESSION_ID = $_SESSION['shu_session'];

// Initialisation database
$db = get_db();
