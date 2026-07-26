# mod/

- **`UptoDate/`** — dossier de travail actif. Tout ce qu'il faut pour builder le mod et obtenir les bons PBO est ici (sources SQF/CPP, extension C#, `build_mod.bat`, `workshop-pack.ps1`). C'est le seul dossier à utiliser pour compiler, packager et déployer.
- **`Ancienne version de tout/`** — archive figée de l'ancienne organisation du dossier `mod/` (anciens PBO buildés, docs, mods tiers de référence type cTab-master/@SIT 1erGTD, backups). Non reconstruite, gardée pour historique/référence uniquement.

Pour builder : `mod\UptoDate\build_mod.bat`, puis `mod\UptoDate\workshop-pack.ps1` avant toute publication Workshop.
