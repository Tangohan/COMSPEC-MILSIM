# API SQF

Toutes les fonctions : `comspec_sse_fnc_*`

## Génération

```sqf
[_unit, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateData;
[_unit, "INSURGENT", "DETAILED", "SCRIPT", _cluster] call comspec_sse_fnc_generateData;
[_building, 40, "INSURGENT", "DETAILED"] call comspec_sse_fnc_generateSite;
[_building, 35, "INSURGENT", "DETAILED", createHashMapFromArray [["maxobjects", 8], ["digital", true]]] call comspec_sse_fnc_generateSite;
```

## Identité / digital

```sqf
[_unit, [["name", "Karim Haddad"], ["alias", "ABU HAMZA"]]] call comspec_sse_fnc_setIdentity;

[_phone, [
    ["owner", "Karim Haddad"],
    ["contacts", ["ABU YASSIN", "MUSTAFA"]],
    ["deviceType", "SMARTPHONE"]
]] call comspec_sse_fnc_setDigitalData;
```

## Liens

```sqf
[_phone, _person, "OWNER"] call comspec_sse_fnc_linkEntities;
[_personA, _personB, "CONTACT", 0.8, "ZEUS"] call comspec_sse_fnc_linkEntities;
[_document, _vehicle, "REFERENCES"] call comspec_sse_fnc_linkEntities;
private _links = [_person] call comspec_sse_fnc_getLinks;
```

## Make searchable / lazy

```sqf
[_object, "PHONE"] call comspec_sse_fnc_makeSearchable;
[_object] call comspec_sse_fnc_ensureGenerated; // force génération
```

## Lecture

```sqf
private _data = [_entity] call comspec_sse_fnc_getData;
private _id = [_entity, "identity"] call comspec_sse_fnc_getSection;
private _state = [_entity] call comspec_sse_fnc_getState;
```

## Modèles

```sqf
[_unit, "builtin_chef_hvt"] call comspec_sse_fnc_applyModel;

private _m = ["Cellule custom", [
    ["profile", "INSURGENT"],
    ["theme", "ied_cell"],
    ["region", "IRAQ"],
    ["includeComputer", true]
]] call comspec_sse_fnc_createModel;
[_m] call comspec_sse_fnc_saveModel;
[_unit, _m get "id"] call comspec_sse_fnc_applyModel;
```

Voir [MODELS.md](MODELS.md).

## Transmission

```sqf
[
    "SSE-26-000184",
    "digital",
    "smartphone",
    "BAKER-2",
    getPosATL player,
    87,
    _dataHashMap
] call comspec_sse_fnc_submitRecord;
```
