/*
    Ouvre la carte / portail Athena depuis le bouton « Carte Athena ».
    N’injecte jamais la clé d’accès dans l’URL.

    Priorité 1 : openURL → navigateur Windows (fiable sur Stable)
    Priorité 2 : ctrlSetURL sur le WebBrowser embarqué (allowExternalURL,
                 souvent limité à la branche Development)

    Ne pas laisser mode=athena + ctrlSetURL en échec sur Stable :
    sinon l’écran local reste vide (« ne fait rien »).
*/
params [["_ctrl", controlNull]];

if (!hasInterface) exitWith {};

if (isNull _ctrl) then {
    private _display = findDisplay 9974;
    if (!isNull _display) then { _ctrl = _display displayCtrl 9401; };
};

// Anti-spam clics
private _now = diag_tickTime;
private _lastOpen = missionNamespace getVariable ["COMSPEC_WebBrowser_AthenaOpenAt", -1e9];
if ((_now - _lastOpen) < 2.5) exitWith {};
missionNamespace setVariable ["COMSPEC_WebBrowser_AthenaOpenAt", _now];

private _url = trim (missionNamespace getVariable ["comspec_overwatch_api_url", ""]);
if (_url isEqualTo "") then {
    _url = trim (profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]);
};
if (_url isEqualTo "") then {
    _url = "https://athena.ttrd.fr/public";
};

while {(_url select [(count _url) - 1, 1]) isEqualTo "/"} do {
    _url = _url select [0, (count _url) - 1];
};

private _atakUrl = _url + "/atak";

if ((count _atakUrl) < 16) exitWith {
    private _displayErr = findDisplay 9974;
    if (!isNull _displayErr) then {
        private _hintErr = _displayErr displayCtrl 9403;
        if (!isNull _hintErr) then {
            _hintErr ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#ff8a7a'>Adresse portail invalide</t>";
        };
    };
    private _lastErr = missionNamespace getVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", -1e9];
    if ((_now - _lastErr) >= 12) then {
        missionNamespace setVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", _now];
        ["COMSPEC_Warn", ["Impossible d’ouvrir la carte : adresse du portail Athena manquante ou invalide."]] call comspec_overwatch_connect_fnc_showNotification;
    };
};

// Alignement anti-spam avec le bouton « Navigateur système »
missionNamespace setVariable ["COMSPEC_WebBrowser_SystemOpenAt", _now];

// ——— Priorité 1 : navigateur système (Windows) ———
openURL _atakUrl;

// Sur Stable, l’embarqué ne charge pas /atak : rester en mode local (radar tablette).
private _branch = "";
private _pv = productVersion;
if (_pv isEqualType [] && {count _pv > 4}) then {
    _branch = str (_pv select 4);
};
private _isDev = (_branch find "Development") >= 0;

if (!_isDev) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];
};

private _display = findDisplay 9974;
if (!isNull _display) then {
    private _hint = _display displayCtrl 9403;
    if (!isNull _hint) then {
        private _hintTxt = if (_isDev) then {
            "<t align='right' size='0.5' color='#7dffb3'>Ouverture du portail…</t>"
        } else {
            "<t align='right' size='0.5' color='#7dffb3'>Carte ouverte dans le navigateur PC</t>"
        };
        _hint ctrlSetStructuredText parseText _hintTxt;
    };
};

// ——— Priorité 2 : embarqué (Dev / allowExternalURL réel) ———
if (_isDev && {!isNull _ctrl}) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "athena"];
    _ctrl ctrlShow true;
    _ctrl ctrlEnable true;
    ctrlSetFocus _ctrl;
    _ctrl ctrlSetURL _atakUrl;
} else {
    // Stable : réinjecter le boot local si le contrôle est encore là
    if (!isNull _ctrl) then {
        missionNamespace setVariable ["COMSPEC_WebBrowser_Mode", "local"];
        [_ctrl] call comspec_overwatch_connect_fnc_webBrowserPageLoaded;
    };
};

private _lastNotify = missionNamespace getVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", -1e9];
if ((_now - _lastNotify) >= 12) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", _now];
    private _msg = if (_isDev) then {
        "Ouverture du portail Athena… Navigateur PC, et éventuelle fenêtre Autoriser / Accepter dans Arma."
    } else {
        "Carte Athena ouverte dans le navigateur de votre PC. La tablette reste sur le suivi local."
    };
    ["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
};
