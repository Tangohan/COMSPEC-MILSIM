# Photos SSE introuvables (URL sans `/public`)

## Contexte

Sur Athena Hostinger, le document root pointe sur la **racine du dépôt** ; l’app est servie sous `/public/` (`index.php` racine → redirection 302, `APP_BASE_PATH=/public` dans `.env`).

## Symptôme

- Dossier SSE : pièce « Photo faciale » avec icône d’image cassée.
- Barre d’adresse / `src` de l’`<img>` : `https://athena.ttrd.fr/uploads/sse/evidence/ev_2_….png` → **404** (page LiteSpeed, pas PHP).
- Le fichier existe bien : `https://athena.ttrd.fr/public/uploads/sse/evidence/ev_2_….png` → **200**, `Last-Modified` = moment du versement (16/08/2026 16:06).

## Chaîne causale (pas un fichier perdu)

```text
1. Upload OK
   ImageCompressionService → disque public/uploads/sse/evidence/
   BDD image_path = uploads/sse/evidence/ev_2_….png

2. Affichage cassé
   listEvidence() faisait : url = '/' + image_path
   → src="/uploads/sse/evidence/…"  (absolu depuis la racine hôte)

3. Navigateur
   Une URL qui commence par "/" ignore le préfixe de page (/public/atak/…).
   LiteSpeed cherche public_html/uploads/… → absent → 404 HTML générique.

4. Fichier réel
   public_html/public/uploads/sse/evidence/… → accessible seulement via /public/uploads/…
```

Point clé : ce n’est **ni** un upload raté, **ni** un FTP qui a effacé `uploads/` (le fichier est présent et daté). C’est uniquement la **construction d’URL web** qui omettait `APP_BASE_PATH`.

Les CSS / liens login passent par `url()` / `asset_url()` → `https://athena.ttrd.fr/public/assets/…` (OK). Les pièces SSE contournaient ce helper depuis juillet 2026 (`SseCaseRepository`, commit `3a0fd5cb`).

Côté ATAK JS, `getApiBase()` dérive souvent `/public` du pathname et `resolveMediaUrl` peut « sauver » un `/uploads/…` en le préfixant — d’où un symptôme plus visible sur le **HTML serveur** du dossier SSE (`case_show.php` : `<img src="<?= $e['url'] ?>">` sans JS).

## Correctif

Utiliser `user_media_public_url($path)` (s’appuie sur `url()` + `APP_BASE_PATH`) partout où une URL navigateur est exposée pour un upload.

## Fichiers touchés

- `app/Repositories/SseCaseRepository.php`
- `app/Repositories/SsePersonRepository.php`
- `app/Services/Sse/SseAtakLayersService.php`
- `app/Controllers/Api/AtakApiController.php` (URLs recon / snapshots — même classe de bug)

## Vérification

1. HTTP : `/uploads/sse/evidence/<fichier>` = 404 ; `/public/uploads/sse/evidence/<fichier>` = 200.
2. Après déploiement PHP : recharger le dossier → `src` contient `/public/uploads/…` → vignette OK.
3. Pas besoin de re-verser la photo déjà présente sur le disque.

## Statut

corrigé (à déployer)
