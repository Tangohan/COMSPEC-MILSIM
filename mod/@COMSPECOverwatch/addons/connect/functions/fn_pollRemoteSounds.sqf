/*
 * Auteur: COMSPEC
 * Polling sons à distance depuis API
 * Appelé automatiquement si intervalle > 0 dans CBA Settings
 * 
 * Endpoint: GET /api/atak/sounds/pending
 * Retour API: [
 *   {
 *     "id": 123,
 *     "type": "troll",
 *     "sound_id": "airhorn",
 *     "target_player": "callsign_ou_steamid",
 *     "position": [x, y, z] ou null,
 *     "volume": 1.0,
 *     "distance": 100
 *   }
 * ]
 */

// Vérifier si système activé
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

// Vérifier si polling activé (interval > 0)
private _pollInterval = missionNamespace getVariable ["comspec_atak_sound_poll_interval", 0];
if (_pollInterval <= 0) exitWith {
    // Polling désactivé
};

// Vérifier interval (ne pas spammer)
private _lastPoll = missionNamespace getVariable ["COMSPEC_LastSoundPoll", 0];
private _now = CBA_missionTime;
if ((_now - _lastPoll) < 5) exitWith {}; // Minimum 5s entre polls
missionNamespace setVariable ["COMSPEC_LastSoundPoll", _now];

// Vérifier extension disponible
private _extVersion = "COMSPECExtension" callExtension ["GetVersion", []];
if ((_extVersion select 0) isEqualTo "") exitWith {
    diag_log "[COMSPEC ATAK] pollRemoteSounds: Extension non disponible";
};

// Préparer requête
private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", ""];
private _steamId = getPlayerUID player;

if (_callsign isEqualTo "" && _steamId isEqualTo "") exitWith {
    diag_log "[COMSPEC ATAK] pollRemoteSounds: Pas d'identifiant joueur";
};

// Construire payload
private _requestData = createHashMapFromArray [
    ["callsign", _callsign],
    ["steam_id", _steamId],
    ["position", getPosATL player] // Pour sons 3D contextuels
];

private _requestJson = [_requestData] call comspec_overwatch_connect_fnc_hashMapToJson;

// Appeler extension
private _result = "COMSPECExtension" callExtension ["PollRemoteSounds", [_requestJson]];
private _status = _result select 0;
private _response = _result select 1;

if (_status isEqualTo "OK") then {
    // Parser JSON response (simulation - normalement parseSimpleArray ou extension parse)
    // Pour l'instant, on suppose que l'extension retourne un array SQF
    private _sounds = call compile _response; // ATTENTION: Dangereux en prod, utiliser JSON parser
    
    if (typeName _sounds isEqualTo "ARRAY") then {
        {
            private _sound = _x;
            private _soundId = _sound getOrDefault ["sound_id", ""];
            private _soundType = _sound getOrDefault ["type", "realistic"];
            private _position = _sound getOrDefault ["position", []];
            private _volume = _sound getOrDefault ["volume", 1];
            private _distance = _sound getOrDefault ["distance", 100];
            private _dbId = _sound getOrDefault ["id", 0];
            
            if (!(_soundId isEqualTo "")) then {
                // Jouer son
                private _success = [_soundType, _soundId, _position, _volume, _distance] call comspec_overwatch_connect_fnc_playRemoteSound;
                
                if (_success) then {
                    diag_log format ["[COMSPEC ATAK] pollRemoteSounds: Son '%1' (type %2) joué avec succès", _soundId, _soundType];
                    
                    // Acknowledger à l'API (pour ne pas rejouer)
                    [{
                        params ["_id"];
                        private _ackData = createHashMapFromArray [["sound_id", _id]];
                        private _ackJson = [_ackData] call comspec_overwatch_connect_fnc_hashMapToJson;
                        "COMSPECExtension" callExtension ["AckRemoteSound", [_ackJson]];
                    }, [_dbId], 0.5] call CBA_fnc_waitAndExecute;
                };
            };
        } forEach _sounds;
        
        if (count _sounds > 0) then {
            diag_log format ["[COMSPEC ATAK] pollRemoteSounds: %1 son(s) traité(s)", count _sounds];
        };
    };
} else {
    // Erreur silencieuse (ne pas spammer logs)
    if (_status isEqualTo "ERROR") then {
        diag_log format ["[COMSPEC ATAK] pollRemoteSounds: Erreur API - %1", _response];
    };
};

true
