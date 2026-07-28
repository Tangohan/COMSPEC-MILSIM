/*
    Préremplit le dialog SSE (identité Eden / inventaire cible).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

private _status = _disp displayCtrl 9505;
lbClear _status;
{
    _x params ["_code", "_label"];
    private _i = _status lbAdd _label;
    _status lbSetData [_i, _code];
} forEach [
    ["civil", "Civil"],
    ["combattant", "Combattant"],
    ["detenu", "Détenu"],
    ["prioritaire", "Personne prioritaire"]
];
_status lbSetCurSel 0;

private _circ = _disp displayCtrl 9506;
lbClear _circ;
{
    _x params ["_code", "_label"];
    private _i = _circ lbAdd _label;
    _circ lbSetData [_i, _code];
} forEach [
    ["controle", "Contrôle"],
    ["perquisition", "Perquisition"],
    ["reddition", "Reddition"],
    ["autre", "Autre"]
];
_circ lbSetCurSel 0;

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _weaponsLines = [];
private _equipmentLines = [];
private _statusGuess = "civil";

if (!isNull _target && { alive _target }) then {
    private _edenLast = _target getVariable ["COMSPEC_SSE_LastName", ""];
    private _edenFirst = _target getVariable ["COMSPEC_SSE_FirstName", ""];
    private _edenAlias = _target getVariable ["COMSPEC_SSE_Alias", ""];
    if (_edenLast isEqualTo "" && _edenFirst isEqualTo "" && _edenAlias isEqualTo "") then {
        private _nm = name _target;
        if (_nm isNotEqualTo "" && { _nm isNotEqualTo "Error: No unit" }) then {
            _edenAlias = _nm;
        };
    };
    (_disp displayCtrl 9501) ctrlSetText _edenLast;
    (_disp displayCtrl 9502) ctrlSetText _edenFirst;
    (_disp displayCtrl 9503) ctrlSetText _edenAlias;

    private _nat = _target getVariable ["COMSPEC_SSE_Nationality", ""];
    private _lang = _target getVariable ["COMSPEC_SSE_Language", ""];
    (_disp displayCtrl 9507) ctrlSetText _nat;
    (_disp displayCtrl 9508) ctrlSetText _lang;

    private _primary = primaryWeapon _target;
    private _handgun = handgunWeapon _target;
    private _secondary = secondaryWeapon _target;
    if (_primary isNotEqualTo "") then {
        _weaponsLines pushBackUnique (getText (configFile >> "CfgWeapons" >> _primary >> "displayName"));
        _statusGuess = "combattant";
    };
    if (_handgun isNotEqualTo "") then {
        _weaponsLines pushBackUnique (getText (configFile >> "CfgWeapons" >> _handgun >> "displayName"));
        _statusGuess = "combattant";
    };
    if (_secondary isNotEqualTo "") then {
        _weaponsLines pushBackUnique (getText (configFile >> "CfgWeapons" >> _secondary >> "displayName"));
        _statusGuess = "combattant";
    };

    {
        private _cls = _x;
        private _dn = getText (configFile >> "CfgWeapons" >> _cls >> "displayName");
        if (_dn isEqualTo "") then { _dn = getText (configFile >> "CfgMagazines" >> _cls >> "displayName"); };
        if (_dn isEqualTo "") then { _dn = _cls; };
        if (_dn isNotEqualTo "") then { _equipmentLines pushBackUnique _dn; };
    } forEach (items _target);

    // ACE restrain → détenu
    if (!isNil "ace_captives_fnc_isHandcuffed") then {
        if ([_target] call ace_captives_fnc_isHandcuffed) then {
            _statusGuess = "detenu";
        };
    } else {
        if (_target getVariable ["ace_captives_isHandcuffed", false]) then {
            _statusGuess = "detenu";
        };
    };

    (_disp displayCtrl 9500) ctrlSetStructuredText parseText format [
        "<t align='center' size='0.55' color='#8aa0b4'>Cible : %1 — inventaire et statut préremplis si disponibles.</t>",
        name _target
    ];
};

for "_i" from 0 to (lbSize _status) - 1 do {
    if ((_status lbData _i) isEqualTo _statusGuess) exitWith {
        _status lbSetCurSel _i;
    };
};

private _wTxt = if ((count _weaponsLines) > 0) then {
    format ["<t size='0.55' color='#e8f4f0'>Armes : %1</t>", _weaponsLines joinString ", "]
} else {
    "<t size='0.55' color='#8aa0b4'>Aucune arme détectée.</t>"
};
private _eTxt = if ((count _equipmentLines) > 0) then {
    private _sample = _equipmentLines select [0, (count _equipmentLines) min 8];
    format ["<br/><t size='0.52' color='#8aa0b4'>Équipement : %1</t>", _sample joinString ", "]
} else {
    ""
};
(_disp displayCtrl 9511) ctrlSetStructuredText parseText (_wTxt + _eTxt);

uiNamespace setVariable ["COMSPEC_SsePerson_WeaponsCache", _weaponsLines];
uiNamespace setVariable ["COMSPEC_SsePerson_EquipmentCache", _equipmentLines];
