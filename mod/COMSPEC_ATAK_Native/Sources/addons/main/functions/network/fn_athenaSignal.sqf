/*
    Signaux du poste reçus par Overwatch connect (fn_receiveOrder), affichés par l'ATAK natif à la place
    du module atak_athena (absent avec le mod natif) :
      "notify", ordre NOTIFY      → SMS dans la Messagerie (expéditeur = l'émetteur du poste) et notification
      "notify", ordre NOTIFY_FULL → alerte plein écran, plus le SMS
      "vibrate", ordre VIBRATE    → vibration et notification
      "health", [type, unité, position, indicatif, libellé] → alerte santé (inconscient, arrêt cardiaque, KIA) :
                                     plein écran, journal de l'app Alertes, position sur la carte
*/
params [["_kind", ""], ["_arg", createHashMap]];
if (!hasInterface) exitWith {};
private _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
switch (_kind) do {
    case "notify": {
        private _type = toUpper (_arg getOrDefault ["type", "NOTIFY"]);
        private _from = trim (_arg getOrDefault ["issuer", "Poste"]);
        if (_from isEqualTo "") then { _from = "Poste"; };
        private _body = trim (_arg getOrDefault ["payload", ""]);
        if !(_body isEqualType "") then { _body = str _body; };
        if (_body isEqualTo "") then { _body = ["Notification du poste", "Alerte du poste"] select (_type isEqualTo "NOTIFY_FULL"); };
        [format ["TOC · %1", _from], _body, _time] call comspec_atak_native_fnc_p2pReceive;
        if (_type isEqualTo "NOTIFY_FULL") then {
            ["ALERTE DU POSTE", _body, _from, [0.55, 0.08, 0.06, 0.92], 12] call comspec_atak_native_fnc_fullAlert;
        };
    };
    case "vibrate": {
        [] call comspec_atak_native_fnc_vibrate;
        ["WARNING", format ["Votre terminal vibre : appel de %1", _arg getOrDefault ["issuer", "Poste"]], 5, 60] call comspec_atak_native_fnc_notify;
    };
    case "health": {
        _arg params [["_k", ""], ["_who", objNull], ["_pos", []], ["_cs", ""], ["_label", ""]];
        if (_who isEqualTo player) exitWith {};
        if (!isNull _who && {(side group _who) isNotEqualTo (side group player)}) exitWith {};
        private _name = [_cs, if (isNull _who) then { "Inconnu" } else { name _who }] select (_cs isEqualTo "");
        private _what = createHashMapFromArray [["unconscious", "INCONSCIENT"], ["cardiac_arrest", "ARRÊT CARDIAQUE"], ["kia", "HORS COMBAT"]] getOrDefault [toLower _k, toUpper _k];
        if ((count _pos) < 2 && {!isNull _who}) then { _pos = getPosASL _who; };
        private _grid = if ((count _pos) >= 2) then { [_pos, 8] call comspec_atak_native_fnc_gridRef } else { "-" };
        private _dist = if ((count _pos) >= 2) then { format [" · %1 m au %2°", round (player distance2D _pos), round (player getDir _pos)] } else { "" };
        ["MEDICAL", _name, _pos, _grid, format ["%1%2", _what, ["", " · " + _label] select (_label isNotEqualTo "")]] call comspec_atak_native_fnc_alertsLog;
        [format ["SANTÉ : %1", _what], format ["%1 · %2%3", _name, _grid, _dist], "Alerte santé", [0.62, 0.05, 0.12, 0.9], 10] call comspec_atak_native_fnc_fullAlert;
    };
};
true
