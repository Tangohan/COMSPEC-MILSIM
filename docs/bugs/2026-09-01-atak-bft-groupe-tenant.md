# Suivi d’effectif — nom de communauté à la place de l’indicatif

## Contexte

Téléphone ATAK en jeu (bandeau suivi d’effectif) et remontée de position vers le poste. L’identité attendue est celle du tableau Effectifs : indicatif + affectation.

## Symptôme

Le groupe du suivi d’effectif affichait le nom de la communauté, pas l’indicatif de l’opérateur ni son affectation.

## Cause

Le suivi lisait le nom du groupe Arma. Après la correction qui a cessé de renommer ce groupe avec l’indicatif, le nom Arma restait souvent le titre de communauté (mission / éditeur). Ce titre partait tel quel vers le poste.

## Correctif

Le groupe du suivi est composé de l’indicatif et de l’affectation de la fiche. Un titre de communauté n’est plus accepté. Le nom Arma n’est utilisé que s’il n’est pas celui de la communauté.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_inGameGroupLabel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Support/OperatorTacticalIdentity.php`
- `app/Controllers/Api/AtakApiController.php`

## Vérification

Tests d’identité et d’assets. En jeu (pack 1.5.3) : bandeau suivi = indicatif · affectation. Au poste, la fiche opérateur reprend le même groupe.

## Statut

corrigé
