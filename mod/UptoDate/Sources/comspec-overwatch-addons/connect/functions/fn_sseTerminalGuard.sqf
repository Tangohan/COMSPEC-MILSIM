/*
    Pose ou retire ce qui accompagne le terminal SEEK ouvert.
    Params : ["on" | "off"]
      "on"  (onLoad) : arme rangée, clavier gardé (fn_sseKeyBlock) sur le SEEK et sur le jeu (46),
                       barre d'état rafraîchie quand la liaison change (le SEEK ne se repeignait
                       qu'à la prochaine action : ouvert hors liaison, il le restait à l'écran) ;
      "off" (onUnload) : retire les gestionnaires posés sur le jeu.
*/
params [["_mode", "on"]];
if (!hasInterface) exitWith {};
private _d46 = findDisplay 46;

if (_mode isEqualTo "off") exitWith {
    if (!isNull _d46) then {
        private _eh = _d46 getVariable ["COMSPEC_SseKeyBlockEh", -1];
        if (_eh >= 0) then { _d46 displayRemoveEventHandler ["KeyDown", _eh]; };
        _d46 setVariable ["COMSPEC_SseKeyBlockEh", -1];
    };
    private _pfh = uiNamespace getVariable ["COMSPEC_SseLinkPfh", -1];
    if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
    uiNamespace setVariable ["COMSPEC_SseLinkPfh", -1];
};

private _seek = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _seek) then { _seek = findDisplay 9991; };
if (isNull _seek) exitWith {};

// Le SEEK s'utilise à deux mains : l'arme part dans le dos (à pied seulement).
if (isNull objectParent player && {(currentWeapon player) isNotEqualTo ""}) then {
    player action ["SwitchWeapon", player, player, 299];
};

if !(_seek getVariable ["COMSPEC_SseKeyBlock", false]) then {
    _seek setVariable ["COMSPEC_SseKeyBlock", true];
    _seek displayAddEventHandler ["KeyDown", { _this call comspec_overwatch_connect_fnc_sseKeyBlock }];
};
if (!isNull _d46 && {(_d46 getVariable ["COMSPEC_SseKeyBlockEh", -1]) < 0}) then {
    _d46 setVariable ["COMSPEC_SseKeyBlockEh", _d46 displayAddEventHandler ["KeyDown", { _this call comspec_overwatch_connect_fnc_sseKeyBlock }]];
};

if ((uiNamespace getVariable ["COMSPEC_SseLinkPfh", -1]) < 0) then {
    uiNamespace setVariable ["COMSPEC_SseLinkPfh", [{
        params ["", "_id"];
        if (isNull (uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull])) exitWith {
            [_id] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_SseLinkPfh", -1];
        };
        private _now = [] call comspec_overwatch_connect_fnc_sseLinkState;
        if (_now isNotEqualTo (uiNamespace getVariable ["COMSPEC_SsePerson_LinkShown", ""])) then {
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
        };
    }, 2] call CBA_fnc_addPerFrameHandler];
};
