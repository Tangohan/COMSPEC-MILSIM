/*
    Peut-on fouiller les papiers de cette personne sans son accord ? Mort, menotté (ACE), rendu, inconscient.
    Joueur menotté ou inconscient : fouille toujours possible (décision Q13), il en est prévenu sur son ATAK.
    [_unit] call comspec_sse_fnc_idCanSearch -> Boolean
*/
params [["_u", objNull, [objNull]]];
if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { false };
!alive _u
    || {_u getVariable ["ace_captives_isHandcuffed", false]}
    || {_u getVariable ["ace_captives_isSurrendering", false]}
    || {_u getVariable ["ACE_isUnconscious", false]}
    || {(lifeState _u) isEqualTo "INCAPACITATED"}
