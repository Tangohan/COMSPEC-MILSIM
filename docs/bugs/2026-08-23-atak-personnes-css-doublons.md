# ATAK INTEL — liste Personnes sans cartes, doublons à la transmission

## Contexte

Onglet INTEL → Personnes d’Athena ATAK. Les fiches arrivent du terminal SEEK (identité, statut, biométrie simulée).

## Symptôme

- Les fiches s’affichent en texte brut (`◎` puis nom, méta, « Biométrie simulée enregistrée »), sans carte, bordure ni photo encadrée.
- La même personne apparaît plusieurs fois (ex. PrenomUlu NomZul transmise par N-10 puis NewPI).

## Cause

- Le script de liste réutilisait des classes de cartes caméras (`atak-cam-card`) qui n’existent pas dans la feuille ATAK.
- Chaque transmission créait une nouvelle ligne, sans rapprochement sur le nom / l’unité, même sans nouvelle biométrie.

## Correctif

- Cartes dédiées (photo ou pictogramme, nom, méta, pastilles Empreintes / Iris / ADN).
- À la transmission : si la personne est déjà au registre **et** qu’il n’y a pas d’iris, d’empreintes ou d’ADN nouveau, on enrichit ou on refuse — on ne crée pas une seconde fiche.
- La liste ATAK n’affiche plus qu’une carte par identité (la plus ancienne, avec la biométrie rassemblée).
- Le terminal SEEK refuse aussi un second envoi identique pendant la mission, sauf nouvelle modalité ou photo du visage en attente.

## Fichiers touchés

- `public/assets/css/atak.css`
- `public/assets/js/atak-sse-persons.js`
- `views/atak.php`
- `app/Support/SsePersonDedupe.php`
- `app/Repositories/SsePersonRepository.php`
- `app/Controllers/Api/SseApiController.php`
- `tests/Unit/SsePersonDedupeTest.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

- Tests unitaires du rapprochement d’identité.
- Onglet Personnes : cartes lisibles, une seule fiche PrenomUlu si les deux lignes n’apportaient pas de nouvelle biométrie.
- Retransmettre la même identité sans iris/empreintes/ADN : pas de nouvelle fiche ; avec un iris nouveau : la fiche existante est enrichie.

## Statut

corrigé
