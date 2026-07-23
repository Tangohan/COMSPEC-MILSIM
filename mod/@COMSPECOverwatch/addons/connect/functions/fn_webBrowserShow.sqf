/*
    Ouvre la tablette Chromium (idd 9974).
    La vue classique (petit modèle, idd 9973) est temporairement désactivée :
    pas de bascule automatique si le chargement est lent.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (findDisplay 9974)) exitWith {};

if !([] call comspec_overwatch_connect_fnc_webBrowserAvailable) exitWith {
    ["COMSPEC_Warning", ["Écran tablette avancé indisponible (réglage ou moteur)."]] call comspec_overwatch_connect_fnc_showNotification;
    // Repli classique uniquement si explicitement réactivé
    [] call comspec_overwatch_connect_fnc_openClassicTablet;
};

private _ok = createDialog "COMSPEC_WebBrowser_Dialog";
if (!_ok) exitWith {
    ["COMSPEC_Warning", ["Impossible d’ouvrir la tablette Athena."]] call comspec_overwatch_connect_fnc_showNotification;
    [] call comspec_overwatch_connect_fnc_openClassicTablet;
};

["start"] call comspec_overwatch_connect_fnc_playAtakNotification;

// Ancien filet : bascule classique après 2,5 s — désactivé temporairement.
// On laisse l’écran avancé ouvert même si PageLoaded est lent (HTML base64).
[] spawn {
    uiSleep 4;
    if (isNull (findDisplay 9974)) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_WebBrowser_PageReady", false]) exitWith {};
    private _display = findDisplay 9974;
    private _hint = _display displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.55' color='#ffd27a'>Chargement prolongé…</t>";
    };
    private _ctrl = _display displayCtrl 9401;
    // Si le contrôle navigateur est bien là, on considère l’UI utilisable
    if (!isNull _ctrl && {ctrlShown _ctrl}) then {
        missionNamespace setVariable ["COMSPEC_WebBrowser_PageReady", true];
    };
};
