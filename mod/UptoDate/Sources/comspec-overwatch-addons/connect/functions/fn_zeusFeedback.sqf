/*
    Message visible par celui qui a lancé une action depuis Zeus.
    Le journal du téléphone (fn_announce) n'est pas visible dans l'interface Zeus :
    sans ce retour, un module Zeus semble ne rien faire.
      Zeus ouvert : bandeau de Zeus Enhanced, sinon message de retour Zeus vanilla.
      Hors Zeus   : notification COMSPEC_Info / COMSPEC_Warning.
    Params : [_message, _warn]
*/
params [["_msg", "", [""]], ["_warn", false, [true]]];
if (!hasInterface || {_msg isEqualTo ""}) exitWith {};
_msg = _msg call comspec_overwatch_connect_fnc_tr;
if (!isNull (findDisplay 312)) exitWith {
    if (!isNil "zen_common_fnc_showMessage") then {
        [_msg splitString "%" joinString "%%"] call zen_common_fnc_showMessage;
    } else {
        [getAssignedCuratorLogic player, _msg] call BIS_fnc_showCuratorFeedbackMessage;
    };
};
[["COMSPEC_Info", "COMSPEC_Warning"] select _warn, [_msg]] call comspec_overwatch_connect_fnc_showNotification;
