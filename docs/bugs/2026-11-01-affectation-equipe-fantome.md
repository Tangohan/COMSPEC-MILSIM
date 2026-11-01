# Membre affecté sur sa fiche mais absent de tous les rosters

## Contexte
Effectifs, fiche membre → « Changer d’unité » (et affectation rapide du tableur). Retour d’un responsable : un membre
« impossible à mettre dans une équipe » après une affectation envoyée pendant que la page se rechargeait (page
d’affectation rouverte avant la fin de l’enregistrement).

## Symptôme
- La fiche affiche l’équipe et « Principale ».
- Pages Équipes / Groupes, effectif de l’unité, filtre « Sans unité » : le membre n’y est pas.
- Réaffecter le membre à la même équipe ne change rien.

## Cause
- L’appartenance était écrite à trois endroits : `personnel_assignments` (lu par la fiche), `user_units` (lu par les
  pages Équipes / Groupes et « Sans unité ») et `personnel_profiles.primary_unit_id` (tableur des effectifs), lus
  chacun par des écrans différents.
- `replaceActiveAssignmentsFromDossier` lisait l’état actuel **hors transaction** : une seconde requête passée entre
  cette lecture et l’écriture (formulaire rouvert et renvoyé) fermait toutes les lignes `user_units` mais pas
  l’affectation créée entre-temps → affectation active sans ligne d’effectif. Le même envoi en double créait deux
  affectations actives ; la seconde survivait ensuite à un changement d’équipe.
- Réenvoyer le formulaire ne réparait pas : une affectation identique était « gardée » et la ligne `user_units`
  manquante n’était jamais recréée.
- L’unité du dossier était écrite avant la transaction : une écriture qui échouait laissait la fiche « affectée ».
- `changePrimaryRoleName` (rôle dans le tableur) créait une affectation sans ligne d’effectif.

## Correctif
- Vérité unique : l’affectation active (`personnel_assignments`) ; `user_units` et l’unité du dossier en sont des
  copies réalignées dans la **même transaction** (`UnitAssignmentConsistency::syncMirrors`), y compris quand
  l’affectation existe déjà : renvoyer le formulaire débloque le membre.
- Écriture atomique et idempotente : transaction, membre verrouillé (`SELECT … FOR UPDATE` sur `users`), état relu
  sous le verrou ; un double envoi donne le même résultat qu’un seul, les doublons sont fermés.
- Même règle de lecture pour la fiche, les pages Équipes / Groupes (`getIdsByUnit`, `countActiveByUnits`),
  l’ORBAT et « Sans unité » : affectations actives, sinon anciennes lignes `user_units` du membre.
- Écritures limitées à la communauté ouverte : les affectations du membre dans ses autres communautés ne sont plus
  fermées, une unité d’une autre communauté est ignorée. Rôle tronqué à la longueur des colonnes.
- Fiche membre : message et bouton « Réparer l’affectation » pour les gestionnaires quand les sources divergent.
- Étape `unit_assignment_repair` (3345) : repère et répare les membres incohérents, journalise chaque correction.

## Fichiers touchés
`app/Services/Personnel/UnitAssignmentConsistency.php`, `app/Repositories/PersonnelAssignmentRepository.php`,
`app/Repositories/UserRepository.php`, `app/Controllers/Admin/EffectifsWorkspaceController.php`,
`app/Controllers/Web/PersonnelController.php`, `routes/web.php`, `views/admin/effectifs_workspace/member.php`,
`database/steps/3345_unit_assignment_repair.php`, `database/steps/registry.php`,
`tests/Unit/TeamAssignmentGhostTest.php`.

## Vérification
`TeamAssignmentGhostTest` : deux connexions sur la même base rejouent le formulaire renvoyé pendant
l’enregistrement (échoue sur l’ancienne version : fiche et effectifs divergent), le double envoi, le membre bloqué
réaffecté, l’autre communauté intacte, la réparation et l’étape relancée sans effet. En ligne : lancer l’étape
3345, ouvrir la fiche du membre concerné, vérifier qu’il apparaît dans l’effectif de son équipe.

## Statut
corrigé (étape 3345 à lancer)
