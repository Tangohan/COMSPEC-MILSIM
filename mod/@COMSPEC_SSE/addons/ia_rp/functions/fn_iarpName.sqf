/*
    Nom d'une personne tel qu'il s'affiche (bulles, fenêtre « Parler à… », atelier IA, menus du Zeus). Pour une IA :
    jamais le nom d'unité Arma (« Khrushchev »), mais le nom que portent son identité et ses papiers SSE
    (comspec_sse_idName, posé par l'identité SSE et par l'atelier IA), sinon « Anonyme ». Pour un joueur : son nom.
    Une seule règle pour le nom affiché d'une IA : nom de ses papiers SSE, sinon de sa légende SSE, sinon « Anonyme ».
      ["ensure", unité]               (SERVEUR) -> nom affiché brut, "" si aucun ; s'il manque : nom imprimé sur ses
                                      papiers (identité SSE, décidée ici au besoin), sinon celui de sa légende ; diffusé
                                      (comspec_sse_idName). Ailleurs qu'au serveur : comme « shown » sans « Anonyme ».
      ["shown", unité]                (toute machine) -> nom affiché aux joueurs (serveur : « ensure » d'abord)
      ["truth", unité]                (SERVEUR) -> [nom affiché brut, vrai nom, nature] ; nature : true (identité vraie),
                                      lies (le nom affiché n'est pas celui de son identité : elle ment), forged (faux
                                      papiers), cover (couverture). Vrai nom : identité réelle (trueFirst / trueLast de ses
                                      papiers au registre, sinon identité générée fn_idGenerate), sinon le nom affiché.
                                      Sans l'addon identity : [nom affiché, nom affiché, "true"]. Nom brut : "" sans nom.
      ["for", [unité, destinataire]]  (SERVEUR) -> ce que voit ce destinataire : la vérité (« truth ») s'il est Zeus ou
                                      administrateur (fn_isAuthority), le nom affiché brut sinon ; à passer à « label »
      ["label", nom | [nom, vrai nom, nature]]   (client) -> texte : « Stavros VLACHOS » pour tous ; pour le Zeus,
                                      « Stavros VLACHOS (Nikos KARRAS · Faux papiers) » (indicateur : Identité vraie,
                                      Fausse identité (ment), Faux papiers, Couverture ; rien si l'identité est vraie)
    « truth » lit ce qui existe au registre, sans rien délivrer (pas d'appel « unitDocs », qui déciderait des papiers).
*/
params [["_op", "", [""]], ["_a", []]];
private _anon = { if (hasInterface) then { localize "STR_COMSPEC_IARP_Anonymous" } else { "" } };
private _rawOf = {
    params ["_u"];
    if (!(_u isEqualType objNull) || {isNull _u}) exitWith { "" };
    if (isPlayer _u) exitWith { name _u };
    private _n = _u getVariable ["comspec_sse_idName", ""];
    if !(_n isEqualType "") then { _n = ""; };
    trim _n
};
private _same = { params ["_x1", "_x2"]; (toUpper (trim _x1)) isEqualTo (toUpper (trim _x2)) };

