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
    ["lens"] renvoie [position ASL, direction] de l'objectif avant (devant l'écran, vers les yeux) tant que le
    téléphone est en main, sinon [] : les caméras du selfie s'y placent, la main ne passe plus devant l'image.
    Si la tête ne regarde pas le téléphone, l'objectif se met face au visage (la tête suit l'appareil).
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
    private _p = _phone modelToWorldVisualWorld [0, -0.03, 0.041];
    private _eye = eyePos _unit;
    // La tête suit l'appareil : on ne commande pas la tête d'un joueur (elle garde le regard qu'il avait en entrant
    // dans le selfie, souvent baissé vers le téléphone ou tourné ailleurs), donc l'objectif se place là où le
    // visage regarde, à longueur de bras. Tête déjà tournée vers le téléphone (moins de 12°) : l'objectif reste
    // sur le téléphone (la main ne passe pas devant) ; au-delà, il glisse vers l'axe du visage (complet à 30°).
    // Orientation de l'os de la tête (pose de référence : face à l'avant du modèle), sinon regard de l'unité.
    private _src = "regard (eyeDirection)";
    private _face = vectorNormalized (eyeDirection _unit);
    {
        private _sel = _unit selectionVectorDirAndUp ["head", _x];
        if ((count _sel) > 0 && {(vectorMagnitude (_sel select 0)) > 0.5}) exitWith {
            _face = vectorNormalized (_unit vectorModelToWorldVisual (_sel select 0));
            _src = format ["os animé (LOD %1)", _x];
        };
    } forEach [1, "FireGeometry"];
    // Regard très bas ou très haut : borné pour ne pas mettre l'objectif dans la poitrine ou au-dessus du casque.
    private _fp = ((asin (((_face select 2) max -1) min 1)) max -35) min 50;
    private _fy = (_face select 0) atan2 (_face select 1);
    _face = [(sin _fy) * (cos _fp), (cos _fy) * (cos _fp), sin _fp];
    private _arm = ((_eye vectorDistance _p) max 0.35) min 0.7;
    private _ang = acos ((((vectorNormalized (_p vectorDiff _eye)) vectorDotProduct _face) max -1) min 1);
    private _k = (((_ang - 12) / 18) max 0) min 1;
    private _lp = (_p vectorMultiply (1 - _k)) vectorAdd ((_eye vectorAdd (_face vectorMultiply _arm)) vectorMultiply _k);
    if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_SelfieLogAt", 0])) then {
        missionNamespace setVariable ["COMSPEC_ATAK_SelfieLogAt", diag_tickTime + 3];
        ["INFO", "PHOTO", format ["Selfie : visage à %1° du téléphone (site %2°), objectif sur l'axe du visage à %3 pour cent, tête %4",
            round _ang, round _fp, round (_k * 100), _src]] call comspec_atak_native_fnc_log;
    };
    [_lp, vectorNormalized (_eye vectorDiff _lp)]
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
            if !(_model in ["s7", "rugged", "iphone", "sonim", "plate", "flip", "ribbed"]) then { _model = "s7"; };
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
