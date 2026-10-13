/*
    HUD des autres mods (F-PANO / ECOTI : boussole, température, heure, distance ; ACE, etc.) masqué sous le téléphone.
    Arma dessine les calques d'écran (cutRsc, RscTitles) de tous les mods au-dessus des fenêtres : en main, le téléphone
    passait sous ces textes ; porté (HUD), sous les calques créés après le sien.
    Tant que le téléphone est affiché, les contrôles de ces calques qui chevauchent la coque sont masqués (ctrlShow false),
    puis réaffichés à la fermeture. Les autres mods ne sont pas modifiés.
    Calques visés : displays rangés dans uiNamespace (onLoad habituel des RscTitles) et absents de allDisplays (donc des
    calques, pas des fenêtres), sauf ceux du téléphone (COMSPEC_ATAK_*) et le HUD du jeu (IGUI_displays).
    Réglage CBA comspec_atak_native_hide_foreign_hud (par joueur, activé par défaut).
    Params : "start" (fn_displayLoad) | "stop" (fn_displayUnload) | "scan" (chaque seconde) | "frame" (chaque image)
*/
params [["_mode", "scan"]];
disableSerialization;
if (!hasInterface) exitWith {};
private _hidden = uiNamespace getVariable ["COMSPEC_ATAK_ForeignHidden", []];
private _restore = {
    { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
    uiNamespace setVariable ["COMSPEC_ATAK_ForeignHidden", []];
};
switch (_mode) do {
    case "start": {
        missionNamespace setVariable ["COMSPEC_ATAK_ForeignNext", 0];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_ForeignPfh", -1]) >= 0) exitWith {};
        private _h = [{
            if (isNull ([] call comspec_atak_native_fnc_display)) exitWith { ["stop"] call comspec_atak_native_fnc_foreignHud; };
            if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_ForeignNext", 0])) then {
                missionNamespace setVariable ["COMSPEC_ATAK_ForeignNext", diag_tickTime + 1];
                ["scan"] call comspec_atak_native_fnc_foreignHud;
            } else {
                ["frame"] call comspec_atak_native_fnc_foreignHud;
            };
        }, 0] call CBA_fnc_addPerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_ForeignPfh", _h];
    };
    case "stop": {
        private _h = missionNamespace getVariable ["COMSPEC_ATAK_ForeignPfh", -1];
        if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
        missionNamespace setVariable ["COMSPEC_ATAK_ForeignPfh", -1];
        call _restore;
    };
    // Un mod qui réaffiche son contrôle est remasqué à l'image suivante.
    case "frame": {
        { if (!isNull _x && {ctrlShown _x}) then { _x ctrlShow false; }; } forEach _hidden;
    };
    case "scan": {
        private _d = [] call comspec_atak_native_fnc_display;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        // Désactivé, téléphone fermé, ou écran 3D du JPC (pas de coque à l'écran) : tout est réaffiché.
        if (!(missionNamespace getVariable ["comspec_atak_native_hide_foreign_hud", true]) || {isNull _d} || {_s getOrDefault ["texture", false]}) exitWith { call _restore; };
        ((([] call comspec_atak_native_fnc_layoutGet) getOrDefault ["visible", [0, 0, 0, 0]])) params ["_px", "_py", "_pw", "_ph"];
        private _skip = allDisplays + (uiNamespace getVariable ["IGUI_displays", []]) + [_d];
        private _want = [];
        {
            if ((_x select [0, 12]) isNotEqualTo "comspec_atak") then {
                private _v = uiNamespace getVariable _x;
                if (!isNil "_v" && {_v isEqualType displayNull} && {!isNull _v} && {!(_v in _skip)}) then {
                    _skip pushBack _v;
                    {
                        if (isNull (ctrlParentControlsGroup _x)) then {
                            (ctrlPosition _x) params [["_cx", 0], ["_cy", 0], ["_cw", 0], ["_ch", 0]];
                            if (_cx < _px + _pw && {_cx + _cw > _px} && {_cy < _py + _ph} && {_cy + _ch > _py}) then { _want pushBack _x; };
                        };
                    } forEach (allControls _v);
                };
            };
        } forEach (allVariables uiNamespace);
        // Plus sous le téléphone (mini déplacé, plein écran réduit) : réaffiché. Un contrôle que son mod a lui-même
        // masqué n'est jamais pris, pour ne pas le réafficher à la fermeture.
        { if (!isNull _x && {!(_x in _want)}) then { _x ctrlShow true; }; } forEach _hidden;
        private _new = _want select { _x in _hidden || {ctrlShown _x} };
        { _x ctrlShow false; } forEach _new;
        uiNamespace setVariable ["COMSPEC_ATAK_ForeignHidden", _new];
    };
};
