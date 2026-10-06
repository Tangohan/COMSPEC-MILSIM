/*
    Nettoyer l'écran du téléphone (action ACE « Nettoyer l'écran du téléphone », Réglages > Matériel, app Profil).
    3 s pour la poussière et les traces, 3 s de plus s'il y a du sang ; barre de progression ACE si ACE est présent et le téléphone rangé.
    Les mains en sang étalent plus qu'elles n'essuient (fn_screenDirt "clean").
    Params : [mode "start" (défaut) | "can" (renvoie true s'il y a quelque chose à nettoyer maintenant)]
*/
params [["_mode", "start"]];
if (!hasInterface) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _amount = (_n getOrDefault ["dust", 0]) max (_n getOrDefault ["prints", 0]) max (_n getOrDefault ["blood", 0]) max (_n getOrDefault ["drops", 0]) max (_n getOrDefault ["frameBlood", 0]);
private _can = (["enabled"] call comspec_atak_native_fnc_screenDirt) && {_amount > 0.05} && {[player] call comspec_atak_native_fnc_hasDevice} && {!(player getVariable ["COMSPEC_ATAK_Cleaning", false])};
if (_mode isEqualTo "can") exitWith { _can };
if (!_can) exitWith { ["INFO", "L'écran est propre", 2, 10] call comspec_atak_native_fnc_notify; false };
player setVariable ["COMSPEC_ATAK_Cleaning", true];
private _blood = ((_n getOrDefault ["blood", 0]) max (_n getOrDefault ["frameBlood", 0])) > 0.1;
private _time = [3, 6] select _blood;
missionNamespace setVariable ["COMSPEC_ATAK_CleanDone", {
    player setVariable ["COMSPEC_ATAK_Cleaning", false];
    ["clean"] call comspec_atak_native_fnc_screenDirt;
    private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
    private _left = (_n getOrDefault ["blood", 0]) max (_n getOrDefault ["prints", 0]);
    ["SUCCESS", switch (true) do {
        case ((missionNamespace getVariable ["COMSPEC_ATAK_HandsBlood", 0]) > 0.3): { "Écran essuyé, mais vos mains en sang laissent des traînées" };
        case (_left > 0.15): { "Écran essuyé : il reste des traces, encore un passage" };
        default { "Écran propre" };
    }, 3, 20] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_deviceOverlay;
}];
// Téléphone affiché : pas de barre ACE (c'est une fenêtre qui passerait devant), simple attente avec bandeau.
if (!isNil "ace_common_fnc_progressBar" && {isNull ([] call comspec_atak_native_fnc_display)}) then {
    [_time, [], {
        call (missionNamespace getVariable ["COMSPEC_ATAK_CleanDone", {}]);
    }, {
        player setVariable ["COMSPEC_ATAK_Cleaning", false];
    }, "Nettoyage de l'écran du téléphone…", { alive player }, ["isNotInside", "isNotSitting", "isNotSwimming"]] call ace_common_fnc_progressBar;
} else {
    ["INFO", format ["Nettoyage de l'écran… (%1 s)", _time], _time, 10] call comspec_atak_native_fnc_notify;
    [{
        if (!alive player) exitWith { player setVariable ["COMSPEC_ATAK_Cleaning", false]; };
        call (missionNamespace getVariable ["COMSPEC_ATAK_CleanDone", {}]);
    }, [], _time] call CBA_fnc_waitAndExecute;
};
true
