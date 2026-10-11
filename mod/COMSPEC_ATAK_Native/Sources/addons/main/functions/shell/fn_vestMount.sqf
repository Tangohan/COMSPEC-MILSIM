/*
    Téléphone visible sur le gilet (essai) : un objet 3D accroché au torse du joueur (os spine3, suit ses mouvements),
    vu par tous, sur n'importe quel gilet. Modèle : téléphone d'Overwatch (cTab) si le mod est chargé, sinon le
    smartphone vanilla. Masqué pour soi en vue à la première personne. Retiré sans téléphone, à la mort ou en véhicule.
    Réglages profil : COMSPEC_ATAK_VestMount (off, left, center, right), COMSPEC_ATAK_VestDepth (épaisseur du gilet).
    Actions : ["tick"] (client, toutes les 2 s) ; ["sweep"] (serveur : objets orphelins après une déconnexion).
*/
params [["_act", "tick"]];
if (_act isEqualTo "sweep") exitWith {
    { if ((_x getVariable ["COMSPEC_ATAK_Vest", false]) && {isNull attachedTo _x}) then { deleteVehicle _x; }; } forEach (allSimpleObjects []);
    true
};
if (!hasInterface) exitWith { false };
private _mount = profileNamespace getVariable ["COMSPEC_ATAK_VestMount", "off"];
private _depth = profileNamespace getVariable ["COMSPEC_ATAK_VestDepth", 0.19];
private _cur = missionNamespace getVariable ["COMSPEC_ATAK_VestObj", objNull];
private _want = _mount in ["left", "center", "right"] && {alive player} && {vehicle player isEqualTo player} && {[player] call comspec_atak_native_fnc_hasDevice};
if (!_want) exitWith {
    if (!isNull _cur) then { deleteVehicle _cur; missionNamespace setVariable ["COMSPEC_ATAK_VestObj", objNull]; };
    false
};
private _key = [_mount, _depth, player];
if (isNull _cur || {(missionNamespace getVariable ["COMSPEC_ATAK_VestKey", []]) isNotEqualTo _key} || {(attachedTo _cur) isNotEqualTo player}) then {
    if (!isNull _cur) then { deleteVehicle _cur; };
    private _model = "\z\comspec_overwatch\addons\connect\data\device\itemandroid.p3d";
    if !(isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect") && {fileExists _model}) then {
        _model = getText (configFile >> "CfgVehicles" >> "Land_MobilePhone_smart_F" >> "model");
    };
    _cur = createSimpleObject [_model, getPosASL player, false];
    _cur setVariable ["COMSPEC_ATAK_Vest", true, true];
    // Décalage depuis spine3 (repère du corps : x à droite, y devant, z en haut) ; écran tourné vers l'avant.
    private _x = switch (_mount) do { case "left": { -0.12 }; case "right": { 0.12 }; default { 0 }; };
    _cur attachTo [player, [_x, _depth, 0.05], "spine3", true];
    _cur setVectorDirAndUp [[0, 0, 1], [0, 1, 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_VestObj", _cur];
    missionNamespace setVariable ["COMSPEC_ATAK_VestKey", _key];
};
// Vue à la première personne : l'objet passerait devant la caméra.
_cur hideObject (cameraView isEqualTo "INTERNAL");
true
