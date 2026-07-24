/*
    Callback appelé par l'extension après une tentative d'envoi de position.
    Permet de tracker les succès/échecs pour le calcul du packet loss.
    
    Params (depuis l'extension):
        _success - Boolean, true si la requête a réussi
        _requestId - String, ID de la requête
        _httpCode - Number, code HTTP (200, 503, etc.)
*/

params [["_success", false], ["_requestId", ""], ["_httpCode", 0]];

if (_requestId isEqualTo "") exitWith {};

// Si succès, enregistrer la réception
if (_success && _httpCode >= 200 && _httpCode < 300) then {
    [_requestId] call comspec_overwatch_connect_fnc_recordPacketReceived;
} else {
    // Échec - le paquet est considéré comme perdu
    // Rien à faire, il ne sera jamais marqué comme reçu
};

// Log pour debug (désactiver en prod)
if (missionNamespace getVariable ["COMSPEC_Debug_PacketLoss", false]) then {
    diag_log format ["[COMSPEC] Position update callback: success=%1, requestId=%2, httpCode=%3", _success, _requestId, _httpCode];
};
