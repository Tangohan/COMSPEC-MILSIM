# Exemples copiables

## 1. Cellule insurgée

```sqf
if (!isServer) exitWith {};
private _pos = getMarkerPos "m_safehouse";
[_pos, 35, "INSURGENT", "DETAILED", createHashMapFromArray [
    ["maxobjects", 8],
    ["digital", true],
    ["documents", true],
    ["network", true]
]] call comspec_sse_fnc_generateSite;
```

## 2. HVT nommé

```sqf
[_hvt, "COMMANDER", "HIGH_VALUE"] call comspec_sse_fnc_generateData;
[_hvt, [
    ["name", "Karim Haddad"],
    ["alias", "ABU HAMZA"],
    ["nationality", "Irakienne"]
]] call comspec_sse_fnc_setIdentity;
```

## 3. Téléphone + propriétaire

```sqf
_phone setVariable ["comspec_sse_forcedType", "PHONE", true];
[_phone, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateData;
[_phone, [
    ["owner", "Karim Haddad"],
    ["contacts", ["ABU YASSIN", "FARID", "MUSTAFA"]]
]] call comspec_sse_fnc_setDigitalData;
[_phone, _hvt, "OWNER", 0.95, "SCRIPT"] call comspec_sse_fnc_linkEntities;
```

## 4. Dotation opérateur SSE

```sqf
player addItem "COMSPEC_SSE_EvidenceBag";
player addItem "COMSPEC_SSE_Camera";
player addItem "COMSPEC_SSE_FingerprintKit";
player addItem "COMSPEC_SSE_DNKit";
player addItem "COMSPEC_SSE_SEEKII";
```

## 5. Transmission manuelle

```sqf
[
    "SSE-26-000184",
    "digital",
    "smartphone",
    name player,
    getPosATL player,
    87,
    createHashMapFromArray [["note", "extraction complète"]]
] call comspec_sse_fnc_submitRecord;
```

## 6. Créer et réutiliser un modèle

```sqf
private _model = ["Réseau ABU YASSIN", createHashMapFromArray [
    ["profile", "INSURGENT"],
    ["complexity", "HIGH_VALUE"],
    ["region", "IRAQ"],
    ["theme", "weapons_cache"],
    ["aliasPool", ["ABU YASSIN"]],
    ["smsTemplates", [
        "Livraison demain après la prière.",
        "Le camion passe par le point ALPHA."
    ]],
    ["includeComputer", true],
    ["includeBiometrics", true]
], "Zeus"] call comspec_sse_fnc_createModel;

[_model] call comspec_sse_fnc_saveModel;

// Plus tard, sur n'importe quelle cible :
[_unit, _model get "id"] call comspec_sse_fnc_applyModel;
[_phone, "builtin_reseau_courriers"] call comspec_sse_fnc_applyModel;
```
