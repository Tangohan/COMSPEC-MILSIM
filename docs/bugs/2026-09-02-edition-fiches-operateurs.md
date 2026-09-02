# Édition des fiches opérateurs — enregistrement incomplet et titres invisibles

## Contexte

Page d’édition du dossier personnel (`/personnel/{id}/edit`), utilisée par le membre et par l’encadrement pour modifier une fiche opérateur.

## Symptôme

Beaucoup de champs semblaient ne pas s’enregistrer : forum, équipement, notes, suivi d’immersion. Le bouton Enregistrer ne faisait parfois rien. Les titres des sections disparaissaient. Décocher « Déployable » ne retirait pas la disponibilité. L’affectation ou la fonction principale pouvait basculer sur la mauvaise ligne.

## Cause

1. Un second formulaire (matricule d’organisation) était placé *dans* le formulaire principal. Le navigateur fermait le premier formulaire trop tôt : tout ce qui suivait (y compris Enregistrer) n’était plus envoyé.
2. Une règle d’affichage masquait tout élément dont la classe contenait « tracking », y compris les titres d’onglets.
3. La case Déployable, une fois décochée, n’envoyait rien ; le serveur remettait alors « déployable » par défaut.
4. La ligne principale d’unité ou de fonction était lue d’après le numéro d’ordre *après* suppression des lignes vides, pas d’après la ligne cochée.

## Correctif

- Le matricule d’organisation a son propre formulaire, à côté du dossier, sans l’interrompre.
- Les titres d’onglets restent visibles.
- Décocher Déployable est enregistré.
- L’affectation et la fonction principales suivent la case cochée.
- Situation familiale, statut opérateur, fuseau et langue se choisissent dans une liste.

## Fichiers touchés

- `views/personnel/edit.php`
- `app/Controllers/Web/PersonnelController.php`
- `public/assets/css/personnel-dossier.css`
- `tests/Unit/PersonnelEditFormAssetTest.php`

## Vérification

`phpunit tests/Unit/PersonnelEditFormAssetTest.php tests/Unit/PersonnelRpIdentityAssetTest.php tests/Unit/DevDispatchCatalogTest.php`. Contrôle visuel : ouvrir l’édition, vérifier les titres, enregistrer depuis Forum ou Équipement, décocher Déployable.

## Statut

Corrigé
