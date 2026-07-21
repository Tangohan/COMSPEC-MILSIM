/*
    Initialisation du terminal messagerie (appelé depuis onLoad du dialog).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_Chat_Display", _display];

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
private _host = [_url] call comspec_overwatch_connect_fnc_portalLabel;

private _urlCtrl = _display displayCtrl 1399;
if (!isNull _urlCtrl) then { _urlCtrl ctrlSetText ("Portail : " + _host); };

private _ip = missionNamespace getVariable ["COMSPEC_userIp", "—"];
private _ipCtrl = _display displayCtrl 1398;
if (!isNull _ipCtrl) then { _ipCtrl ctrlSetText ("Votre adresse : " + _ip); };

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
private _logCtrl = _display displayCtrl 1402;
if (!isNull _logCtrl) then {
    if (_log isEqualTo "") then {
        private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
        private _hint = switch (_state) do {
            case "linked": {
                "[Athena] Journal vide — liaison affichée active. Envoyez un message ou rouvrez Compte Athena si le site ne reçoit rien."
            };
            default {
                "[Athena] Aucun événement. Touche K → Compte Athena (saisir un code), ou vérifiez l’URL https://athena.ttrd.fr/public dans CBA."
            };
        };
        _logCtrl ctrlSetText (_hint + "\n");
    } else {
        _logCtrl ctrlSetText _log;
    };
};

private _console = _display displayCtrl 1401;
if (!isNull _console && {ctrlText _console isEqualTo ""}) then {
    _console ctrlSetText "Les messages envoyés apparaissent ici.\n";
};

// Reflète l'état masqué/affiché sauvegardé (profileNamespace) sur les boutons de filtre,
// sans changer le réglage — le libellé est juste redessiné.
{
    _x params ["_category", "_idc", "_label"];
    private _ctrl = _display displayCtrl _idc;
    if (!isNull _ctrl) then {
        private _muted = profileNamespace getVariable [format ["comspec_overwatch_mute_%1", _category], false];
        _ctrl ctrlSetText format ["%1 : %2", _label, if (_muted) then { "masqué" } else { "affiché" }];
    };
} forEach [
    ["liaison", 1411, "Liaison"],
    ["cas", 1412, "CAS"],
    ["medical", 1413, "Médical"]
];

[] call comspec_overwatch_connect_fnc_updateStatusBadges;
