/*
    Ouvre la tablette Chromium (idd 9974). Si le navigateur est indisponible ou échoue,
    bascule vers la vue tablette classique (idd 9973).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (findDisplay 9974)) exitWith {};

if !([] call comspec_overwatch_connect_fnc_webBrowserAvailable) exitWith {
    if (isNull (findDisplay 9973)) then {
        createDialog "COMSPEC_Device_Dialog";
        ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
    };
};

private _ok = createDialog "COMSPEC_WebBrowser_Dialog";
if (!_ok) exitWith {
    ["COMSPEC_Warning", ["Écran tablette avancé indisponible — ouverture de la vue classique."]] call comspec_overwatch_connect_fnc_showNotification;
    if (isNull (findDisplay 9973)) then {
        createDialog "COMSPEC_Device_Dialog";
        ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
    };
};

["start"] call comspec_overwatch_connect_fnc_playAtakNotification;

// Filet de sécurité : si PageLoaded ne survient pas, bascule classique
[] spawn {
    uiSleep 2.5;
    if (isNull (findDisplay 9974)) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith {};
    private _display = findDisplay 9974;
    private _ctrl = _display displayCtrl 9401;
    if (!isNull _ctrl && {ctrlShown _ctrl}) exitWith {
        // Contrôle présent mais page lente — on laisse encore une chance
        private _hint = _display displayCtrl 9403;
        if (!isNull _hint) then {
            _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#ffd27a'>Chargement prolongé…</t>";
        };
    };
    closeDialog 0;
    uiSleep 0.05;
    ["COMSPEC_Info", ["Basculé sur la tablette classique."]] call comspec_overwatch_connect_fnc_showNotification;
    if (isNull (findDisplay 9973)) then {
        createDialog "COMSPEC_Device_Dialog";
    };
};
