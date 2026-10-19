/*
    Inscrit une photo de preuve sur les entités cadrées (app Photo de l'ATAK, ou bouton de l'app SSE terrain).
    [_framed, _zoom] call comspec_sse_fnc_evidencePhoto
      _framed : [[entité, distance]...]
    Section « photos » (fusion serveur), ligne PHOTOGRAPHIE au journal de la pièce, marque « en place »
    si la pièce n'est pas encore scellée.
*/
params [["_framed", [], [[]]], ["_zoom", 1, [0]]];
if (_framed isEqualTo []) exitWith { false };
private _shot = (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamShots", 0]) + 1;
private _labels = [];
{
    _x params ["_e", "_d"];
    private _custody = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
    private _pid = _e getVariable ["COMSPEC_SSE_PieceId", ""];
    private _moved = _pid isNotEqualTo "" && {((_custody getOrDefault [_pid, createHashMap]) getOrDefault ["seal", ""]) isNotEqualTo ""};
    private _photo = createHashMapFromArray [
        ["id", format ["PH-%1-%2-%3", getPlayerUID player, _shot, netId _e]],
        ["by", name player],
        ["at", serverTime],
        ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
        ["grid", mapGridPosition _e],
        ["distance", round _d],
        ["zoom", _zoom],
        ["inSitu", !_moved],
        ["source", "ATAK"]
    ];
    ["append", _e, ["photos", [_photo], "id"]] call comspec_sse_fnc_recordOp;
    if (!_moved && {!(_e getVariable ["comspec_sse_photoInSitu", false])}) then {
        _e setVariable ["comspec_sse_photoInSitu", true, true];
    };
    if (_pid isNotEqualTo "") then {
        ["event", [_e, "PHOTOGRAPHIE", player, format ["cliché ATAK n°%1 à %2 m", _shot, round _d]]] call comspec_sse_fnc_custody;
    };
    _labels pushBack ([_e] call comspec_sse_fnc_makeEvidenceLabel);
} forEach _framed;

[format ["Photo de preuve : %1 pièce(s) cadrée(s)", count _framed]] call comspec_sse_fnc_log;
["SUCCESS", format ["Photo de preuve : %1" call comspec_sse_fnc_tr, _labels joinString ", "], 4, 25, "SSEFIELD"] call comspec_atak_native_fnc_notify;
true
