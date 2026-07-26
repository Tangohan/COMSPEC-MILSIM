/*
    Confirmation note bêta : persistance locale + inscription bêta Athena.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];

profileNamespace setVariable ["comspec_overwatch_beta_note_ack", true];
saveProfileNamespace;

missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", true, false];
missionNamespace setVariable ["COMSPEC_BetaNoteRetries", 99, false];

[] call comspec_overwatch_connect_fnc_registerBetaClient;

if (!isNull _display) then {
    _display closeDisplay 1;
} else {
    closeDialog 0;
};
