/*
    Actions du socle de recharge CS-10 (appelé une fois par XEH_postInitClient).
    ACE : sur le socle, « Socle de recharge » > Poser mon téléphone (emplacement libre), Reprendre (un par téléphone
    à soi, ou à tous pour Zeus, l'admin et le réglage « Tout le monde peut reprendre »), État du socle, Replier ;
    sur soi, Équipement > « Poser le socle de recharge » avec le kit.
    Sans ACE : actions molette en regardant le socle à moins de 3 m.
*/
if (!hasInterface) exitWith {};
["comspec_atak_native_dockResult", { ["result", _this] call comspec_atak_native_fnc_dockClient; }] call CBA_fnc_addEventHandler;
private _icon = "\z\comspec_atak_native\addons\main\data\item_dock.paa";
private _staff = { serverCommandAvailable "#kick" || {!isNull (getAssignedCuratorLogic player)} || {missionNamespace getVariable ["comspec_atak_native_dock_anyone", false]} };
missionNamespace setVariable ["COMSPEC_ATAK_DockStaff", _staff];
if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _root = ["COMSPEC_ATAK_Dock", "Socle de recharge", _icon, { ["status", [_target]] call comspec_atak_native_fnc_dockClient; }, { true }, {
        private _out = [];
        private _uid = getPlayerUID _player;
        private _slots = _target getVariable ["COMSPEC_ATAK_DockSlots", []];
        private _free = _slots findIf { (count _x) == 0 };
        if (_free >= 0 && {(["phone"] call comspec_atak_native_fnc_dockClient) isNotEqualTo ""}) then {
            _out pushBack [["COMSPEC_ATAK_DockPut", format ["Poser mon téléphone (emplacement %1)", _free + 1], "\z\comspec_atak_native\addons\main\data\item_dock.paa",
                { ["put", [_target, -1]] call comspec_atak_native_fnc_dockClient; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
        };
        private _all = call (missionNamespace getVariable "COMSPEC_ATAK_DockStaff");
        {
            if ((count _x) > 3 && {_all || {(_x select 0) isEqualTo _uid}}) then {
                _x params ["_owner", "_name", "_lv"];
                private _txt = if (_owner isEqualTo _uid) then { format ["Reprendre mon téléphone · %1 · %2 %%", _forEachIndex + 1, _lv] } else { format ["Reprendre · %1 · %2 · %3 %%", _forEachIndex + 1, _name, _lv] };
                _out pushBack [[format ["COMSPEC_ATAK_DockTake%1", _forEachIndex], _txt, "\z\comspec_atak_native\addons\main\data\item_phone.paa",
                    { ["take", [_target, _this select 2]] call comspec_atak_native_fnc_dockClient; }, { true }, {}, _forEachIndex] call comspec_atak_native_fnc_aceAction, [], _target];
            };
        } forEach _slots;
        _out pushBack [["COMSPEC_ATAK_DockStatus", "État du socle", "", { ["status", [_target]] call comspec_atak_native_fnc_dockClient; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
        if ((_target getVariable ["COMSPEC_ATAK_DockKit", false]) && {(_slots findIf { (count _x) > 0 }) < 0}) then {
            _out pushBack [["COMSPEC_ATAK_DockPack", "Replier le socle", "", { ["pack", [_target]] call comspec_atak_native_fnc_dockClient; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
        };
        _out
    }, [], [0, 0, 0.12], 3] call comspec_atak_native_fnc_aceAction;
    ["COMSPEC_ATAK_ChargingDock", 0, ["ACE_MainActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
    private _deploy = ["COMSPEC_ATAK_DockDeploy", "Poser le socle de recharge", _icon, { ["deploy"] call comspec_atak_native_fnc_dockClient; },
        { "COMSPEC_ATAK_DockKit" in (items _player) && {isNull objectParent _player} }] call comspec_atak_native_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions", "ACE_Equipment"], _deploy, true] call ace_interact_menu_fnc_addActionToClass;
} else {
    private _add = {
        private _cond = "cursorObject isKindOf 'COMSPEC_ATAK_ChargingDock' && {(player distance cursorObject) < 3}";
        player addAction ["<t color='#7fd0b0'>Socle : poser mon téléphone</t>", { ["put", [cursorObject, -1]] call comspec_atak_native_fnc_dockClient; }, nil, 1.4, false, true, "",
            _cond + " && {(['free', [cursorObject]] call comspec_atak_native_fnc_dockClient) >= 0} && {(['phone'] call comspec_atak_native_fnc_dockClient) isNotEqualTo ''}"];
        player addAction ["<t color='#7fd0b0'>Socle : reprendre mon téléphone</t>", {
            private _uid = getPlayerUID player;
            private _i = (cursorObject getVariable ["COMSPEC_ATAK_DockSlots", []]) findIf { (count _x) > 3 && {(_x select 0) isEqualTo _uid} };
            if (_i >= 0) then { ["take", [cursorObject, _i]] call comspec_atak_native_fnc_dockClient; };
        }, nil, 1.39, false, true, "", _cond + " && {private _u = getPlayerUID player; ((cursorObject getVariable ['COMSPEC_ATAK_DockSlots', []]) findIf { (count _x) > 3 && {(_x select 0) isEqualTo _u} }) >= 0}"];
        player addAction ["<t color='#7fd0b0'>Socle : état</t>", { ["status", [cursorObject]] call comspec_atak_native_fnc_dockClient; }, nil, 1.38, false, true, "", _cond];
        player addAction ["<t color='#7fd0b0'>Socle : replier</t>", { ["pack", [cursorObject]] call comspec_atak_native_fnc_dockClient; }, nil, 1.37, false, true, "",
            _cond + " && {cursorObject getVariable ['COMSPEC_ATAK_DockKit', false]} && {((cursorObject getVariable ['COMSPEC_ATAK_DockSlots', []]) findIf { (count _x) > 0 }) < 0}"];
        player addAction ["<t color='#7fd0b0'>Poser le socle de recharge</t>", { ["deploy"] call comspec_atak_native_fnc_dockClient; }, nil, 1.36, false, true, "",
            "'COMSPEC_ATAK_DockKit' in (items player) && {isNull objectParent player}"];
    };
    call _add;
    missionNamespace setVariable ["COMSPEC_ATAK_DockActions", _add];
    player addEventHandler ["Respawn", { call (missionNamespace getVariable "COMSPEC_ATAK_DockActions"); }];
};
