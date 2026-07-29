# Contexte

Marqueur posé in-game via **Marker Dropper** (BCE/cTab) avec libellé « helico » et symbole hélicoptère.

# Symptôme

Dans le journal TOC web, l’entrée apparaît comme `Marqueur placé — _USER_DEFINED #0/1/-1/0/0/3` (auteur = même identifiant technique). Plusieurs lignes dupliquées à chaque resync.

# Cause

1. `MarkerCreated` déclenche la sync **avant** que `setMarkerText` ne soit appliqué côté BCE.
2. Côté PHP, `normalizeArmaMarkerData` utilisait le nom Arma (`_USER_DEFINED #…`) comme libellé de repli.
3. Chaque upsert API créait une nouvelle ligne de journal, même sans changement de libellé.

# Correctif

- SQF : `fn_resolveBceMarkerText` — lit le texte dans `cTabUserMarkerList` via l’ID encodé dans le nom du marqueur ; resync différé +1 s.
- PHP : `ArmaMarkerLabel` — libellé humain (texte, type hélico, « Repère tactique ») ; journal uniquement si nouveau marqueur ou libellé changé ; auteur = indicatif.

# Fichiers touchés

- `connect/functions/fn_resolveBceMarkerText.sqf`
- `connect/functions/fn_syncMapMarker.sqf`
- `connect/XEH_postInit.sqf`
- `connect/config.cpp`
- `app/Support/ArmaMarkerLabel.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakDataRepository.php`

# Vérification

1. Placer un marqueur « helico » (symbole rotatif) via Marker Dropper in-game.
2. Journal web : `Marqueur placé — helico` (ou « Hélicoptère » si texte vide mais symbole air).
3. Une seule entrée journal (pas de spam à chaque poll).
4. Fiche événement : auteur = indicatif opérateur, pas `_USER_DEFINED`.

# Statut

Corrigé (déploiement PHP + rebuild `connect.pbo` requis).
