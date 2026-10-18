/*
    Envoie la fiche opérateur observée (register au premier passage, sync ensuite).
    Ne doit PAS être appelé à chaque tick de position.
    Rien ne part si l'empreinte (identité, équipement, versions) n'a pas changé, sauf à l'enregistrement, au
    forçage manuel et au rappel « heartbeat » (toutes les 15 min, fn_operatorProfileTick) qui garde à jour la
    comparaison faite par le site (écarts, mise à jour demandée). Catalogue d'objets et liste des mods : seulement
    à l'enregistrement et quand ils changent (fn_buildOperatorProfile).
    Un refus durable du site (HTTP 4xx, compte bloqué…) attend 1 min puis de plus en plus longtemps (10 min au plus)
    avant de retenter : avant, la même fiche repartait à chaque contrôle (8 s).
    Params: [_event, _reason, _force]
    Retour: HashMap résultat (ok, pending, …) ou false
*/
params [
    ["_event", "sync", [""]],
    ["_reason", "profile_sync", [""]],
    ["_force", false, [true]]
];

if (!hasInterface) exitWith { false };
// Hors spawn, l'envoi (callExtension OperatorRegister / OperatorSync) figeait le jeu le temps de la requête :
// relancé dans un spawn, où extAwait attend la réponse de la DLL sans bloquer. Un seul envoi à la fois.
if (!canSuspend) exitWith {
    if (missionNamespace getVariable ["COMSPEC_OperatorSyncBusy", false]) exitWith { false };
    missionNamespace setVariable ["COMSPEC_OperatorSyncBusy", true, false];
    _this spawn {
        _this call comspec_overwatch_connect_fnc_syncOperatorProfile;
        missionNamespace setVariable ["COMSPEC_OperatorSyncBusy", false, false];
    };
    true
};
if (isNull player || {!alive player}) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith { false };
if !([] call comspec_overwatch_connect_fnc_isReady) exitWith { false };

// Pendant / juste après handshake : ignorer _force pour ne pas bypass le backoff 401.
private _forceEff = _force;
private _readyAt = missionNamespace getVariable ["COMSPEC_AthenaReadyAt", -1e9];
if (!(_readyAt isEqualType 0)) then { _readyAt = -1e9; };
if ((diag_tickTime - _readyAt) < 12) then { _forceEff = false; };

// Sortie au niveau du script : un exitWith dans le bloc « then » ne quittait que ce bloc, le délai d'attente
// n'était jamais appliqué et une fiche refusée ou « pending » repartait à chaque contrôle (toutes les 8 s).
if (!_forceEff && {diag_tickTime < (missionNamespace getVariable ["COMSPEC_OperatorProfileBackoffUntil", 0])}) exitWith { false };

private _payload = [player, _event, _reason] call comspec_overwatch_connect_fnc_buildOperatorProfile;
private _fp = _payload getOrDefault ["fingerprint", ""];
private _lastFp = missionNamespace getVariable ["COMSPEC_OperatorFingerprint", ""];
if (!_forceEff && {(toLower _event) isNotEqualTo "register"} && {_reason isNotEqualTo "heartbeat"} && {_fp isEqualTo _lastFp} && {_fp isNotEqualTo ""}) exitWith {
    false
};
// Champs de l'empreinte qui ont changé (journal : savoir pourquoi la fiche repart).
private _changed = [];
if (_lastFp isNotEqualTo "" && {_fp isNotEqualTo _lastFp}) then {
    private _names = ["compte Steam", "indicatif", "visage", "groupe sanguin", "sexe", "nom", "tenue", "gilet", "sac", "casque",
        "arme principale", "JVN", "version Overwatch", "version ATAK", "version ACE", "groupe", "rôle", "grade"];
    private _a = [_fp, "|"] call comspec_overwatch_connect_fnc_splitKeepEmpty;
    private _b = [_lastFp, "|"] call comspec_overwatch_connect_fnc_splitKeepEmpty;
    { if ((_a param [_forEachIndex, ""]) isNotEqualTo (_b param [_forEachIndex, ""])) then { _changed pushBack _x; }; } forEach _names;
};
private _catKey = _payload getOrDefault ["item_catalog_key", ""];
private _modsKey = _payload getOrDefault ["mods_key", ""];
_payload deleteAt "item_catalog_key";
_payload deleteAt "mods_key";

private _identity = _payload getOrDefault ["identity", createHashMap];
private _steam = _identity getOrDefault ["steam_uid", ""];
if (_steam isEqualTo "") then { _steam = _payload getOrDefault ["steam_id", ""]; };
if ((count _steam) < 15) exitWith {
    ["WARN", "OperatorProfile", "Steam UID absent — fiche non transmise"] call comspec_overwatch_connect_fnc_log;
    false
};

private _fncOmitLoadout = {
    params ["_map"];
    private _eq = _map getOrDefault ["equipment", createHashMap];
    _eq set ["loadout", []];
    _eq set ["loadout_omitted", true];
    _map set ["equipment", _eq];
    _map set ["loadout", []];
    _map
};

private _equipment = _payload getOrDefault ["equipment", createHashMap];
private _loadout = _payload getOrDefault ["loadout", _equipment getOrDefault ["loadout", []]];
private _loadoutJson = [_loadout] call comspec_overwatch_connect_fnc_jsonValue;
if ((count _loadoutJson) > 14000) then {
    _payload = [_payload] call _fncOmitLoadout;
};

