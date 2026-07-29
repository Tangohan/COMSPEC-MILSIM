# Métriques liaison web vides alors qu’État ATAK in-game affiche des données

## Contexte

L’app native **État ATAK** (cTab / ATAK Enhanced) calcule latence, pertes de paquets et stabilité **localement** côté client Arma. La Tacmap web affiche des métriques partielles dans la bande « ATAK Athena » et le panneau « État de la liaison ».

## Symptôme

- In-game (Zeus / tablette) : État ATAK rempli (ex. « Hors liaison », pertes 35 %).
- Web Tacmap : bandeau métriques reste sur « En attente » / « — » pour les pertes, même quand un joueur envoie des positions.

## Cause

1. **API** : `getMeasuredPacketLoss()` exigeait `extra.updated_at`, champ jamais envoyé par le mod (`fn_updatePosition.sqf` envoie `packet_loss`, `packets_sent`, `packets_received`, `link_state`, `latency_ms` sans `updated_at` dans `extra`).
2. **Front** : `refreshLiaisonMetrics()` dans `views/atak.php` remettait systématiquement les pertes à « — » à chaque poll, écrasant toute valeur affichée par `atak-roleplay-effects.js`.
3. **Produit** : État ATAK in-game ≠ panneau web dédié ; le web ne duplique pas tous les champs (certificat, débit, radio…) tant que le joueur n’est pas **en liaison Athena** et ne remonte pas de positions.

## Correctif

- Nouvelle extraction `getLatestLinkTelemetry()` (unités `linked`/`delayed`, fusion mapId mod + théâtre).
- `/api/atak/stats` et `/api/atak/roleplay-stats` exposent `link_telemetry` / `measured_packet_loss`.
- Bandeau Tacmap : affiche pertes et latence jeu si disponibles ; ne force plus « — ».
- `atak-roleplay-effects.js` : affiche aussi 0 % de pertes (pas seulement > 0).

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php`
- `views/atak.php`
- `public/assets/js/atak-roleplay-effects.js`

## Vérification

1. Joueur connecté via **Connexion en jeu** (liaison Athena active).
2. Ouvrir Tacmap web → bandeau : pertes % et « Théâtre » à jour (il y a X s).
3. Si **Hors liaison** in-game : web peut rester vide — normal tant qu’aucune position n’est remontée au portail.

## Statut

Corrigé (portail). Rappel usage : liaison jeu requise pour alimenter le web.
