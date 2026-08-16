# Module Transmissions terrain (Centre SSE)

## Contexte

Besoin d’un écran listant l’ensemble des envois faits depuis Arma 3 vers Athena,
visible dans la navigation du Centre SSE (Pilotage).

## Correctif

- Entrée **05 — Transmissions terrain** dans `views/atak/sse/_layout.php`
- Routes `GET /atak/sse/transmissions` et `…/transmissions/{id}`
- Liste + fiche basées sur `sse_intel_events` (filtre défaut : sources terrain Arma)
- Guide bureau mis à jour

## Fichiers touchés

- `app/Repositories/SseIntelEventRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `views/atak/sse/_layout.php`
- `views/atak/sse/transmissions.php`
- `views/atak/sse/transmission_show.php`
- `views/atak/sse/guide/_part_bureau.php`

## Vérification

Ouvrir le Centre SSE → Pilotage → Transmissions terrain. Après un envoi Arma
(fiche personne / site), l’entrée doit apparaître ; « Ouvrir » mène à la fiche
avec lien vers l’objet lié si présent.

## Statut

corrigé
