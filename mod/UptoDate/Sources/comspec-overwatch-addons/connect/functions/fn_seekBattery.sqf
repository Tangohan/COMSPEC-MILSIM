/*
    Batterie réelle du SEEK II (poste du joueur, toute la mission).

    L'ancienne batterie était un affichage (« 97 % moins la durée de mission, jamais sous 12 % ») :
    elle restait bloquée à 12 %. Ici elle se décharge vraiment : écran allumé (fn_sseTerminalGuard,
    toutes les 2 s), chaque capture, chaque interrogation, chaque envoi.
      12 % et moins : avertissement à l'écran du SEEK (barre de titre et barre d'état).
      0 %          : l'appareil s'éteint (écran noir), plus aucune action.

    [_op, _arg] call comspec_overwatch_connect_fnc_seekBattery
      "get"            -> niveau 0-100
      "can"            -> true si l'appareil est allumé (niveau > 0)
      "use"  [points]  décharge (capture 0.8, requête 0.5, envoi 0.5) ; rend "can"
      "tick"           décharge d'écran allumé (2 s) puis "render"
      "set"  [niveau]  recharge ou réglage (Zeus, script de mission)
      "render"         barre de titre (BAT, heure) et écran éteint

    Réglages (missionNamespace) : comspec_seek_batteryStart (100 par défaut),
    comspec_seek_batteryScreenDrain (0.04 point par tranche de 2 s, soit ~80 min d'écran).
*/
params [["_op", "get", [""]], ["_arg", 0]];

private _lvl = missionNamespace getVariable ["comspec_seek_battery", -1];
if (!(_lvl isEqualType 0) || {_lvl < 0}) then {
    _lvl = (missionNamespace getVariable ["comspec_seek_batteryStart", 100]) min 100 max 0;
    missionNamespace setVariable ["comspec_seek_battery", _lvl];
};

private _store = {
    params ["_v"];
    private _was = missionNamespace getVariable ["comspec_seek_battery", 100];
    _v = (_v max 0) min 100;
    missionNamespace setVariable ["comspec_seek_battery", _v];
    if (_was > 0 && {_v <= 0}) then { diag_log text "[COMSPEC SEEK] Batterie vide : appareil éteint."; };
    _v
};

switch (_op) do {
    case "get": { _lvl };
    case "can": { _lvl > 0 };
    case "set": {
        [_arg] call _store;
        ["render"] call comspec_overwatch_connect_fnc_seekBattery;
        true
    };
    case "use": {
        private _pts = if (_arg isEqualType 0) then { _arg } else { 0.5 };
        if (_lvl <= 0) exitWith { false };
        private _v = [_lvl - _pts] call _store;
        ["render"] call comspec_overwatch_connect_fnc_seekBattery;
        _v > 0
    };
    case "tick": {
        if (_lvl > 0) then {
            [_lvl - (missionNamespace getVariable ["comspec_seek_batteryScreenDrain", 0.04])] call _store;
        };
        ["render"] call comspec_overwatch_connect_fnc_seekBattery;
        true
    };
    case "render": {
        private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9991; };
        if (isNull _disp) exitWith { false };
        private _pct = ceil _lvl;
        private _st = systemTime;
        private _two = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
        private _clock = format ["%1:%2", [_st select 3] call _two, [_st select 4] call _two];
        private _col = switch (true) do {
            case (_pct <= 12): { "#ff8a7a" };
            case (_pct <= 25): { "#ffd166" };
            default { "#ffffff" };
        };
        private _bat = _disp displayCtrl 9526;
        if (!isNull _bat) then {
            _bat ctrlSetStructuredText parseText format [
                "<t size='0.48' align='right' color='%1'>%2 %3%4</t><t size='0.48' align='right' color='#ffffff'>   %5</t>",
                _col, ["BAT", "BAT FAIBLE" call comspec_overwatch_connect_fnc_tr] select (_pct <= 12), _pct, "%", _clock
            ];
        };
        // Écran éteint : voile noir par-dessus tout l'écran (les actions sont refusées en amont).
        private _off = _lvl <= 0;
        { private _c = _disp displayCtrl _x; if (!isNull _c) then { _c ctrlShow _off; }; } forEach [9690, 9691];
        if (_off) then {
            private _t = _disp displayCtrl 9691;
            if (!isNull _t) then {
                _t ctrlSetStructuredText parseText format [
                    "<t align='center' size='0.5' color='#3a3f44'>%1</t>",
                    "Batterie vide : appareil éteint" call comspec_overwatch_connect_fnc_tr
                ];
            };
        };
        true
    };
    default { _lvl };
};
