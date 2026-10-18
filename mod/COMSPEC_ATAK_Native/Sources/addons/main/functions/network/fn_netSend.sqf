/*
    Envoi soumis au débit simulé (fn_linkQuality). Params : [code, arguments, libellé, taille en Ko, essai (1), clé ("")]
    - réseau correct : envoi après latence + temps de transfert (taille / débit) ;
    - perte de paquets : renvoi automatique (3 essais) ;
    - aucun signal : mis en file, parti dès que le réseau revient (vidé par la boucle de XEH_postInitClient) ;
      un envoi avec une clé remplace celui de même clé déjà en file (marqueur modifié plusieurs fois : seul le dernier part) ;
      file pleine : les envois urgents (MEDEVAC, alertes, JTAC, rapports…) sont gardés, les autres les plus anciens partent.
    Renvoie "SENT" (immédiat), "DELAYED" ou "QUEUED".
*/
params ["_code", ["_args", []], ["_label", "Message"], ["_kb", 1], ["_try", 1], ["_key", ""]];
private _q = [] call comspec_atak_native_fnc_linkQuality;
// Journal des transferts (app Debug) : [heure, libellé, Ko, état, détail]
private _trace = {
    params ["_st", ["_det", ""]];
    private _t = missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []];
    _t pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _label, _kb, _st, _det];
    if ((count _t) > 100) then { _t deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_NetLog", _t];
};
// Horodatage des émissions pour la consommation de batterie (fn_battery).
private _txAt = missionNamespace getVariable ["COMSPEC_ATAK_NetTxAt", []];
_txAt pushBack diag_tickTime;
if ((count _txAt) > 50) then { _txAt deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ATAK_NetTxAt", _txAt];
if !(_q get "sim") exitWith { ["IMMÉDIAT", "simulation coupée"] call _trace; _args call _code; "SENT" };
if ((_q get "bars") isEqualTo 0) exitWith {
    private _queue = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    if (_key isNotEqualTo "") then { _queue = _queue select { (_x param [5, ""]) isNotEqualTo _key }; };
    _queue pushBack [_code, _args, _label, _kb, 1, _key];
    // File bornée : sans réseau longtemps, les plus anciens envois sont abandonnés au-delà de 50, les urgents en dernier.
    private _urgent = { params ["_l"]; _l = toLower _l; (["medevac", "alerte", "salute", "jtac", "rapport", "blessure", "décès", "panique", "accusé"] findIf { (_l find _x) >= 0 }) >= 0 };
    while { (count _queue) > 50 } do {
        private _i = _queue findIf { !([_x param [2, ""]] call _urgent) };
        _queue deleteAt (_i max 0);
    };
    ["EN FILE", "aucun signal"] call _trace;
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", _queue];
    ["WARNING", format ["Pas de réseau : %1 en attente (%2 dans la file)", _label, count _queue], 4, 40] call comspec_atak_native_fnc_notify;
    "QUEUED"
};
private _delay = ((_q get "latency") / 1000) + (_kb * 8 / ((_q get "kbps") max 1));
["ENVOI", format ["essai %1 · %2 s · %3 kbit/s · perte %4 %%", _try, _delay toFixed 1, _q get "kbps", _q get "loss"]] call _trace;
if (_delay > 3) then { ["INFO", format ["%1 : envoi en cours, environ %2 s (%3 kbit/s)", _label, ceil _delay, _q get "kbps"], (_delay min 8), 15] call comspec_atak_native_fnc_notify; };
[{
    params ["_code", "_args", "_label", "_kb", "_try"];
    private _q = [] call comspec_atak_native_fnc_linkQuality;
    private _t = missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []];
    private _lost = (random 100) < (_q get "loss") && {_try < 3};
    _t pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _label, _kb, ["LIVRÉ", "PERDU"] select _lost, ["", "renvoi"] select _lost];
    if ((count _t) > 100) then { _t deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_NetLog", _t];
    if (_lost) exitWith {
        ["WARNING", format ["%1 : paquets perdus, renvoi (%2/3)", _label, _try + 1], 3, 20] call comspec_atak_native_fnc_notify;
        [_code, _args, _label, _kb, _try + 1] call comspec_atak_native_fnc_netSend;
    };
    _args call _code;
}, [_code, _args, _label, _kb, _try], _delay] call CBA_fnc_waitAndExecute;
"DELAYED"
