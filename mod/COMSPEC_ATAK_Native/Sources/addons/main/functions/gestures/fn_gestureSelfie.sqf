/*
    Pose du selfie et de la photo (réglages CBA « Animation du selfie » et « Animation de l'appareil photo », valeur
    par défaut) : appelée par fn_photoMode avec [joueur, "selfie"] ou [joueur, "photo"] toutes les 1,2 s pendant le
    mode photo, puis [joueur, "exit"] à la fermeture ; fn_photoCam (app Photo, objectif avant) fait de même.
    Photo (objectif arrière) et selfie : même pose, téléphone tenu devant le visage, écran vers soi.
    Bras droit qui se lève (GestureComspecSelfieUp, 0,6 s), tenu tendu devant le visage (GestureComspecSelfie,
    en boucle avec un léger balancement), puis qui redescend (GestureComspecSelfieDown, 0,5 s). Arme rangée par
    l'appareil photo, donc bras droit libre.
    Le téléphone du profil (S7 par défaut, même modèle que sur le JPC) est tenu dans la main droite, en paysage,
    écran vers le visage : objet simple global accroché à l'os de la main (il suit le bras pour tout le monde).
    ["lens"] renvoie [position ASL, direction] de l'objectif avant (devant l'écran, visé sur le visage, position lissée)
    tant que le téléphone est en main, sinon [] : les caméras du selfie s'y placent, la main ne passe plus devant l'image.
    ["rear"] renvoie [position ASL] de l'objectif arrière (dos du téléphone) tant qu'il est en main, sinon [] :
    la caméra de la photo s'y place, ni la main ni le téléphone ne passent devant l'image.
    Réglage fin en jeu (console) : COMSPEC_ATAK_SelfiePhoneAttach = [os, décalage, vectorDir, vectorUp] dans le
    repère de la main (pose de référence), valeurs par défaut calculées par tools/gen_gestures.py (selfie_phone).
*/
params [["_unit", player], ["_mode", "selfie"]];
private _phone = missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
_mode = toLower _mode;
if (_mode isEqualTo "rear") exitWith {
    if (isNull _phone || {(attachedTo _phone) isNotEqualTo _unit}) exitWith { [] };
    [_phone modelToWorldVisualWorld [0, 0.02, 0.041]]
};
if (_mode isEqualTo "lens") exitWith {
    if (isNull _phone || {(attachedTo _phone) isNotEqualTo _unit}) exitWith { [] };
    // Objectif avant sur la face écran du téléphone, visé sur le visage, comme un vrai selfie. L'ancien calcul suivait
    // l'axe de l'os de la tête (objectif qui flottait devant le casque, cadrage de travers : « selfie horrible »).
    // Position lissée : le balancement du bras ne fait plus trembler l'image.
    private _p = _phone modelToWorldVisualWorld [0, -0.03, 0.041];
    private _prev = missionNamespace getVariable ["COMSPEC_ATAK_SelfieLensPos", []];
    if ((count _prev) isEqualTo 3 && {(_prev vectorDistance _p) < 0.3}) then { _p = (_prev vectorMultiply 0.8) vectorAdd (_p vectorMultiply 0.2); };
    missionNamespace setVariable ["COMSPEC_ATAK_SelfieLensPos", _p];
    // Visage (un peu sous les yeux), à 35 cm au moins : la tête entière reste dans l'image.
    private _face = (eyePos _unit) vectorAdd [0, 0, -0.05];
    private _d = vectorNormalized (_face vectorDiff _p);
    if ((_p vectorDistance _face) < 0.35) then { _p = _face vectorDiff (_d vectorMultiply 0.35); };
    [_p, _d]
};
private _drop = {
    if (!isNull _phone) then { deleteVehicle _phone; };
    missionNamespace setVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
};
if (!local _unit || {!alive _unit} || {vehicle _unit isNotEqualTo _unit}) exitWith { call _drop; };
private _gs = toLower (gestureState _unit);
switch (_mode) do {
    case "photo";
    case "selfie": {
        if (isNull _phone || {(attachedTo _phone) isNotEqualTo _unit}) then {
            call _drop;
            private _model = profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"];
            if !(_model in ["s7", "s23", "rugged", "iphone", "sonim", "ip17"]) then { _model = "s7"; };
            _phone = createSimpleObject ["COMSPEC_ATAK_JpcPhone_" + _model, getPosASL _unit, false];
            private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
            if (_model isEqualTo "s7" && {_tint in ["od", "black"]}) then {
                _phone setObjectTextureGlobal [0, format ["\z\comspec_atak_native\addons\main\data\jpc_s7_%1_ca.paa", _tint]];
            };
            _phone setVariable ["COMSPEC_ATAK_Jpc", true, true];
            _phone setObjectTextureGlobal [1, "#(argb,8,8,3)color(0.02,0.03,0.03,1)"];
            (missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhoneAttach",
                ["righthand", [0.054, 0.064, 0.001], [-0.311, 0.261, -0.914], [0.706, -0.58, -0.406]]]) params ["_bone", "_off", "_vd", "_vu"];
            _phone attachTo [_unit, _off, _bone, true];
            _phone setVectorDirAndUp [_vd, _vu];
            missionNamespace setVariable ["COMSPEC_ATAK_SelfiePhone", _phone];
        };
        // Déjà levé (ou en train de se lever) : rien à faire.
        if ((_gs find "comspecselfie") >= 0 && {(_gs find "down") < 0}) exitWith {};
        _unit playActionNow "GestureComspecSelfieUp";
        // Enchaîne la pose tenue juste avant la fin de la montée (pas de retombée du bras entre les deux).
        [{
            params ["_unit"];
            if ((toLower (gestureState _unit)) find "comspecselfieup" >= 0) then { _unit playActionNow "GestureComspecSelfie"; };
        }, [_unit], 0.55] call CBA_fnc_waitAndExecute;
    };
    case "exit": {
        if ((_gs find "comspecselfie") >= 0 && {(_gs find "down") < 0}) then { _unit playActionNow "GestureComspecSelfieDown"; };
        // Le téléphone descend avec la main, puis disparaît (rangé).
        missionNamespace setVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
        if (!isNull _phone) then { [{ deleteVehicle _this; }, _phone, 0.5] call CBA_fnc_waitAndExecute; };
    };
    default {};
};
