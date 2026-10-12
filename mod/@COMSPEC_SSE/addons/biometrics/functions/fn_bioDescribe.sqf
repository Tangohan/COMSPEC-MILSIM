/*
    Lignes lisibles d'un résultat de recherche (hint, fiche, journal).
    [_result] call comspec_sse_fnc_bioDescribe -> [String]
*/
params [["_res", createHashMap, [createHashMap]]];
private _v = _res getOrDefault ["verdict", "none"];
private _lines = [];
_lines pushBack (switch (_v) do {
    case "match": { format ["MATCH · TIER %1 · score %2", _res getOrDefault ["tier", 0], _res getOrDefault ["score", 0]] };
    case "possible": { format ["POSSIBLE MATCH · score %1", _res getOrDefault ["score", 0]] };
    case "insufficient": { "QUALITÉ INSUFFISANTE · RECAPTURER" };
    case "latent": { "INCONNU DES BASES · EMPREINTES SUR OBJETS SAISIS" };
    default { "NO MATCH" };
});
if ((_res getOrDefault ["name", ""]) isNotEqualTo "") then {
    _lines pushBack format ["Dossier %1 : %2%3", _res getOrDefault ["ref", ""], _res get "name", [format [" (alias %1)", _res getOrDefault ["alias", ""]], ""] select ((_res getOrDefault ["alias", ""]) isEqualTo "")];
    if ((_res getOrDefault ["tier", 0]) > 0) then { _lines pushBack format ["Motif : %1", _res getOrDefault ["reason", ""]]; };
    _lines pushBack format ["Conduite : %1", _res getOrDefault ["action", ""]];
    _lines pushBack format ["Source : %1", _res getOrDefault ["enrolledAt", _res getOrDefault ["source", ""]]];
    private _enc = _res getOrDefault ["encounters", []];
    if (_enc isNotEqualTo []) then {
        private _last = _enc select ((count _enc) - 1);
        _lines pushBack format ["Déjà rencontré %1 fois, dernière : %2 %3 (%4)", count _enc, _last param [0, ""], _last param [1, ""], _last param [2, ""]];
    };
    if ((_res getOrDefault ["athenaRef", ""]) isNotEqualTo "") then { _lines pushBack format ["Avis de recherche Athena %1", _res get "athenaRef"]; };
};
if (_res getOrDefault ["mismatch", false]) then {
    _lines pushBack format ["ALERTE : identité déclarée « %1 » différente du dossier (faux papiers ?)", _res getOrDefault ["declaredName", ""]];
};
if ((_res getOrDefault ["fpCompared", 0]) > 0 || {(_res getOrDefault ["irisCompared", 0]) > 0} || {(_res getOrDefault ["faceScore", 0]) > 0}) then {
    _lines pushBack format ["Concordance : doigts %1/%2 · iris %3/%4 · visage %5", _res getOrDefault ["fpMatched", 0], _res getOrDefault ["fpCompared", 0], _res getOrDefault ["irisMatched", 0], _res getOrDefault ["irisCompared", 0], [format ["%1", _res get "faceScore"], "--"] select ((_res getOrDefault ["faceScore", 0]) == 0)];
};
private _fpD = _res getOrDefault ["fpDetail", []];
if (_fpD isNotEqualTo []) then {
    private _fn = ["PD", "ID", "MD", "AD", "OD", "PG", "IG", "MG", "AG", "OG"];
    _lines pushBack format ["Minuties concordantes (seuil 12) : %1", (_fpD apply { format ["%1 %2/%3", _fn select (_x select 0), _x select 1, _x select 2] }) joinString ", "];
};
private _hd = _res getOrDefault ["irisHd", [-1, -1]];
if ((_hd findIf { _x >= 0 }) >= 0) then {
    _lines pushBack format ["Iris, distance de Hamming (seuil 0,32) : OD %1, OG %2", ["--", (_hd select 0) toFixed 3] select ((_hd select 0) >= 0), ["--", (_hd select 1) toFixed 3] select ((_hd select 1) >= 0)];
};
if ((_res getOrDefault ["faceSim", -1]) >= 0) then { _lines pushBack format ["Visage, similarité : %1", (_res get "faceSim") toFixed 2]; };
if ((_res getOrDefault ["henry", ""]) isNotEqualTo "") then { _lines pushBack format ["Classification Henry %1 · FPC %2", _res get "henry", _res getOrDefault ["fpc", ""]]; };
private _cands = _res getOrDefault ["candidates", []];
if ((count _cands) > 1) then {
    _lines pushBack format ["Autre candidat : %1 (%2) score %3", (_cands select 1) select 1, (_cands select 1) select 0, (_cands select 1) select 2];
};
{
    _lines pushBack format ["Latente %1 : %2 (%3, %4)", _x select 0, _x select 1, _x select 2, _x select 3];
} forEach (_res getOrDefault ["latents", []]);
if ((_res getOrDefault ["note", ""]) isNotEqualTo "") then { _lines pushBack (_res get "note"); };
_lines pushBack (["Base locale du terminal (hors liaison)", "ABIS complet (en liaison)"] select ((_res getOrDefault ["scope", "local"]) isEqualTo "abis"));
_lines
