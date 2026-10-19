/*
    Marque les intel TIME_SENSITIVITY expirées (serveur, horloge serverTime).
*/
if (!isServer) exitWith {};
if (isNil "comspec_sse_discoveryStates") exitWith {};
private _changed = false;
{
    private _rec = comspec_sse_discoveryStates get _x;
    if (_rec isEqualType createHashMap && {!(_rec getOrDefault ["expired", false])}) then {
        private _exp = _rec getOrDefault ["expiresAt", -1];
        if (_exp > 0 && {serverTime > _exp}) then {
            _rec set ["discoveryState", "DISPROVEN"];
            _rec set ["expired", true];
            comspec_sse_discoveryStates set [_x, _rec];
            _changed = true;
            ["comspec_sse_intelExpired", [_x]] call CBA_fnc_globalEvent;
        };
    };
} forEach (keys comspec_sse_discoveryStates);
if (_changed) then { publicVariable "comspec_sse_discoveryStates"; };
true
