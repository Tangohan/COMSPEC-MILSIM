/*
    Téléphone visible sur le gilet : notre modèle 3D (COMSPEC_ATAK_VestPhone, data/vest_phone.p3d) accroché au torse
    du joueur (os spine3, suit ses mouvements), vu par tous, sur n'importe quel gilet. Couleur de la coque
    (COMSPEC_ATAK_ShellTint) et patch du dos (COMSPEC_ATAK_Patch) appliqués à tous par setObjectTextureGlobal.
    Masqué pour soi en vue à la première personne. Retiré sans téléphone, à la mort ou en véhicule.
    Réglages profil : COMSPEC_ATAK_VestMount (off, left, center, right), COMSPEC_ATAK_VestDepth (épaisseur du gilet),
    COMSPEC_ATAK_VestFace (back : dos et patch vers l'extérieur, pochette rabattue ; screen : écran vers l'extérieur).
    Gilet avec ATAK intégré (fn_vestBuiltin) : rien n'est accroché tant que COMSPEC_ATAK_VestBuiltinUse ne vaut pas "ours".
    Réglage fin de l'orientation (essais en jeu) : missionNamespace COMSPEC_ATAK_VestDirUp = [[dir], [up]].
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
// Gilet qui porte déjà un ATAK modélisé : le sien suffit, sauf si le joueur veut aussi le nôtre (menu ACE « Gilet »).
private _builtinOnly = [player] call comspec_atak_native_fnc_vestBuiltin && {(profileNamespace getVariable ["COMSPEC_ATAK_VestBuiltinUse", "vest"]) isNotEqualTo "ours"};
// Tablette sur le JPC (fn_jpcScreen) : c'est elle le terminal visible, le téléphone n'est pas accroché en plus.
private _jpc = (profileNamespace getVariable ["COMSPEC_ATAK_CarryStyle", "hud"]) isEqualTo "jpc" && {vest player isNotEqualTo ""};
private _want = _mount in ["left", "center", "right"] && {!_builtinOnly} && {!_jpc} && {alive player} && {vehicle player isEqualTo player} && {[player] call comspec_atak_native_fnc_hasDevice};
if (!_want) exitWith {
    if (!isNull _cur) then { deleteVehicle _cur; missionNamespace setVariable ["COMSPEC_ATAK_VestObj", objNull]; };
    false
};
private _face = profileNamespace getVariable ["COMSPEC_ATAK_VestFace", "back"];
private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
private _patch = profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"];
private _key = [_mount, _depth, _face, _tint, _patch, player];
if (isNull _cur || {(missionNamespace getVariable ["COMSPEC_ATAK_VestKey", []]) isNotEqualTo _key} || {(attachedTo _cur) isNotEqualTo player}) then {
    if (!isNull _cur) then { deleteVehicle _cur; };
    private _d = "\z\comspec_atak_native\addons\main\data\";
    _cur = createSimpleObject ["COMSPEC_ATAK_VestPhone", getPosASL player, false];
    _cur setVariable ["COMSPEC_ATAK_Vest", true, true];
    _cur setObjectTextureGlobal [0, format ["%1vest_phone%2_co.paa", _d, ["", "_" + _tint] select (_tint in ["od", "black"])]];
    // Patch du dos : invisible sans patch ou écran vers l'extérieur (le dos est alors contre le gilet).
    _cur setObjectTextureGlobal [1, ["", ([player, _patch] call comspec_atak_native_fnc_patchTexture) select 0] select (_face isEqualTo "back")];
    // Décalage depuis spine3 (repère du corps : x à droite, y devant, z en haut).
    // Modèle : l'avant (+Z du P3D, vectorDir) est le dos du téléphone, l'écran regarde vers l'arrière.
    private _x = switch (_mount) do { case "left": { -0.12 }; case "right": { 0.12 }; default { 0 }; };
    _cur attachTo [player, [_x, _depth, 0.05], "spine3", true];
    private _dirUp = missionNamespace getVariable ["COMSPEC_ATAK_VestDirUp", [[[0, -1, 0], [0, 1, 0]] select (_face isEqualTo "back"), [0, 0, 1]]];
    _cur setVectorDirAndUp _dirUp;
    missionNamespace setVariable ["COMSPEC_ATAK_VestObj", _cur];
    missionNamespace setVariable ["COMSPEC_ATAK_VestKey", _key];
};
// Vue à la première personne : l'objet passerait devant la caméra.
_cur hideObject (cameraView isEqualTo "INTERNAL");
true
