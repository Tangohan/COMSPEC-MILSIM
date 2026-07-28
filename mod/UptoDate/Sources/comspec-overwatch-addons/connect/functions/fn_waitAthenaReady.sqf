/*
    Author: COMSPEC
    Description:
        Handshake Athena avant la sync lourde (inspiré Athena Remastered init.sqf).
        Attend que l’extension réponde et que le portail soit joignable.
        Messages joueur : silence pendant les essais (journal seulement) ;
        1 message discret max en cas d’échec final — pas de défilé d’annonces.
        Non bloquant pour le scheduler Arma (uiSleep dans un spawn).

    Retour: true si prêt, false si abandon après tentatives.
*/
if (!hasInterface) exitWith { false };

private _maxAttempts = 45; // ~90 s à 2 s d’intervalle
private _attempts = 0;
private _ready = false;

missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "Vérification de la liaison…", false];
// Coupe systemChat / bandeaux pendant le handshake (connect + callback inclus)
missionNamespace setVariable ["COMSPEC_HandshakeQuiet", true, false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

while {!_ready && {_attempts < _maxAttempts}} do {
    _attempts = _attempts + 1;

    // 1) Extension chargée ?
    private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;
    _extStatus params ["_extOk", "_extCode", "_ping"];

    if (!_extOk) then {
        if (_attempts == 1 || {_attempts == 5} || {(_attempts mod 10) == 0}) then {
            [format ["[Athena] Handshake — module non prêt (essai %1).", _attempts]] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
        uiSleep 2;
    } else {
        // 2) Tentative de connexion portail (Connect + whoami)
        [] call comspec_overwatch_connect_fnc_connect;

        private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
        private _healthOk = (missionNamespace getVariable ["COMSPEC_LastHealthOk", -1e9]) > (diag_tickTime - 30);

        if (_state isEqualTo "linked" || {_healthOk && {_state isEqualTo "offline"}}) then {
            // « offline » avec whoami OK = portail joignable mais compte non lié — on laisse démarrer
            // la sync légère ; le rappel de liaison gère le reste.
            _ready = true;
        } else {
            if (_attempts == 1 || {_attempts == 5} || {(_attempts mod 10) == 0}) then {
                private _stateLog = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
                [format ["[Athena] Handshake — portail non prêt (essai %1, état %2).", _attempts, _stateLog]] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
            uiSleep 2;
        };
    };
};

missionNamespace setVariable ["COMSPEC_HandshakeQuiet", false, false];

if (_ready) then {
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
    // Succès : journal seulement — pas de bandeau / systemChat au démarrage
    ["INFO", "Athena", format ["Handshake OK après %1 essai(s)", _attempts]] call comspec_overwatch_connect_fnc_log;
    [format ["[Athena] Handshake OK après %1 essai(s).", _attempts]] call comspec_overwatch_connect_fnc_appendLinkLog;
    ["COMSPEC_AthenaLinkChanged", ["ready"]] call CBA_fnc_localEvent;
    true
} else {
    // Mode dégradé : on démarre quand même pour ne pas bloquer la mission
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Athena injoignable", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    ["WARN", "Athena", format ["Handshake abandonné après %1 essais — mode dégradé", _attempts]] call comspec_overwatch_connect_fnc_log;
    // Un seul message joueur si le handshake échoue vraiment
    ["Athena reste injoignable. La synchronisation reprendra dès que possible. Utilisez « Vérifier la liaison » dans le menu.", "link", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["[Athena] Handshake abandonné — démarrage en mode dégradé."] call comspec_overwatch_connect_fnc_appendLinkLog;
    ["COMSPEC_AthenaLinkChanged", ["degraded"]] call CBA_fnc_localEvent;
    false
};
