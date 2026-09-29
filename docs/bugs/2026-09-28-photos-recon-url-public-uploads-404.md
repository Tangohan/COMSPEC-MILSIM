# Photos terrain — 404 sur /public/uploads/recon

## Contexte

Overwatch Beta / ATAK Photos. Les clichés remontés depuis le jeu (`recon_YYYY…_CALLSIGN.jpg`) apparaissent en liste mais l’image répond 404.

## Symptôme

Console navigateur : `recon_….jpg` / `recon_….png` en 404 en rafale. Vignettes vides.

## Cause

Sur le VPS, le document root Nginx est `…/public` et `APP_BASE_PATH` est vide.  
`normalize_public_uploads_url` forçait quand même le préfixe `/public` devant `/uploads/…`, produisant des URLs du type `/public/uploads/recon/…`.  
Sans le rewrite Nginx `^~ /public`, le serveur cherche `public/public/uploads/…` → 404, alors que le fichier est bien dans `public/uploads/recon/`.

## Correctif

- Si `APP_BASE_PATH` est vide : URLs canoniques `/uploads/…` ; retirer un éventuel `/public/uploads`.
- Si `APP_BASE_PATH=/public` (Hostinger) : conserver le préfixe.
- Overwatch Beta + Caméras : normalisent aussi les anciennes URLs `/public/uploads`.

## Fichiers touchés

- `app/Support/helpers.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-cams.js`
- `tests/Unit/UserMediaPublicUrlTest.php`
- UPDATE #00727

## Vérification

1. Ouvrir une photo listée : `https://athena.ttrd.fr/uploads/recon/recon_….jpg` → 200.
2. Ctrl+F5 Overwatch / ATAK Photos : vignettes visibles.

## Statut

Partiellement corrigé (URL). Voir aussi `2026-09-28-photos-recon-fichier-absent-disque.md` : même après URL canonique, le fichier peut être absent du disque.
