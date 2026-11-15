# Photothèque SSE : images des membres visibles par tout lecteur du portail

## Contexte
Signalement : la photothèque des fichiers SSE (`/atak/sse/fichiers/photos`) affichait les photos d'identité et de couverture des joueurs alliés, pour tout lecteur du portail.

## Symptôme
Portraits, identités de couverture ou d'emprunt, papiers et clichés SEEK II d'un membre apparaissaient dans la planche, la fiche sujet, la comparaison, l'impression et l'API d'image, sans autre garde que l'entrée au portail.

## Cause
Les images d'une identité du registre (`sse_identities.photo_path`, sous `public/uploads/sse-id-photos`) étaient affichées par leur adresse publique, et `fichiers/photos/{id}/image` ne distinguait pas un membre d'un sujet du lore (seules les identités couverture ou fausse demandaient l'habilitation Confidentiel). Aucune notion de propriétaire membre n'existait dans le contrôle d'accès.

## Correctif
`SseMemberImageGate` classe CONFIDENTIEL ÉLEVÉ toute image dont le propriétaire est un membre (`sse_identities.user_id`, photo rattachée ou fiche SEEK II rattachée ; identité introuvable = classée aussi), et ne l'ouvre qu'au titulaire, à sa hiérarchie (organigramme), à une habilitation Très restreint avec la section fermée `identites_service`, ou à une dérogation datée avec motif. Aucun octet n'est servi sans autorisation ; chaque consultation est journalisée. Cadre « CONFIDENTIEL ÉLEVÉ » et « Demander l'accès » dans l'interface. Voir `docs/sse-droits-acces.md`.

## Fichiers touchés
`app/Services/Sse/SseMemberImageGate.php`, `app/Repositories/SseFileRecordRepository.php`, `app/Services/Sse/SseFileSearchService.php`, `SseFileRecordService.php`, `SseSeekPhotoService.php`, `app/Controllers/Web/SseFileRecordController.php`, `views/atak/sse/files/*`, `views/atak/sse/papers/identity_show.php`, `database/steps/4650_sse_member_image_grants.php`.

## Vérification
`tests/Unit/SseMemberImageGateTest.php` (refus sans octet, titulaire, supérieur direct et indirect, collègue, gestionnaire SSE, habilitation, dérogation, non membre inchangé, fail-closed, isolation des communautés, journal). Captures de la planche avec données fictives.

## Statut
corrigé ; étape `sse_member_image_grants` à lancer (sans elle : aucune dérogation, images masquées). Les fichiers d'image eux-mêmes restent sous `public/uploads/` : suite à prévoir (déplacement hors du dossier public).
