# Photo Library — transférer vers le poste

## Contexte

L’opérateur voulait envoyer une vue de Photo Library vers le poste depuis la zone libre entre la liste et le nom du fichier, puis la retirer de la bibliothèque du téléphone.

## Symptôme

- La zone grise sous la liste restait vide.
- Send ne visait qu’un autre téléphone.
- Après un envoi vers le poste, la photo restait dans Photo Library et dans le dossier IceMan.

## Cause

Les boutons étaient créés trop tôt, parfois derrière la zone d’aperçu, et le téléphone IceMan n’appliquait pas toujours le calage. Sur le pack FN, une version précédente restait aussi en mémoire tant qu’Arma n’était pas quitté.

## Correctif

- Boutons **TRANSFÉRER** et **TOUT TRANSFÉRER** posés dans la zone sous la liste, dès que Photo Library est visible.
- Si la création échoue, Send devient Transférer vers le poste.
- Transférer envoie la vue sélectionnée vers le poste, puis la retire de la bibliothèque et du dossier.
- Tout transférer fait de même pour toutes les vues locales.
- Send reste l’envoi vers un autre téléphone.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installPhotoLibraryAthena.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendLibraryPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_removeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.149)
- `mod/UptoDate/COMSPECExtension/Extension.cs` (retrait du fichier après envoi)

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.149. Photo Library : Transférer et Tout transférer sous la liste. Transférer envoie la vue vers le poste puis la retire de la liste.

## Statut

Corrigé
