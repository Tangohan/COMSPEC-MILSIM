# ATAK — historique des soirées empilé sur la carte

## Contexte

Parties du vendredi soir (21 h → samedi 2 h) puis du samedi soir. Sans reset, marqueurs, positions et photos des deux soirées restent visibles sur l’ATAK web.

## Symptôme

La carte s’empile : traces de la veille, photos de vendredi encore posées samedi. Pas d’action simple dans le poste pour « nouvelle soirée » sans tout détruire, photos comprises.

## Cause

Les données de mission (marqueurs, unités, ordres, tchat, tracés) et les photos n’avaient pas de notion de soirée. La purge admin existante efface aussi les images.

## Correctif

- Soirée de 10 h à 10 h (Europe/Paris) : vendredi 21 h et samedi 2 h restent ensemble ; samedi soir = nouvelle soirée.
- Réglages du poste : « Vider la carte (nouvelle soirée) » retire l’historique visible, **sans** toucher aux photos.
- Onglet Photos : menu par soirée ; les soirées précédentes n’apparaissent plus sur la carte par défaut ; suppression manuelle d’une soirée (les clichés déjà classés SSE sont conservés).

## Fichiers touchés

- `app/Support/AtakPlayNight.php`
- `app/Services/Tactical/AtakTenantDataService.php`
- `app/Controllers/Api/AtakApiController.php`
- `routes/web.php`
- `views/atak.php`
- `public/assets/js/atak-c2-workspace.js`
- `public/assets/js/atak-cams.js`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak.css`
- `tests/Unit/AtakPlayNightTest.php`

## Vérification

- Tests unitaires `AtakPlayNightTest`.
- Réglages ⚙ → vider la carte : photos toujours dans l’onglet, carte sans marqueurs de la veille.
- Photos : soirée en cours sur la carte ; soirée précédente rappelable puis supprimable à la main.

## Statut

corrigé
