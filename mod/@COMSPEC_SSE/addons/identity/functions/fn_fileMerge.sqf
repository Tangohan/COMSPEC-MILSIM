/*
    Réponse d'Athena ajoutée à une consultation locale (fn_fileClient « result »).
    [_res, [trouvé, verdict, réf, niveau, [drapeaux], mis à jour, [nom, prénom, niveau]]] call comspec_sse_fnc_fileMerge
    Drapeaux du passage aux fichiers (GET /api/sse/identity/documents/lookup) : mentions (warrant, wanted, flagged,
    missing, armed_dangerous, caution, stolen, lost, revoked, suspended, expired, holder_mismatch, watchlist,
    person_priority, unknown_document, no_vehicle_file), qrn:<n° Athena>, doc:<PAYS>|<type>|<numéro>|<état>,
    dob:<AAAA-MM-JJ>, checks:<n>, lastcheck:<date>, seek:<REC>. Modifie _res (hit, codes, records, athena).
*/
params [["_res", createHashMap], ["_arr", []]];
_arr params [["_found", false], ["_verdict", ""], ["_ref", ""], ["_level", ""], ["_flags", []], ["_upd", ""], ["_holder", []]];
if !(_flags isEqualType []) then { _flags = []; };
private _codes = _res getOrDefault ["codes", []];
private _recs = _res getOrDefault ["records", []];
private _info = createHashMapFromArray [["found", _found], ["verdict", _verdict], ["ref", _ref], ["level", _level], ["updated", _upd], ["docs", []], ["seek", []]];
private _known = ["warrant", "wanted", "flagged", "missing", "armed_dangerous", "caution", "stolen", "lost", "revoked", "expired", "holder_mismatch"];
private _alias = createHashMapFromArray [["suspended", "revoked"], ["watchlist", "flagged"], ["person_priority", "hvt"], ["dob_mismatch", "holder_mismatch"]];
{
    if !(_x isEqualType "") then { continue; };
    private _f = _x;
    private _i = _f find ":";
    if (_i > 0) then {
        private _k = _f select [0, _i];
        private _v = _f select [_i + 1];
        switch (_k) do {
            case "qrn": { _info set ["qrn", _v]; };
            case "dob": { _info set ["dob", _v]; };
            case "checks": { _info set ["checks", parseNumber _v]; };
            case "lastcheck": { _info set ["lastcheck", _v]; };
            case "seek": { (_info get "seek") pushBack _v; };
            case "doc": { (_info get "docs") pushBack (_v splitString "|"); };
        };
    } else {
        private _c = _alias getOrDefault [_f, _f];
        if (_c in _known || {_c in ["hvt"]}) then { _codes pushBackUnique _c; };
    };
} forEach _flags;
// Niveau de liste et verdict du fichier : mêmes mentions que le fichier local.
private _lc = switch (_level) do { case "hvt": { "hvt" }; case "prioritaire"; case "recherche": { "wanted" }; case "surveillance": { "flagged" }; default { "" }; };
if (_lc isNotEqualTo "") then { _codes pushBackUnique _lc; };
private _vc = createHashMapFromArray [["vole", "stolen"], ["revoque", "revoked"], ["expire", "expired"], ["recherche", "wanted"], ["hvt", "hvt"], ["signale", "flagged"]] getOrDefault [_verdict, ""];
if (_vc isNotEqualTo "") then { _codes pushBackUnique _vc; };
// Documents connus d'Athena et absents de la réponse locale : ajoutés (numéro, pays, type, état).
private _st = createHashMapFromArray [["stolen", "stolen"], ["lost", "lost"], ["revoked", "revoked"], ["suspended", "revoked"], ["expired", "expired"]];
{
    _x params [["_c", ""], ["_t", ""], ["_n", ""], ["_s", "ok"]];
    // Numéros comparés normalisés (fn_fileNorm) : « AL 123-456 » d'Athena est le même document que « AL123456 » local.
    if ((_recs findIf { (["number", _x getOrDefault ["number", ""]] call comspec_sse_fnc_fileNorm) isEqualTo (["number", _n] call comspec_sse_fnc_fileNorm) && {(_x getOrDefault ["kind", ""]) isEqualTo "document"} }) < 0) then {
        _recs pushBack createHashMapFromArray [["kind", "document"], ["ref", "ATHENA"], ["number", _n], ["country", _c], ["docType", _t],
            ["last", _holder param [0, ""]], ["first", _holder param [1, ""]], ["dob", _info getOrDefault ["dob", ""]],
            ["codes", [[], [_st get _s]] select (_s in _st)], ["lines", ["Fichier Athena."]], ["checks", []]];
    };
} forEach (_info get "docs");
// Titulaire donné par le fichier (personne ou biométrie trouvée) : fiche personne si la réponse locale n'en a pas.
if (_holder isEqualType [] && {(_holder param [0, ""]) isNotEqualTo ""} && {(_recs findIf { (_x getOrDefault ["kind", ""]) isEqualTo "person" }) < 0}) then {
    _recs pushBack createHashMapFromArray [["kind", "person"], ["ref", _ref], ["last", _holder select 0], ["first", _holder param [1, ""]],
        ["dob", _info getOrDefault ["dob", ""]], ["codes", +_codes], ["lines", [format ["Fichier Athena%1.", ["", format [" : %1 contrôle(s) subi(s)", _info get "checks"]] select ((_info getOrDefault ["checks", 0]) > 0)]]], ["checks", []]];
};
if (_found) then {
    _res set ["hit", true];
    if ((_res getOrDefault ["match", "none"]) isEqualTo "none") then { _res set ["match", "hit"]; };
};
if (_res get "hit") then { _codes = _codes - ["unknown"]; };
_res set ["codes", _codes];
_res set ["records", _recs];
_res set ["athena", _info];
_res
