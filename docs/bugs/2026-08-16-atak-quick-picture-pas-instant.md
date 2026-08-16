# Quick Picture — photos ne remontent pas instantanément (ATAK)

## Contexte

Prise de vue Quick Picture (Iceman / Overwatch) : la photo doit apparaître côté TOC ATAK en quelques secondes via le poll recon / caméras.

## Symptôme

Journal Overwatch :

```
[ERROR][Tx] HTTP POST — code 500 · /public/api/atak/video-feeds
[INFO][Tx] NotifyNewPhoto — 2026_08_16_03_26_43.jpg
```

(souvent deux lignes `NotifyNewPhoto` quasi identiques ; pas de ligne `PhotoUpload` OK/ÉCHEC claire.)

Côté web : panneau Photos / aperçus casque restent vides ou figés ; anciennes URLs `/uploads/recon/…` parfois en 404.

## Cause

1. **Chaîne cassée côté Athena** : `NotifyNewPhoto` ne fait qu’enfiler le fichier local ; l’upload Athena (`POST /api/recon/images`) et le roster caméras (`POST/GET /api/atak/video-feeds`) tombent en **500** dès que le tenant est résolu (même famille que `docs/bugs/2026-08-16-atak-api-500-session.md`). Sans indexation BDD, le poll TOC (≈ 3 s) n’a rien à afficher.
2. **Schéma / SQL fragile** : `recon_images` avec colonnes optionnelles manquantes (`deleted_at`, `fx_*`, etc.) ou insert qui remonte une exception → 500 opaque.
3. **Chemin d’upload** : construction relative `dirname(__DIR__, 2)/../public/uploads/recon` fragile selon le déploiement ; échec silencieux possible après `move_uploaded_file`.

`NotifyNewPhoto` en double reste surtout un bruit de journal (déjà documenté le 06/08) ; ce n’est pas la cause du non-affichage.

## Correctif

- `ReconImageRepository` : détection colonnes, `tablesReady()`, list/create/snapshots résilients (plus de 500 SQL si migration partielle).
- `videoFeeds` : `requireTenant` dans le try → 503 métier plutôt que 500.
- `reconImagesIndex` / `reconImagesStore` : try/catch, `base_path('public/uploads/recon')`, messages métier, 503 si indexation échoue (fichier nettoyé si create vide).
- Défenses session ATAK déjà prêtes (Response.json, requireTenant, etc.) — **à déployer ensemble**.

## Fichiers touchés

- `app/Repositories/ReconImageRepository.php`
- `app/Controllers/Api/AtakApiController.php` (`videoFeeds`, `reconImagesIndex`, `reconImagesStore`)
- liés : `app/Core/Response.php`, `docs/bugs/2026-08-16-atak-api-500-session.md`

## Vérification

1. Déployer le correctif sur Athena (+ migrations recon si besoin via `run-migrations.php`).
2. Quick Picture en jeu → journal : `PhotoUpload` OK / uploaded (ou ERR avec code HTTP).
3. ATAK web → onglet Photos : nouvelle capture visible au plus tard au poll (~3 s).
4. Plus de 500 systématique sur `video-feeds` ; au pire 503 avec message lisible.
5. Si échec : coller le JSON 500 (`message`, `request_id`) ou le mail `ERROR_ALERT`.

## Statut

identifié — correctifs défensifs prêts, à déployer et retester en live
