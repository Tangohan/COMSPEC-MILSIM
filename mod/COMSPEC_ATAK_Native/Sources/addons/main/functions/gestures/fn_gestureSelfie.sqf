/*
    Pose du selfie (réglage CBA « Animation du selfie », valeur par défaut) : appelée par fn_photoMode avec
    [joueur, "selfie"] toutes les 1,2 s pendant le mode selfie, puis [joueur, "exit"] à la fermeture ; fn_photoCam
    (app Photo, objectif avant) fait de même.
    Bras droit qui se lève (GestureComspecSelfieUp, 0,6 s), tenu tendu devant le visage (GestureComspecSelfie,
    en boucle avec un léger balancement), puis qui redescend (GestureComspecSelfieDown, 0,5 s). Arme rangée par
    l'appareil photo, donc bras droit libre.
    Le téléphone du profil (S7 par défaut, même modèle que sur le JPC) est tenu dans la main droite, en paysage,
    écran vers le visage : objet simple global accroché à l'os de la main (il suit le bras pour tout le monde).
    ["lens"] renvoie [position ASL, direction] de l'objectif avant (devant l'écran, vers les yeux) tant que le
    téléphone est en main, sinon [] : les caméras du selfie s'y placent, la main ne passe plus devant l'image.
    Réglage fin en jeu (console) : COMSPEC_ATAK_SelfiePhoneAttach = [os, décalage, vectorDir, vectorUp] dans le
    repère de la main (pose de référence), valeurs par défaut calculées par tools/gen_gestures.py (selfie_phone).
*/
params [["_unit", player], ["_mode", "selfie"]];
private _phone = missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
_mode = toLower _mode;
if (_mode isEqualTo "lens") exitWith {
    if (isNull _phone || {(attachedTo _phone) isNotEqualTo _unit}) exitWith { [] };
    private _p = _phone modelToWorldVisualWorld [0, -0.03, 0.041];
    private _eye = eyePos _unit;
    [_p, vectorNormalized (_eye vectorDiff _p)]
};
private _drop = {
    if (!isNull _phone) then { deleteVehicle _phone; };
    missionNamespace setVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
};
if (!local _unit || {!alive _unit} || {vehicle _unit isNotEqualTo _unit}) exitWith { call _drop; };
private _gs = toLower (gestureState _unit);
switch (_mode) do {
    case "selfie": {
        if (isNull _phone || {(attachedTo _phone) isNotEqualTo _unit}) then {
            call _drop;
            private _model = profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"];
            if !(_model in ["s7", "rugged", "iphone", "plate", "flip", "ribbed"]) then { _model = "s7"; };
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
