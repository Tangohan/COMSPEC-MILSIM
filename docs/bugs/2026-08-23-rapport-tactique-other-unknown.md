# ATAK — rapport tactique « OTHER / Unknown »

## Contexte

Un rapport envoyé depuis le jeu apparaît dans le journal d’activité avec un résumé vide, un type technique OTHER, un auteur « Unknown » et une erreur de diffusion.

## Symptôme

Fiche d’événement :

- Résumé : `Rapport OTHER soumis : ` (rien après les deux-points)
- Auteur : Unknown (alors que le compte est N-10 / Noopy)
- `routing_error: routing_unavailable`

## Cause

1. Les coordonnées partaient avec une virgule française : le JSON était invalide, le serveur créait quand même une entrée vide (type OTHER par défaut).
2. L’auteur du journal prenait le callsign du groupe, pas l’indicatif, et tombait sur « Unknown » si le corps était vide.
3. Le type d’événement était enregistré `TACTICAL_REPORT` (majuscules) alors que l’affichage ne reconnaissait que `tactical_report`.
4. Un envoi SSE générique (`type` / `kind`) était parfois classé à tort comme rapport tactique.

## Correctif

- Nombres JSON en point décimal ; indicatif réel dans le rapport.
- Refus des rapports vides ; libellé français + auteur depuis le profil.
- Journal : type `tactical_report` ; fiche : N-10, « Rapport », sans codes techniques de diffusion.
- SSE : seulement un vrai `report_type` part vers les rapports.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hashMapToJson.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_submitTacticalReport.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakActivityLogService.php`
- `public/assets/js/atak-activity.js`

## Vérification

1. Relancer Arma (Overwatch 1.4.30) et déployer le PHP/JS Athena.
2. L’entrée déjà en journal (id 44) : catégorie Rapport, auteur N-10, résumé « Rapport » (le texte d’origine reste vide).
3. Envoyer une nouvelle observation depuis le terminal : résumé et type réels, auteur = indicatif.

## Statut

corrigé
