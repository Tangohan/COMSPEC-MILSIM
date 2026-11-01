/*
    Persiste la file offline dans le profil (survit au reload mission).
*/
if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };

// Sérialisation légère : liste de hashmaps → tableau de pairs
private _serial = [];
{
    private _pairs = [];
    { _pairs pushBack [_x, _y]; } forEach _x;
    _serial pushBack _pairs;
} forEach comspec_sse_txQueue;

// File identique à celle déjà dans le profil : pas de saveProfileNamespace (écriture disque, accroc d'image toutes
// les 3 min sur chaque machine tant que la file hors ligne ne se vide pas).
if ((profileNamespace getVariable ["comspec_sse_txQueue_v1", []]) isEqualTo _serial) exitWith { true };
profileNamespace setVariable ["comspec_sse_txQueue_v1", _serial];
saveProfileNamespace;
[format ["persistQueue n=%1", count comspec_sse_txQueue], "DEBUG"] call comspec_sse_fnc_log;
true
