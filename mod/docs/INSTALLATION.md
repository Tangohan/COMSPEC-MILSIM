# Installation — COMSPEC SSE

## Dépendances

1. [CBA_A3](https://github.com/CBATeam/CBA_A3)
2. [ACE3](https://github.com/acemod/ACE3)
3. Arma 3 (2.10+)

Optionnel : `@COMSPECOverwatch` pour la remontée Athena.

## Publication Steam

Le dossier à envoyer au Publisher Arma 3 est `mod/publisher/@COMSPEC_SSE` (assemblé par `workshop-pack.ps1`).  
Guide chef de mission : [PUBLICATION.md](PUBLICATION.md). Note d’atelier : [../PACKAGING.md](../PACKAGING.md).

## Packing

Les sources sont dans `mod/@COMSPEC_SSE/addons/<component>/`.

Chaque composant possède un `$PBOPREFIX$` :

```
z\comspec_sse\addons\<component>
```

### Addon Builder (Bohemia)

1. Ouvrir Addon Builder
2. Source : `mod/@COMSPEC_SSE/addons/core` (répéter pour chaque addon)
3. Destination : `mod/@COMSPEC_SSE/addons/`
4. Nom PBO : `comspec_sse_core.pbo` (etc.)
5. Cocher *Binarize* selon besoin (configs oui, SQF non obligatoire)

### armake2 (exemple)

```bat
armake2 pack -v addons\main addons\comspec_sse_main.pbo
armake2 pack -v addons\core addons\comspec_sse_core.pbo
armake2 pack -v addons\generator addons\comspec_sse_generator.pbo
armake2 pack -v addons\evidence addons\comspec_sse_evidence.pbo
armake2 pack -v addons\interaction addons\comspec_sse_interaction.pbo
armake2 pack -v addons\zeus addons\comspec_sse_zeus.pbo
armake2 pack -v addons\eden addons\comspec_sse_eden.pbo
armake2 pack -v addons\ui addons\comspec_sse_ui.pbo
armake2 pack -v addons\network addons\comspec_sse_network.pbo
armake2 pack -v addons\digital addons\comspec_sse_digital.pbo
armake2 pack -v addons\biometrics addons\comspec_sse_biometrics.pbo
```

## Lancement

```
-mod=@CBA_A3;@ace;@COMSPEC_SSE
```

## Vérification rapide

En jeu, console :

```sqf
comspec_sse_debug = true;
[!isNil "comspec_sse_fnc_generateData"] call BIS_fnc_log;
```

Doit retourner `true`.
