/*
    Filtre « seulement les entités équipées d'un ATAK » du TOC (demande de TTRD : « mets un paramètre addon dans le TOC
    pour forcer l'affichage que des entités avec un ATAK »). Réglage du serveur comspec_atak_native_toc_atak_only
    (décoché par défaut, le même pour tous les joueurs). Coché : le TOC et ses écrans ne montrent que les unités,
    véhicules et aéronefs qui portent un ATAK allumé ; les autres ne sont ni listés, ni dessinés, ni comptés.
    C'est un filtre en plus du réglage « Source des positions du TOC », qui ne dit que d'où vient la position :
      Mixte ou Jeu + filtre coché : les porteurs seulement, à leur vraie position ;
      Réseau ATAK : déjà les porteurs seulement, à leur dernier point ; le filtre écarte en plus les téléphones éteints.
    Porteur d'ATAK : la même notion que les nœuds du réseau (fn_hasDevice : téléphone porté, tablette de bord d'un
    aéronef ou d'un véhicule équipé, IA équipée), balise allumée (COMSPEC_ATAK_Beacon). Véhicule, aéronef, drone : au
    moins un membre d'équipage porteur, ou, pour un drone, son opérateur.
    Jamais caché : l'opérateur assis à un PC du poste (COMSPEC_ATAK_TocView) et, sur son propre poste, le joueur lui-même.

    Une seule fonction pour toutes les vues (pistes, GROUPES, ORBAT, EFFECTIF, compteurs, caméras, tableau radio, listes
    de la console, listes du tableau des écrans en Zeus) :
      [entité] call comspec_atak_native_fnc_tocHasAtak          -> vrai si l'entité porte un ATAK allumé (gardé 10 s)
      ["only"]                                                  -> vrai si le filtre est coché
      ["keep", entité]                                          -> vrai si le TOC doit montrer cette entité, réglages lus
      ["show", [filtre coché, porte un ATAK, source "MIX" | "GAME" | "ATAK", poste du joueur]]   pure -> vrai | faux
      ["selfTest"]                                              -> [réussis, noms des cas en échec]
    Exemple (liste d'une autre vue) : _unites = _unites select { ["keep", _x] call comspec_atak_native_fnc_tocHasAtak };
*/
params [["_a", objNull], ["_b", []]];
private _fn = comspec_atak_native_fnc_tocHasAtak;
if (_a isEqualType "") exitWith {
    switch (_a) do {
        case "only": { (missionNamespace getVariable ["comspec_atak_native_toc_atak_only", false]) isEqualTo true };
        case "show": {
            _b params [["_only", false], ["_has", false], ["_src", "MIX"], ["_own", false]];
            if (_own) exitWith { true };
            if (_has) exitWith { true };
            // Sans ATAK : montré seulement si le filtre est décoché et si la source lit le jeu.
            !_only && {_src isNotEqualTo "ATAK"}
        };
        case "keep": {
            if (!(_b isEqualType objNull) || {isNull _b}) exitWith { false };
            private _own = (hasInterface && {_b isEqualTo player}) || {((_b getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isNotEqualTo ""};
            ["show", [["only"] call _fn, [_b] call _fn, ["mode"] call comspec_atak_native_fnc_tocTracks, _own]] call _fn
        };
        case "selfTest": {
            // ---- CAS FILTRE (JSON) : [nom, [filtre coché, porte un ATAK, source, poste du joueur], montré].
            private _casesShow = [
                ["décoché, mixte, sans ATAK", [false, false, "MIX", false], true],
                ["décoché, mixte, avec ATAK", [false, true, "MIX", false], true],
                ["décoché, jeu, sans ATAK", [false, false, "GAME", false], true],
                ["décoché, jeu, avec ATAK", [false, true, "GAME", false], true],
                ["décoché, réseau ATAK, sans ATAK", [false, false, "ATAK", false], false],
                ["décoché, réseau ATAK, avec ATAK", [false, true, "ATAK", false], true],
                ["coché, mixte, sans ATAK", [true, false, "MIX", false], false],
                ["coché, mixte, avec ATAK", [true, true, "MIX", false], true],
                ["coché, jeu, sans ATAK", [true, false, "GAME", false], false],
                ["coché, jeu, avec ATAK", [true, true, "GAME", false], true],
                ["coché, réseau ATAK, sans ATAK", [true, false, "ATAK", false], false],
                ["coché, réseau ATAK, avec ATAK", [true, true, "ATAK", false], true],
                ["coché, sans ATAK, mais c'est le poste du joueur", [true, false, "MIX", true], true],
                ["coché, réseau ATAK, poste du joueur", [true, false, "ATAK", true], true]
            ];
            // ---- FIN CAS FILTRE
            private _ok = 0;
            private _bad = [];
            {
                _x params ["_name", "_args", "_want"];
                if ((["show", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _name; };
            } forEach _casesShow;
            [_ok, _bad]
        };
        default { false };
    }
};
if (!(_a isEqualType objNull) || {isNull _a}) exitWith { false };
// Inventaire lu au plus toutes les 10 s par entité (comme les nœuds de fn_tocFeed).
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_TocHasAtak", createHashMap];
if ((count _cache) > 800) then { _cache = createHashMap; };
missionNamespace setVariable ["COMSPEC_ATAK_TocHasAtak", _cache];
private _key = netId _a;
private _c = _cache getOrDefault [_key, [false, -1e9]];
if (diag_tickTime <= (_c select 1)) exitWith { _c select 0 };
private _one = { params ["_u"]; alive _u && {[_u] call comspec_atak_native_fnc_hasDevice} && {(_u getVariable ["COMSPEC_ATAK_Beacon", true]) isEqualTo true} };
private _has = if (_a isKindOf "CAManBase") then { [_a] call _one } else {
    private _crew = crew _a;
    // Drone : son équipage est fictif, c'est l'opérateur qui porte le terminal.
    if (unitIsUAV _a) then { _crew = [(UAVControl _a) select 0] select { !isNull _x }; };
    (_crew findIf { [_x] call _one }) >= 0
};
_cache set [_key, [_has, diag_tickTime + 8 + random 4]];
_has
