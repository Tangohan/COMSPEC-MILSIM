/*
    Interroge Athena pour les alertes médicales actives (≤ 30 min) et met à jour le cache local.
    Notifie en cas de nouvelle alerte critique non encore vue.
    Premier poll de la session : peuplement silencieux (pas de replay des alertes passées).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _raw = ["COMSPECExtension" callExtension ["GetMedicalAlerts", ["1", "25"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if (_prefix != "OK") exitWith { false };

private _body = if ((count _parts) >= 2) then { _raw select [3] } else { "" };
// Si le payload contient des | (rare), reconstituer après le premier "OK|"
if ((_raw select [0, 3]) isEqualTo "OK|") then { _body = _raw select [3]; };

private _lines = _body splitString (toString [10]);
private _alerts = [];

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString (toString [9]);
    if ((count _cols) < 6) then { continue };

    private _id = _cols select 0;
    private _kind = _cols select 1;
    private _callSign = _cols select 2;
    private _label = _cols select 3;
    private _grid = _cols select 4;
    private _created = _cols select 5;
    private _triageStatus = if ((count _cols) > 6) then { _cols select 6 } else { "a_secourir" };
    private _triageLabel = if ((count _cols) > 7) then { _cols select 7 } else { "À secourir" };
    private _severity = if ((count _cols) > 8) then { _cols select 8 } else { "urgent" };

    private _hm = createHashMapFromArray [
        ["id", _id],
        ["kind", _kind],
        ["call_sign", _callSign],
        ["label", _label],
        ["grid", _grid],
        ["created_at", _created],
        ["triage_status", _triageStatus],
        ["triage_label", _triageLabel],
        ["severity", _severity]
    ];
    _alerts pushBack _hm;
} forEach _lines;

missionNamespace setVariable ["COMSPEC_MedicalAlerts", _alerts, false];

private _seen = missionNamespace getVariable ["COMSPEC_MedicalAlertsSeen", []];
private _bootstrapped = missionNamespace getVariable ["COMSPEC_MedicalAlertsBootstrapped", false];

// Premier poll réussi : marquer toutes les alertes déjà présentes comme vues, sans toast/son.
if (!_bootstrapped) then {
    {
        private _id = _x getOrDefault ["id", ""];
        if (!(_id isEqualTo "") && {!(_id in _seen)}) then { _seen pushBack _id; };
    } forEach _alerts;
    missionNamespace setVariable ["COMSPEC_MedicalAlertsBootstrapped", true, false];
} else {
    {
        private _a = _x;
        private _id = _a getOrDefault ["id", ""];
        if (_id isEqualTo "" || {_id in _seen}) then { continue };
        _seen pushBack _id;
        if ((_a getOrDefault ["severity", ""]) isEqualTo "critical") then {
            private _cs = _a getOrDefault ["call_sign", ""];
            private _lb = _a getOrDefault ["label", "Assistance médicale"];
            private _kind = toLower (_a getOrDefault ["kind", ""]);
            private _grid = _a getOrDefault ["grid", ""];
            private _msg = if (_cs isEqualTo "") then { _lb } else { format ["%1 — %2", _cs, _lb] };
            private _toast = switch (_kind) do {
                case "cardiac_arrest";
                case "cardiac-arrest";
                case "death";
                case "dead";
                case "kia": { format ["Alerte médicale — arrêt cardiaque — %1", _msg] };
                case "unconscious": { format ["Alerte médicale — inconscient — %1", _msg] };
                default { format ["Alerte médicale — %1", _msg] };
            };
            ["COMSPEC_Warning", [_toast]] call comspec_overwatch_connect_fnc_showNotification;
            [format ["[Médical] %1", _msg], "medical"] call comspec_overwatch_connect_fnc_appendLinkLog;

            // Miroir Athena / cTAB
            private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
            if (!(_inbox isEqualType [])) then { _inbox = []; };
            _inbox pushBack [
                "MEDICAL",
                "Alerte médicale",
                _msg,
                _grid,
                [daytime, "HH:MM"] call BIS_fnc_timeToString,
                _cs
            ];
            if ((count _inbox) > 40) then { _inbox deleteRange [0, (count _inbox) - 40]; };
            missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

            if (!isNil "comspec_overwatch_atak_athena_fnc_athena_pushNotification") then {
                private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
                private _detail = format [
                    "<t color='#ff9a4a'>Alerte médicale</t><br/><t color='#8aa0b4'>Blessé</t>  %1<br/><t color='#8aa0b4'>Grille</t>  %2<br/><t color='#8aa0b4'>Heure</t>  %3<br/>%4",
                    if (_cs isEqualTo "") then { "—" } else { _cs },
                    if (_grid isEqualTo "") then { "—" } else { _grid },
                    _timeStr,
                    _lb
                ];
                [
                    "alert",
                    "Médical",
                    _msg,
                    _detail,
                    _id,
                    _timeStr
                ] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;
            };
            if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
                private _grp = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
                if (!isNull _grp && {ctrlShown _grp}) then {
                    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
                };
            };

            // Relais local vers le panneau PANIC (si le signal jeu n’est pas passé).
            if (!isNil "comspec_overwatch_connect_fnc_pushIcemanMedicalAlert") then {
                private _kindNorm = toLower _kind;
                if (_kindNorm in ["death", "dead", "kia"]) then { _kindNorm = "kia"; };
                if (_kindNorm in ["unconscious", "cardiac_arrest", "kia"]) then {
                    private _sender = objNull;
                    private _csUp = toUpper _cs;
                    {
                        private _n = toUpper (name _x);
                        private _c = toUpper (trim (_x getVariable ["COMSPEC_Callsign", ""]));
                        if (_n isEqualTo _csUp || {_c isEqualTo _csUp}) exitWith { _sender = _x; };
                    } forEach allPlayers;
                    private _pos = if (!isNull _sender) then { getPos _sender } else { [] };
                    if ((count _pos) < 2 && {_grid isNotEqualTo ""} && {!isNil "BIS_fnc_gridToPos"}) then {
                        private _corners = [_grid] call BIS_fnc_gridToPos;
                        if ((_corners isEqualType []) && {(count _corners) >= 2}) then {
                            private _a = _corners select 0;
                            private _b = _corners select 1;
                            if ((_a isEqualType []) && {(_b isEqualType [])} && {(count _a) >= 2} && {(count _b) >= 2}) then {
                                _pos = [
                                    ((_a select 0) + (_b select 0)) / 2,
                                    ((_a select 1) + (_b select 1)) / 2,
                                    0
                                ];
                            };
                        };
                    };
                    if ((count _pos) >= 2) then {
                        [_kindNorm, _sender, _pos, _cs, _lb, false] call comspec_overwatch_connect_fnc_pushIcemanMedicalAlert;
                    };
                };
            };
        };
    } forEach _alerts;
};

if (count _seen > 100) then { _seen deleteRange [0, (count _seen) - 100]; };
missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", _seen, false];

true
