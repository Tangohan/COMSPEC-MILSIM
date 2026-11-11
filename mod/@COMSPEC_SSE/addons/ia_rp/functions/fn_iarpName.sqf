/*
    Nom d'une personne tel qu'il s'affiche (bulles, fenêtre « Parler à… », atelier IA, menus du Zeus). Pour une IA :
    jamais le nom d'unité Arma (« Afanasyev »), mais le nom qu'elle donne, celui de son identité unique (addon identity,
    fn_idIdentityOf : nom donné par le Zeus, Eden, l'atelier IA, un profil, sinon tiré une fois et gardé) et de ses
    papiers ; faux papiers ou couverture : le nom imprimé. Diffusé dans comspec_sse_idName. Pour un joueur : son nom.

    Règle du nom montré (en-tête ET bulles, la même) : un joueur voit le nom d'une IA une fois qu'il l'a APPRIS — elle
    l'a dit (« Comment vous appelez-vous ? », réponse donnée), ou il l'a lu sur ses papiers (visionneuse) ; sinon
    « Inconnu », partout. Le Zeus (curateur), l'administrateur et l'hôte voient toujours le nom (et, dans les bulles,
    le vrai nom et la nature de l'identité). Joueurs qui le connaissent : variable d'unité diffusée
    comspec_sse_nameKnownBy (identifiants Steam), posée par le serveur (« learn »).
      ["ensure", unité]               (SERVEUR) -> nom présenté brut, "" si aucun ; papiers décidés au besoin (identité
                                      unique, fn_idIdentityOf « presented ») ; diffusé (comspec_sse_idName). Ailleurs
                                      qu'au serveur : comme « shown » sans « Inconnu ».
      ["shown", unité]                (toute machine) -> nom montré au joueur de ce poste : le nom s'il le connaît
                                      (« known »), sinon « Inconnu » (serveur : « ensure » d'abord)
      ["known", [unité, joueur]]      -> vrai si ce joueur connaît son nom (Zeus, administrateur, hôte : toujours)
      ["learn", [unité, [joueurs]]]   (SERVEUR) ces joueurs connaissent désormais son nom
      ["truth", unité]                (SERVEUR) -> [nom présenté brut, vrai nom, nature] ; nature : true (identité vraie),
                                      lies (le nom présenté n'est pas celui de son identité : elle ment), forged (faux
                                      papiers), cover (couverture). Vrai nom : identité réelle (trueFirst / trueLast de ses
                                      papiers au registre, sinon son identité unique), sinon le nom présenté.
                                      Sans l'addon identity : [nom, nom, "true"]. Nom brut : "" sans nom.
      ["for", [unité, destinataire]]  (SERVEUR) -> ce que voit ce destinataire : la vérité (« truth ») s'il est Zeus ou
                                      administrateur (fn_isAuthority), le nom brut s'il le connaît, "" sinon ; à passer à
                                      « label » (qui affiche « Inconnu » pour "")
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
private _uidOf = { params ["_p"]; private _i = getPlayerUID _p; if (_i isEqualTo "") then { _i = "N:" + name _p; }; _i };
// Ce joueur connaît-il le nom de cette personne ?
private _knows = {
    params ["_u", "_v"];
    if (!(_u isEqualType objNull) || {isNull _u}) exitWith { false };
    if (isPlayer _u || {!(_v isEqualType objNull)} || {isNull _v}) exitWith { true };
    if (!isNull (getAssignedCuratorLogic _v)) exitWith { true };
    // Administrateur, hôte, partie solo : fn_isAuthority (à jour au serveur et au poste de l'hôte).
    if (isServer && {[_v] call comspec_sse_fnc_isAuthority}) exitWith { true };
    ([_v] call _uidOf) in (_u getVariable ["comspec_sse_nameKnownBy", []])
};

switch (_op) do {
    case "ensure": {
        private _n = [_a] call _rawOf;
        if (!isServer || {!(_a isEqualType objNull)} || {isNull _a} || {isPlayer _a}) exitWith { _n };
        // Identité unique (addon identity) : papiers décidés d'abord, puis le nom présenté, toujours le même.
        if (!isNil "comspec_sse_fnc_idIdentityOf") exitWith { ["presented", [_a]] call comspec_sse_fnc_idIdentityOf };
        if (_n isNotEqualTo "") exitWith { _n };
        if (!isNil "comspec_sse_fnc_idLegend") then {
            private _L = ["get", [_a]] call comspec_sse_fnc_idLegend;
            if (_L isEqualType createHashMap) then { _n = trim format ["%1 %2", _L getOrDefault ["first", ""], _L getOrDefault ["last", ""]]; };
        };
        if (_n isNotEqualTo "") then { _a setVariable ["comspec_sse_idName", _n, true]; };
        _n
    };
    case "shown": {
        private _n = [_a] call _rawOf;
        if (_n isEqualTo "" && {isServer}) then { _n = ["ensure", _a] call comspec_sse_fnc_iarpName; };
        // Nom pas encore appris par le joueur de ce poste : « Inconnu », comme dans les bulles.
        if (hasInterface && {!isNull player} && {_n isNotEqualTo ""} && {!([_a, player] call _knows)}) then { _n = ""; };
        [_n, call _anon] select (_n isEqualTo "")
    };
    case "known": {
        _a params [["_u", objNull], ["_v", objNull]];
        [_u, _v] call _knows
    };
    case "learn": {
        if (!isServer) exitWith { false };
        _a params [["_u", objNull, [objNull]], ["_who", [], [[]]]];
        if (isNull _u || {isPlayer _u}) exitWith { false };
        private _list = +(_u getVariable ["comspec_sse_nameKnownBy", []]);
        private _n0 = count _list;
        { if (_x isEqualType objNull && {!isNull _x} && {isPlayer _x || {_x isEqualTo player}}) then { _list pushBackUnique ([_x] call _uidOf); }; } forEach _who;
        if ((count _list) > _n0) then { _u setVariable ["comspec_sse_nameKnownBy", _list, true]; };
        true
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
        if (_true isEqualTo "" && {!isNil "comspec_sse_fnc_idIdentityOf"}) then { _true = ["full", [_a]] call comspec_sse_fnc_idIdentityOf; };
        if (_true isEqualTo "") then { _true = _raw; };
        // Nom présenté différent de son identité réelle sans faux papier ni couverture : elle ment sur son nom.
        if (_nat isEqualTo "true" && {_raw isNotEqualTo ""} && {!([_raw, _true] call _same)}) then { _nat = "lies"; };
        [_raw, _true, _nat]
    };
    case "for": {
        _a params [["_u", objNull], ["_to", objNull]];
        if (!isServer) exitWith { [[_u] call _rawOf, ""] select !([_u, _to] call _knows) };
        if (_u isEqualType objNull && {!isNull _u} && {!isPlayer _u}) then { ["ensure", _u] call comspec_sse_fnc_iarpName; };
        if (!isNull _to && {_u isEqualType objNull} && {!isNull _u} && {!isPlayer _u} && {[_to] call comspec_sse_fnc_isAuthority}) exitWith {
            ["truth", _u] call comspec_sse_fnc_iarpName
        };
        // Joueur qui ne l'a pas encore appris : rien (« Inconnu » à l'affichage).
        if (!([_u, _to] call _knows)) exitWith { "" };
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
