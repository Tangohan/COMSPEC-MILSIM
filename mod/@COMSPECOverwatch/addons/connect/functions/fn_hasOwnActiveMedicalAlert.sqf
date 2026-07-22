/*
    Retourne la plus récente alerte médicale active (non résolue) du JOUEUR LOCAL dans le
    cache COMSPEC_MedicalAlerts (alimenté par fn_pollMedicalAlerts), ou un hashmap vide si
    aucune. Comparaison par indicatif (insensible à la casse) : sert à la fois de condition
    d'affichage pour l'action « Je vais bien » et de résolution d'id pour
    fn_selfCancelMedicalAlert.
*/
if (!hasInterface) exitWith { createHashMap };

private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callSign isEqualTo "") then { _callSign = name player; };
private _callSignUp = toUpper _callSign;

private _alerts = missionNamespace getVariable ["COMSPEC_MedicalAlerts", []];
private _best = createHashMap;
private _bestCreated = "";
{
    private _a = _x;
    if (!(_a isEqualType createHashMap)) then { continue };
    private _cs = toUpper (_a getOrDefault ["call_sign", ""]);
    if (_cs isEqualTo "" || {_cs != _callSignUp}) then { continue };
    private _status = _a getOrDefault ["triage_status", "a_secourir"];
    if (_status in ["traite", "kia", "annule"]) then { continue };
    private _created = _a getOrDefault ["created_at", ""];
    if (_created > _bestCreated) then {
        _best = _a;
        _bestCreated = _created;
    };
} forEach _alerts;

_best
