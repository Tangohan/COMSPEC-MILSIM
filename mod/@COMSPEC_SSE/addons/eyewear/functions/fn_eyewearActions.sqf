/*
    Actions du slot virtuel COMSPEC (expérimental), client.
    ACE présent (cas normal, le SSE dépend d'ACE) : actions personnelles › « Lunettes » › « Porter : <modèle> » (un par
    objet <classe>_Item de l'inventaire) et « Retirer les lunettes ». Sinon : mêmes actions dans le menu molette,
    reposées sur chaque nouvelle unité du joueur.
*/
if (!hasInterface) exitWith {};
private _cat = [] call comspec_sse_fnc_eyewearCatalog;
private _icon = ((_cat select 0) getOrDefault ["radar_white", createHashMap]) getOrDefault ["picture", ""];

if (!isNil "ace_interact_menu_fnc_addActionToClass" && {!isNil "ace_interact_menu_fnc_createAction"}) exitWith {
    if (missionNamespace getVariable ["comspec_sse_eyewear_aceDone", false]) exitWith {};
    missionNamespace setVariable ["comspec_sse_eyewear_aceDone", true];
    private _p = [false, false, false, false, false];
    missionNamespace setVariable ["comspec_sse_eyewear_aceParams", _p];
    private _root = [
        "COMSPEC_SSE_Eyewear", "Lunettes", _icon, {},
        {
            params ["", "_player"];
            private _items = ([] call comspec_sse_fnc_eyewearCatalog) select 3;
            ((_player getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo "")
                || {((items _player) findIf { (toLower _x) in _items }) >= 0}
        },
        {
            params ["_target", "_player"];
            private _cat = [] call comspec_sse_fnc_eyewearCatalog;
            private _p = missionNamespace getVariable ["comspec_sse_eyewear_aceParams", [false, false, false, false, false]];
            private _out = [];
            if ((_player getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo "") then {
                _out pushBack [[
                    "COMSPEC_SSE_EyewearOff", "Retirer les lunettes", "",
                    { params ["", "_player"]; [_player, true] call comspec_sse_fnc_eyewearRemove; },
                    { true }, {}, [], [0, 0, 0], 2, _p
                ] call comspec_sse_fnc_aceAction, [], _target];
            };
            private _inv = items _player;
            {
                private _e = (_cat select 0) get _x;
                if ((_e get "item") in _inv) then {
                    _out pushBack [[
                        "COMSPEC_SSE_EyewearOn_" + _x, "Porter : " + (_e get "name"), _e get "picture",
                        { params ["", "_player", "_id"]; [_player, _id] call comspec_sse_fnc_eyewearWear; },
                        { true }, {}, _x, [0, 0, 0], 2, _p
                    ] call comspec_sse_fnc_aceAction, [], _target];
                };
            } forEach (_cat select 2);
            _out
        },
        [], [0, 0, 0], 2, _p
    ] call comspec_sse_fnc_aceAction;
    ["CAManBase", 1, ["ACE_SelfActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
};

// Sans ACE : actions du menu molette sur l'unité du joueur.
private _install = {
    params ["_unit"];
    if (isNull _unit || {_unit getVariable ["comspec_sse_eyewear_actions", false]}) exitWith {};
    _unit setVariable ["comspec_sse_eyewear_actions", true];
    private _cat = [] call comspec_sse_fnc_eyewearCatalog;
    {
        private _e = (_cat select 0) get _x;
        _unit addAction [
            "Porter les lunettes : " + (_e get "name"),
            { params ["", "_caller", "", "_id"]; [_caller, _id] call comspec_sse_fnc_eyewearWear; },
            _x, 0.4, false, true, "",
            format ["_this isEqualTo _target && {'%1' in (items _this)}", _e get "item"]
        ];
    } forEach (_cat select 2);
    _unit addAction [
        "Retirer les lunettes",
        { params ["", "_caller"]; [_caller, true] call comspec_sse_fnc_eyewearRemove; },
        nil, 0.4, false, true, "",
        "_this isEqualTo _target && {(_this getVariable ['comspec_sse_eyewear_worn', '']) isNotEqualTo ''}"
    ];
};
missionNamespace setVariable ["comspec_sse_eyewear_install", _install];
[{ !isNull player }, { [player] call (missionNamespace getVariable "comspec_sse_eyewear_install"); }] call CBA_fnc_waitUntilAndExecute;
["unit", { params ["_new"]; [_new] call (missionNamespace getVariable "comspec_sse_eyewear_install"); }] call CBA_fnc_addPlayerEventHandler;
