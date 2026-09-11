# CallExtension COMSPECExtension introuvable (pas de journal, auth bloquée)

## Contexte

11 septembre 2026. Pack 1.5.18 affiché, pied de fenêtre « Liaison 1.18.0 », connexion Athena bloquée sur « Authentification en cours… », plus de journal COMSPEC. BattleEye désactivé.

## Symptôme

- RPT : `CallExtension 'COMSPECExtension' could not be found` en boucle
- Pas de nouveau fichier dans `%LOCALAPPDATA%\Arma 3\COMSPEC\logs`
- Auth e-mail reste sur « Authentification en cours… »

## Cause

Arma charge la liaison depuis le pack **`@# S.O.A.R - FN`**, pas depuis `@COMSPECOverwatch` :

```
CallExtension loaded: …\!Workshop\@# S.O.A.R - FN\COMSPECExtension_x64.dll
```

Après une sync Steam / rebuild du pack SOAR, la DLL avait disparu de ce dossier. Le build ne déployait que vers Workshop `3684656708` et `@COMSPECOverwatch`.

## Correctif

- Recopier `COMSPECExtension_x64.dll` + PBO Overwatch dans `@# S.O.A.R - FN`
- `build_mod.bat` déploie aussi vers `SOAR_FN`

## Vérification

- RPT : `CallExtension loaded: COMSPECExtension (...\@# S.O.A.R - FN\COMSPECExtension_x64.dll)`
- Pied de fenêtre : version de liaison réelle (pas 1.18.0)
- Journal session recréé sous `COMSPEC\logs`

## Statut

corrigé (DLL restaurée dans SOAR FN ; build ciblé)
