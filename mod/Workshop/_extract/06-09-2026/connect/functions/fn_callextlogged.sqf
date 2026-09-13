/*
    callExtension Athena avec journalisation tentative + résultat.

    Params:
        0: STRING — commande extension
        1: ARRAY  — arguments
        2: STRING — libellé métier court (optionnel, défaut = commande)
        3: BOOL   — journal modules (défaut false)
        4: BOOL   — journaliser aussi la tentative (défaut true)
        5: STRING — catégorie liaison (défaut "system")
        6: BOOL   — mettre en file si la liaison est coupée (défaut false)

    Le 7e paramètre est le point d'entrée du tampon hors ligne. Il est à false par
    défaut, et c'est délibéré : on ne met en file que ce qu'un humain a rédigé.
    Mettre en file les positions et les interrogations périodiques rejouerait au
    rétablissement des données périmées, ce qui trompe le poste de commandement
    plus sûrement que leur absence.

    Retour: [_ok, _status, _detail] — même format que parseAtakExtResponse
*/
params [
    ["_cmd", "", [""]],
    ["_args", [], [[]]],
    ["_label", "", [""]],
    ["_moduleLog", false, [true]],
    ["_logAttempt", true, [true]],
    ["_linkCat", "system", [""]],
    ["_queueable", false, [true]]
];

if (_cmd isEqualTo "") exitWith { [false, "ERR", "empty_cmd"] };
if (_label isEqualTo "") then { _label = _cmd; };

// Liaison coupée et transmission rejouable : on met en file plutôt que d'appeler
// l'extension pour rien. L'appelant reçoit le statut « QUEUED », distinct de
// l'échec — ce n'est pas perdu, c'est différé, et il ne doit pas afficher la
// même chose à l'opérateur.
//
// La décision est calculée d'abord, puis l'unique sortie est faite au niveau du
// script : un exitWith placé dans un bloc « then » ne quitterait que ce bloc, et
// l'extension serait appelée quand même.
private _mustQueue = false;
private _queueReason = "offline";
if (_queueable) then {
    private _link = [false] call comspec_overwatch_connect_fnc_canTransmit;
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])
        || { !(_link getOrDefault ["can_transmit", true]) }) then {
        _mustQueue = true;
        _queueReason = _link getOrDefault ["reason", "offline"];
        if (!(_queueReason isEqualType "") || { _queueReason isEqualTo "" }) then {
            _queueReason = "offline";
        };
    };
};

if (_mustQueue) exitWith {
    [_cmd, _args, _label, _linkCat] call comspec_overwatch_connect_fnc_outboxPush;
    [_label, "queued", _queueReason, nil, _moduleLog, _linkCat]
        call comspec_overwatch_connect_fnc_logTransmission;
    [false, "QUEUED", _queueReason]
};

if (_logAttempt) then {
    [_label, "attempt", "", nil, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
};

private _raw = "COMSPECExtension" callExtension [_cmd, _args];
private _parsed = [_raw] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "_status", "_detail"];

private _extErr = missionNamespace getVariable ["COMSPEC_LastExtError", 0];
if (!_ok) then {
    private _why = _detail;
    if (_why isEqualTo "" && {_status isNotEqualTo ""}) then { _why = _status; };
    if (_extErr isEqualType 0 && {_extErr != 0}) then {
        _why = format ["%1 (ext %2)", _why, _extErr];
    };
    [_label, "fail", _why, _raw, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
} else {
    [_label, "ok", if (_detail isEqualTo "") then { "OK" } else { _detail }, nil, _moduleLog, _linkCat] call comspec_overwatch_connect_fnc_logTransmission;
};

_parsed
