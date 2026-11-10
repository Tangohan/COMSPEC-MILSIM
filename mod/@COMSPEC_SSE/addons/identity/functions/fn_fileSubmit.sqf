/*
    Envoi au passage aux fichiers d'Athena (fiche SEEK II, consultation, relevé) par la commande existante de COMSPEC Link
    (SubmitIdCheck → POST /api/sse/identity/checks, champ « kind »), aucune nouvelle commande de DLL.
    [_payload] call comspec_sse_fnc_fileSubmit -> true (l'envoi part en tâche de fond)
      _payload HashMap avec kind (seek_record | file_query | id_record) et idempotency_key (4 à 80 caractères).
    Licence pas encore acceptée : rien ne part ni n'est mis en file. Pas de liaison : file hors ligne du SSE
    (fn_queueOffline, renvoyée par fn_flushQueue au retour de la liaison).
    Évènement CBA local comspec_sse_fileSubmitted [clé, état (sent | queued | error), référence].
*/
params [["_payload", createHashMap, [createHashMap]]];
private _key = _payload getOrDefault ["idempotency_key", ""];
if (_key isEqualTo "" || {(_payload getOrDefault ["kind", ""]) isEqualTo ""}) exitWith { ["comspec_sse_fileSubmitted", [_key, "error", ""]] call CBA_fnc_localEvent; false };
if ((_payload getOrDefault ["map", ""]) isEqualTo "") then { _payload set ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])]; };
if ((_payload getOrDefault ["op", ""]) isEqualTo "") then { _payload set ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]]; };
[_payload, _key] spawn {
    params ["_payload", "_key"];
    private _lic = isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]};
    if (!_lic) exitWith { ["comspec_sse_fileSubmitted", [_key, "error", "licence"]] call CBA_fnc_localEvent; };
    private _sent = false;
    if (["has"] call comspec_sse_fnc_idAthenaPack) then {
        private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
        private _parts = [];
        for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
        private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
        if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
            private _p = _raw splitString "|";
            ["comspec_sse_fileSubmitted", [_key, "sent", _p param [2, _p param [1, ""]]]] call CBA_fnc_localEvent;
            _sent = true;
        } else {
            diag_log text format ["[COMSPEC SSE] Fichiers : envoi %1 refusé (%2), mis en file.", _key, _raw];
        };
    };
    if (_sent) exitWith {};
    [createHashMapFromArray [["kind", "FILEPUSH"], ["command", "SubmitIdCheck"], ["payload", _payload], ["idempotency_key", _key], ["record_id", _key], ["txStatus", "PENDING"]]] call comspec_sse_fnc_queueOffline;
    ["comspec_sse_fileSubmitted", [_key, "queued", ""]] call CBA_fnc_localEvent;
};
true
