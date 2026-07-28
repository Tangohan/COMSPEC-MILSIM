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
switch (_phaseKey) do {
    case "ok";
    case "success": {
        _level = "INFO";
        _prefix = "[Tx OK]";
    };
    case "fail";
    case "failed";
    case "error": {
        _level = "ERROR";
        _prefix = "[Tx ÉCHEC]";
    };
    case "warn";
    case "warning": {
        _level = "WARN";
        _prefix = "[Tx]";
    };
    default {
        _level = "INFO";
        _prefix = "[Tx →]";
        // Anti-spam tentatives répétitives (position / marqueurs) : DEBUG si canal TxAttempt soft
        if ((toLower _cmd) in ["updateposition", "sendmarker", "updatevehicletracking"]) then {
            _level = "DEBUG";
        };
    };
};

[_level, "Tx", _label, if (isNil "_raw") then { nil } else { _raw }] call comspec_overwatch_connect_fnc_log;

private _line = format ["%1 %2", _prefix, _label];
[_line, _linkCat] call comspec_overwatch_connect_fnc_appendLinkLog;

if (_moduleLog) then {
    [_line] call comspec_overwatch_connect_fnc_appendModuleLog;
};
