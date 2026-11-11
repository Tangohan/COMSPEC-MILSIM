/*
    IA RP, poste du joueur : « Parler à… » sur une IA vivante à moins de 5 m (à pied).
      ACE : menu d'interaction de la personne (ACE_MainActions), posé une fois par classe (aucune action par objet).
      Sans ACE : action à la molette sur l'IA visée.   Touche : CBA › Touches › COMSPEC SSE (Ctrl+T par défaut).
    Zeus (ZEN) : modules « COMSPEC SSE » et menu contextuel « SSE · Roleplay de l'IA » (fn_iarpZen) ; bouton « SSE » de
    la fenêtre d'édition d'une IA (fn_iarpZenHub).
*/
if (!hasInterface) exitWith {};
private _can = {
    params ["_t", "_p"];
    (missionNamespace getVariable ["comspec_sse_iarp_enabled", true]) && {!isNull _t} && {alive _t} && {_t isKindOf "CAManBase"} && {!isPlayer _t}
        && {(_p distance _t) <= 5} && {isNull objectParent _p} && {alive _p} && {!(_t getVariable ["ACE_isUnconscious", false])}
};
missionNamespace setVariable ["comspec_sse_iarpCan", _can];
// IA en face : celle qu'on vise, sinon la plus proche à 5 m dans un cône de 60°.
missionNamespace setVariable ["comspec_sse_iarpPick", {
    private _t = cursorObject;
    if ([_t, player] call comspec_sse_iarpCan) exitWith { _t };
    private _l = (player nearEntities ["CAManBase", 5]) select { [_x, player] call comspec_sse_iarpCan && {(abs ((player getRelDir _x) - ([0, 360] select ((player getRelDir _x) > 180)))) < 30} };
    _l = _l apply { [player distance _x, _x] };
    _l sort true;
    (_l param [0, [0, objNull]]) select 1
}];

if (!isNil "ace_interact_menu_fnc_createAction") then {
    private _act = ["COMSPEC_IARP_Talk", localize "STR_COMSPEC_IARP_Action", "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa",
        { params ["_t"]; ["open", [_t]] call comspec_sse_fnc_iarpUi; },
        { params ["_t", "_p"]; [_t, _p] call comspec_sse_iarpCan }] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions"], _act, true] call ace_interact_menu_fnc_addActionToClass;
} else {
    missionNamespace setVariable ["comspec_sse_iarpWheel", {
        params ["_u"];
        _u addAction [localize "STR_COMSPEC_IARP_Action", { ["open", [cursorObject]] call comspec_sse_fnc_iarpUi; }, nil, 1.4, false, true, "",
            "[cursorObject, _this] call comspec_sse_iarpCan", 5];
    }];
    [{ !isNull player }, {
        [player] call comspec_sse_iarpWheel;
        player addEventHandler ["Respawn", { [_this select 0] call comspec_sse_iarpWheel; }];
    }] call CBA_fnc_waitUntilAndExecute;
};

// Zeus (ZEN) : modules Atelier IA, Faire parler une IA, Journal des échanges, et menu contextuel « SSE · Roleplay de
// l'IA » (fn_iarpZen), une fois ZEN chargé ; sans ZEN, rien n'est posé.
[{ !isNil "zen_custom_modules_fnc_register" }, { [] call comspec_sse_fnc_iarpZen; }] call CBA_fnc_waitUntilAndExecute;
// Bouton « SSE » de la fenêtre d'édition d'une IA (double-clic ZEN) : fenêtre « SSE · <nom de l'IA> » (fn_iarpZenHub).
[{ !isNil "zen_attributes_fnc_addButton" }, { ["register"] call comspec_sse_fnc_iarpZenHub; }] call CBA_fnc_waitUntilAndExecute;

if (!isNil "CBA_fnc_addKeybind") then {
    ["COMSPEC SSE", "comspec_sse_iarp_key", [localize "STR_COMSPEC_IARP_Key_T", localize "STR_COMSPEC_IARP_Key_D"], {
        if (dialog || {!(missionNamespace getVariable ["comspec_sse_iarp_enabled", true])}) exitWith { false };
        private _t = call comspec_sse_iarpPick;
        if (isNull _t) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoOne"]] call comspec_sse_fnc_iarpClient; true };
        ["open", [_t]] call comspec_sse_fnc_iarpUi;
        true
    }, {}, [0x14, [false, true, false]]] call CBA_fnc_addKeybind;
};
