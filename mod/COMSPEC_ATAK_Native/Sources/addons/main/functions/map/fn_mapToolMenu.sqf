/* Choix dans le menu « Outils carte » (et le bouton marqueur). Les outils à clic restent actifs jusqu'au clic droit. */
params ["_key"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
switch (_key) do {
    case "DISTANCE": { _s set ["mapDistance", !(_s getOrDefault ["mapDistance", true])]; };
    case "COMPASS": { if ((["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 1) then { ["INFO", "Réglage imposé par votre communauté", 3, 20] call comspec_atak_native_fnc_notify; } else { profileNamespace setVariable ["COMSPEC_ATAK_Compass", !(profileNamespace getVariable ["COMSPEC_ATAK_Compass", true])]; }; };
    case "ZONES": {
        private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]);
        profileNamespace setVariable ["COMSPEC_ATAK_ZonesLayer", _on];
        // Overwatch pose aussi chaque zone en marqueur COMSPEC_TZ_<id> : on les masque avec le calque.
        { if ((_x select [0, 11]) isEqualTo "COMSPEC_TZ_") then { _x setMarkerAlphaLocal ([0, 0.7] select _on); }; } forEach allMapMarkers;
        private _n = (count (missionNamespace getVariable ["COMSPEC_DangerZones", []])) + (count (missionNamespace getVariable ["COMSPEC_RoleplayZones", []]));
        ["INFO", if (_on) then { [format ["Zones tactiques affichées (%1)", _n], "Zones tactiques affichées : aucune zone reçue d'Athena pour cette carte"] select (_n isEqualTo 0) } else { format ["Zones tactiques masquées (%1)", _n] }, 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "SIGINT": { profileNamespace setVariable ["COMSPEC_ATAK_SigintLayer", !(profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true])]; [] spawn comspec_atak_native_fnc_sigintPoll; };
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
