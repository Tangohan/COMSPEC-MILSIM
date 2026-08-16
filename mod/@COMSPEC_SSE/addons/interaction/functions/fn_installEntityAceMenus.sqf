/*
    Installe les menus ACE SSE sur UNE entité déjà marquée SSE.
    Verrou immédiat (queued/installed) avant tout waitAndExecute —
    sinon chaque rappel entityEnabled duplique Biométrie / Digital / enfants.
*/
params [
    ["_entity", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };
if (isNull _entity) exitWith { false };
if (_entity getVariable ["comspec_sse_generating", false]) exitWith { false };
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToObject"}) exitWith { false };

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap) || {count _cache == 0}) exitWith {
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            [_e] call comspec_sse_fnc_installEntityAceMenus;
        }, [_entity], 0.5] call CBA_fnc_waitAndExecute;
    };
    false
};

private _isPerson = _entity isKindOf "CAManBase";
private _rootDone = _entity getVariable ["comspec_sse_aceMenusInstalled", false];
private _childrenDone = _entity getVariable ["comspec_sse_aceChildrenQueued", false];
private _bioDone = _entity getVariable ["comspec_sse_aceBioInstalled", false]
    || {_entity getVariable ["comspec_sse_aceBioQueued", false]};
private _digDone = _entity getVariable ["comspec_sse_aceDigInstalled", false]
    || {_entity getVariable ["comspec_sse_aceDigQueued", false]};
private _readyFired = _entity getVariable ["comspec_sse_aceReadyFired", false];

if (_rootDone && {_childrenDone} && {_digDone} && {!_isPerson || {_bioDone}} && {_readyFired}) exitWith { true };

private _rootKey = if (_isPerson) then { "personRoot" } else { "objectRoot" };
private _root = _cache getOrDefault [_rootKey, []];
if (_root isEqualTo []) exitWith {
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            [_e] call comspec_sse_fnc_installEntityAceMenus;
        }, [_entity], 0.5] call CBA_fnc_waitAndExecute;
    };
    false
};

private _childrenKey = if (_isPerson) then { "personChildren" } else { "objectChildren" };
private _children = _cache getOrDefault [_childrenKey, []];
private _rootId = if (_isPerson) then { "COMSPEC_SSE" } else { "COMSPEC_SSE_OBJ" };

if (!_rootDone) then {
    // Verrou AVANT addAction (anti course entre deux entityEnabled).
    _entity setVariable ["comspec_sse_aceMenusInstalled", true];
    [_entity, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToObject;
};

if (!_childrenDone) then {
    _entity setVariable ["comspec_sse_aceChildrenQueued", true];
    if (!isNil "CBA_fnc_waitAndExecute") then {
        {
            private _delay = 0.02 + (_forEachIndex * 0.015);
            [{
                params ["_e", "_rootId", "_act"];
                if (isNull _e) exitWith {};
                [_e, 0, ["ACE_MainActions", _rootId], _act] call ace_interact_menu_fnc_addActionToObject;
            }, [_entity, _rootId, _x], _delay] call CBA_fnc_waitAndExecute;
        } forEach _children;
    } else {
        { [_entity, 0, ["ACE_MainActions", _rootId], _x] call ace_interact_menu_fnc_addActionToObject; } forEach _children;
    };
};

if (_isPerson && {!_bioDone}) then {
    private _bioRoot = _cache getOrDefault ["bioRoot", []];
    private _bioChildren = _cache getOrDefault ["bioChildren", []];
    if !(_bioRoot isEqualTo []) then {
        _entity setVariable ["comspec_sse_aceBioQueued", true];
        if (!isNil "CBA_fnc_waitAndExecute") then {
            [{
                params ["_e", "_bioRoot", "_bioChildren"];
                if (isNull _e) exitWith {};
                [_e, 0, ["ACE_MainActions", "COMSPEC_SSE"], _bioRoot] call ace_interact_menu_fnc_addActionToObject;
                {
                    private _i = _forEachIndex;
                    [{
                        params ["_ent", "_act"];
                        if (isNull _ent) exitWith {};
                        [_ent, 0, ["ACE_MainActions", "COMSPEC_SSE", "COMSPEC_SSE_Bio"], _act] call ace_interact_menu_fnc_addActionToObject;
                    }, [_e, _x], 0.02 + (_i * 0.015)] call CBA_fnc_waitAndExecute;
                } forEach _bioChildren;
                _e setVariable ["comspec_sse_aceBioInstalled", true];
            }, [_entity, _bioRoot, _bioChildren], 0.12] call CBA_fnc_waitAndExecute;
        } else {
            [_entity, 0, ["ACE_MainActions", "COMSPEC_SSE"], _bioRoot] call ace_interact_menu_fnc_addActionToObject;
            { [_entity, 0, ["ACE_MainActions", "COMSPEC_SSE", "COMSPEC_SSE_Bio"], _x] call ace_interact_menu_fnc_addActionToObject; } forEach _bioChildren;
            _entity setVariable ["comspec_sse_aceBioInstalled", true];
        };
    };
};

if (!_digDone) then {
    private _digRoot = _cache getOrDefault ["digitalRoot", []];
    private _digChildren = _cache getOrDefault ["digitalChildren", []];
    if !(_digRoot isEqualTo []) then {
        _entity setVariable ["comspec_sse_aceDigQueued", true];
        private _digParent = if (_isPerson) then { ["ACE_MainActions", "COMSPEC_SSE"] } else { ["ACE_MainActions", "COMSPEC_SSE_OBJ"] };
        if (!isNil "CBA_fnc_waitAndExecute") then {
            [{
                params ["_e", "_digParent", "_digRoot", "_digChildren"];
                if (isNull _e) exitWith {};
                [_e, 0, _digParent, _digRoot] call ace_interact_menu_fnc_addActionToObject;
                {
                    private _i = _forEachIndex;
                    [{
                        params ["_ent", "_parent", "_act"];
                        if (isNull _ent) exitWith {};
                        [_ent, 0, _parent + ["COMSPEC_SSE_DIGITAL"], _act] call ace_interact_menu_fnc_addActionToObject;
                    }, [_e, _digParent, _x], 0.02 + (_i * 0.012)] call CBA_fnc_waitAndExecute;
                } forEach _digChildren;
                _e setVariable ["comspec_sse_aceDigInstalled", true];
            }, [_entity, _digParent, _digRoot, _digChildren], 0.2] call CBA_fnc_waitAndExecute;
        } else {
            [_entity, 0, _digParent, _digRoot] call ace_interact_menu_fnc_addActionToObject;
            { [_entity, 0, _digParent + ["COMSPEC_SSE_DIGITAL"], _x] call ace_interact_menu_fnc_addActionToObject; } forEach _digChildren;
            _entity setVariable ["comspec_sse_aceDigInstalled", true];
        };
    };
};

if (!_readyFired) then {
    _entity setVariable ["comspec_sse_aceReadyFired", true];
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            if (!isNil "CBA_fnc_localEvent") then {
                ["comspec_sse_entityAceReady", [_e]] call CBA_fnc_localEvent;
            };
        }, [_entity], 0.45] call CBA_fnc_waitAndExecute;
    } else {
        if (!isNil "CBA_fnc_localEvent") then {
            ["comspec_sse_entityAceReady", [_entity]] call CBA_fnc_localEvent;
        };
    };
};

true
