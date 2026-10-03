/*
    Envoi soumis au débit simulé (fn_linkQuality). Params : [code, arguments, libellé, taille en Ko]
    - réseau correct : envoi après latence + temps de transfert (taille / débit) ;
    - perte de paquets : renvoi automatique (3 essais) ;
    - aucun signal : mis en file, parti dès que le réseau revient (vidé par la boucle de XEH_postInitClient).
    Renvoie "SENT" (immédiat), "DELAYED" ou "QUEUED".
*/
params ["_code", ["_args", []], ["_label", "Message"], ["_kb", 1], ["_try", 1]];
private _q = [] call comspec_atak_native_fnc_linkQuality;
if !(_q get "sim") exitWith { _args call _code; "SENT" };
if ((_q get "bars") isEqualTo 0) exitWith {
    private _queue = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    _queue pushBack [_code, _args, _label, _kb];
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", _queue];
    ["WARNING", format ["Pas de réseau : %1 en attente (%2 dans la file)", _label, count _queue], 4, 40] call comspec_atak_native_fnc_notify;
    "QUEUED"
};
private _delay = ((_q get "latency") / 1000) + (_kb * 8 / ((_q get "kbps") max 1));
if (_delay > 3) then { ["INFO", format ["%1 : envoi en cours, environ %2 s (%3 kbit/s)", _label, ceil _delay, _q get "kbps"], (_delay min 8), 15] call comspec_atak_native_fnc_notify; };
[{
    params ["_code", "_args", "_label", "_kb", "_try"];
    private _q = [] call comspec_atak_native_fnc_linkQuality;
    if ((random 100) < (_q get "loss") && {_try < 3}) exitWith {
        ["WARNING", format ["%1 : paquets perdus, renvoi (%2/3)", _label, _try + 1], 3, 20] call comspec_atak_native_fnc_notify;
        [_code, _args, _label, _kb, _try + 1] call comspec_atak_native_fnc_netSend;
    };
    _args call _code;
}, [_code, _args, _label, _kb, _try], _delay] call CBA_fnc_waitAndExecute;
"DELAYED"
