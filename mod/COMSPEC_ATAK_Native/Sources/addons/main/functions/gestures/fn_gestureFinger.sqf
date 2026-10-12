/*
    Doigt d'honneur (geste GestureComspecFinger, anim/comspec_finger.rtm) : bras gauche levé, dos de la main
    vers la cible, majeur tendu, deux coups secs, ~2,4 s. Main gauche libre : marche aussi fusil en main.
    Touche CBA « Doigt d'honneur » (COMSPEC ATAK, non attribuée par défaut) et action ACE perso (Gestes).
    Params : [unité (défaut player)] ; renvoie true si le geste est joué.
*/
params [["_unit", player]];
if (!local _unit || {!alive _unit}) exitWith { false };
if (vehicle _unit isNotEqualTo _unit) exitWith { false };
if (_unit getVariable ["ACE_isUnconscious", false]) exitWith { false };
if ((eyePos _unit) select 2 < 0) exitWith { false };
if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) exitWith { false };
if (diag_tickTime < (_unit getVariable ["comspec_atak_native_gestureUntil", -1])) exitWith { false };
_unit setVariable ["comspec_atak_native_gestureUntil", diag_tickTime + 2.5];
_unit playActionNow "GestureComspecFinger";
true
