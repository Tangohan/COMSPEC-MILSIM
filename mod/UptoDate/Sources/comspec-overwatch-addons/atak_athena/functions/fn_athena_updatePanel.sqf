/*
    Tuile Athena : bouton Connexion / Liaison OK + journal ou fiche.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _pageNow = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_pageNow isNotEqualTo "" && {_pageNow isNotEqualTo "athena"}) exitWith {};

[] call comspec_overwatch_atak_athena_fnc_athena_applyHomeLayout;

private _statusCtrl = [_group, 9701] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { _linked } else { _steamRaw isEqualTo true };

private _fncClean = {
    params ["_v"];
    private _s = trim (str _v);
    if (_s isEqualTo "" || {(toLower _s) in ["<null>", "any", "nil"]}) then { "" } else { _s };
};

private _fullName = [missionNamespace getVariable ["comspec_profile_name", ""]] call _fncClean;
private _first = "";
private _last = "";
if (_fullName isNotEqualTo "") then {
    private _bits = _fullName splitString " ";
    if ((count _bits) >= 2) then {
        _first = _bits select 0;
        _last = (_bits select [1, (count _bits) - 1]) joinString " ";
    } else {
        _last = _fullName;
    };
};
if (_first isEqualTo "" || {_last isEqualTo ""}) then {
    if (!isNil "comspec_overwatch_connect_fnc_collectOperatorIdentity") then {
        private _ident = [player] call comspec_overwatch_connect_fnc_collectOperatorIdentity;
        if (_ident isEqualType createHashMap) then {
            if (_first isEqualTo "") then { _first = [_ident getOrDefault ["first_name_detected", ""]] call _fncClean; };
            if (_last isEqualTo "") then { _last = [_ident getOrDefault ["last_name_detected", ""]] call _fncClean; };
        };
    };
};
private _role = [missionNamespace getVariable ["comspec_profile_role", ""]] call _fncClean;
private _fn = [missionNamespace getVariable ["comspec_profile_function", ""]] call _fncClean;
private _unit = [missionNamespace getVariable ["comspec_profile_unit", ""]] call _fncClean;
if (_role isEqualTo "") then { _role = "—"; };
if (_fn isEqualTo "") then { _fn = "—"; };
if (_unit isEqualTo "") then { _unit = "—"; };
if (_first isEqualTo "") then { _first = "—"; };
if (_last isEqualTo "") then { _last = "—"; };

private _allOk = _linked && {_steamOk};
private _statusTxt = if (_allOk) then {
    format [
        "<t color='#8aa0b4' size='0.92'>NOM</t>  <t color='#e8f4f0'>%1</t><br/>" +
        "<t color='#8aa0b4' size='0.92'>PRÉNOM</t>  <t color='#e8f4f0'>%2</t><br/>" +
        "<t color='#8aa0b4' size='0.92'>RÔLE</t>  <t color='#e8f4f0'>%3</t><br/>" +
        "<t color='#8aa0b4' size='0.92'>FONCTION</t>  <t color='#e8f4f0'>%4</t><br/>" +
        "<t color='#8aa0b4' size='0.92'>AFFECTATION</t>  <t color='#e8f4f0'>%5</t>",
        _last, _first, _role, _fn, _unit
    ]
} else {
    private _lines = [];
    if (!_steamOk) then { _lines pushBack "<t color='#FF8A80'>Steam NON LINK</t>"; };
    if (!_linked) then { _lines pushBack "<t color='#FFD27A'>Compte non connecté</t>"; };
    if (_lines isEqualTo []) then { _lines pushBack "<t color='#FFD27A'>Compte non connecté</t>"; };
    _lines joinString "<br/>"
};

if (!isNull _statusCtrl) then {
    _statusCtrl ctrlSetBackgroundColor (if (_allOk) then { [0.08, 0.08, 0.08, 0.94] } else { [0.12, 0.08, 0.04, 0.94] });
    _statusCtrl ctrlSetStructuredText parseText _statusTxt;
};
