# Guide mission maker

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
