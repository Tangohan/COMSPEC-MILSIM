# Guide mission maker

## Dataset FALCON (recommandé)

```sqf
// Pose le réseau FALCON (niveau tactique) sur les unités proches
["falcon", player, 50, 1] call comspec_sse_fnc_applyDataset;

// Ou via pack scénario
["FALCON", player, 50] call comspec_sse_fnc_loadScenarioPack;

// Changer le niveau de révélation (0–3)
[2, true] call comspec_sse_fnc_setScenarioLevel;
```

Rôles Eden : `falcon_hvt`, `falcon_ied`, `falcon_courier`, `falcon_finance`, `falcon_safehouse`, `falcon_noise`.

## Personnage préparé

```sqf
// init de l'unité
[_this, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateData;
[_this, [["alias", "ABU HAMZA"]]] call comspec_sse_fnc_setIdentity;
```

## Téléphone placé dans un bâtiment

```sqf
_phone setVariable ["comspec_sse_forcedType", "PHONE", true];
[_phone, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateData;
[_phone, _ownerUnit, "OWNER"] call comspec_sse_fnc_linkEntities;
```

## Site complet au démarrage

```sqf
if (isServer) then {
    [getMarkerPos "sse_compound", 40, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateSite;
};
```

## Ne pas générer 200 profils au start

Préférer :

```sqf
{ [_x, "PERSON"] call comspec_sse_fnc_makeSearchable; } forEach (allUnits select {side _x == civilian});
```

Le contenu détaillé n'apparaît qu'au premier examen.

## Zeus

- **Scenario Director** — dataset FALCON + niveau 0–3
- **Générer depuis brief / scénario** — pack `FALCON` ou brief libre
- **Appliquer modèle SSE** — builtins Irak / Russie
- **Spoil Control** / **After Action** — vérité vs connu
