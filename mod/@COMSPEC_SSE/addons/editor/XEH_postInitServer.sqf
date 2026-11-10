// IA gardées pour les prochaines parties, enregistrées sur ce serveur (profil du serveur) : remises en place une fois les
// unités de la mission apparues. Celles d'Athena sont remises par le paquet d'identité (fn_idAthenaPack, fn_idAiConfig).
if (!isServer) exitWith {};
[{ ["restoreLocal"] call comspec_sse_fnc_editorAiServer; }, [], 15] call CBA_fnc_waitAndExecute;
