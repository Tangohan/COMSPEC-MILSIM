# COMSPEC Overwatch — dossier de build actif

Contenu minimal pour compiler le mod et obtenir des PBO à jour.

> **Publication** : ce dossier ne publie plus rien sur le Steam Workshop. L'objet `3684656708` est l'ATAK natif ; il se publie seulement depuis `mod/COMSPEC_ATAK_Native`, dont le build recompile ici la DLL COMSPEC Link et les PBO `main` et `connect`. Le pack hérité `@COMSPECOverwatch` n'a plus d'objet Workshop.

## Documentation

**Index complet** : [`docs/README.md`](docs/README.md)

| Guide | Description |
|---|---|
| [Guide joueur](docs/guide-joueur.md) | Terminal, hub, liaison Athena |
| [Chef de mission / Zeus](docs/guide-chef-mission.md) | Zones roleplay, OP |
| [Réalisme liaison](docs/realisme-liaison-atak.md) | Coupures, dommages, reprise |
| [Terminal SSE](docs/terminal-sse-renseignement.md) | Renseignement interpersonnel (roadmap) |
| [Architecture](docs/architecture-et-addons.md) | Addons, DLL, intégrations |
| [Build & Workshop](docs/compilation-et-publication.md) | PBO, DLL, publication |
| [Assets visuels](docs/assets-visuels.md) | Textures, overlays |

Changelog pack : [`@COMSPECOverwatch/CHANGELOG.md`](@COMSPECOverwatch/CHANGELOG.md) · Steam : [`STEAM_CHANGELOG.txt`](STEAM_CHANGELOG.txt)

## Build

- `Sources/comspec-overwatch-addons/{main,connect,atak_athena,mavik_compat}` — sources addons
- `COMSPECExtension/` — extension native (DLL)
- `build_mod.bat` — compile DLL + PBO → `@COMSPECOverwatch/`, puis copie dans les dossiers Arma locaux ; s'arrête en échec sans DLL fraîche
- `workshop-pack.ps1` — pack propre après build ; refuse une DLL sous 2.0.96 et un `meta.cpp` qui vise l'objet `3684656708` de l'ATAK natif

Les archives historiques sont dans `mod/Ancienne version de tout/`.
