/*
    Envoi soumis au débit simulé (fn_linkQuality). Params : [code, arguments, libellé, taille en Ko, essai (1), clé ("")]
    - réseau correct : envoi après latence + temps de transfert (taille / débit) ;
    - un seul canal radio : les envois passent l'un après l'autre, le débit se partage (une photo retarde le message
      suivant) ; latence avec gigue (fn_linkQuality "jitter") ;
    - perte de paquets : renvoi automatique (3 essais) ; après 3 échecs l'envoi retourne en file au lieu d'arriver ;
      pertes en rafales (modèle de Gilbert-Elliott : un lien passe par de mauvaises périodes où plusieurs envois de
      suite se perdent, puis redevient bon) ; condition « Pertes de paquets » coupée (fn_netCond "loss") : rien ne se perd ;
    - surcharge : chaque envoi compte dans le trafic publié du téléphone (fn_netLoad "track") ;
    - réception seule (hors de portée d'émission, fn_linkQuality "tx" faux) : comme sans signal ;
    - aucun signal : mis en file, parti dès que le réseau revient (vidé par la boucle de XEH_postInitClient) ; la file
      porte le code de chaque envoi : elle est rangée en localNamespace (propre à la machine, jamais synchronisée) ;
      un envoi avec une clé remplace celui de même clé déjà en file (marqueur modifié plusieurs fois : seul le dernier part) ;
      les envois urgents (MEDEVAC, alertes, JTAC, rapports…) passent devant les autres dans la file et ne sont jamais
      abandonnés quand elle est pleine (les autres les plus anciens partent).
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
if (_try isEqualTo 1) then { ["track", _kb] call comspec_atak_native_fnc_netLoad; };
if !(_q get "sim") exitWith { ["IMMÉDIAT", "simulation coupée"] call _trace; _args call _code; "SENT" };
if ((_q get "bars") isEqualTo 0 || {!(_q getOrDefault ["tx", true])}) exitWith {
    private _queue = localNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    if (_key isNotEqualTo "") then { _queue = _queue select { (_x param [5, ""]) isNotEqualTo _key }; };
    private _urgent = { params ["_l"]; _l = toLower _l; (["medevac", "alerte", "salute", "jtac", "rapport", "blessure", "décès", "panique", "accusé"] findIf { (_l find _x) >= 0 }) >= 0 };
    // Priorité : un envoi urgent se place après le dernier urgent déjà en file, devant les envois ordinaires.
    if ([_label] call _urgent) then {
        private _at = _queue findIf { !([_x param [2, ""]] call _urgent) };
        if (_at < 0) then { _queue pushBack [_code, _args, _label, _kb, 1, _key]; } else { _queue insert [_at, [[_code, _args, _label, _kb, 1, _key]]]; };
    } else {
        _queue pushBack [_code, _args, _label, _kb, 1, _key];
    };
    // File bornée : sans réseau longtemps, les plus anciens envois sont abandonnés au-delà de 50, les urgents en dernier.
    while { (count _queue) > 50 } do {
        private _i = _queue findIf { !([_x param [2, ""]] call _urgent) };
        _queue deleteAt (_i max 0);
    };
    private _why = ["aucun signal", "réception seule"] select ((_q get "bars") > 0);
    ["EN FILE", _why] call _trace;
    localNamespace setVariable ["COMSPEC_ATAK_NetQueue", _queue];
    ["WARNING", format [["Pas de réseau : %1 en attente (%2 dans la file)", "Réception seule, émission impossible : %1 en attente (%2 dans la file)"] select ((_q get "bars") > 0), _label, count _queue], 4, 40] call comspec_atak_native_fnc_notify;
    "QUEUED"
};
// Canal partagé : cet envoi attend la fin de ceux déjà en cours (COMSPEC_ATAK_NetBusyUntil, horloge time) ; latence
// plus une gigue aléatoire.
private _air = _kb * 8 / ((_q get "kbps") max 1);
private _wait = 0;
if ((["realism"] call comspec_atak_native_fnc_netCond) isNotEqualTo 0) then {
    private _busy = missionNamespace getVariable ["COMSPEC_ATAK_NetBusyUntil", 0];
    _wait = (_busy - time) max 0;
    missionNamespace setVariable ["COMSPEC_ATAK_NetBusyUntil", time + _wait + _air];
};
private _delay = _wait + (((_q get "latency") + random (_q getOrDefault ["jitter", 0])) / 1000) + _air;
["ENVOI", format ["essai %1 · %2 s · %3 kbit/s · perte %4 %%", _try, _delay toFixed 1, _q get "kbps", _q get "loss"]] call _trace;
if (_delay > 3) then { ["INFO", format ["%1 : envoi en cours, environ %2 s (%3 kbit/s)", _label, ceil _delay, _q get "kbps"], (_delay min 8), 15] call comspec_atak_native_fnc_notify; };
[{
    params ["_code", "_args", "_label", "_kb", "_try", "_key"];
    private _q = [] call comspec_atak_native_fnc_linkQuality;
    private _t = missionNamespace getVariable ["COMSPEC_ATAK_NetLog", []];
    private _real = (["realism"] call comspec_atak_native_fnc_netCond) isNotEqualTo 0;
    private _lost = false;
    if (["loss"] call comspec_atak_native_fnc_netCond) then {
        if (_real) then {
            // Gilbert-Elliott : état « mauvais » où un envoi sur deux se perd, rafales d'environ 3 envois ; en bon état,
            // pertes résiduelles faibles. La perte moyenne suit celle du lien.
            private _l = ((_q get "loss") / 100) min 0.95;
            private _pib = (_l / 0.5) min 0.9;
            private _bad = missionNamespace getVariable ["COMSPEC_ATAK_NetGE", false];
            _bad = switch (true) do {
                case (_l <= 0): { false };
                case (_bad): { (random 1) >= 0.35 };
                default { (random 1) < ((_pib * 0.35 / ((1 - _pib) max 0.05)) min 1) };
            };
            missionNamespace setVariable ["COMSPEC_ATAK_NetGE", _bad];
            _lost = _l > 0 && {(random 1) < ([_l * 0.1, 0.5 max _l] select _bad)};
        } else {
            _lost = (random 100) < (_q get "loss") && {_try < 3};
        };
    };
    _t pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _label, _kb, ["LIVRÉ", "PERDU"] select _lost, ["", ["renvoi", "remis en file"] select (_try >= 3)] select _lost];
    if ((count _t) > 100) then { _t deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_NetLog", _t];
    // Troisième échec (modèle réaliste) : l'envoi n'arrive pas, il retourne en tête de file et repartira avec elle.
    if (_lost && {_try >= 3}) exitWith {
        private _queue = localNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
        _queue insert [0, [[_code, _args, _label, _kb, 1, _key]]];
        localNamespace setVariable ["COMSPEC_ATAK_NetQueue", _queue];
        ["WARNING", format ["%1 : échec après 3 essais, remis en file", _label], 4, 30] call comspec_atak_native_fnc_notify;
    };
    if (_lost) exitWith {
        ["WARNING", format ["%1 : paquets perdus, renvoi (%2/3)", _label, _try + 1], 3, 20] call comspec_atak_native_fnc_notify;
        [_code, _args, _label, _kb, _try + 1, _key] call comspec_atak_native_fnc_netSend;
    };
    _args call _code;
}, [_code, _args, _label, _kb, _try, _key], _delay] call CBA_fnc_waitAndExecute;
"DELAYED"
