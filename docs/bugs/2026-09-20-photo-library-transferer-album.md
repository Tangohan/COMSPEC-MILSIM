# Photo Library — Transférer ne trouvait pas les vues

## Contexte

20 septembre 2026. Le dépannage de liaison prend une vue neuve et la transmet au poste. Dans Photo Library, Transférer et Tout transférer n’envoyaient rien, alors que les vues sont bien dans l’album du téléphone (ATAK Enhanced).

## Symptôme

- Dépannage : « Photo prise et transmise vers le poste », puis envoi réussi.
- Photo Library : Transférer / Tout transférer n’envoient pas les vues déjà enregistrées.

## Cause

Le dépannage prend une vue neuve dans le dossier de captures du profil. L’album du téléphone enregistre les vues dans le dossier du pack ATAK Enhanced. Les boutons ne regardaient que l’index interne et les dossiers « Screenshots » du profil, pas ce dossier d’album.

## Correctif

- La recherche des vues inclut le dossier d’album d’ATAK Enhanced.
- Tout transférer envoie aussi les vues présentes sur le disque si l’index interne est vide.
- Transférer retente avec le nom du fichier si le chemin annoncé est incomplet.

Pack : Athena **1.0.162**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendLibraryPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

1. Quitter Arma. Recharger Athena 1.0.162.
2. Prendre une vue depuis Quick Pictures / l’appareil photo du téléphone.
3. Photo Library : la vue est dans la liste. Transférer : elle arrive au poste.
4. Tout transférer envoie le reste de l’album.

## Statut

corrigé (Athena 1.0.162)
