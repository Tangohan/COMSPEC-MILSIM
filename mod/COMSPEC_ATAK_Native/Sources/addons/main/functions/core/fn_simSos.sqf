/*
    Mode secours de la carte SIM : téléphone à batterie vide, mais la SIM garde une réserve et continue de
    s'annoncer aux relais. Il n'affiche plus rien et ne sert plus à rien pour son porteur, mais il reste
    localisable par les relais à partir de son numéro ou de son IMEI (app GE, onglet GÉOLOC, et RECHERCHER de la carte).
    Réglages serveur « ATAK · Géolocalisation (GEOLOC) » : comspec_atak_native_sim_sos (mode secours actif),
    comspec_atak_native_sim_sos_own (on peut retrouver le téléphone d'un allié en mode secours, même quand
    « Tracer son propre camp » est décoché).
    Params : [action, unité]
      "publish"   : client, toutes les 5 s : variable d'unité COMSPEC_ATAK_SimSos (diffusée seulement quand elle change)
      "active"    : [unité] -> le téléphone de l'unité est-il en mode secours SIM ?
      "ownOk"     : [unité] -> un allié peut-il le retrouver (réglage) ?
      "fix"       : [unité] -> [position, rayon m, nombre de relais, relais le plus proche] ou [] si aucun relais ne l'entend.
                    Précision selon le nombre de relais qui l'entendent : un seul = cellule (cercle large), deux = mieux,
                    trois et plus = triangulation. Mission sans aucun relais : antennes fictives de GÉOLOC, rayon doublé.
*/
params [["_act", "active"], ["_u", objNull]];
switch (_act) do {
    case "publish": {
        if (!hasInterface || {isNull player}) exitWith { false };
        private _on = (missionNamespace getVariable ["comspec_atak_native_sim_sos", true])
            && {missionNamespace getVariable ["comspec_atak_native_battery_sim", true]}
            && {profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]}
            && {alive player}
            && {[player, true] call comspec_atak_native_fnc_hasDevice}
            && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0};
        if ((player getVariable ["COMSPEC_ATAK_SimSos", false]) isNotEqualTo _on) then { player setVariable ["COMSPEC_ATAK_SimSos", _on, true]; };
        _on
    };
    case "active": {
        !isNull _u && {alive _u} && {missionNamespace getVariable ["comspec_atak_native_sim_sos", true]} && {_u getVariable ["COMSPEC_ATAK_SimSos", false]}
    };
    case "ownOk": {
        (["active", _u] call comspec_atak_native_fnc_simSos) && {missionNamespace getVariable ["comspec_atak_native_sim_sos_own", true]}
    };
    case "fix": {
        if (isNull _u) exitWith { [] };
        private _tp = (getPosASL _u) vectorAdd [0, 0, 1];
        private _relays = (["list"] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isEqualTo "relay" };
        private _prec = (missionNamespace getVariable ["comspec_atak_native_geoloc_precision", 150]) max 10;
        if (_relays isEqualTo []) exitWith {
            private _r = _prec * 2 * (0.6 + random 0.8);
            private _p = (getPosATL _u) getPos [random (_r * 0.8), random 360];
            [[_p select 0, _p select 1, 0], round _r, 0, ""]
        };
        private _heard = [];
        {
            if !(_x select 6) then { continue };
            private _l = ["link", [_x select 1, _x select 4, _x select 5, _tp]] call comspec_atak_native_fnc_relayNet;
            if ((_l select 0) > 0) then { _heard pushBack [(_x select 1) distance2D _tp, _x select 2]; };
        } forEach _relays;
        if (_heard isEqualTo []) exitWith { [] };
        _heard sort true;
        private _n = count _heard;
        private _d = (_heard select 0) select 0;
        private _r = switch (true) do {
            case (_n isEqualTo 1): { (_d * 0.5) max 300 };
            case (_n isEqualTo 2): { (_d * 0.3) max 150 };
            default { (_prec * 1.5) max 80 };
        };
        _r = _r * (0.8 + random 0.4);
        private _p = (getPosATL _u) getPos [random (_r * 0.7), random 360];
        [[_p select 0, _p select 1, 0], round _r, _n, (_heard select 0) select 1]
    };
    default { false };
};
