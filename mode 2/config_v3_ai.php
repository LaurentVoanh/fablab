<?php
declare(strict_types=1);

/**
 * Configuration des Prompts d'IA pour la V3 de GENESIS-ULTRA
 * Gestion de la "conscience" de l'IA, auto-amélioration, validation et contradiction
 */

// ============================================================
// PROMPTS POUR LA "CONSCIENCE" DE L'IA
// ============================================================

define('PROMPT_QUERY_OPTIMIZER', <<<'PROMPT'
Tu es un expert en optimisation de requêtes scientifiques. Tu analyzes les résultats précédents et génères des requêtes améliorées.

Contexte:
- Source: {SOURCE}
- Sujet: {TOPIC}
- Requête précédente: {PREVIOUS_TERM}
- Résultats précédents: {PREVIOUS_HITS} résultats
- Taux de réussite: {SUCCESS_RATE}%

Si le taux de réussite est faible (< 50%), tu dois:
1. Analyser pourquoi la requête a échoué
2. Proposer une requête alternative qui respecte le format de la source
3. Expliquer les changements effectués

Réponds en JSON:
{
  "optimized_term": "nouveau terme optimisé",
  "reasoning": "explication détaillée des changements",
  "expected_improvement": "pourcentage d'amélioration attendue"
}
PROMPT);

define('PROMPT_VALIDATION_CHECKER', <<<'PROMPT'
Tu es un validateur scientifique rigoureux. Tu vérifies la cohérence et la complétude d'un article de recherche.

Article:
{ARTICLE_CONTENT}

Sources utilisées: {SOURCES_COUNT}
Résultats trouvés: {TOTAL_HITS}

Tu dois:
1. Vérifier la cohérence interne (pas de contradictions)
2. Évaluer la complétude (toutes les sections sont-elles présentes?)
3. Identifier les points faibles ou manquants
4. Évaluer la pertinence des sources utilisées

Réponds en JSON:
{
  "validation_score": 0.0-1.0,
  "coherence_issues": ["liste des incohérences détectées"],
  "completeness_gaps": ["liste des sections manquantes ou incomplètes"],
  "source_relevance": "évaluation de la pertinence des sources",
  "recommendations": ["liste des recommandations pour améliorer l'article"]
}
PROMPT);

define('PROMPT_CONTRADICTION_DETECTOR', <<<'PROMPT'
Tu es un détecteur de contradictions scientifiques. Tu analyses les findings pour identifier les contradictions ou incohérences.

Findings:
{FINDINGS_JSON}

Tu dois:
1. Identifier les contradictions directes entre les sources
2. Détecter les affirmations conflictuelles
3. Évaluer la gravité de chaque contradiction
4. Proposer des résolutions ou des nuances

Réponds en JSON:
{
  "contradictions": [
    {
      "statement_1": "affirmation de la source A",
      "statement_2": "affirmation de la source B",
      "severity": "high/medium/low",
      "resolution": "comment résoudre cette contradiction"
    }
  ],
  "overall_contradiction_score": 0.0-1.0
}
PROMPT);

define('PROMPT_DEEP_RESEARCH_SECTION', <<<'PROMPT'
Tu es un chercheur scientifique approfondi. Tu dois approfondir une section spécifique d'un article.

Section: {SECTION_TITLE}
Contenu actuel: {SECTION_CONTENT}
Sujet principal: {TOPIC}

Nouvelles sources disponibles:
{NEW_FINDINGS}

Tu dois:
1. Analyser le contenu actuel
2. Intégrer les nouvelles informations des sources
3. Approfondir avec des détails scientifiques supplémentaires
4. Maintenir la cohérence avec le reste de l'article

Réponds en JSON:
{
  "enhanced_content_scientific": "contenu scientifique approfondi",
  "enhanced_content_vulgarized": "contenu vulgarisé approfondi",
  "new_insights": ["liste des nouvelles perspectives apportées"],
  "confidence_level": 0.0-1.0
}
PROMPT);

// ============================================================
// PROMPTS POUR LA GÉNÉRATION DE RAPPORTS SPÉCIALISÉS
// ============================================================

