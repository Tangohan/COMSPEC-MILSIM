# COMSPEC Overwatch — dossier de build actif

Contenu minimal nécessaire pour compiler le mod et obtenir des PBO à jour :

- `Sources/comspec-overwatch-addons/{main,connect,atak_athena}` — sources SQF/CPP des 3 addons.
- `COMSPECExtension/` — source C# de l'extension native (DLL).
- `mod.cpp` — descriptif du mod (copié automatiquement dans `@COMSPECOverwatch/` par le build).
- `build_mod.bat` — compile l'extension C#, buide les 3 PBO, déploie vers Arma 3 local.
- `workshop-pack.ps1` — assemble un pack Workshop propre dans `publisher/@COMSPECOverwatch/` à partir du dernier build.

## Utilisation

1. `build_mod.bat` — produit `@COMSPECOverwatch/addons/*.pbo` + `@COMSPECOverwatch/COMSPECExtension_x64.dll` (dossiers créés automatiquement, ignorés par git).
2. `workshop-pack.ps1` — à lancer après le build, avant toute publication Steam Workshop.

Les anciennes versions, la doc annexe (CHANGELOG, SECURITY, PACKAGING, guides...) et les mods tiers de référence (cTab-master, @SIT 1erGTD...) sont archivés dans `mod/Ancienne version de tout/`.
