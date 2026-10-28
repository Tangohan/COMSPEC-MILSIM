/*
    ATAK natif : ouvre le téléphone en main sur une app (page de config.cpp de l'ATAK natif : BRIEFING, FRS, MEDICAL…),
    à la place d'une ancienne fenêtre plein écran d'Overwatch par-dessus le jeu.
    Params : [_page, _noPhoneMsg] ; sans téléphone, une notification (_noPhoneMsg) qui ouvrira l'app au clic.
    Retour : true si l'ATAK natif est chargé (l'appelant s'arrête là), false sinon (ancien chemin).
*/
params [["_page", "LAUNCHER", [""]], ["_noPhoneMsg", "", [""]]];
if (!isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main")) exitWith { false };
if (!hasInterface) exitWith { true };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {
    if (_noPhoneMsg isNotEqualTo "") then {
        ["WARNING", _noPhoneMsg, 5, 30, _page] call comspec_atak_native_fnc_notify;
    };
    true
};
if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
    [true] call comspec_atak_native_fnc_open;
};
[{ [_this] call comspec_atak_native_fnc_navigate; }, _page] call CBA_fnc_execNextFrame;
true
