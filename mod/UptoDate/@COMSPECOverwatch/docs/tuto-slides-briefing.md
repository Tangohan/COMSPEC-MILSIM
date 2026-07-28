# Diapositives de briefing (écrans Eden + Google Slides)

## Images Athena (chemin stable)

1. Publiez des JPG/PNG « Visible en jeu » dans Athena → Diapositives de briefing.
2. En Eden, posez un écran et collez dans Init :

```sqf
this setVariable ["comspec_briefingScreenIndex", 0];
[[this, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;
[this, 0] spawn {
    params ["_obj", "_selIdx"];
    waitUntil { !isNull _obj };
    private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
    if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
    if (count _slides > 0) then {
        private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
        if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
    };
};
```

3. Action « Consulter le briefing » ou tablette Athena → Briefing → **Consulter les diapositives**.

## Google Slides (optionnel, fragile)

L’extension peut télécharger une présentation **publique** (partagée avec toute personne disposant du lien) et l’afficher sur le même tableau / les mêmes écrans.

- **Athena** : champ « Présentation Google Slides » sur la page Diapositives de briefing.
- **Tablette** : coller un lien, ou « Charger le brief Google de la communauté ».
- **Avertissement** : dépend des pages d’export Google (non documentées). Peut casser sans préavis (ToS / changements Google). Préférez les images Athena pour les missions critiques.

Plusieurs écrans :

```sqf
[[briefingScreen1, 0], [briefingScreen2, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;
```

## Build

```bat
cd mod\UptoDate
build_mod.bat
```

La DLL Native AOT est produite dans `COMSPECExtension\bin\publish\COMSPECExtension_x64.dll` puis copiée à la racine de `@COMSPECOverwatch`.
