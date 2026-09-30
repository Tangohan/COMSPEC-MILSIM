# Bug — Suivi carte sans téléphone après drop

## Contexte

30 septembre 2026. L’opérateur jette son téléphone ATAK. Il n’en a plus en inventaire, mais le pion continue de se déplacer sur la carte web.

## Symptôme

Sans terminal, la position devrait cesser d’être mise à jour (dernière position figée ~3 min puis différé). Or le picto **suit encore** les déplacements.

## Cause

`fn_hasTerminal.sqf` considérait le joueur encore équipé dès que la variable `cTabIfOpen` existait (`!isNil`), même après fermeture du téléphone ou drop de l’objet. Une fois le téléphone ouvert une fois dans la session, le suivi restait autorisé.

Secondaire : le filtre « itemctab » pouvait aussi matcher une caméra casque (`ItemcTabHCam`), qui n’est pas un terminal de liaison.

## Correctif

- N’accepter l’UI ouverte que si l’affichage cTab existe réellement.
- Exclure les classes caméra casque du test terminal.
- Conserver le contrôle inventaire / slot pour ItemAndroid / ItemcTab.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hasTerminal.sqf`

## Vérification

Drop du téléphone sans UI ouverte → plus de `Tx → position` dans le journal ; le pion web ne suit plus. Rééquipement → reprise du suivi.

## Statut

corrigé (PBO connect à recharger, quitter Arma ou au minimum relancer la mission)
