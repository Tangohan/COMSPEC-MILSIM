# SSE — Documentation fonctionnelle et produit

## Objectif

SSE-DI transforme un signal brut (individu inconnu, alias, visage, biométrie partielle, véhicule, téléphone, lieu ou association) en dossier de travail traçable. Il couvre les dossiers d’intérêt, personnes consolidées, sites, matériels, véhicules, relations, croisements, collectes, validations et chronologie.

Le sous-module **Exploitation numérique** (`ATH-SSE-LABNUM`) complète ce périmètre pour les supports saisis : enregistrement, acquisition simulée ou import documentaire, visionneuse, analyses, chronologie numérique, graphe et rapports — sans jamais conclure automatiquement. Voir [présentation du laboratoire numérique](../08-exploitation-numerique/presentation.md).

## Cycle

1. Recevoir et qualifier la source.
2. Séparer faits, hypothèses et contradictions.
3. Émettre des besoins de collecte vers le terrain.
4. Produire des propositions de rapprochement explicables.
5. Faire décider un opérateur habilité.
6. Consolider, infirmer, classer sans suite ou archiver.

Le périmètre initial livré couvre la création et la consultation des quatre blocs du dossier d’intérêt. Les médias, biométries, moteur asynchrone, consolidation et échanges ATAK sont des extensions à implémenter selon l’architecture documentée, jamais des automatismes implicites.
