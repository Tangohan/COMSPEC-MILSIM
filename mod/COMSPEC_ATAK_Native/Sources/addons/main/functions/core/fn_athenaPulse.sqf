/*
    Réponses d'Athena aux envois courants (positions, télémétrie, comptes rendus), lues en mémoire dans COMSPEC Link
    sans requête réseau. COMSPEC_LastHealthOk ne bougeait qu'à la liaison : l'app Réseau affichait « Athena hors ligne ·
    réponse jamais · 0 paquet » alors que les positions arrivaient sur le site.
    Sources, de la plus précise à la plus ancienne :
    - SendQueue.Status avec compteurs (COMSPEC Link après 2.0.67) : requêtes, réponses, âge de la dernière réponse, latence,
      perte sur 15 min ;
    - GetTelemetryMetrics (lots de positions et télémétrie) : une hausse du compteur de lots réussis = Athena a répondu ;
    - SendQueue.Status ancien (2.0.65+) : une latence moyenne non nulle = des réponses dans les 15 dernières minutes.
    Met à jour COMSPEC_LastHealthOk (lu aussi par Overwatch). Appelée par le planificateur toutes les 5 s et par l'app Réseau.
    Retourne un HashMap : sent (requêtes, -1 inconnu), okAge (s, -1 inconnu), lat (ms, -1), loss (%, -1), recent (réponse < 15 min),
    idle (rien en attente ni en échec : une réponse ancienne n'est pas une panne).
*/
private _out = createHashMapFromArray [["sent", -1], ["okAge", -1], ["lat", -1], ["loss", -1], ["recent", false], ["idle", false]];
if !([] call comspec_atak_native_fnc_bridge) exitWith { _out };
private _now = diag_tickTime;
private _prev = missionNamespace getVariable ["COMSPEC_ATAK_AthenaPulse", createHashMap];
if ((count _prev) > 0 && {_now - (_prev getOrDefault ["at", -1e9]) < 2}) exitWith { _prev };

private _head = [];
if !(missionNamespace getVariable ["COMSPEC_ATAK_SendQNo", false]) then {
    private _raw = ("COMSPECExtension" callExtension ["SendQueue.Status", []]) select 0;
    if ((_raw select [0, 3]) isEqualTo "OK|") then {
        _head = (((_raw select [3]) splitString toString [10]) param [0, ""]) splitString toString [9];
    } else {
        // DLL plus ancienne : plus redemandé de la mission.
        if (_raw isEqualTo "" || {(_raw find "unknown") >= 0}) then { missionNamespace setVariable ["COMSPEC_ATAK_SendQNo", true]; };
    };
};
if ((count _head) > 16) then {
    // [11] requêtes, [12] réponses 2xx, [13] âge de la dernière réponse, [14] sa latence, [15] requêtes et [16] incidents sur 15 min.
    _out set ["sent", parseNumber (_head select 11)];
    _out set ["okAge", parseNumber (_head select 13)];
    _out set ["lat", parseNumber (_head select 14)];
    private _wReq = parseNumber (_head select 15);
    if (_wReq > 0) then { _out set ["loss", ((parseNumber (_head select 16)) / _wReq * 100) min 100]; };
    _out set ["idle", (parseNumber (_head select 16)) == 0 && {parseNumber (_head select 0) == 0} && {parseNumber (_head select 1) == 0}];
} else {
    if ((count _head) > 6 && {parseNumber (_head select 6) > 0}) then {
        _out set ["recent", true];
        _out set ["lat", parseNumber (_head select 6)];
    };
    // Lots de télémétrie (positions comprises) : « OK|mode=batch|...|latency_ms=…|ok=…|fail=…|… ».
    private _tm = ("COMSPECExtension" callExtension ["GetTelemetryMetrics", []]) select 0;
    if ((_tm select [0, 3]) isEqualTo "OK|") then {
        private _kv = createHashMap;
        { private _i = _x find "="; if (_i > 0) then { _kv set [_x select [0, _i], parseNumber (_x select [_i + 1])]; }; } forEach (_tm splitString "|");
        private _ok = _kv getOrDefault ["ok", 0];
        private _fail = _kv getOrDefault ["fail", 0];
        // Première lecture : un lot déjà réussi compte comme une réponse maintenant (lu dès l'ouverture du téléphone).
        if (_ok > (_prev getOrDefault ["tmOk", 0])) then { missionNamespace setVariable ["COMSPEC_ATAK_AthenaOkAt", _now]; };
        _out set ["tmOk", _ok];
        _out set ["tmFail", _fail];
        // Rien en attente et aucun échec depuis la dernière lecture : Athena n'a simplement rien reçu de neuf
        // (joueur immobile : positions identiques non renvoyées). La liaison reste considérée en ligne.
        _out set ["idle", _fail <= (_prev getOrDefault ["tmFail", _fail]) && {(_kv getOrDefault ["depth", 0]) == 0}];
        if ((_ok + _fail) > 0) then {
            _out set ["sent", (_out get "sent") max (_ok + _fail)];
            _out set ["loss", _fail / (_ok + _fail) * 100];
        };
        if ((_kv getOrDefault ["latency_ms", 0]) > 0) then { _out set ["lat", _kv get "latency_ms"]; };
        private _okAt = missionNamespace getVariable ["COMSPEC_ATAK_AthenaOkAt", -1];
        if (_okAt > 0) then { _out set ["okAge", _now - _okAt]; };
    };
};
private _okAge = _out get "okAge";
if (_okAge >= 0) then {
    missionNamespace setVariable ["COMSPEC_LastHealthOk", (_now - _okAge) max 0.001, false];
};
_out set ["at", _now];
missionNamespace setVariable ["COMSPEC_ATAK_AthenaPulse", _out];
_out
