/*
    Message visible par celui qui a lancé une action depuis Zeus.
    Le journal du téléphone (fn_announce) n'est pas visible dans l'interface Zeus :
    sans ce retour, un module Zeus semble ne rien faire.
      Zeus ouvert avec Zeus Enhanced : bandeau de ZEN (interface du mod ZEN).
      Sinon : notification COMSPEC_Info / COMSPEC_Warning (téléphone ATAK natif ou RPT, fn_showNotification).
      Jamais le message de retour du curateur d'Arma (BIS_fnc_showCuratorFeedbackMessage : alerte de l'interface du jeu).
    Params : [_message, _warn]
*/
params [["_msg", "", [""]], ["_warn", false, [true]]];
if (!hasInterface || {_msg isEqualTo ""}) exitWith {};
_msg = _msg call comspec_overwatch_connect_fnc_tr;
if (!isNull (findDisplay 312) && {!isNil "zen_common_fnc_showMessage"}) exitWith {
    [_msg splitString "%" joinString "%%"] call zen_common_fnc_showMessage;
};
[["COMSPEC_Info", "COMSPEC_Warning"] select _warn, [_msg]] call comspec_overwatch_connect_fnc_showNotification;
