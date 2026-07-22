/*
    Bascule la vue de la tablette Athena (dialog COMSPEC_Device_Dialog, idd 9973) entre "Profil"
    (photo + nom, idc 9302/9303) et "Effectifs" (BFT léger — callsign + grille, idc 9314/9315),
    les deux occupant le même espace écran et n'étant donc jamais affichées ensemble.

    Params : [_display, _mode] — _mode optionnel : "profile"/"roster" force la vue, sinon bascule
    par rapport à l'état actuel (utilisé par le bouton "Effectifs").
*/
params [["_display", displayNull], ["_mode", ""]];
if (isNull _display) exitWith {};

private _rosterList = _display displayCtrl 9314;
private _rosterTitle = _display displayCtrl 9315;
private _profileAvatar = _display displayCtrl 9302;
private _profileName = _display displayCtrl 9303;
private _btnRoster = _display displayCtrl 9306;

private _showRoster = if (_mode == "roster") then {
    true
} else {
    if (_mode == "profile") then {
        false
    } else {
        // Bascule : l'état actuel se lit sur la visibilité de la liste effectifs.
        isNull _rosterList || {!(ctrlShown _rosterList)}
    };
};

{ if (!isNull _x) then { _x ctrlShow _showRoster; }; } forEach [_rosterList, _rosterTitle];
{ if (!isNull _x) then { _x ctrlShow !_showRoster; }; } forEach [_profileAvatar, _profileName];
if (!isNull _btnRoster) then { _btnRoster ctrlSetText (if (_showRoster) then { "Profil" } else { "Effectifs" }); };

if (_showRoster) then {
    [_display] spawn comspec_overwatch_connect_fnc_showDeviceRoster;
};