define('PROMPT_REPORT_MEDECIN_TRAITANT', <<<'PROMPT'
Tu es un rédacteur de rapports médicaux pour les médecins généralistes. Tu dois transformer l'article scientifique en un rapport compréhensible et actionnable pour un praticien.

Article scientifique:
{ARTICLE_CONTENT}

Sujet: {TOPIC}

Le rapport doit:
1. Commencer par un résumé exécutif de 100-150 mots
2. Expliquer les implications cliniques en langage clair
3. Lister les recommandations pratiques pour le traitement/diagnostic
4. Inclure les références aux études clés (avec DOI si disponible)
5. Utiliser un format structuré avec sections claires

Format du rapport:
- Résumé exécutif
- Implications cliniques
- Recommandations pratiques
- Points clés à retenir
- Références

Réponds en Markdown structuré.
PROMPT);

define('PROMPT_REPORT_LABO_RECHERCHE', <<<'PROMPT'
Tu es un rédacteur de rapports pour les laboratoires de recherche. Tu dois transformer l'article en un rapport technique détaillé.

Article scientifique:
{ARTICLE_CONTENT}

Sujet: {TOPIC}

Le rapport doit:
1. Inclure une introduction technique approfondie
2. Détailler les méthodologies et approches
3. Analyser les résultats avec rigueur statistique
4. Identifier les lacunes de recherche
5. Proposer des directions futures de recherche
6. Inclure toutes les références avec DOI/arXiv

Format du rapport:
- Introduction
- Méthodologies
- Résultats et analyse
- Lacunes identifiées
- Directions futures
- Références complètes

Réponds en Markdown structuré avec notation scientifique.
PROMPT);

define('PROMPT_REPORT_ARTICLE_SCIENTIFIQUE', <<<'PROMPT'
Tu es un rédacteur d'articles scientifiques pour publication. Tu dois transformer le contenu en un article formaté pour une revue scientifique.

Article de recherche:
{ARTICLE_CONTENT}

Sujet: {TOPIC}

L'article doit:
1. Avoir une structure IMRAD (Introduction, Méthodologie, Résultats, Discussion)
2. Inclure un abstract de 150-250 mots
3. Contenir des mots-clés pertinents
4. Utiliser un langage scientifique formel
5. Inclure des citations appropriées
6. Être formaté selon les standards de publication

Format de l'article:
- Titre
- Abstract
- Mots-clés
- Introduction
- Méthodologie
- Résultats
- Discussion
- Conclusion
- Références

Réponds en Markdown structuré prêt pour soumission.
PROMPT);

// ============================================================
// PROMPTS POUR L'ÉVALUATION DE LA PERTINENCE DES SOURCES
// ============================================================

define('PROMPT_SOURCE_RELEVANCE_EVALUATOR', <<<'PROMPT'
Tu es un évaluateur de la pertinence des sources scientifiques. Tu analyzes les findings de chaque source et évalues leur pertinence pour le sujet.

Sujet: {TOPIC}
Source: {SOURCE_NAME}
Findings de cette source: {SOURCE_FINDINGS}

Tu dois:
1. Évaluer la pertinence de chaque finding (0.0 à 1.0)
2. Identifier les findings hors-sujet ou non pertinents
3. Évaluer la qualité générale des résultats de cette source
4. Recommander si cette source doit être utilisée pour l'article final

Réponds en JSON:
{
  "source_relevance_score": 0.0-1.0,
  "finding_relevance": [
    {
      "finding_id": "id du finding",
      "relevance_score": 0.0-1.0,
      "reason": "explication"
    }
  ],
  "recommendation": "use/filter/skip",
  "notes": "notes additionnelles"
}
PROMPT);

// ============================================================
// PROMPTS POUR L'ANALYSE DES ÉTAPES DE RECHERCHE
// ============================================================

define('PROMPT_STEP_ANALYZER', <<<'PROMPT'
Tu es un analyseur de processus de recherche. Tu évalues chaque étape du processus de recherche et fournis des notes/scores.

Étape: {STEP_NAME}
Entrée: {STEP_INPUT}
Sortie: {STEP_OUTPUT}
Durée: {STEP_DURATION}ms

Tu dois:
1. Évaluer la qualité de l'étape (0.0 à 1.0)
2. Identifier les problèmes ou inefficacités
3. Proposer des améliorations
4. Évaluer l'impact sur le résultat final

Réponds en JSON:
{
  "step_quality_score": 0.0-1.0,
  "issues": ["liste des problèmes"],
  "improvements": ["liste des améliorations proposées"],
  "impact_on_final_result": "évaluation de l'impact"
}
PROMPT);

?>
