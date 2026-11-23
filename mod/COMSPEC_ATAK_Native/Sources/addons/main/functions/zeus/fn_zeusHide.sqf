/*
    Objets d'appui du mod que le Zeus ne doit jamais voir dans sa liste d'objets éditables ni pouvoir déplacer :
    supports invisibles des relais convertis (Land_HelipadEmpty_F au pied des antennes du décor), supports des dalles
    d'écran TOC supplémentaires et nœuds simulés du maillage (sphères du mode développeur). Retour de TTRD : « le TOC
    fait spawn des héliports invisibles », « le réseau fait spawn des sphères » dans la liste du Zeus.
    Le Zeus (ou un autre mod, Zeus Enhanced par exemple) inscrit seul les objets créés sur le serveur : retrait au moment
    de la création, puis vérification toutes les 30 s (sweep) pour ceux qui se réinscrivent.
      ["add", [objet ou [objets]]]  retire de tous les curateurs et mémorise (sur un client : demande envoyée au serveur)
      ["sweep"]                    serveur : oublie les objets supprimés, retire de nouveau ceux qui sont revenus
    Variable serveur : COMSPEC_ATAK_ZeusHidden (liste d'objets).
*/
params [["_mode", "sweep"], ["_a", []]];
if (!isServer) exitWith {
    if (_mode isEqualTo "add") then {
        private _objs = (_a param [0, []]);
        if (_objs isEqualType objNull) then { _objs = [_objs]; };
        _objs = _objs select { !isNull _x };
        if (_objs isNotEqualTo []) then { ["comspec_atak_native_zeusHide", [_objs]] call CBA_fnc_serverEvent; };
    };
    false
};
private _list = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusHidden", []]) select { !isNull _x };
// Retire seulement ce qui est réellement inscrit chez un curateur.
private _strip = {
    params ["_objs"];
    {
        private _have = _objs arrayIntersect (curatorEditableObjects _x);
        if (_have isNotEqualTo []) then { _x removeCuratorEditableObjects [_have, true]; };
    } forEach allCurators;
};
switch (_mode) do {
    case "add": {
        private _objs = (_a param [0, []]);
        if (_objs isEqualType objNull) then { _objs = [_objs]; };
        _objs = _objs select { !isNull _x };
        if (_objs isEqualTo []) exitWith { false };
        [_objs] call _strip;
        { _list pushBackUnique _x; } forEach _objs;
        // Borné : les plus anciens sortent (un objet supprimé est de toute façon retiré au prochain passage).
        if ((count _list) > 400) then { _list = _list select [(count _list) - 400, 400]; };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusHidden", _list];
        true
    };
    default {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusHidden", _list];
        if (_list isNotEqualTo [] && {allCurators isNotEqualTo []}) then { [_list] call _strip; };
        true
    };
};
