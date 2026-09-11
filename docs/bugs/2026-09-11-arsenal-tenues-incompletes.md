# Tenues arsenal — listes vides / équipement incomplet

## Contexte

11 septembre 2026. Overlay Athena dans l’arsenal ACE : collections « SOAR (56) » visibles, mais aucune tenue listée tant que le dossier reste replié. Certaines tenues denses donnaient un aperçu vide ou « trop volumineuse » à l’import.

## Symptôme

- Compteurs de collection visibles, zone sous les en-têtes vide (`▸`).
- Aperçu limité à 6 slots (pas de JVN / radio / cargo).
- `GetWardrobe` → `ERR|too_large` au-delà de ~8 Ko (limite d’extension).
- `ListWardrobes` tronqué silencieusement à 8 Ko (communauté incomplète).

## Cause

1. Collections **repliées par défaut** (`collapsed = true`).
2. Aperçu volontairement réduit à 6 macros.
3. Plafond `MaxOutputBytes = 8000` sur le détail et la liste, sans pagination / découpage.

## Correctif

- Première collection ouverte par défaut (toutes si ≤ 40 tenues et ≤ 6 collections) ; message d’aide si tout est replié.
- Aperçu : lunettes, jumelles, assignés (JVN, radio…), résumés « Contenu gilet / sac ».
- DLL : `GetWardrobe` → `CHUNKED` + `GetWardrobeChunk` ; `ListWardrobes` paginé (`NEXT` / `END`).
- SQF : `fn_arsenalListWardrobes`, réassemblage dans `fn_arsenalCloudLoadout`.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/.../fn_arsenalOverlayRefresh.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalLoadoutIcons.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalOverlayPreview.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalCloudLoadout.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalListWardrobes.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalPullAll.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalApplyCloud.sqf`
- `mod/UptoDate/Sources/.../fn_arsenalOverlayShow.sqf`
- `app/Support/DevDispatchCatalog.php` (UPDATE #500)

## Vérification

1. Ouvrir l’arsenal → Athena : une collection est déjà déployée (`▾`).
2. Sélectionner une tenue : aperçu avec slots + contenu.
3. Tenue dense communauté : apply / import OK (plus de `too_large` bloquant).
4. Communauté > 8 Ko de métadonnées : toutes les tenues listées.

## Statut

corrigé — Overwatch 1.5.37 · liaison 2.0.27
