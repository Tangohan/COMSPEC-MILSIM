// Lunettes de sport : actions du slot virtuel, dessin local des lunettes portées, teinte de l'écran.
if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_eyewearActions;

// Boucles légères : dessin des porteurs proches toutes les 0,2 s, teinte toutes les 0,5 s.
[{ [] call comspec_sse_fnc_eyewearRender; }, 0.2] call CBA_fnc_addPerFrameHandler;
[{ [] call comspec_sse_fnc_eyewearTint; }, 0.5] call CBA_fnc_addPerFrameHandler;
// Changement de vue ou de mode de vision : tout de suite (pas d'intérieur du modèle aperçu en passant en vue subjective).
["cameraView", { [] call comspec_sse_fnc_eyewearRender; [] call comspec_sse_fnc_eyewearTint; }] call CBA_fnc_addPlayerEventHandler;
// Lunettes au slot JVN : visionMode[] = {"Normal"} garde l'image normale, mais la touche des JVN passe quand même
// currentVisionMode à 1 (et ACE applique alors ses effets de JVN). On éteint aussitôt : ce ne sont pas des JVN.
["visionMode", {
    params ["_unit", "_mode"];
    if (_mode isEqualTo 1 && {((([] call comspec_sse_fnc_eyewearCatalog) select 1) getOrDefault [toLower (hmd _unit), ""]) isNotEqualTo ""}) then {
        _unit action ["nvGogglesOff", _unit];
    };
    [] call comspec_sse_fnc_eyewearTint;
}] call CBA_fnc_addPlayerEventHandler;

// Changement d'unité (Zeus, changement d'équipier) : l'ancienne unité retire ses lunettes du slot virtuel (remises dans son sac).
["unit", {
    params ["_new", "_old"];
    if (!isNull _old && {_old isNotEqualTo _new} && {local _old} && {(_old getVariable ["comspec_sse_eyewear_worn", ""]) isNotEqualTo ""}) then {
        [_old, true] call comspec_sse_fnc_eyewearRemove;
    };
}] call CBA_fnc_addPlayerEventHandler;
