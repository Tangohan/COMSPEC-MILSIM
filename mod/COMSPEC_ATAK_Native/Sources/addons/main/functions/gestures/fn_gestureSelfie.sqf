/*
    Pose du selfie (réglage CBA « Animation du selfie », valeur par défaut) : appelée par fn_photoMode avec
    [joueur, "selfie"] toutes les 1,2 s pendant le mode selfie, puis [joueur, "exit"] à la fermeture.
    Bras droit qui se lève (GestureComspecSelfieUp, 0,6 s), tenu tendu devant le visage, téléphone tourné
    vers soi (GestureComspecSelfie, en boucle avec un léger balancement), puis qui redescend
    (GestureComspecSelfieDown, 0,5 s). Arme rangée par l'appareil photo, donc bras droit libre.
*/
params [["_unit", player], ["_mode", "selfie"]];
if (!local _unit || {!alive _unit} || {vehicle _unit isNotEqualTo _unit}) exitWith {};
private _gs = toLower (gestureState _unit);
switch (toLower _mode) do {
    case "selfie": {
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
    };
    default {};
};
