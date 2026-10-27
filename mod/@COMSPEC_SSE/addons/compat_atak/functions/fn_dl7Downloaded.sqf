/*
    Fin d'un téléchargement DL-7 sur un support SSE (serveur, événement comspec_atak_native_dataDownloaded).
    L'extraction verse ses résultats au dossier :
      - section « digitalExploitation » (qui, quand, combien de fichiers) ;
      - exploitation poussée jusqu'au niveau DETAILED (renseignements, données supprimées récupérées) ;
      - état EXPLOITED, ligne EXTRACTION DL-7 au journal de la pièce, liens révélés (organigramme) ;
      - compte rendu dans le téléphone de l'opérateur (app SSE terrain).
    [_obj, _who, _names] call comspec_sse_fnc_dl7Downloaded
*/
params [["_obj", objNull, [objNull]], ["_who", objNull, [objNull]], ["_names", [], [[]]]];
if (!isServer || {isNull _obj}) exitWith { false };
if !(_obj getVariable ["comspec_sse_enabled", false]) exitWith { false };

["merge", _obj, ["digitalExploitation", createHashMapFromArray [
    ["by", if (isNull _who) then { "" } else { name _who }],
    ["byUid", if (isNull _who) then { "" } else { getPlayerUID _who }],
    ["at", serverTime],
    ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    ["files", count _names],
    ["tool", "DL-7"]
]]] call comspec_sse_fnc_recordOp;

// L'extraction complète vaut une exploitation détaillée : on monte jusqu'à DETAILED.
private _order = ["NONE", "TACTICAL", "FIELD", "DETAILED", "FUSION"];
private _lines = [];
// Le téléchargement DL-7 terminé vaut accès : un support LOCKED / ENCRYPTED passe en PARTIAL_ACCESS, sinon
// advanceExploitation refusait DETAILED (compétence opérateur par défaut 1) et l'extraction restait bloquée à FIELD.
[_obj] call comspec_sse_fnc_ensureGenerated;
if (!isNil "comspec_sse_fnc_attachIntelLayers") then { [_obj] call comspec_sse_fnc_attachIntelLayers; };
private _secA = [_obj, "sections"] call comspec_sse_fnc_getSection;
if (!isNil "_secA" && {_secA isEqualType createHashMap}) then {
    private _acc = _secA getOrDefault ["accessState", createHashMap];
    if (_acc isEqualType createHashMap && {(_acc getOrDefault ["state", "OPEN"]) in ["LOCKED", "ENCRYPTED"]}) then {
        _acc set ["state", "PARTIAL_ACCESS"];
        _secA set ["accessState", _acc];
        [_obj, "sections", _secA, true] call comspec_sse_fnc_setSection;
    };
};
private _guard = 0;
while { _guard < 3 && {(_order find ([_obj] call comspec_sse_fnc_getExploitationLevel)) < (_order find "DETAILED")} } do {
    private _r = [_obj, _who] call comspec_sse_fnc_advanceExploitation;
    // Un refus (blocked) n'est pas un renseignement : ni compté ni répété.
    if (_r getOrDefault ["blocked", false]) exitWith {};
    _lines append (_r getOrDefault ["lines", []]);
    _guard = _guard + 1;
};
[_obj, "EXPLOITED", true] call comspec_sse_fnc_setState;

if (!isNil "comspec_sse_fnc_custody" && {(["piece", [_obj]] call comspec_sse_fnc_custody) isNotEqualTo ""}) then {
    ["event", [_obj, "EXTRACTION DL-7", _who, format ["%1 fichier(s) téléchargé(s)", count _names]]] call comspec_sse_fnc_custody;
};

if (!isNull _who && {isPlayer _who}) then {
    private _msg = format ["Extraction versée au dossier : %1 fichier(s), %2 renseignement(s) nouveau(x)" call comspec_sse_fnc_tr, count _names, count _lines];
    ["SUCCESS", _msg, 6, 40, "SSEFIELD"] remoteExecCall ["comspec_atak_native_fnc_notify", owner _who];
};
[format ["DL-7 : %1 exploité par %2 (%3 fichiers)", _obj, _who, count _names]] call comspec_sse_fnc_log;
true
