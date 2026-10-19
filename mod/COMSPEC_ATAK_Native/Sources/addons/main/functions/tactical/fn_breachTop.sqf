/*
    Réception d'un top de brèche : compte à rebours dans l'écran du téléphone (alerte fn_fullAlert, jamais sur l'écran
    de jeu), puis « BRÈCHE ! ». Téléphone rangé : notification et vibration seulement.
    Params : [nom de l'émetteur, secondes]
*/
params [["_from", ""], ["_secs", 5]];
if (!hasInterface) exitWith {};
[_from, _secs] spawn {
    params ["_from", "_secs"];
    ["WARNING", format ["Top de brèche de %1 : %2 s", _from, _secs], 3, 90] call comspec_atak_native_fnc_notify;
    [str _secs, format ["brèche · %1", _from], "Top de brèche", [0.70, 0.40, 0.04, 0.94], _secs + 2] call comspec_atak_native_fnc_fullAlert;
    private _at = (uiNamespace getVariable ["COMSPEC_ATAK_FullAlert", []]) param [5, -1];
    // Le même bandeau change de chiffre chaque seconde (pas de nouvelle alerte ni de nouvelle vibration).
    private _set = {
        params ["_title", "_rgba"];
        private _cur = uiNamespace getVariable ["COMSPEC_ATAK_FullAlert", []];
        if ((_cur param [5, -2]) isNotEqualTo _at) exitWith {};
        _cur set [0, _title];
        if !(isNil "_rgba") then { _cur set [3, _rgba]; };
        ["draw"] call comspec_atak_native_fnc_fullAlert;
    };
    for "_i" from (_secs - 1) to 1 step -1 do {
        uiSleep 1;
        [str _i] call _set;
    };
    uiSleep 1;
    ["BRÈCHE !", [0.62, 0.07, 0.06, 0.94]] call _set;
};
