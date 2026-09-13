/*
    Ouvre la tablette Chromium (idd 9974).
    Params: [_fromAtak] — true si ouvert depuis ATAK Enhanced.
    La vue classique (petit modèle, idd 9973) est temporairement désactivée :
    pas de bascule automatique si le chargement est lent.
*/
params [["_fromAtak", false, [true]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if !([_fromAtak] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith {
    private _msg = if (!(missionNamespace getVariable ["comspec_overwatch_require_item", true]) || {([player] call comspec_overwatch_connect_fnc_hasTerminal)}) then {
        "Ouvrez le téléphone ATAK pour accéder à Athena."
    } else {
        "Terminal ATAK manquant — emportez votre téléphone ou tablette tactique pour synchroniser et ouvrir l’interface."
    };
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
};

if (!isNull (findDisplay 9974)) exitWith {};

if !([] call comspec_overwatch_connect_fnc_webBrowserAvailable) exitWith {
    ["COMSPEC_Warning", ["Écran tablette avancé indisponible (réglage ou moteur)."]] call comspec_overwatch_connect_fnc_showNotification;
    // Repli classique uniquement si explicitement réactivé
    [_fromAtak] call comspec_overwatch_connect_fnc_openClassicTablet;
};

private _ok = createDialog "COMSPEC_WebBrowser_Dialog";
if (!_ok) exitWith {
    ["COMSPEC_Warning", ["Impossible d’ouvrir la tablette Athena."]] call comspec_overwatch_connect_fnc_showNotification;
    [_fromAtak] call comspec_overwatch_connect_fnc_openClassicTablet;
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
    // Ne pas forcer PageReady : laisse onLoad retenter LoadFile / base64.
    // Si le contrôle manque encore, l’écran de repli est déjà affiché.
    if (isNull _ctrl) then {
        private _help = _display displayCtrl 9430;
        if (!isNull _help && {!(ctrlShown _help)}) then {
            _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Écran intégré indisponible</t>";
        };
    };
};

