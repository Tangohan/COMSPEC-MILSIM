# Journal des transmissions : PDF et relais Discord

## Contexte

Le journal SSE des transmissions terrain permettait d’ouvrir une entrée, pas de l’exporter ni de la publier vers Discord.

## Symptôme

Pas de PDF. Pas de liaison Discord depuis cette page.

## Cause

Aucune action d’export ni de relais n’était branchée sur le journal.

## Correctif

Téléchargement PDF du journal (filtres compris) et d’une fiche. Relais Discord : un ou plusieurs salons, optionnellement le salon déjà configuré pour la communauté. Publication automatique des nouvelles transmissions terrain, plus envoi manuel.

## Fichiers touchés

- `app/Services/Sse/SseTransmissionPdfService.php`
- `app/Services/Sse/SseTransmissionDiscordService.php`
- `app/Controllers/Web/SsePortalController.php`
- `app/Services/Sse/SseIntelFoundationService.php`
- `views/atak/sse/transmissions.php`
- `views/atak/sse/transmission_show.php`
- `routes/web.php`

## Vérification

Test `SseTransmissionExportAssetTest`. Sur le journal : Télécharger le journal (PDF), Ajouter un relais Discord, Essai, puis PDF / Envoyer vers Discord sur une fiche.

## Statut

corrigé
