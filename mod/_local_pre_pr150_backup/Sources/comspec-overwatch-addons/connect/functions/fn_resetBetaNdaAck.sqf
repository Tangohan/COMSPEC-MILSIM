/*
    Efface l’acceptation NDA locale et réaffiche l’accord (immédiatement si possible).
    Ne touche pas à l’inscription Athena (RegisterBeta) déjà enregistrée.
*/
if (!hasInterface) exitWith { false };

profileNamespace setVariable ["comspec_overwatch_beta_note_ack", false];
saveProfileNamespace;

missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", false, false];
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", false];

// Fermer un éventuel NDA déjà ouvert avant de rouvrir
private _open = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
if (!isNull _open) then {
    _open closeDisplay 2;
};

[] call comspec_overwatch_connect_fnc_showBetaAccessNote;

["COMSPEC_Info", ["L’accord d’accès anticipé sera affiché à nouveau."]] call comspec_overwatch_connect_fnc_showNotification;

true
