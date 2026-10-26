# COMSPEC Overwatch — Stable Beta snapshot

Date : 2026-09-11

## Versions

- Overwatch Connect : 1.5.41 (pack live archivé juste avant ECOTI / HUD situation JVN)
- Extension liaison : 2.0.28

## Contenu

- `addons/` : main, connect, atak_athena, mavik_compat, sse_ace
- `COMSPECExtension_x64.dll`
- `mod.cpp`, logos, CHANGELOG / DOCS

## Note

Snapshot **avant** ECOTI / affichage situation sous JVN (inspiré F-PANO ECOTI).
Archive de secours Stable Beta — ne pas utiliser comme source de build.

## Retrait de l'archive (nettoyage 2026-10-26)

Les binaires de cet instantané (deux copies de `addons/*.pbo`, `COMSPECExtension_x64.dll`,
`mod.cpp`, logos, `CHANGELOG.md`, `DOCS.md` — 150,4 Mo) ont été retirés de l'arbre : ils
restent dans l'historique Git sur `main` avant ce nettoyage. Pour conserver une archive
consultable, la publier en release GitHub plutôt que de la réintroduire dans l'arbre
(`mod/UptoDate/StableBeta/` et `mod/UptoDate/@COMSPECOverwatch_StableBeta/` sont désormais
dans `.gitignore`).

Seule cette fiche, qui documente le contenu et les versions de l'instantané, a été gardée.
