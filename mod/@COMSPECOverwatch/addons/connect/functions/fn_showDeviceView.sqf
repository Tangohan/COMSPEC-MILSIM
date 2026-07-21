/*
    Initialise la "vue tablette" (dialog COMSPEC_Device_Dialog, idd 9973) : statut de liaison
    (idc 9312) + profil joueur (photo idc 9302 / nom idc 9303), superposés sur l'écran de la
    tablette Athena (image de fond). Appelé depuis l'onLoad du dialog.
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _syncLabel = switch (_state) do {
    case "linked": { "Lié à Athena" };
    case "connecting": { "Connexion…" };
    case "disabled": { "Overwatch désactivé" };
    default { "Hors liaison" };
};
private _syncColor = switch (_state) do {
    case "linked": { "#7dffb3" };
    case "connecting": { "#ffd27a" };
    case "disabled": { "#8899aa" };
    default { "#ff8a7a" };
};

private _statusCtrl = _display displayCtrl 9312;
if (!isNull _statusCtrl) then {
    _statusCtrl ctrlSetStructuredText parseText format [
        "<t align='right' size='0.6'><t color='%1'>●</t>  <t color='#d0dce8'>%2</t></t>",
        _syncColor,
        _syncLabel
    ];
};

[_display, 9302, 9303] spawn comspec_overwatch_connect_fnc_showPlayerProfile;
