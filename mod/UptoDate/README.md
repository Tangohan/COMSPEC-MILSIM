# COMSPEC Overwatch — dossier de build actif

Contenu minimal pour compiler le mod et obtenir des PBO à jour.

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
- `build_mod.bat` — compile DLL + PBO → `@COMSPECOverwatch/`
- `workshop-pack.ps1` — pack Workshop propre après build

Les archives historiques sont dans `mod/Ancienne version de tout/`.
