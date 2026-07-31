/*
    Module Zeus/Eden : profil d'identité SSE.

    Pose sur un ou plusieurs sujets ce que le terminal SEEK trouvera en base :
    état civil de couverture, verdict de requête, référence de dossier.

    L'entrée est volontairement tolérante — BI passe [logic, units, activated],
    parfois un libellé d'événement en tête côté Eden. Le module ne doit pas mourir
    sur une forme d'appel : c'est ce qui produisait des « Type Chaîne, Objet attendu ».
*/
private _logic = objNull;
private _units = [];
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        _units = _this param [1, []];
        _activated = _this param [2, true];
    } else {
        if (_a0 isEqualType "" && { (_this param [1, objNull]) isEqualType objNull }) then {
            _logic = _this param [1, objNull];
            _units = _this param [2, []];
            _activated = _this param [3, true];
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_units isEqualType [])) then { _units = []; };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

// Un seul écrivain : les variables sont diffusées, il ne faut pas que chaque
// client réapplique le profil de son côté.
if (!isServer && { isMultiplayer }) exitWith {
    deleteVehicle _logic;
    true
};

private _targets = [_logic, _units, 15] call comspec_overwatch_connect_fnc_sseModuleTargets;
if (_targets isEqualTo []) exitWith {
    ["WARN", "SSE", "Module profil SSE : aucun sujet sous le module"] call comspec_overwatch_connect_fnc_log;
    deleteVehicle _logic;
    false
};

private _preset = _logic getVariable ["Preset", "auto"];
if (!(_preset isEqualType "")) then { _preset = "auto"; };

private _profile = [_preset] call comspec_overwatch_connect_fnc_sseProfilePreset;

// Les champs saisis à la main complètent le preset et le remplacent en cas de conflit.
{
    _x params ["_varName", "_key"];
    private _v = _logic getVariable [_varName, ""];
    if ((_v isEqualType "") && { (trim _v) isNotEqualTo "" }) then {
        _profile pushBack [_key, trim _v];
    };
} forEach [
    ["LastName", "last_name"],
    ["FirstName", "first_name"],
    ["Alias", "alias"],
    ["Nationality", "nationality"],
    ["Language", "language"],
    ["RecordRef", "record_ref"]
];

private _seed = _logic getVariable ["Seed", 0];
if (_seed isEqualType 0 && { _seed > 0 }) then { _profile pushBack ["seed", _seed]; };

{
    [_x, _profile] call comspec_overwatch_connect_fnc_sseApplyProfile;
} forEach _targets;

[
    "INFO",
    "SSE",
    format ["Profil SSE « %1 » appliqué à %2 sujet(s)", _preset, count _targets]
] call comspec_overwatch_connect_fnc_log;

deleteVehicle _logic;
true
