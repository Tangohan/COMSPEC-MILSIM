/*
    Branche les listes BFT cTab / IceMan pour afficher l’indicatif Athena.
*/
if (!hasInterface) exitWith {};
if (isNil "cTab_fnc_updateLists") exitWith {};

if (isNil "COMSPEC_Wrapped_BftUpdateLists") then {
    COMSPEC_Wrapped_BftUpdateLists = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_updateLists", cTab_fnc_updateLists];
    cTab_fnc_updateLists = {
        private _r = call (missionNamespace getVariable ["COMSPEC_Prev_cTab_updateLists", { true }]);
        [] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
        _r
    };
};

if (isNil "COMSPEC_Wrapped_BftAwareDraw" && {!isNil "Iceman_fnc_aware_drawbftmarkers"}) then {
    COMSPEC_Wrapped_BftAwareDraw = true;
    missionNamespace setVariable ["COMSPEC_Prev_Iceman_aware_drawbftmarkers", Iceman_fnc_aware_drawbftmarkers];
    Iceman_fnc_aware_drawbftmarkers = {
        params ["_ctrlScreen", ["_mode", 0]];
        private _awareMode = "default";
        if (!isNil "Iceman_fnc_aware_getMode") then {
            _awareMode = call Iceman_fnc_aware_getMode;
        };
        if (_awareMode isEqualTo "default" || {_mode != 0} || {isNil "cTab_player"} || {isNull cTab_player}) exitWith {
            false
        };

        private _validSides = call cTab_fnc_getPlayerSides;
        private _blue = missionNamespace getVariable ["cTabColorBlue", [0, 0.35, 1, 0.95]];
        private _manSize = missionNamespace getVariable ["cTabIconManSize", 18];
        private _textSize = missionNamespace getVariable ["cTabTxtSize", 0.04];
        private _personnelDevices = missionNamespace getVariable ["ctab_core_personnelDevices", []];

        {
            private _unit = _x;
            if !(
                alive _unit
                && {side group _unit in _validSides}
                && {_unit isEqualTo cTab_player || {isPlayer _unit}}
                && {_unit isEqualTo cTab_player || {[_unit, _personnelDevices] call cTab_fnc_checkGear}}
            ) then { continue };

            private _veh = vehicle _unit;
            private _pos = getPosASL _veh;
            private _dir = if (_veh isEqualTo _unit) then { getDirVisual _unit } else { direction _veh };
            private _label = [_unit] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel;
            if (_label isEqualTo "") then { _label = name _unit; };
            if (_label isEqualTo "") then {
                _label = format ["%1:%2", groupId (group _unit), [_unit] call CBA_fnc_getGroupIndex];
            };

            _ctrlScreen drawIcon [
                "\A3\ui_f\data\map\VehicleIcons\iconmanvirtual_ca.paa",
                _blue, _pos, _manSize, _manSize, _dir, "", 0, _textSize, "TahomaB", "center"
            ];
            _ctrlScreen drawIcon [
                "\A3\ui_f\data\map\Markers\System\dummy_ca.paa",
                _blue, _pos, _manSize, _manSize, 0, _label, 0, _textSize, "TahomaB", "right"
            ];
        } forEach allUnits;

        true
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
