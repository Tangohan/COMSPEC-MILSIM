/* Choix dans le menu « Outils carte » (et le bouton marqueur). Les outils à clic restent actifs jusqu'au clic droit. */
params ["_key"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
switch (_key) do {
    case "DISTANCE": { _s set ["mapDistance", !(_s getOrDefault ["mapDistance", true])]; };
    case "COMPASS": { profileNamespace setVariable ["COMSPEC_ATAK_Compass", !(profileNamespace getVariable ["COMSPEC_ATAK_Compass", true])]; };
    case "GRID": {
        private _d = profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6];
        profileNamespace setVariable ["COMSPEC_ATAK_GridDigits", [8, 10, 6] select (([6, 8, 10] find _d) max 0)];
    };
    case "CLEAR": {
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        { _s set [_x, []]; } forEach ["mapMeasure", "mapHouses", "mapHeights", "mapFlat", "mapLos"];
    };
    default {
        private _cur = _s getOrDefault ["mapMode", "SELECT"];
        [["SELECT", _key] select (_cur isNotEqualTo _key)] call comspec_atak_native_fnc_mapToolSet;
        if (_key isNotEqualTo "MARKER") then { _s set ["mapToolsOpen", false]; };
    };
};
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
