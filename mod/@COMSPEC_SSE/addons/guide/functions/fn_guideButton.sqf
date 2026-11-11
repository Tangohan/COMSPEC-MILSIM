/*
    Pose sur un écran SSE un petit bouton carré « ? » qui ouvre la page _topic du MANUEL SSE par-dessus cet écran
    (comspec_sse_fnc_openGuide). À appeler dans l'onLoad d'une fenêtre, par exemple :
        [_display, "Documents", safeZoneX + safeZoneW - 0.06, safeZoneY + 0.01] call comspec_sse_fnc_guideButton

    [_display, _topic, _x, _y, _size] call comspec_sse_fnc_guideButton
      _display : écran (dialogue ou display) qui reçoit le bouton ; un idd est accepté (findDisplay).
      _topic   : classe de CfgHints › COMSPEC_SSE (« Documents », « Editor », « IdCheck », « AiZone »...).
      _x, _y   : coin haut gauche, dans les coordonnées de l'écran (safeZone) ; par défaut : coin haut droit.
      _size    : côté du bouton ; par défaut safeZoneH * 0.035.
    Retour : le contrôle créé (controlNull si l'écran est introuvable).
    Bulle d'aide : « Guide : <titre du chapitre> ».
*/
params [["_display", displayNull, [displayNull, 0]], ["_topic", "Start", [""]], ["_x", -1, [0]], ["_y", -1, [0]], ["_size", 0, [0]]];
if (!hasInterface) exitWith { controlNull };
disableSerialization;
if (_display isEqualType 0) then { _display = findDisplay _display; };
if (isNull _display) exitWith { controlNull };
if (_size <= 0) then { _size = safeZoneH * 0.035; };
if (_x < 0) then { _x = safeZoneX + safeZoneW - _size - safeZoneH * 0.01; };
if (_y < 0) then { _y = safeZoneY + safeZoneH * 0.01; };

private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> _topic;
if (!isClass _cfg) then { _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> "Start"; _topic = "Start"; };
private _name = (getText (_cfg >> "displayName")) call comspec_sse_fnc_tr;

private _b = _display ctrlCreate ["ctrlButton", -1];
_b ctrlSetPosition [_x, _y, _size, _size];
_b ctrlSetText "?";
_b ctrlSetFont "RobotoCondensedBold";
_b ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
_b ctrlSetTextColor [1, 1, 1, 1];
_b ctrlSetTooltip (format [("Guide : %1") call comspec_sse_fnc_tr, _name]);
_b setVariable ["comspec_sse_guide_topic", _topic];
_b ctrlAddEventHandler ["ButtonClick", {
    params ["_b"];
    [_b getVariable ["comspec_sse_guide_topic", "Start"], ctrlParent _b] call comspec_sse_fnc_openGuide;
    false
}];
_b ctrlCommit 0;
_b
