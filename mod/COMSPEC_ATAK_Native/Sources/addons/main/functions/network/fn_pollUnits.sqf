/* Unités suivies par Athena (BFT web). Avec COMSPEC Link, on reprend sa liste d'effectifs (même session). */
private _units = createHashMap;
private _now = diag_tickTime;
private _sig = "";
private _same = false;
private _add = {
    params ["_callsign", "_wx", "_wy", ["_role", ""], ["_age", 0], ["_status", ""]];
    _callsign = trim _callsign;
    if (_callsign isEqualTo "" || {_wx isEqualTo 0 && {_wy isEqualTo 0}}) exitWith {};
    private _id = "athena:" + toLower _callsign;
    // Téléphone tracé (Zeus ou ordre de géoloc d'Athena, COMSPEC Link fn_reportPhonePosition : rôle « Téléphone »,
    // indicatif « Tél. Nom » ou « TEL-… ») : ni allié ni infanterie. Avant, toute piste Athena devenait « ami,
    // infanterie » : le téléphone tracé d'une IA ennemie s'affichait en allié (symbole bleu, BFT « Infanterie »).
    private _phone = (_role in ["Téléphone", "téléphone", "TÉLÉPHONE", "Telephone", "telephone", "Phone", "phone"])
        || {(_callsign find "Tél. ") isEqualTo 0} || {(_callsign find "TEL-") isEqualTo 0};
    _units set [_id, createHashMapFromArray [
        ["id", _id], ["callsign", _callsign], ["position", [_wx, _wy, 0]], ["heading", 0],
        ["affiliation", ["friend", "unknown"] select _phone], ["type", ["infantry", "phone"] select _phone], ["phone", _phone],
        ["freshness", "LIVE"], ["updated", _now - _age], ["role", _role], ["status", _status]
    ]];
};
if ([] call comspec_atak_native_fnc_bridge) then {
    if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
    {
        _x params [["_cs", ""], "", "", ["_self", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_age", 0], ["_status", ""]];
        if (!_self) then { [_cs, _wx, _wy, _role, _age, _status] call _add; };
    } forEach ([] call comspec_overwatch_connect_fnc_getUnitsList);
} else {
    private _raw = ["GetUnits", []] call comspec_atak_native_fnc_extensionCall;
    if ((_raw find "OK|") isNotEqualTo 0) exitWith {};
    // Texte de la DLL identique au précédent : pas de relecture, seule l'heure de réception avance (comme une relecture).
    if ((((uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["signatures",createHashMap]) getOrDefault ["remoteUnits",""]) isEqualTo _raw) exitWith { _same = true; };
    _sig = _raw;
    private _tab = toString [9];
    {
        private _cols = _x splitString _tab;
        if ((count _cols) < 4 || {(_cols select 0) isNotEqualTo "U"}) then { continue };
        [_cols select 1, parseNumber (_cols param [5, _cols select 2]), parseNumber (_cols param [6, _cols select 3]), _cols param [4, ""], parseNumber (_cols param [7, "0"]), _cols param [8, ""]] call _add;
    } forEach ((_raw select [3]) splitString (toString [10]));
};
if (_same) exitWith {
    private _dt = _now - (uiNamespace getVariable ["COMSPEC_ATAK_UnitsPollAt", _now]);
    uiNamespace setVariable ["COMSPEC_ATAK_UnitsPollAt", _now];
    { _y set ["updated", (_y getOrDefault ["updated", _now]) + _dt]; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["remoteUnits",createHashMap]);
    true
};
uiNamespace setVariable ["COMSPEC_ATAK_UnitsPollAt", _now];
["remoteUnits", _units, -1, _sig] call comspec_atak_native_fnc_storeSet
