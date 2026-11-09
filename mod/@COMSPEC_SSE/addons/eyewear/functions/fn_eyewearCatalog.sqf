/*
    Catalogue des lunettes de sport (config racine COMSPEC_SSE_Eyewear, voir CfgEyewear.hpp), en cache.

    Renvoie [parId, parClasse, ids, objets] :
      parId     id -> hashmap (id, name, glasses, nvg, item, head, picture, tint)
      parClasse classe en minuscules (lunettes, _NVG, _Item, objet de tête) -> id
      ids       ids dans l'ordre de la config
      objets    classes <classe>_Item en minuscules (objets du slot virtuel)
*/
private _c = missionNamespace getVariable ["comspec_sse_eyewear_cat", []];
if (_c isNotEqualTo []) exitWith { _c };
private _byId = createHashMap;
private _byClass = createHashMap;
private _ids = [];
private _items = [];
{
    private _id = configName _x;
    private _e = createHashMapFromArray [
        ["id", _id],
        ["name", getText (_x >> "displayName")],
        ["glasses", getText (_x >> "glasses")],
        ["nvg", getText (_x >> "nvg")],
        ["item", getText (_x >> "item")],
        ["head", getText (_x >> "head")],
        ["picture", getText (_x >> "picture")],
        ["tint", getArray (_x >> "tint")]
    ];
    _byId set [_id, _e];
    _ids pushBack _id;
    _items pushBack toLower (_e get "item");
    { _byClass set [toLower (_e get _x), _id]; } forEach ["glasses", "nvg", "item", "head"];
} forEach ("true" configClasses (configFile >> "COMSPEC_SSE_Eyewear"));
_c = [_byId, _byClass, _ids, _items];
missionNamespace setVariable ["comspec_sse_eyewear_cat", _c];
_c
