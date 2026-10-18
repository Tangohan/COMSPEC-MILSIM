/*
    Nettoyer l'écran du téléphone (action ACE « Nettoyer l'écran du téléphone », Réglages > Matériel, app Profil).
    Sans kit ni objet, autant de fois qu'on veut (retour de jeu 1.97.0) : le bouton reste toujours disponible.
      "start" : coup de chiffon, 3 s, 3 s de plus s'il y a du sang (le sang demande deux passages ; des mains en sang
                étalent plus qu'elles n'essuient, fn_screenDirt "clean") ;
      "deep"  : nettoyage complet, 10 s : tout part d'un coup (poussière, traces, sang sur l'écran et la coque) ;
      "can"   : le nettoyage est-il possible maintenant (téléphone porté, pas déjà en train de nettoyer) ?
    Kit en complément (réglage « Kits de nettoyage ») : deux fois plus vite, nettoyage complet.
    Barre de progression ACE si ACE est présent et le téléphone rangé.
*/
params [["_mode", "start"]];
if (!hasInterface) exitWith { false };
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _amount = (_n getOrDefault ["dust", 0]) max (_n getOrDefault ["prints", 0]) max (_n getOrDefault ["blood", 0]) max (_n getOrDefault ["drops", 0]) max (_n getOrDefault ["frameBlood", 0]);
private _stains = count (_n getOrDefault ["stains", []]);
private _can = (["enabled"] call comspec_atak_native_fnc_screenDirt) && {[player] call comspec_atak_native_fnc_hasDevice} && {!(player getVariable ["COMSPEC_ATAK_Cleaning", false])};
if (_mode isEqualTo "can") exitWith { _can };
if (!_can) exitWith { false };
if (_amount <= 0.02 && {_stains isEqualTo 0}) exitWith { ["INFO", "L'écran est déjà propre", 2, 10] call comspec_atak_native_fnc_notify; true };
player setVariable ["COMSPEC_ATAK_Cleaning", true];
private _deep = _mode isEqualTo "deep";
private _blood = _stains > 0 || {((_n getOrDefault ["blood", 0]) max (_n getOrDefault ["frameBlood", 0])) > 0.1};
private _time = [[3, 6] select _blood, 10] select _deep;
// Kit de nettoyage en complément (réglage serveur, jamais obligatoire) : deux fois plus vite, et tout part d'un coup.
private _kits = ((missionNamespace getVariable ["comspec_atak_native_clean_items", ""]) splitString ", ;") apply { toLower _x };
if (_kits isNotEqualTo [] && {((items player) findIf { (toLower _x) in _kits }) >= 0}) then { _deep = true; _time = _time / 2; };
missionNamespace setVariable ["COMSPEC_ATAK_CleanDeep", _deep];
missionNamespace setVariable ["COMSPEC_ATAK_CleanDone", {
    player setVariable ["COMSPEC_ATAK_Cleaning", false];
    private _deep = missionNamespace getVariable ["COMSPEC_ATAK_CleanDeep", false];
    ["clean", _deep] call comspec_atak_native_fnc_screenDirt;
    private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
    private _left = (_n getOrDefault ["blood", 0]) max (_n getOrDefault ["prints", 0]);
    ["SUCCESS", switch (true) do {
        case (_deep): { "Nettoyage complet : écran et coque propres" };
        case ((missionNamespace getVariable ["COMSPEC_ATAK_HandsBlood", 0]) > 0.3): { "Écran essuyé, mais vos mains en sang laissent des traînées" };
        case (_left > 0.15 || {(count (_n getOrDefault ["stains", []])) > 0}): { "Écran essuyé : il reste des traces, encore un passage" };
        default { "Écran propre" };
    }, 3, 20] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_deviceOverlay;
}];
private _label = ["Nettoyage de l'écran du téléphone…", "Nettoyage complet du téléphone…"] select _deep;
// Téléphone affiché : pas de barre ACE (c'est une fenêtre qui passerait devant), simple attente avec bandeau.
if (!isNil "ace_common_fnc_progressBar" && {isNull ([] call comspec_atak_native_fnc_display)}) then {
    [_time, [], {
        call (missionNamespace getVariable ["COMSPEC_ATAK_CleanDone", {}]);
    }, {
        player setVariable ["COMSPEC_ATAK_Cleaning", false];
    }, _label, { alive player }, ["isNotInside", "isNotSitting", "isNotSwimming"]] call ace_common_fnc_progressBar;
} else {
    ["INFO", format ["%1 (%2 s)", _label, _time], _time, 10] call comspec_atak_native_fnc_notify;
    [{
        if (!alive player) exitWith { player setVariable ["COMSPEC_ATAK_Cleaning", false]; };
        call (missionNamespace getVariable ["COMSPEC_ATAK_CleanDone", {}]);
    }, [], _time] call CBA_fnc_waitAndExecute;
};
true
