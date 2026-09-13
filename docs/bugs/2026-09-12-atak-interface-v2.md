# ATAK Interface V2 — tablette web

**Date :** 2026-09-12  
**Statut :** livré (bêta)

## Contexte

L’ancien prototype Chrome (`phone.html` + moteur carte) n’était plus branché sur la tablette Overwatch actuelle (`tablet.html`). Les opérateurs avaient besoin d’une interface carte satellite moderne sans casser la tablette V1 ni inventer de nouveaux endpoints.

## Symptôme / besoin

- Tablette V1 : radar native Arma, pas de tuiles satellite ni dock téléphone.
- Prototype Chrome : riche mais coupé des APIs / pont actuels.

## Cause

Deux lignées UI (téléphone HTML historique vs tablette Athena) sans point d’entrée V2 maintenu dans `connect/web`.

## Correctif / livrable

- Nouveau point d’entrée `connect/web/atak-v2/atak-v2.html`
- Port adapté : store, bus, tiles, theatres, symbols, engine, live-map + Leaflet
- Coque V2 (dock FR, charbon/cyan Athena) branchée sur `COMSPEC_BOOT` et `COMSPEC|…`
- Bascule `ui:v1` / `ui:v2` persistée en profil
- Boot enrichi : `world` + `worldSize`

## Fichiers touchés

- `connect/web/atak-v2/*`
- `connect/web/tablet.html` (bouton Interface V2)
- `fn_webBrowserOnLoad.sqf`, `fn_webBrowserJSDialog.sqf`, `fn_webBrowserPageLoaded.sqf`
- `connect/config.cpp` (1.5.58)

## Vérification

- [ ] Navigateur : `atak-v2.html?preview=1` → carte Altis + dock
- [ ] En jeu : Tablette → État → Interface V2 → carte + contacts boot
- [ ] Retour V1 via bandeau Tablette
- [ ] Message chat / marqueur Point remontent via protocole existant

## Écarts restants

- Pas de parity complète avec l’ancien `phone.html` (boot gate, pairing P2P, IceMan)
- Dessins complexes restent surtout locaux (IndexedDB) ; seul `marker.created` pousse `marker:place`
- Repli `OpenDataAsURL` (base64) casse les scripts relatifs : V2 exige un `LoadFile` réussi

