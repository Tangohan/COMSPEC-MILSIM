/*
    Pose d'un marqueur au point choisi (clic droit) avec la palette du mode MARQUEUR :
    ["open", pos]      ouvre la pose au point (menu du clic droit, double clic) : palette, ou éditeur complet selon le
                       réglage d'addon « Menu de pose des marqueurs » (comspec_atak_native_marker_menu)
    ["anchor", pos]    choisit le point de pose (clic droit en mode MARQUEUR), la palette reste ouverte
    ["validate"]       VALIDER : pose le marqueur au point choisi ; « À la suite » garde la palette ouverte
    ["close"]          ferme la palette (retour à la sélection)
    ["fold"]           replie / déplie la palette (gardé dans le profil)
    ["series"]         bascule « À la suite » (gardé dans le profil)
    ["setChannel", n]  canal des prochains marqueurs
    ["channel"]        -> canal des marqueurs posés par le téléphone (défaut : réglage d'addon, Général)
    ["channelName", n] -> nom du canal n (Général, Camp, Commandement, Groupe, Véhicule, Direct)
    État : COMSPEC_ATAK_State markerAnchor = position monde ou [], markerChannel = canal choisi dans la palette.
*/
params [["_mode", ""], ["_arg", []]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _redraw = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (_mode) do {
    case "channel": {
        private _c = _s getOrDefault ["markerChannel", missionNamespace getVariable ["comspec_atak_native_marker_channel", 0]];
        if !(_c isEqualType 0) then { _c = 0; };
        (_c max 0) min 5
    };
    case "channelName": {
        (["Général", "Camp", "Commandement", "Groupe", "Véhicule", "Direct"] param [_arg, format ["%1", _arg]]) call comspec_atak_native_fnc_tr
    };
    case "setChannel": {
        _s set ["markerChannel", _arg];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "open": {
        if ((count _arg) < 2) exitWith { false };
        private _pos = [_arg select 0, _arg select 1, 0];
        if ((missionNamespace getVariable ["comspec_atak_native_marker_menu", "palette"]) isEqualTo "editor") exitWith {
            ["", _pos] call comspec_atak_native_fnc_markerEditOpen;
            true
        };
        _s set ["selectedMarker", ""];
        _s set ["markerEdit", createHashMap];
        _s set ["mapToolsOpen", false];
        if ((_s getOrDefault ["mapMode", "SELECT"]) isNotEqualTo "MARKER") then { ["MARKER"] call comspec_atak_native_fnc_mapToolSet; };
        // Ouverte par le clic droit : on la déplie pour qu'on voie de quoi choisir.
        profileNamespace setVariable ["COMSPEC_ATAK_MarkerFold", false];
        _s set ["markerAnchor", _pos];
        call _redraw;
        true
    };
    case "anchor": {
        if ((count _arg) < 2) exitWith { false };
        _s set ["markerAnchor", [_arg select 0, _arg select 1, 0]];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "validate": {
        private _pos = _s getOrDefault ["markerAnchor", []];
        if ((count _pos) < 2) exitWith {
            ["INFO", "Clic droit sur la carte pour choisir le point, puis VALIDER (ou clic gauche : posé tout de suite)", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        private _m = [_pos] call comspec_atak_native_fnc_markerDrop;
        if (_m isEqualTo "") exitWith { false };
        _s set ["markerAnchor", []];
        // Une seule pose : retour à la sélection ; « À la suite » : la palette reste pour le marqueur suivant.
        if (!(profileNamespace getVariable ["COMSPEC_ATAK_MarkerSeries", false]) && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
            ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        };
        call _redraw;
        true
    };
    case "close": {
        _s set ["markerAnchor", []];
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "fold": {
        profileNamespace setVariable ["COMSPEC_ATAK_MarkerFold", !(profileNamespace getVariable ["COMSPEC_ATAK_MarkerFold", false])];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "series": {
        profileNamespace setVariable ["COMSPEC_ATAK_MarkerSeries", !(profileNamespace getVariable ["COMSPEC_ATAK_MarkerSeries", false])];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    default { false };
};
