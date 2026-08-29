# ATAK — mise en prod des dernières features

Branche de déploiement : `cursor/atak-prod-features-fa98` (à merger dans `main`).

## Déjà sur `main`

| Feature | PR |
|---------|-----|
| Terrain3DRenderer (module + démo) | #238 |
| Refonte carto C2 (CSS / symbologie) | #239 |
| Alertes DSFR BO / flash | #240 |
| Réseau geo villes/routes + plan A* | #241 |
| Corrections RH anomalie + validation | #242 |

## Activé sur `/public/atak` par cette branche

| Feature | Flag / entrée |
|---------|----------------|
| Carte C2 live (rail, panneau unité, MarkerManager ↔ WebSocket) | `ATAK_MAP_C2_V2` + `atak-c2-bridge.js` |
| Vue topo premium Three.js | `ATAK_TERRAIN3D_PREMIUM` + `atak-terrain3d-premium.js` |
| Calques Villes / Routes + snap itinéraire routier | `atak-geo-live.js` |

## Déploiement

1. Merger cette PR dans `main` (déclenche **Deploy VPS** → `git pull` sur `athena.ttrd.fr`).
2. Secret **`VPS_SSH_KEY`** obligatoire dans GitHub Actions (sinon le workflow échoue immédiatement).
3. SSH VPS si schéma à jour :

```bash
cd /var/www/athena.ttrd.fr
php8.4 run-migrations.php
```

Migrations utiles déjà livrées avec #241 / #242 : geo network, personnel correction requests.

4. Smoke test : `/public/atak` → rail C2, bouton **3D** (mesh Three.js), cases Villes/Routes, outil Itinéraire.
