/*
    Icônes des objets portés pour Athena (Équipement des joueurs, Kits par rôle).
    Pour chaque classe du catalogue pas encore proposée pendant cette partie : chemin de l'image `picture` de la config.
    COMSPEC Link (« Item.Icons », 2.0.88) demande au site celles qui lui manquent, lit le .paa dans le PBO du mod
    sur ce PC et l'envoie une seule fois (le site le convertit en PNG). Rien n'est renvoyé pour une classe déjà proposée.
    Params: [_catalog (retour de collectItemCatalog)]
*/
params [["_catalog", [], [[]]]];
if (!hasInterface || {_catalog isEqualTo []}) exitWith {};

private _sent = missionNamespace getVariable ["COMSPEC_ItemIconsSent", nil];
if (isNil "_sent") then {
    _sent = createHashMap;
    missionNamespace setVariable ["COMSPEC_ItemIconsSent", _sent];
};
private _batch = [];
{
    private _class = if (_x isEqualType createHashMap) then { _x getOrDefault ["class", ""] } else { "" };
    if (_class isEqualTo "" || {_class in _sent}) then { continue };
    _sent set [_class, true];
    private _pic = "";
    {
        private _cfg = configFile >> _x >> _class;
        if (isClass _cfg) exitWith { _pic = getText (_cfg >> "picture"); };
    } forEach ["CfgWeapons", "CfgMagazines", "CfgVehicles", "CfgGlasses"];
    // « pictureThing », « pictureStaticObject »… ne sont pas des fichiers.
    if (_pic isEqualTo "" || {((_pic find "\") < 0) && {(_pic find "/") < 0}}) then { continue };
    _batch pushBack [_class, _pic select [0, 255]];
} forEach _catalog;
if (_batch isEqualTo []) exitWith {};

private _r = "COMSPECExtension" callExtension ["Item.Icons", [[createHashMapFromArray [["items", _batch]]] call comspec_overwatch_connect_fnc_jsonValue]];
if ((_r select 0) isEqualTo "") then {
    // Ancienne DLL : pas de nouvel essai pendant cette partie (les classes restent marquées).
    if (isNil "COMSPEC_ItemIconsOldDllLogged") then {
        COMSPEC_ItemIconsOldDllLogged = true;
        ["INFO", "ItemIcons", "Icônes des objets non envoyées : COMSPEC Link 2.0.88 requis"] call comspec_overwatch_connect_fnc_log;
    };
};
