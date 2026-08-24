/*
    Change le mode de déclenchement d’une charge déjà armée.
    Params: [_explosive, _kind, _unit]
      _kind : "atak" | "clacker"
*/
params [
    ["_explosive", objNull, [objNull]],
    ["_kind", "atak", [""]],
    ["_unit", objNull, [objNull]]
];
if (isNull _explosive) exitWith { false };
if (isNull _unit) then { _unit = player; };
if (_explosive getVariable ["COMSPEC_detonateFired", false]) exitWith { false };

private _kindKey = toLower _kind;
if (!(_kindKey in ["atak", "clacker"])) exitWith { false };

private _current = toLower (_explosive getVariable ["COMSPEC_triggerKind", ""]);
if (_current isEqualTo "timer") exitWith {
    ["Une minuterie déjà lancée ne peut pas changer de mode.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
if (_current isEqualTo _kindKey) exitWith {
    if (_kindKey isEqualTo "atak") then {
        [_explosive, _unit] call comspec_overwatch_connect_fnc_chargeUnhookClacker;
    };
    true
};

_explosive setVariable ["COMSPEC_triggerKind", _kindKey, true];

if (_kindKey isEqualTo "atak") then {
    [_explosive, _unit] call comspec_overwatch_connect_fnc_chargeUnhookClacker;
} else {
    if (!isNil "ace_explosives_fnc_addClacker") then {
        private _mag = _explosive getVariable ["ace_explosives_class", ""];
        if (_mag isEqualTo "") then {
            _mag = _explosive getVariable ["ace_explosives_magazineClass", ""];
        };
        if (_mag isNotEqualTo "") then {
            private _cfg = configFile >> "ACE_Triggers" >> "Command";
            if (!isNull _cfg) then {
                [_unit, _explosive, _mag, [_cfg]] call ace_explosives_fnc_addClacker;
            };
        };
    };
};

[_explosive, 0, _unit, "armed", "", _kindKey] call comspec_overwatch_connect_fnc_reportExplosiveTimer;

if (_kindKey isEqualTo "atak") then {
    ["Charge raccordée à ATAK uniquement : tablette en jeu et poste de commandement. Le déclencheur local ne la fera plus sauter.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
} else {
    ["Charge rendue au déclencheur local.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};

true
