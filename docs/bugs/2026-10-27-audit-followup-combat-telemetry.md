# Audit follow-up — télémétrie combat / dégâts / routes

## Contexte

Reprise audit perf/sécurité ATAK après LOT-COMBAT. Agents A1/A2/A3 ont signalé des résidus critiques hors du premier lot.

## Symptôme

- À la perte (KIA) ou pendant les rafales, un envoi télémétrie synchrone pouvait figer l’image.
- Avec le téléphone natif chargé, le contrôle périodique des dégâts faisait encore le travail lourd (points de vie / KAT) avant de l’ignorer.
- Des routes admin relais / tests roleplay étaient enregistrées deux fois.
- Sans session joueur et avec `source=client`, le lot temps de jeu pouvait encore accepter plusieurs Steam (écart vs distances).

## Cause

- `EmitTelemetry` appelé depuis EH / chemin sync (`callExtension`).
- Early-exit natif placé trop tard dans `fn_checkAtakDamage`.
- Doublons dans `routes/web.php` (ARCH-006).
- `screenTimeBatch` sans branche `source === client` présente dans `distanceBatch`.

## Correctif

- EntityKilled KIA : `spawn` + `extAwait`.
- `fn_emitTelemetryEvent` : non bloquant (`spawn` + `extAwait`).
- `fn_checkAtakDamage` : sortie dès que `comspec_atak_native_fnc_deviceImpact` existe.
- Suppression des seconds enregistrements routes relays / roleplay.
- Parité SEC-042 sur `screenTimeBatch`.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/.../XEH_postInitClient.sqf`
- `mod/UptoDate/.../fn_emitTelemetryEvent.sqf`
- `mod/UptoDate/.../fn_checkAtakDamage.sqf`
- `routes/web.php`
- `app/Controllers/Api/AtakApiController.php`
- `reports/engineering/*`, `issues.json`

## Vérification

- Lecture code + revue Bugbot (ACCEPT avec résidus connus : ordre marqueurs Async, flicker HUD 10 Hz).
- Arma non lancé : pas de mesure FPS.
- PHPUnit absent.

## Statut

Corrigé en sources (non mesuré en jeu).
