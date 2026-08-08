/*
    Crée un modèle SSE utilisateur.
    [_name, _overrides, _author] call comspec_sse_fnc_createModel

    _overrides (HashMap ou pairs) — champs optionnels :
      profile, complexity, region, theme,
      namePool, aliasPool, contactPool, smsTemplates, documentTemplates,
      codewords, locations, forcedIdentity, forcedPhone,
      noiseProbability, falseLeadProbability,
      includeBiometrics, includePhone, includeDocuments, includeComputer,
      networkSize, tags, notes
*/
params [
    ["_name", "Nouveau modèle", [""]],
    ["_overrides", [], [[], createHashMap]],
    ["_author", "USER", [""]]
];

private _ov = if (_overrides isEqualType createHashMap) then {
    private _norm = createHashMap;
    { _norm set [toLower _x, _overrides get _x]; } forEach (keys _overrides);
    _norm
} else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [toLower _k, _v]; } forEach _overrides;
    _h
};

private _idBase = toLower (([_name, " ", "_"] call BIS_fnc_replaceString) + "_" + str (round time) + "_" + str (floor random 9999));
_idBase = [_idBase, "-", "_"] call BIS_fnc_replaceString;
private _id = format ["mdl_%1", _idBase];

private _model = createHashMapFromArray [
    ["id", _id],
    ["name", _name],
    ["author", _author],
    ["createdAt", time],
    ["updatedAt", time],
    ["version", 1],
    ["profile", toUpper (_ov getOrDefault ["profile", "INSURGENT"])],
    ["complexity", toUpper (_ov getOrDefault ["complexity", "DETAILED"])],
    ["region", toUpper (_ov getOrDefault ["region", "IRAQ"])],
    ["theme", _ov getOrDefault ["theme", "RANDOM"]],
    ["namePool", _ov getOrDefault ["namepool", []]],
    ["aliasPool", _ov getOrDefault ["aliaspool", []]],
    ["contactPool", _ov getOrDefault ["contactpool", []]],
    ["smsTemplates", _ov getOrDefault ["smstemplates", []]],
    ["documentTemplates", _ov getOrDefault ["documenttemplates", []]],
    ["codewords", _ov getOrDefault ["codewords", []]],
    ["locations", _ov getOrDefault ["locations", []]],
    ["forcedIdentity", _ov getOrDefault ["forcedidentity", createHashMap]],
    ["forcedPhone", _ov getOrDefault ["forcedphone", createHashMap]],
    ["noiseProbability", _ov getOrDefault ["noiseprobability", -1]],
    ["falseLeadProbability", _ov getOrDefault ["falseleadprobability", -1]],
    ["includeBiometrics", _ov getOrDefault ["includebiometrics", true]],
    ["includePhone", _ov getOrDefault ["includephone", true]],
    ["includeDocuments", _ov getOrDefault ["includedocuments", true]],
    ["includeComputer", _ov getOrDefault ["includecomputer", false]],
    ["networkSize", _ov getOrDefault ["networksize", 8]],
    ["tags", _ov getOrDefault ["tags", []]],
    ["notes", _ov getOrDefault ["notes", ""]],
    ["source", "USER"]
];

_model
