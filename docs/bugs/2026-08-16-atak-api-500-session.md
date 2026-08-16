# ATAK — 500 généralisés après màj UI (session connectée)

## Contexte

Sur `https://athena.ttrd.fr/public/atak`, après une mise à jour d’interface (chrome C2 / lot JNET-SSE du 16/08), la carte charge mais presque tous les appels API échouent. Overwatch reste en liaison (handshake OK) puis échoue aussi sur certains POST (`video-feeds`).

## Symptôme

- Console navigateur : nombreux `500` sur `/api/atak/*`, `/api/chat`, `/api/units`, `/api/mission-cycle/current`, etc.
- Tuiles Jetelain Altis en `404` (bruit secondaire, hors Athena).
- Assets roleplay (`glitch-*.png`) et anciennes captures recon en `404` (secondaire).
- Sans cookie de session : `/api/atak/ping` → **200**, `/api/atak/stats` → **401** (auth OK).
- Avec session : les mêmes routes passent l’auth puis tombent en **500**.

## Cause

Cause racine **pas encore isolée en prod** (corps JSON / mail `ERROR_ALERT` manquants). Hypothèses retenues :

1. Exception PHP après résolution du tenant (lecture BDD / sérialisation JSON / simulateur roleplay).
2. Sur PHP 8.4, `Response::json()` passait le `false` de `json_encode` à `setBody(string)` → **TypeError** (500 opaque) si données non JSON-encodables (UTF-8 invalide, `NAN`, etc.).
3. Latence roleplay mal configurée (usleep trop long) pouvant faire timeout le ping authentifié.
4. Déploiement FTP partiel (`cancel-in-progress`) ou migrations non jouées — à confirmer via le message métier / `request_id`.

## Correctif (défense en profondeur, à déployer)

- `Response::json` : flags UTF-8 + repli si `json_encode` échoue.
- `requireTenant` / ping / roleplayStats / effets roleplay : try/catch.
- Plafond latence roleplay (2 s).
- `getUnits` / `getMarkers` : ne plus laisser remonter une PDOException.
- Modèles / types d’ordres + cycle de mission : lecture défensive.
- JSON 500 : champ `request_id` pour corrélation avec les mails d’alerte.

## Fichiers touchés

- `app/Core/Response.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/MissionCycleApiController.php`
- `app/Repositories/AtakDataRepository.php`
- `app/Services/Tactical/RoleplaySimulationService.php`
- `public/index.php`

## Vérification

1. Déployer ce correctif sur Athena.
2. Connecté sur `/public/atak` : F12 → Network → une requête en 500 → coller le JSON (`message`, `request_id`).
3. Si le message parle de mise à jour de base : lancer `run-migrations.php` sur l’hébergeur.
4. Vérifier la boîte `ERROR_ALERT_EMAIL` (trace complète).
5. Ping authentifié doit rester 200 même si le simulateur roleplay est cassé.

## Statut

identifié — correctifs défensifs prêts, cause prod à confirmer avec un corps de réponse 500
