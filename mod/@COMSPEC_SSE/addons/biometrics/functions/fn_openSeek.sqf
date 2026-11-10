/*
    Ouvre le SEEK II sur une personne, prêt pour le CONTRÔLE RAPIDE (page 10 du terminal Overwatch).
    Sans personne (ou sur soi) : accueil du terminal. SEEK déjà ouvert sur un autre sujet : il se
    referme puis se rouvre sur le nouveau (les relevés ne passent pas d'une personne à l'autre).

    [_target] call comspec_sse_fnc_openSeek -> Bool
    Appelée par : action ACE du kit SEEK II (interaction/fn_kitActions), app SSE terrain du téléphone
    (compat_atak/fn_fieldAction « seek »), BII-10 (compat_bii), niveau N4 du contrôle d'identité,
    Ctrl+Shift+S (fn_openSeekKeybind).
*/
params [
    ["_target", objNull, [objNull]]
];

if !([player, "seek"] call comspec_sse_fnc_hasEquipment) exitWith {
    diag_log text "[COMSPEC SSE] SEEK II : terminal absent de l'inventaire, ouverture refusée.";
    false
};

if (_target isEqualTo player) then { _target = objNull; };
missionNamespace setVariable ["comspec_sse_seekTarget", _target];
if (!isNull _target) then { [_target] call comspec_sse_fnc_ensureGenerated; };
if (!isNil "comspec_sse_fnc_uiSetRecord" && {!isNull _target}) then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

if (!isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith {
    private _page = [0, 10] select (!isNull _target);
    private _open = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (isNull _open) then { _open = findDisplay 9991; };
    private _cur = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
    if (!isNull _open && {!isNull _target} && {_cur isNotEqualTo _target}) exitWith {
        _open closeDisplay 2;
        [{ _this call comspec_sse_fnc_uiOpenSeekHost; }, [_target, _page, "seek"], 0.1] call CBA_fnc_waitAndExecute;
        true
    };
    [_target, _page, "seek"] call comspec_sse_fnc_uiOpenSeekHost
};

if (isNull _target) then { _target = player; };
missionNamespace setVariable ["comspec_sse_seekTarget", _target];
if !(createDialog "COMSPEC_SSE_SeekDialog") exitWith {
    diag_log text "[COMSPEC SSE] SEEK II : ni terminal Overwatch ni dialogue de secours.";
    false
};

true