private _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
if ((count _json) > 28000) then {
    private _eq = _payload getOrDefault ["equipment", createHashMap];
    _eq set ["magazines", []];
    _payload set ["equipment", _eq];
    _payload = [_payload] call _fncOmitLoadout;
    _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
};
// Encore trop lourd : la liste des mods, puis le catalogue des objets, repartent au prochain envoi.
if ((count _json) > 28000) then {
    _payload deleteAt "mods";
    _modsKey = "";
    _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
};
if ((count _json) > 28000) then {
    _payload deleteAt "item_catalog";
    _catKey = "";
    _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
};
private _what = switch (true) do {
    case ((toLower _event) isEqualTo "register"): { "première connexion" };
    case (_reason isEqualTo "heartbeat"): { "rappel 15 min" };
    case (_reason isEqualTo "manual"): { "resynch manuelle" };
    case (_changed isNotEqualTo []): { "changé : " + (_changed joinString ", ") };
    default { _reason };
};
private _extras = [];
if ("item_catalog" in _payload) then { _extras pushBack "catalogue d'objets"; };
if ("mods" in _payload) then { _extras pushBack "liste des mods"; };
if (_extras isNotEqualTo []) then { _what = _what + " · avec " + (_extras joinString " et "); };

private _cmd = if ((toLower _event) isEqualTo "register") then { "OperatorRegister" } else { "OperatorSync" };
private _parsed = [
    _cmd,
    [_json, _steam],
    format ["fiche opérateur (%1, %2 Ko)", _what, (round ((count _json) / 100)) / 10],
    true,
    true,
    "link",
    false
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "_status", "_detail"];

private _result = [_ok, _status, _detail] call comspec_overwatch_connect_fnc_applyOperatorProfileResponse;

if (_ok && {!(_result getOrDefault ["pending", false])}) then {
    missionNamespace setVariable ["COMSPEC_OperatorFingerprint", _fp, false];
    missionNamespace setVariable ["COMSPEC_OperatorRegistered", true, false];
    missionNamespace setVariable ["COMSPEC_OperatorProfileFailCount", 0, false];
    if (_modsKey isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_OpModsSentKey", _modsKey, false]; };
    if (_catKey isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_OpCatalogSentKey", _catKey, false]; };
    // Lu par l'app Réseau du téléphone (Synchronisation) : dernier envoi, motif, taille, nombre d'envois.
    missionNamespace setVariable ["COMSPEC_OperatorLastSentAt", diag_tickTime, false];
    missionNamespace setVariable ["COMSPEC_OperatorLastWhat", _what, false];
    missionNamespace setVariable ["COMSPEC_OperatorLastBytes", count _json, false];
    missionNamespace setVariable ["COMSPEC_OperatorSentCount", (missionNamespace getVariable ["COMSPEC_OperatorSentCount", 0]) + 1, false];
};
if (_result getOrDefault ["pending", false]) then {
    missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", diag_tickTime + 300, false];
    missionNamespace setVariable ["COMSPEC_OperatorRegistered", true, false];
} else {
    if (!_ok) then {
        private _blob = toUpper (format ["%1 %2", _status, _detail]);
        private _http503 = (_blob find "503") >= 0 || {(_blob find "INDISPONIBLE") >= 0};
        private _authFail = (_blob find "UNAUTHORIZED") >= 0
            || {(_blob find "401") >= 0}
            || {(_blob find "403") >= 0}
            || {(_blob find "FORBIDDEN") >= 0};
        private _transient = _http503
            || {_authFail}
            || {(toUpper _status) in ["TIMEOUT", "NETWORK_ERROR"]};
        if (_transient) then {
            private _n = (missionNamespace getVariable ["COMSPEC_OperatorProfileFailCount", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_OperatorProfileFailCount", _n, false];
            private _delay = 30;
            if (_authFail) then {
                // 401 au boot : ne pas marteler le thread jeu (HTTP sync).
                _delay = (20 * ((2 ^ ((_n - 1) min 4)) min 8)) min 300;
            } else {
                if (_http503) then {
                    private _exp = (2 ^ ((_n - 1) min 5));
                    _delay = ((8 * _exp) min 300);
                };
            };
            missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", diag_tickTime + _delay, false];
        } else {
            // Refus durable (fiche refusée, compte bloqué, migration en attente…) : 1, 2, 4… 10 min au plus.
            private _n = (missionNamespace getVariable ["COMSPEC_OperatorProfileFailCount", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_OperatorProfileFailCount", _n, false];
            private _delay = (60 * (2 ^ ((_n - 1) min 4))) min 600;
            missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", diag_tickTime + _delay, false];
            ["WARN", "OperatorProfile", format ["Fiche refusée par Athena (%1) : nouvel essai dans %2 min", _detail, round (_delay / 60)]] call comspec_overwatch_connect_fnc_log;
        };
    };
};

private _bt = (_payload get "medical") getOrDefault ["blood_type", ""];
if (_bt isNotEqualTo "") then {
    ["COMSPECExtension" callExtension ["SetBloodType", [_bt]]] call comspec_overwatch_connect_fnc_extResult;
};

_result
