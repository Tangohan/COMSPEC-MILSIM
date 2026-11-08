/*
    « J'accepte » sur la note de service : licence (version courante) + note bêta enregistrées sur le profil,
    puis inscription bêta Athena (seulement si l'unité utilise Athéna, fn_registerBetaClient).
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
// Bouton grisé tant que la note n'est pas lue jusqu'à la fin (fn_ndaWatch).
if (!(uiNamespace getVariable ["COMSPEC_NDA_ReadDone", true])) exitWith {};

[true] call comspec_overwatch_connect_fnc_licenceStatus;
profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
profileNamespace setVariable ["comspec_overwatch_cgu_ack", true];
[] call comspec_overwatch_connect_fnc_saveProfile;

missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
missionNamespace setVariable ["COMSPEC_BetaNoteRetries", 99, false];

[] call comspec_overwatch_connect_fnc_registerBetaClient;
[] call comspec_overwatch_connect_fnc_refreshMainMenuBetaBanner;

if (!isNull _display) then {
    _display closeDisplay 1;
} else {
    closeDialog 0;
};
