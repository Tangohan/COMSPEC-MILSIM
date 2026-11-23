# SSE : registres, liens et exports

## Corrections

- Une personne créée par « Créer un objet » reçoit une fiche `sse_persons` et un nœud d’investigation portant sa référence. Les caractéristiques et la photo suivent ; une simple désignation reste un alias et n’invente pas d’état civil.
- Les rattachements enrichis se consultent depuis leurs deux extrémités. Les liens historiques `sse_interest_case_links` alimentent maintenant le graphe commun. Un dossier d’intérêt peut être relié à un dossier constitué même avant l’étape des rattachements enrichis.
- La fiche sujet propose la sélection d’une organisation existante. La fiche identité expose les observations de sa fiche personne liée, les contrôles SEEK II et les dossiers accessibles associés.
- La création, la modification et l’actualisation du Lore utilisent des paramètres SQL distincts : les paramètres `:now` répétés étaient incompatibles avec les préparations natives PDO MySQL.
- Les avis actifs disposent d’un écran de modification avec échéance, motif obligatoire et reprise facultative du signalement connu. L’état précédent et le nouvel état sont conservés dans la chronologie en ajout seul, avec auteur et date, dans la même transaction. Le circuit de validation est réappliqué s’il est configuré ; un avis clos ou expiré doit être renouvelé.
- Les pistes et investigations donnent accès au rédacteur de fiche existant. Le contexte est conservé après une erreur de saisie et la fiche enregistrée est rattachée à son origine.
- Le PDF du dossier d’intérêt comprend le pilotage, le signalement, la provenance, l’analyse, le graphe vectoriel paginé, les fiches liées, les brouillons accessibles, les relevés biométriques et SEEK II, les photos transmises, la discussion et l’historique. Discussion et historique sont lus par lots, sans l’ancienne coupure à 25 événements. Le graphe garde ses bornes de 6 niveaux / 200 éléments et signale sa troncature. Les données illisibles et les images manquantes ne sont pas reconstituées.

## Rattrapage des personnes déjà créées

Le code ne transforme pas les anciens nœuds pendant une simple consultation. Utiliser la commande explicite, d’abord en simulation :

```sh
php tools/sse-backfill-object-persons.php --tenant=7
php tools/sse-backfill-object-persons.php --tenant=7 --apply
```

Remplacer `7` par la communauté concernée. La commande ne traite que les nœuds personne sans référence, les verrouille et crée la fiche et son lien dans une transaction. Une seconde exécution ne crée pas une seconde fiche pour un nœud déjà rattrapé. Elle n’essaie pas de fusionner des homonymes.

Les liens historiques des dossiers d’intérêt sont lus directement : aucun rattrapage n’est nécessaire pour les faire apparaître dans le graphe.

## Vérification

Tests SQLite des rattachements, du Lore et du cycle des avis ; contrôles des anciens liens, des droits, des modifications concurrentes et du retour arrière si la chronologie ne peut pas être écrite. Tests d’export au-delà de 500 événements et 100 notes ; génération d’un vrai PDF avec photo et graphe sur plusieurs pages, puis contrôle visuel du rendu.

Le site distant redirige vers la porte d’accès SSE sans session authentifiée. Ces modifications et la commande de rattrapage n’ont pas été exécutées en production depuis cet environnement.
