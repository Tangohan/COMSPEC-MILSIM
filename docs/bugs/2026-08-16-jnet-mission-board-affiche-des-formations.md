# JNET — le mission board affichait des formations et des chiffres inventés

## Contexte

Portail JNET, page « Opérations » (`/jnet/operations`) et bloc « Opérations en cours » du tableau d’unité.

## Symptôme

Le mur des opérations listait des fiches qui ne sont pas des engagements sur le terrain : « BIENVENUE » et
« PARCOURS PORTAIL — BIEN UTILISER LE SITE », qui sont des activités de formation du tableau opérationnel.

Deux défauts s’ajoutaient : chaque carte affichait les mêmes éléments fictifs (ALPHA / BRAVO / ISR) quelle que
soit l’opération, et quatre compteurs (Personnel, Objectifs, PIR, Intel ouvert) figés à 0 parce qu’aucune de ces
valeurs n’existe en base. Quand aucune opération n’était ouverte, trois opérations de démonstration
(IRON VEIL, NIGHT SPEAR, BLUE DAGGER) prenaient la place, sans être signalées comme fictives.

## Cause

`JnetDashboardService::loadOperations()` interrogeait le tableau opérationnel sans filtrer la nature de la fiche
(`entry_type`), alors que ce champ distingue mission, manifestation, formation, permanence, information, tâche
interne et flash. Toutes les fiches actives remontaient donc sur le mur des opérations.

Le reste de la carte était composé de valeurs écrites en dur dans le service, faute de données correspondantes
dans `planning_entries`.

## Correctif

- `PlanningEntryRepository::listForBoard()` accepte un filtre `entry_types` (liste de natures de fiche).
- Le mur JNET ne remonte plus que les natures qui constituent un engagement : `mission` et `manifestation`.
  Formations, permanences, informations, flashs et tâches internes restent sur leurs écrans.
- Les éléments ALPHA / BRAVO / ISR et les quatre compteurs à zéro sont remplacés par des faits réellement
  présents sur la fiche : période, zone, priorité, chef, et avancement des points de contrôle obligatoires.
- Suppression des opérations de démonstration au profit d’un état vide qui explique d’où viennent les
  opérations et renvoie vers le tableau opérationnel.
- Dans la fiche d’unité, une opération n’est rattachée à une sous-unité que si la fiche lui est explicitement
  destinée (`visibility_unit_id`), au lieu d’une répartition à tour de rôle qui attribuait des opérations au
  hasard.

## Fichiers touchés

- `app/Services/Jnet/JnetDashboardService.php`
- `app/Repositories/PlanningEntryRepository.php`
- `views/jnet/operations.php`
- `views/jnet/operation_show.php`
- `views/jnet/home.php`
- `views/jnet/_layout.php` (version de la feuille de style)
- `public/assets/css/jnet_portal.css`

## Vérification

`php -l` sur les fichiers modifiés, puis rendu du mur des opérations dans un navigateur avec deux jeux de
données : deux opérations (les cartes affichent bien des faits distincts) et aucune opération (état vide
lisible, compteur « 0 engagement »).

## Statut

Corrigé.
