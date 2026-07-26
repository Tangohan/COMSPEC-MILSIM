/*
    Retourne les informations sur l'état de déconnexion simulée.
    
    Returns:
        HashMap avec les clés:
            is_disconnected - Boolean
            disconnect_until - Temps absolu de fin
            remaining_seconds - Secondes restantes
            disconnect_count - Nombre total de déconnexions
*/

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _result = createHashMap;

private _isDisconnected = _state getOrDefault ["is_disconnected", false];
private _until = _state getOrDefault ["disconnect_until", -1];
private _remaining = 0;

if (_isDisconnected && _until > 0) then {
    _remaining = (_until - time) max 0;
};

_result set ["is_disconnected", _isDisconnected];
_result set ["disconnect_until", _until];
_result set ["remaining_seconds", floor _remaining];
_result set ["disconnect_count", _state getOrDefault ["disconnect_count", 0]];
_result set ["next_disconnect_at", _state getOrDefault ["next_disconnect_at", -1]];

_result
