# Bug — Pièces de fiche hors du dossier Captures

## Contexte

2 septembre 2026. Les photos du téléphone et du casque sont recopiées dans `Documents\Arma 3 - COMSPEC\Captures`, puis envoyées au poste. Les photos jointes à une fiche de renseignement (transmission) et la photo du visage SEEK ne suivaient pas ce chemin.

## Symptôme

- Le dossier Captures se remplit pour un cliché téléphone, pas pour une photo de fiche.
- La fiche arrive au bureau, la pièce jointe est parfois refusée (erreur 400) ou le jeu se fige pendant l’envoi.
- Une capture de fiche pouvait aussi apparaître dans la galerie photos du poste, mélangée aux clichés téléphone.

## Cause

Deux chemins distincts :

1. **Téléphone / casque** : cliché → copie dans Captures → file d’envoi en arrière-plan → poste.
2. **Transmission (fiche / visage)** : cliché puis lecture immédiate du fichier, en mémoire, sur le fil du jeu, sans copie Captures. Si le fichier n’était pas encore écrit, le bureau recevait une demande vide.

## Correctif

Les photos de fiche et de visage empruntent la même file que le téléphone : copie dans Captures, attente que le fichier soit complet, envoi au bureau (fiche ou identité), sans les classer comme photos téléphone.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseCaptureFacePhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`

## Vérification

Tests d’assets photo / fiche + UPDATE 387. Rebuild du pack. En jeu : joindre une photo à une fiche, valider. Un PNG apparaît dans Documents\Arma 3 - COMSPEC\Captures, puis sur la fiche au bureau. Pas de gel, pas de refus juste après « mise en file ».

## Statut

corrigé (pack à recharger, quitter Arma complètement)
