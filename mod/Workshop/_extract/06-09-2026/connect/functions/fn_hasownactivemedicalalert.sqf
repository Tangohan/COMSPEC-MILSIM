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
// Score de fraîcheur TOUJOURS numérique (évite Error > string/number sur created_at mixte)
private _bestScore = -1;
{
    private _a = _x;
    if (!(_a isEqualType createHashMap)) then { continue };
    private _cs = toUpper (_a getOrDefault ["call_sign", ""]);
    if (_cs isEqualTo "" || {_cs != _callSignUp}) then { continue };
    private _status = _a getOrDefault ["triage_status", "a_secourir"];
    if (_status in ["traite", "kia", "annule"]) then { continue };

    // created_at : ISO (chaîne) ou epoch (nombre) → un seul type comparable (SCALAR)
    private _created = _a getOrDefault ["created_at", ""];
    private _score = -1;
    if (_created isEqualType 0) then {
        _score = _created;
    } else {
        if (_created isEqualType "") then {
            if (_created isEqualTo "") then { continue };
            // Epoch textuel ("1719000000") ou ISO ("2024-07-22 14:30:00") :
            // ne garder que les chiffres → SCALAR triable sans opérateur string/number
            private _digits = "";
            {
                if (_x >= 48 && {_x <= 57}) then {
                    _digits = _digits + (toString [_x]);
                };
            } forEach (toArray _created);
            if (_digits isEqualTo "") then { continue };
            _score = parseNumber _digits;
        } else {
            continue;
        };
    };
    if (!(_score isEqualType 0) || {_score < 0}) then { continue };
    if (_score > _bestScore) then {
        _best = _a;
        _bestScore = _score;
    };
} forEach _alerts;

_best
