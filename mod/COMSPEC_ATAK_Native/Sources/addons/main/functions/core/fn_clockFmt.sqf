/*
    Heure affichée sur les alertes (app Alertes, notifications, alertes plein écran, MEDEVAC, alertes santé Athena), selon le
    réglage CBA par joueur « Heure des alertes » (comspec_atak_native_alert_clock) :
      "game"  heure de la mission (par défaut, comme avant)       "local" heure du PC du joueur
      "zulu"  heure UTC (« 21:30Z »)                               "both"  heure du PC et UTC (« 22:30 · 21:30Z »)
    Params : [source] : "now" (maintenant, par défaut) ou une date UTC d'Athena (« 2026-10-26 21:30:00 », ISO accepté :
    created_at des alertes médicales, gmdate côté site ; ou horodatage Unix). Une date d'Athena n'a pas d'heure de mission : en « game »,
    elle s'affiche à l'heure du PC (elle sortait brute, en UTC). Texte illisible : rendu tel quel.
*/
params [["_src", "now"]];
private _mode = missionNamespace getVariable ["comspec_atak_native_alert_clock", "game"];
private _two = { [str _this, "0" + str _this] select (_this < 10) };
private _hm = { params ["_m"]; _m = ((round _m) % 1440 + 1440) % 1440; format ["%1:%2", (floor (_m / 60)) call _two, (_m % 60) call _two] };
// Décalage du PC sur UTC, en minutes (passage de minuit ramené entre -12 h et +14 h).
private _off = {
    systemTime params ["", "", "", "_h", "_mi"];
    systemTimeUTC params ["", "", "", "_hu", "_miu"];
    private _d = (_h * 60 + _mi) - (_hu * 60 + _miu);
    if (_d > 840) then { _d = _d - 1440; };
    if (_d < -720) then { _d = _d + 1440; };
    _d
};
private _utc = -1;
if (_src isEqualType "" && {_src isNotEqualTo "now"}) then {
    // « AAAA-MM-JJ HH:MM:SS » ou « AAAA-MM-JJTHH:MM:SSZ » : heure et minutes après le séparateur de la date.
    private _t = (_src splitString " T") param [1, ""];
    private _p = _t splitString ":";
    if ((count _p) >= 2) then { _utc = (parseNumber (_p select 0)) * 60 + parseNumber (_p select 1); };
} else {
    // Horodatage Unix (secondes, UTC) : certaines alertes d'Athena le donnent en nombre.
    if (_src isEqualType 0) then { if (_src > 0) then { _utc = floor ((_src % 86400) / 60); }; } else {
        systemTimeUTC params ["", "", "", "_hu", "_miu"];
        _utc = _hu * 60 + _miu;
    };
};
if (_utc < 0) exitWith { [str _src, _src] select (_src isEqualType "") };
private _isNow = _src isEqualTo "now";
switch (_mode) do {
    case "local": { [_utc + call _off] call _hm };
    case "zulu": { ([_utc] call _hm) + "Z" };
    case "both": { format ["%1 · %2Z", [_utc + call _off] call _hm, [_utc] call _hm] };
    default { [[_utc + call _off] call _hm, [dayTime, "HH:MM"] call BIS_fnc_timeToString] select _isNow };
}