switch (_op) do {
    case "ensure": {
        private _n = [_a] call _rawOf;
        if (_n isNotEqualTo "" || {!isServer} || {!(_a isEqualType objNull)} || {isNull _a} || {isPlayer _a}) exitWith { _n };
        if (!isNil "comspec_sse_fnc_idRegistry") then {
            ["ensure", [_a]] call comspec_sse_fnc_idRegistry;
            private _d = ["doc", [(["unitDocs", [_a]] call comspec_sse_fnc_idRegistry) param [0, ""]]] call comspec_sse_fnc_idRegistry;
            if (_d isEqualType createHashMap && {(count _d) > 0}) then { _n = trim format ["%1 %2", _d getOrDefault ["first", ""], _d getOrDefault ["last", ""]]; };
        };
        if (_n isEqualTo "" && {!isNil "comspec_sse_fnc_idLegend"}) then {
            private _L = ["get", [_a]] call comspec_sse_fnc_idLegend;
            if (_L isEqualType createHashMap) then { _n = trim format ["%1 %2", _L getOrDefault ["first", ""], _L getOrDefault ["last", ""]]; };
        };
        if (_n isNotEqualTo "") then { _a setVariable ["comspec_sse_idName", _n, true]; };
        _n
    };
    case "shown": {
        private _n = [_a] call _rawOf;
        if (_n isEqualTo "" && {isServer}) then { _n = ["ensure", _a] call comspec_sse_fnc_iarpName; };
        [_n, call _anon] select (_n isEqualTo "")
    };
    case "truth": {
        private _raw = [_a] call _rawOf;
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a}) exitWith { [_raw, _raw, "true"] };
        if (isPlayer _a) exitWith { [_raw, _raw, "true"] };
        private _true = "";
        private _nat = "true";
        private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
        if (_reg isEqualType createHashMap && {(count _reg) > 0} && {!isNil "comspec_sse_fnc_idRegistry"}) then {
            private _key = ["key", [_a]] call comspec_sse_fnc_idRegistry;
            if !(_key isEqualType "") then { _key = ""; };
            private _docs = ((_reg getOrDefault ["units", createHashMap]) getOrDefault [_key, []]) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
            _docs = _docs select { _x isEqualType createHashMap && {(count _x) > 0} };
            private _kinds = _docs apply { _x getOrDefault ["kind", "civil"] };
            if ("cover" in _kinds) then { _nat = "cover"; };
            if ("forged" in _kinds) then { _nat = "forged"; };
            private _main = _docs param [0, createHashMap];
            if ((_main getOrDefault ["trueLast", ""]) isNotEqualTo "") then {
                _true = trim format ["%1 %2", _main getOrDefault ["trueFirst", ""], toUpper (_main get "trueLast")];
            };
        };
        if (_true isEqualTo "" && {!isNil "comspec_sse_fnc_idGenerate"}) then {
            private _g = [_a] call comspec_sse_fnc_idGenerate;
            if (_g isEqualType createHashMap && {(_g getOrDefault ["last", ""]) isNotEqualTo ""}) then {
                _true = trim format ["%1 %2", _g getOrDefault ["first", ""], toUpper (_g get "last")];
            };
        };
        if (_true isEqualTo "") then { _true = _raw; };
        // Nom affiché différent de son identité réelle (nom de l'atelier IA) : elle ment sur son nom.
        if (_nat isEqualTo "true" && {_raw isNotEqualTo ""} && {!([_raw, _true] call _same)}) then { _nat = "lies"; };
        [_raw, _true, _nat]
    };
    case "for": {
        _a params [["_u", objNull], ["_to", objNull]];
        if (!isServer) exitWith { [_u] call _rawOf };
        if (_u isEqualType objNull && {!isNull _u} && {!isPlayer _u}) then { ["ensure", _u] call comspec_sse_fnc_iarpName; };
        if (!isNull _to && {_u isEqualType objNull} && {!isNull _u} && {!isPlayer _u} && {[_to] call comspec_sse_fnc_isAuthority}) exitWith {
            ["truth", _u] call comspec_sse_fnc_iarpName
        };
        [_u] call _rawOf
    };
    case "label": {
        if (_a isEqualType "") exitWith { [_a, call _anon] select (_a isEqualTo "") };
        if !(_a isEqualType []) exitWith { call _anon };
        _a params [["_shown", "", [""]], ["_true", "", [""]], ["_nat", "true", [""]]];
        if (_shown isEqualTo "") then { _shown = call _anon; };
        private _differ = _true isNotEqualTo "" && {!([_true, _shown] call _same)};
        if (_nat isEqualTo "true" && {!_differ}) exitWith { _shown };
        private _tag = localize ("STR_COMSPEC_IARP_Z_Nat" + ((createHashMapFromArray [["true", "True"], ["lies", "Lies"], ["forged", "Forged"], ["cover", "Cover"]]) getOrDefault [_nat, "True"]));
        format ["%1 (%2)", _shown, [_tag, format ["%1 · %2", _true, _tag]] select _differ]
    };
    default { "" };
}
