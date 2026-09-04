/*
    Construit le HTML inspecteur (détail médical + radio + origine marqueur).
*/
params [["_unit", objNull], ["_marker", "", [""]]];
if (isNull _unit && {_marker isEqualTo ""}) exitWith {
    missionNamespace setVariable ["COMSPEC_MapInspectorHtml", "", false];
};

if (_marker isNotEqualTo "") then {
    private _low = toLower _marker;
    private _origin = "ARMA";
    if ((_low find "comspec") >= 0 || {(_low find "athena") >= 0}) then { _origin = "ATHENA"; };
    if ((_low find "overwatch") >= 0 || {(_low find "comspec_tablet") >= 0}) then { _origin = "OVERWATCH"; };
    private _grid = [markerPos _marker] call comspec_overwatch_atak_athena_fnc_formatGrid;
    private _html = format [
        "<t font='RobotoCondensedBold' size='0.64' color='#5EC7F2'>%1</t><br/>" +
        "<t size='0.52' color='#8aa0b4'>Origine</t>  <t size='0.52' color='#E8F0F4'>%2</t><br/>" +
        "<t size='0.52' color='#8aa0b4'>Grille</t>  <t size='0.52' color='#E8F0F4'>%3</t>",
        markerText _marker,
        _origin,
        _grid
    ];
    missionNamespace setVariable ["COMSPEC_MapInspectorHtml", _html, false];
};

if (isNull _unit) exitWith {};

private _cs = [_unit] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel;
if (_cs isEqualTo "") then { _cs = name _unit; };
private _role = "";
if (!isNil "comspec_overwatch_connect_fnc_getUnitRole") then {
    _role = [_unit] call comspec_overwatch_connect_fnc_getUnitRole;
};
private _med = [_unit] call comspec_overwatch_atak_athena_fnc_formatUnitStatus;
private _medDetail = "";
if (!isNil "ace_medical_status_fnc_getBloodVolume") then {
    private _bv = [_unit] call ace_medical_status_fnc_getBloodVolume;
    if (_bv isEqualType 0) then {
        _medDetail = format ["sang %1 l", ((round (_bv * 10)) / 10)];
    };
};
private _pain = _unit getVariable ["ace_medical_pain", 0];
if (_pain isEqualType 0 && {_pain > 0.15}) then {
    _medDetail = trim (_medDetail + format ["  douleur %1", round (_pain * 100)]);
};
private _radio = ["none", "", "", false, false, "", false];
if (!isNil "comspec_overwatch_connect_fnc_getRadioTxState") then {
    _radio = [_unit] call comspec_overwatch_connect_fnc_getRadioTxState;
};
_radio params ["_net", "_freq", "_ch", "_spk", "_tx", ["_rid", ""]];
private _band = "";
private _ridU = toUpper (str _rid);
if ((_ridU find "343") >= 0 || {(_ridU find "SEM52") >= 0}) then { _band = "SR"; };
if ((_ridU find "152") >= 0 || {(_ridU find "117") >= 0} || {(_ridU find "PRC77") >= 0}) then { _band = "LR"; };
if (_band isEqualTo "" && {_net isEqualTo "ACRE"}) then { _band = "SR"; };
private _grid = [getPosASLVisual _unit] call comspec_overwatch_atak_athena_fnc_formatGrid;
private _age = time - (_unit getVariable ["COMSPEC_PliAt", time]);
private _txTxt = if (_tx) then { "EN ÉMISSION" } else { if (_spk) then { "PARLE" } else { "—" }; };
private _laser = missionNamespace getVariable ["COMSPEC_MapLaser", ""];
private _laserAge = time - (missionNamespace getVariable ["COMSPEC_LaserSeenAt", time]);
private _laserTxt = if (_laser isEqualTo "") then { "—" } else {
    format ["%1  %2 s", _laser, round _laserAge]
};
private _htmlU = format [
    "<t font='RobotoCondensedBold' size='0.64' color='#5EC7F2'>%1</t><br/>" +
    "<t size='0.52' color='#8aa0b4'>Rôle</t>  <t size='0.52' color='#E8F0F4'>%2</t><br/>" +
    "<t size='0.52' color='#8aa0b4'>État</t>  <t size='0.52' color='#E8F0F4'>%3 %4</t><br/>" +
    "<t size='0.52' color='#8aa0b4'>Grille</t>  <t size='0.52' color='#E8F0F4'>%5</t><br/>" +
    "<t size='0.52' color='#8aa0b4'>Radio</t>  <t size='0.52' color='#E8F0F4'>%6 %7 %8 ch.%9  %10</t><br/>" +
    "<t size='0.52' color='#8aa0b4'>Laser</t>  <t size='0.52' color='#E8F0F4'>%11</t><br/>" +
    "<t size='0.48' color='#8aa0b4'>Dernière position  %12 s</t>",
    _cs, _role, _med, _medDetail, _grid, _net, _band, _freq, _ch, _txTxt, _laserTxt, round _age
];
missionNamespace setVariable ["COMSPEC_MapInspectorHtml", _htmlU, false];
