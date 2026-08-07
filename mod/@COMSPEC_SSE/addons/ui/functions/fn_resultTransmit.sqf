private _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
if (count _fog == 0) exitWith { hint "Aucun résultat à transmettre."; false };

if (!isNil "comspec_sse_fnc_submitDigitalAcquisition") then {
    [objNull, _fog] call comspec_sse_fnc_submitDigitalAcquisition;
} else {
    if (!isNil "comspec_sse_fnc_submitRecord") then {
        [
            _fog getOrDefault ["uid", "SSE-UNKNOWN"],
            "digital",
            _fog getOrDefault ["type", "unknown"],
            name player,
            getPosATL player,
            _fog getOrDefault ["quality", 0],
            _fog
        ] call comspec_sse_fnc_submitRecord;
    } else {
        hint "Couche réseau indisponible — résultat LOCAL.";
    };
};
true
