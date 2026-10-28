/*
    Met à jour l'état de découverte d'une donnée renseignement.
    [_intelId, _state, _datum] call comspec_sse_fnc_setDiscoveryState
    _datum (optionnel) : sa durée de validité (ttl) fixe l'échéance à la première découverte.
    Le registre est tenu par le serveur et diffusé (AAR, fusion et JIP cohérents).
*/
params [
    ["_intelId", "", [""]],
    ["_state", "KNOWN", [""]],
    ["_datum", createHashMap, [createHashMap]]
];
if (_intelId == "") exitWith { false };
private _ttl = _datum getOrDefault ["ttl", -1];

if (!isServer) exitWith {
    // Copie locale immédiate pour l'affichage, le serveur diffuse la version de référence.
    if (isNil "comspec_sse_discoveryStates") then { comspec_sse_discoveryStates = createHashMap; };
    private _loc = comspec_sse_discoveryStates getOrDefault [_intelId, createHashMap];
    _loc set ["discoveryState", toUpper _state];
    comspec_sse_discoveryStates set [_intelId, _loc];
    ["comspec_sse_discoveryState", [_intelId, _state, _ttl]] call CBA_fnc_serverEvent;
    true
};

if (isNil "comspec_sse_discoveryStates") then { comspec_sse_discoveryStates = createHashMap; };
private _rec = comspec_sse_discoveryStates getOrDefault [_intelId, createHashMap];
if !(_rec isEqualType createHashMap) then { _rec = createHashMap; };
// Une donnée expirée reste DISPROVEN.
if (_rec getOrDefault ["expired", false]) exitWith { false };
_rec set ["discoveryState", toUpper _state];
_rec set ["updatedAt", serverTime];
if (_ttl > 0 && {(_rec getOrDefault ["expiresAt", -1]) < 0}) then {
    _rec set ["expiresAt", serverTime + _ttl];
};
comspec_sse_discoveryStates set [_intelId, _rec];

// Diffusion groupée : plusieurs découvertes dans la même seconde = un seul envoi.
if !(missionNamespace getVariable ["comspec_sse_discoveryPublishPending", false]) then {
    comspec_sse_discoveryPublishPending = true;
    [{
        comspec_sse_discoveryPublishPending = false;
        publicVariable "comspec_sse_discoveryStates";
    }, [], 1] call CBA_fnc_waitAndExecute;
};
true
