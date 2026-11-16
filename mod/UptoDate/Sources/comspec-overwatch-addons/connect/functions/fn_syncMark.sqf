/*
    Instrumentation de la synchronisation avec Athena : dernière réponse de COMSPEC Link par commande, lue par la page
    Debug du téléphone (onglet TRANSFERTS, section « Synchronisation par flux »). Appelée par fn_extDirect et fn_extAwait
    (connect et ATAK natif) : une écriture de table par appel, aucune boucle, rien à l'écran.
      [commande, résultat brut] call comspec_overwatch_connect_fnc_syncMark   ; résultat "HELD" = retenu (fn_uplinkHold)
    État : missionNamespace COMSPEC_SyncMarks, commande -> [dernier succès, dernier échec, erreur, nb succès,
    nb échecs, dernière retenue] (heures diag_tickTime, -1 = jamais).
    Succès = réponse reçue de la DLL (« OK|queued » compris : la DLL a pris l'envoi en charge) ; échec = « ERR|… ».
*/
params [["_cmd", ""], ["_r", ""]];
if (!(_cmd isEqualType "") || {_cmd in ["", "LogWrite", "LogSessionStart", "Async", "HashText", "GetExtensionVersion", "GetVersion", "Ping", "Perf.Stats"]}) exitWith {};
if (_r isEqualType []) then { _r = _r param [0, ""]; };
if !(_r isEqualType "") then { _r = str _r; };
private _m = missionNamespace getVariable ["COMSPEC_SyncMarks", objNull];
if ((typeName _m) isNotEqualTo "HASHMAP") then { _m = createHashMap; missionNamespace setVariable ["COMSPEC_SyncMarks", _m]; };
private _e = _m getOrDefault [_cmd, [-1, -1, "", 0, 0, -1]];
switch (true) do {
    case (_r isEqualTo "HELD"): { _e set [5, diag_tickTime]; };
    case ((_r select [0, 4]) isEqualTo "ERR|"): {
        _e set [1, diag_tickTime];
        _e set [2, _r select [4, 40]];
        _e set [4, (_e select 4) + 1];
    };
    default {
        _e set [0, diag_tickTime];
        _e set [3, (_e select 3) + 1];
    };
};
_m set [_cmd, _e];
