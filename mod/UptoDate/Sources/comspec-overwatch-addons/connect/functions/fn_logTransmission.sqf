/*
    Journal centralisé des transmissions Athena (tentative / succès / échec).

    Params:
        0: STRING — commande extension ou libellé métier (ex. "UploadReconImage", "PING")
        1: STRING — phase "attempt" | "ok" | "fail" | "error" (défaut "attempt")
        2: STRING — détail lisible (optionnel)
        3: ANY    — détail technique brut (optionnel, sérialisé)
        4: BOOL   — aussi journal modules Athena (défaut false ; true pour photo/SSE/manifest…)
        5: STRING — catégorie liaison "system"|"liaison"|"cas"|"medical"|"orders" (défaut "system")

    Écrit :
      - journal technique (fnc_log) — ERROR/WARN/INFO selon la phase
      - journal liaison (appendLinkLog) — sauf mute catégorie
      - journal modules (appendModuleLog) si demandé
*/
params [
    ["_cmd", "", [""]],
    ["_phase", "attempt", [""]],
    ["_detail", "", [""]],
    ["_raw", nil],
    ["_moduleLog", false, [true]],
    ["_linkCat", "system", [""]]
];

if (_cmd isEqualTo "") exitWith {};

private _phaseKey = toLower _phase;
private _label = if (_detail isEqualTo "") then { _cmd } else { format ["%1 — %2", _cmd, _detail] };

private _level = "INFO";
private _prefix = "[Tx]";
private _techLabel = _label;
switch (_phaseKey) do {
    case "ok";
    case "success": {
        _level = "INFO";
        _prefix = "[Tx OK]";
        _techLabel = format ["OK · %1", _label];
    };
    case "fail";
    case "failed";
    case "error": {
        _level = "ERROR";
        _prefix = "[Tx ÉCHEC]";
        _techLabel = format ["ÉCHEC · %1", _label];
    };
    case "warn";
    case "warning": {
        _level = "WARN";
        _prefix = "[Tx]";
        _techLabel = format ["WARN · %1", _label];
    };
    default {
        _level = "INFO";
        _prefix = "[Tx →]";
        _techLabel = format ["→ %1", _label];
        // Anti-spam tentatives répétitives (position / marqueurs) : DEBUG si canal TxAttempt soft
        if ((toLower _cmd) in ["updateposition", "sendmarker", "updatevehicletracking"]) then {
            _level = "DEBUG";
        };
        // Tentative photo : DEBUG — l’OK/ÉCHEC reste en INFO (évite le doublement identique).
        if ((toLower _cmd) in ["notifynewphoto", "uploadreconimage", "enqueuereconimage", "uploadimage", "uploadssephoto"]) then {
            _level = "DEBUG";
        };
    };
};

[_level, "Tx", _techLabel, if (isNil "_raw") then { nil } else { _raw }] call comspec_overwatch_connect_fnc_log;

private _line = format ["%1 %2", _prefix, _label];
[_line, _linkCat] call comspec_overwatch_connect_fnc_appendLinkLog;

if (_moduleLog) then {
    [_line] call comspec_overwatch_connect_fnc_appendModuleLog;
};
