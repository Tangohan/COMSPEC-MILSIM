/*
    Contrôle de l'analyse du Zeus (fn_idZeusAnalysis, fonction pure) : console de débogage, n'importe quel poste.
      copier ce fichier dans la console et l'exécuter -> [échecs] ([] : tout passe)
    Documents d'exemple : faux nom (contrefaçon, qualité 60), papier vrai périmé, vrai document modifié (base d'un autre
    nom), couverture, document vierge. Hors jeu : tests/Unit/IdZeusPapersAssetTest.php (seuils, clés, câblage).
*/
private _fails = [];
private _p = "STR_COMSPEC_SSE_ZP_";
private _keys = { (_this getOrDefault ["checks", []]) apply { _x select 0 } };
private _ql = { (_this getOrDefault ["qualityLines", []]) apply { _x select 0 } };
private _want = { params ["_label", "_ok"]; if !(_ok) then { _fails pushBack _label; }; };

// 1. Faux nom : contrefaçon, qualité 60 -> hologramme absent (il fallait 70), police et MRZ évitées (60, 45).
private _a = [createHashMapFromArray [
    ["kind", "forged"], ["forgeryKind", "counterfeit"], ["quality", 60], ["flaws", ["holo"]],
    ["last", "Oikonomou"], ["first", "Christos"], ["trueLast", "Afanasyev"], ["trueFirst", "Pavel"],
    ["expires", "2031-09-14"], ["issued", "2021-09-14"], ["chipExpected", true], ["chip", "absent"], ["report", ""]
], [2035, 6, 1], createHashMapFromArray [["base", []], ["hasMrz", true]]] call comspec_sse_fnc_idZeusAnalysis;
["faux : verdict", ((_a get "verdict") select 0) isEqualTo (_p + "V_forged")] call _want;
["faux : 1 défaut annoncé", (((_a get "verdict") select 1) select 2) isEqualTo 1] call _want;
["faux : nom différent", ((_a get "names") select 4) isEqualTo true] call _want;
["faux : hologramme présent sous 70", (_a call _ql) isEqualTo [_p + "Q_head", _p + "Q_missing", _p + "Q_avoided", _p + "Q_avoided", _p + "Q_eye"]] call _want;
["faux : seuil 70 de l'hologramme", ((((_a get "qualityLines") select 1) select 1) select 1) isEqualTo 70] call _want;
["faux : facteur à l'œil 0.65", ((((_a get "qualityLines") select 4) select 1) select 0) isEqualTo "0.65"] call _want;
["faux : N0, N2, N3, N4", ((_a call _keys) arrayIntersect [_p + "N0_flaws", _p + "N2_absent", _p + "N3_unknown", _p + "N4_bad"]) isEqualTo [_p + "N0_flaws", _p + "N2_absent", _p + "N3_unknown", _p + "N4_bad"]] call _want;
["faux : sens du défaut", (((_a get "flaws") select 0) select 2) isEqualTo [_p + "F_holo_D", []]] call _want;
["faux : état", (_a get "state") isEqualTo "bad"] call _want;

// 2. Vrais papiers périmés : rien de faux, mais périmé (doute) ; la qualité ne compte pas.
private _b = [createHashMapFromArray [
    ["kind", "civil"], ["quality", 60], ["flaws", []], ["last", "Leboube"], ["first", "Hugo"], ["trueLast", "Leboube"], ["trueFirst", "Hugo"],
    ["expires", "2033-02-01"], ["chipExpected", false], ["report", ""]
], [2035, 6, 1], createHashMapFromArray [["base", ["ID0001", "Leboube", "Hugo", ""]], ["hasMrz", false]]] call comspec_sse_fnc_idZeusAnalysis;
["périmé : expiré", (_b get "expired") isEqualTo true] call _want;
["périmé : N0", (_p + "N0_expired") in (_b call _keys)] call _want;
["périmé : verdict vrai", ((_b get "verdict") select 0) isEqualTo (_p + "V_true")] call _want;
["périmé : qualité sans objet", (_b call _ql) isEqualTo [_p + "Q_na"]] call _want;
["périmé : base au même nom", (_p + "N3_ok") in (_b call _keys)] call _want;
["périmé : état doute", (_b get "state") isEqualTo "warn"] call _want;
private _b2 = [createHashMapFromArray [["kind", "civil"], ["expires", "2036-02-01"], ["last", "A"], ["first", "B"]], [2035, 6, 1], createHashMap] call comspec_sse_fnc_idZeusAnalysis;
["valide : pas expiré", (_b2 get "expired") isEqualTo false] call _want;

// 3. Vrai document modifié, qualité 50 : police et MRZ d'un autre nom ; la base connaît l'ancien titulaire.
private _c = [createHashMapFromArray [
    ["kind", "forged"], ["forgeryKind", "altered"], ["quality", 50], ["flaws", ["font", "mrzdata"]],
    ["last", "Vlachos"], ["first", "Stavros"], ["trueLast", "Vlachos"], ["trueFirst", "Stavros"], ["expires", "2034-01-01"],
    ["chipExpected", true], ["chip", "mismatch"], ["chipNote", ""], ["report", ""]
], [2035, 1, 1], createHashMapFromArray [["base", ["ID0002", "Petrakis", "Nikos", ""]]]] call comspec_sse_fnc_idZeusAnalysis;
["modifié : MRZ d'un autre nom", (_p + "N1_name") in (_c call _keys)] call _want;
["modifié : base d'un autre nom", (_p + "N3_other") in (_c call _keys)] call _want;
["modifié : puce", (_p + "N2_mismatch") in (_c call _keys)] call _want;
["modifié : visage correspond (vrai nom imprimé)", (_p + "N4_ok") in (_c call _keys)] call _want;
["modifié : deux défauts manquants", ((_c call _ql) select { _x isEqualTo (_p + "Q_missing") }) isEqualTo [_p + "Q_missing", _p + "Q_missing"]] call _want;

// 4. Couverture préparée, 5. document vierge.
private _d = [createHashMapFromArray [["kind", "cover"], ["quality", 90], ["last", "Doe"], ["first", "John"], ["trueLast", "Smith"], ["trueFirst", "Jack"]], [2035, 1, 1], createHashMap] call comspec_sse_fnc_idZeusAnalysis;
["couverture : verdict", ((_d get "verdict") select 0) isEqualTo (_p + "V_cover")] call _want;
["couverture : qualité", (_d call _ql) isEqualTo [_p + "Q_cover"]] call _want;
private _e = [createHashMap, [2035, 1, 1], createHashMap] call comspec_sse_fnc_idZeusAnalysis;
["vierge : verdict", ((_e get "verdict") select 0) isEqualTo (_p + "V_blank")] call _want;

// Chaque clé produite existe dans la stringtable (français et anglais).
{
    private _k = _x;
    { if (_x isEqualType "" && {(_x select [0, 19]) isEqualTo _p} && {!isLocalized _x}) then { _fails pushBack ("clé absente : " + _x); }; } forEach _k;
} forEach ([_a, _b, _c, _d, _e] apply { [(_x get "verdict") select 0, _x getOrDefault ["kindKey", ""]] + ((_x getOrDefault ["checks", []]) apply { _x select 0 }) + ((_x getOrDefault ["qualityLines", []]) apply { _x select 0 }) });
_fails
