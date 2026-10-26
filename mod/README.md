# mod/

- **`COMSPEC_ATAK_Native/`** — le mod publié sur le Steam Workshop : objet `3684656708`, « COMSPEC ATAK Native ». Son `build_mod.bat` recompile les deux DLL et empaquète le mod avec Overwatch connect, dont il reprend les sources dans `UptoDate/`. L'ATAK natif se publie seulement depuis ce dossier (`@COMSPEC_ATAK_Native`).
- **`UptoDate/`** — sources d'Overwatch (SQF/CPP, extension C# COMSPEC Link) que le build de l'ATAK natif recompile. Le pack hérité `UptoDate/@COMSPECOverwatch` n'a plus d'objet Workshop : son `build_mod.bat` ne sert plus qu'aux essais locaux, et son `workshop-pack.ps1` refuse une DLL sous 2.0.96 ou un `meta.cpp` qui vise l'objet `3684656708`.
- **`Ancienne version de tout/`** — archive figée de l'ancienne organisation du dossier `mod/` (anciens PBO buildés, docs, mods tiers de référence type cTab-master/@SIT 1erGTD, backups). Non reconstruite, gardée pour historique/référence uniquement.

Pour builder **l'ATAK natif** (Overwatch connect inclus) : `mod\COMSPEC_ATAK_Native\build_mod.bat`, puis publier `mod\COMSPEC_ATAK_Native\@COMSPEC_ATAK_Native` sur l'objet `3684656708`. Ne publiez jamais `mod\UptoDate\@COMSPECOverwatch`, ni la sortie de `mod\UptoDate\workshop-pack.ps1`, sur cet objet.

Pour builder **SSE** : `mod\@COMSPEC_SSE\build_mod.bat`, puis `mod\@COMSPEC_SSE\workshop-pack.ps1` → dossier `mod\publisher\@COMSPEC_SSE`. Guide chef de mission : `mod/@COMSPEC_SSE/docs/PUBLICATION.md`.

Pour builder **COMSPEC Base SOF** (mobilier de base SOF posable dans Eden, mod indépendant) : `mod\COMSPEC_SOF_Base\build_mod.bat` (voir `mod/COMSPEC_SOF_Base/README.md`).
