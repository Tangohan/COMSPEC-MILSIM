/*
    Marque les intel TIME_SENSITIVITY expirés / critiques.
*/
if (isNil "comspec_sse_discoveryStates") exitWith {};
{
    private _rec = comspec_sse_discoveryStates get _x;
    if (_rec isEqualType createHashMap) then {
        private _exp = _rec getOrDefault ["expiresAt", -1];
        if (_exp > 0 && {time > _exp}) then {
            _rec set ["discoveryState", "DISPROVEN"];
            _rec set ["expired", true];
            comspec_sse_discoveryStates set [_x, _rec];
        };
    };
} forEach (keys comspec_sse_discoveryStates);
true
