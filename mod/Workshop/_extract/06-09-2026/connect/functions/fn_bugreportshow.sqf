/*
    Ouvre le formulaire de signalement joueur.
    Ne pas parenter au panneau HTML « gestion du mod » : createDisplay y échoue
    (fenêtre absente ou Envoyer inerte). On le ferme, puis on s’accroche au menu Échap.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Activez d’abord Overwatch pour signaler un problème.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull (uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_BugReportOpening", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_BugReportOpening", true, false];

[] spawn {
    private _pm = uiNamespace getVariable ["COMSPEC_PauseManager_Display", displayNull];
    if (!isNull _pm) then {
        _pm closeDisplay 2;
        uiSleep 0.12;
    };

    private _parent = findDisplay 49;
    if (isNull _parent) then { _parent = findDisplay 46; };
    if (isNull _parent) then { _parent = findDisplay 0; };

    private _ok = false;
    private _disp = displayNull;
    if (!isNull _parent) then {
        _disp = _parent createDisplay "COMSPEC_BugReport_Dialog";
        _ok = !isNull _disp;
    };

    if (!_ok || {isNull _disp}) then {
        _ok = createDialog "COMSPEC_BugReport_Dialog";
        _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9992; };
    };

    missionNamespace setVariable ["COMSPEC_BugReportOpening", false, false];

    if (!_ok || {isNull _disp}) exitWith {
        ["Impossible d’ouvrir le formulaire de signalement. Réessayez depuis Échap → gestion du mod.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };

    uiNamespace setVariable ["COMSPEC_BugReport_Display", _disp];
    (_disp displayCtrl 9802) ctrlSetText "";

    private _combo = _disp displayCtrl 9801;
    lbClear _combo;
    {
        _x params ["_label", "_code"];
        private _i = _combo lbAdd _label;
        _combo lbSetData [_i, _code];
    } forEach [
        ["Liaison / connexion Athena", "Liaison"],
        ["Menu ACE / ATAK", "ACE"],
        ["Carte / marqueurs", "Marqueurs"],
        ["Photos / messagerie", "Médias"],
        ["Gel / performance", "Perf"],
        ["Autre", "Autre"]
    ];
    _combo lbSetCurSel 0;

    private _chk = _disp displayCtrl 9805;
    if (!isNull _chk) then {
        _chk setVariable ["COMSPEC_LogAttach", true];
        _chk ctrlSetText "Joindre le journal de session : oui";
    };
};
