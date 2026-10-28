/*
    Applique au lancement les réglages Eden « COMSPEC SSE — Extraction DL-7 » (serveur).
    [] call comspec_sse_fnc_dl7ApplyEden
*/
if (!isServer) exitWith {};
private _keys = ["Content", "Files", "Name", "Theme", "Banner", "Password", "Hint", "Attempts", "OnFail", "Crack", "CrackTime", "Duration", "Port", "Alarm", "OneShot"];
private _n = 0;
{
    if (_x getVariable ["COMSPEC_SSE_Dl7_Enabled", false] && {!(_x getVariable ["COMSPEC_SSE_Dl7Ready", false])}) then {
        private _o = _x;
        private _cfg = createHashMap;
        {
            private _v = _o getVariable format ["COMSPEC_SSE_Dl7_%1", _x];
            if (!isNil "_v") then { _cfg set [(toLower (_x select [0, 1])) + (_x select [1]), _v]; };
        } forEach _keys;
        if ((_cfg getOrDefault ["password", ""]) isEqualTo "") then { _cfg deleteAt "password"; };
        [_o, _cfg] call comspec_sse_fnc_dl7Apply;
        _n = _n + 1;
    };
} forEach (allMissionObjects "All");
if (_n > 0) then { diag_log text format ["[COMSPEC SSE] DL-7 : %1 support(s) configuré(s) depuis Eden", _n]; };
