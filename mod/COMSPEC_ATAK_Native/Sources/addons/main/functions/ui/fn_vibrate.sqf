/*
    Vibration du téléphone à l'arrivée d'un message : tout le téléphone tremble un instant, écran compris (porté ou en main)
    et Overwatch joue son son de vibration (anti-spam intégré). Réglage profil COMSPEC_ATAK_Vibrate ;
    mode discrétion (COMSPEC_ATAK_Silent) : pas de son.
    Params : [vibreur seul (true : jamais de son, alerte discrète de fn_notify mode « vibrate »)]
*/
params [["_noSound", false]];
if !((["COMSPEC_ATAK_Vibrate", true, "native_vibrate"] call comspec_atak_native_fnc_pref) select 0) exitWith { false };
// Mode discrétion : la coque tremble encore (visible pour soi seul) mais aucun son.
private _silent = (_noSound isEqualTo true) || {profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]} || {["mute", false] call comspec_atak_native_fnc_zeusFx};
if (missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]) exitWith { false };
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
// Haut-parleur hors service (fn_deviceDamage) : plus de son de vibration.
if (((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["audio", 0]) >= 0.5) then { _silent = true; };
if (!_silent && {!isNil "comspec_overwatch_connect_fnc_playAtakVibrate"}) then { [0.7] call comspec_overwatch_connect_fnc_playAtakVibrate; };
// Tout le téléphone tremble d'un bloc, écran compris (fn_phoneShake) ; avant, seule la coque bougeait.
[0.6, 3, 2] call comspec_atak_native_fnc_phoneShake
