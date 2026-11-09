/*
    Couverture d'un infiltré (SERVEUR). La personne vit sous une identité de couverture (papiers « couverture » et
    légende, fn_idLegend) : tant qu'elle tient, elle est captive (setCaptive, réglage) et les ennemis la prennent pour un
    civil ; elle saute (« brûlée ») sur arme visible, tenue militaire, tir (FiredMan), zone interdite, contrôle raté ou
    soupçons accumulés. Une seule boucle serveur de 2 s, posée seulement tant qu'il y a des infiltrés et qui ne regarde
    qu'eux (rien chez les autres joueurs, rien par image).
    ["start", [unité, réglages]] -> [texte, genre]   réglages (HashMap) : captive (vrai | faux | absent = réglage), by
    ["stop", [unité]] -> [texte, genre]          fin de l'infiltration (plus captive)
    ["auto", [unité]]                            joueur au kit d'infiltré (papier « couverture » du kit Athena, ou brief
                                                 de couverture du paquet, bloc « briefs ») : infiltration ouverte
    ["burn", [unité, motif]]                     brûlée : plus captive, alerte aux groupes ennemis proches, alerte
                                                 discrète au joueur, Zeus, compte rendu AAR
    ["suspect", [unité, points, motif]]          soupçons (contrôle raté, mauvaise réponse…) ; au seuil : brûlée
    ["fired", [unité]]                           tir de l'infiltré (FiredMan, relayé par son poste)
    ["answer", [unité, juste]]                   réponse à une question de légende (cohérence)
    ["score", [unité]] -> [score 0-100, [motifs…], [[quoi corriger, points repris]…]]   cohérence papiers · légende ·
                                                 équipement · comportement ; codes d'actions : app Ma couverture
    ["state", [unité]] -> HashMap (vide hors infiltration)      ["list"] -> états (Zeus)
    ["zone", [position, rayon, nom]] -> [texte, genre]   zone interdite aux couvertures (Zeus) ; rayon 0 : retire la
                                                 plus proche. Marqueurs de mission « comspec_id_interdit… » aussi lus.
    ["tick"]                                     boucle de 2 s
    Variable serveur (jamais diffusée) : comspec_sse_idCoverOn sur l'unité.
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { nil };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _covers = _reg getOrDefault ["covers", createHashMap];
_reg set ["covers", _covers];
private _zones = _reg getOrDefault ["forbidden", []];
_reg set ["forbidden", _zones];
private _limit = missionNamespace getVariable ["comspec_sse_id_coverLimit", 100];
private _tell = {
    params ["_u", "_op2", "_args"];
    if (isNull _u || {!isPlayer _u}) exitWith {};
    if (local _u && {hasInterface}) then { [_op2, _args] call comspec_sse_fnc_idClient; } else { [_op2, _args] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
};
private _zeus = {
    params ["_t", ["_k", "info"]];
    {
        private _z = getAssignedCuratorUnit _x;
        if (!isNull _z && {isPlayer _z}) then { [_z, "zeus", [_t, _k]] call _tell; };
    } forEach allCurators;
};
private _stateOf = { params ["_u"]; _covers getOrDefault [netId _u, createHashMap] };
private _visible = {
    params ["_u"];
    private _out = [];
    private _w = currentWeapon _u;
    if (_w isNotEqualTo "" && {_w isNotEqualTo (binocular _u)} && {(vehicle _u) isEqualTo _u}) then { _out pushBack ["arme à la main", 1000]; };
    if (((primaryWeapon _u) isNotEqualTo "" && {_w isNotEqualTo primaryWeapon _u}) || {(secondaryWeapon _u) isNotEqualTo "" && {_w isNotEqualTo secondaryWeapon _u}}) then { _out pushBack ["arme longue dans le dos", 40]; };
    private _uc = getText (configFile >> "CfgWeapons" >> uniform _u >> "ItemInfo" >> "uniformClass");
    if (_uc isNotEqualTo "" && {(getNumber (configFile >> "CfgVehicles" >> _uc >> "side")) in [0, 1, 2]}) then { _out pushBack ["tenue militaire", 35]; };
    if ((vest _u) isNotEqualTo "" && {(getNumber (configFile >> "CfgWeapons" >> vest _u >> "ItemInfo" >> "HitpointsProtectionInfo" >> "Chest" >> "armor")) > 0}) then { _out pushBack ["gilet pare-balles", 12]; };
    if ((headgear _u) isNotEqualTo "" && {(getNumber (configFile >> "CfgWeapons" >> headgear _u >> "ItemInfo" >> "HitpointsProtectionInfo" >> "Head" >> "armor")) > 0}) then { _out pushBack ["casque", 8]; };
    if ((hmd _u) isNotEqualTo "") then { _out pushBack ["jumelles de vision nocturne", 10]; };
    _out
};
private _inForbidden = {
    params ["_u"];
    private _p = getPosATL _u;
    ((_zones findIf { (_p distance2D (_x select 0)) <= (_x select 1) }) >= 0)
        || {(allMapMarkers findIf { ((toLower _x) find "comspec_id_interdit") isEqualTo 0 && {_p inArea _x} }) >= 0}
};
private _setCaptive = { params ["_u", "_v"]; ["comspec_sse_idLocalCaptive", [_u, _v], _u] call CBA_fnc_targetEvent; };
private _ensureLoop = {
    if (!isNil "comspec_sse_idCoverPfh") exitWith {};
    comspec_sse_idCoverPfh = [{ ["tick"] call comspec_sse_fnc_idCover; }, 2] call CBA_fnc_addPerFrameHandler;
};

switch (_op) do {
    case "start": {
        _a params [["_u", objNull, [objNull]], ["_spec", createHashMap, [createHashMap]]];
        if (isNull _u || {!alive _u} || {!(_u isKindOf "CAManBase")}) exitWith { ["Placez le module sur une personne vivante.", "warn"] };
        private _cap = _spec getOrDefault ["captive", missionNamespace getVariable ["comspec_sse_id_coverCaptive", true]];
        private _s = createHashMapFromArray [
            ["unit", _u], ["on", true], ["burnt", false], ["susp", 0], ["captive", _cap isEqualTo true], ["side", side group _u],
            ["answers", [0, 0]], ["why", []], ["since", time], ["burnWhy", ""], ["warned", -100], ["name", name _u]
        ];
        _covers set [netId _u, _s];
        _u setVariable ["comspec_sse_idCoverOn", true];
        ["reset", [_u]] call comspec_sse_fnc_idLegend;
        if (_cap isEqualTo true) then { [_u, true] call _setCaptive; };
        [_u, "coverState", ["on", ""]] call _tell;
        call _ensureLoop;
        private _L = ["get", [_u]] call comspec_sse_fnc_idLegend;
        [format ["%1 est infiltré sous l'identité %2%3.", name _u, _L get "name", ["", " (captif tant que la couverture tient)"] select (_cap isEqualTo true)], "info"]
    };
    case "auto": {
        _a params [["_u", objNull, [objNull]]];
        if (isNull _u || {!alive _u}) exitWith { false };
        // Déjà infiltré (attribut Eden, Zeus) : son poste (connexion, JIP) reçoit l'état pour relever ses tirs.
        private _s0 = [_u] call _stateOf;
        if ((count _s0) > 0) exitWith { if (_s0 get "on") then { [_u, "coverState", ["on", ""]] call _tell; }; false };
        private _uid = getPlayerUID _u;
        private _brief = if (_uid isEqualTo "") then { createHashMap } else { (_reg getOrDefault ["briefs", createHashMap]) getOrDefault [_uid, createHashMap] };
        // Identité réelle + couverture portées ensemble (papiers Athena avec un rôle) : infiltré seulement si c'est la
        // couverture qu'il présente. Papiers sans rôle (Zeus, Eden) : comme avant.
        private _myDocs = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
        private _activeOk = ((_myDocs findIf { (_x getOrDefault ["role", ""]) isNotEqualTo "" }) < 0) || {(["get", [_u]] call comspec_sse_fnc_idActive) isEqualTo "cover"};
        private _cov = _activeOk && {(_myDocs findIf { (_x getOrDefault ["kind", ""]) isEqualTo "cover" }) >= 0};
        private _bc = _activeOk && {((_brief getOrDefault ["identity_id", ""]) isNotEqualTo "") || {((_brief getOrDefault ["cover_name", ""]) isNotEqualTo "")}};
        if !(_cov || _bc) exitWith { false };
        ["start", [_u, createHashMap]] call comspec_sse_fnc_idCover;
        true
    };
    case "stop": {
        _a params [["_u", objNull, [objNull]]];
        private _s = [_u] call _stateOf;
        if ((count _s) isEqualTo 0) exitWith { ["Cette personne n'est pas infiltrée.", "warn"] };
        if ((_s get "captive") && {!(_s get "burnt")}) then { [_u, false] call _setCaptive; };
        _covers deleteAt (netId _u);
        _u setVariable ["comspec_sse_idCoverOn", false];
        [_u, "coverState", ["off", ""]] call _tell;
        [format ["Infiltration de %1 levée.", name _u], "info"]
    };
    case "burn": {
        _a params [["_u", objNull, [objNull]], ["_why", "soupçons"]];
        private _s = [_u] call _stateOf;
        if ((count _s) isEqualTo 0 || {_s get "burnt"}) exitWith { false };
        _s set ["burnt", true];
        _s set ["on", false];
        _s set ["burnWhy", _why];
        _s set ["burnAt", time];
        _u setVariable ["comspec_sse_idCoverOn", false];
        if (_s get "captive") then { [_u, false] call _setCaptive; };
        // Les groupes ennemis proches (400 m) passent en combat et connaissent sa position.
        private _real = _s get "side";
        private _done = [];
        {
            private _g = group _x;
            if (alive _x && {!isPlayer _x} && {!(_g in _done)} && {[side _g, _real] call BIS_fnc_sideIsEnemy}) then {
                _done pushBack _g;
                ["comspec_sse_idAiDo", [leader _g, "alert", [[_u]]], leader _g] call CBA_fnc_targetEvent;
            };
        } forEach (_u nearEntities ["CAManBase", 400]);
        [_u, "coverState", ["burnt", _why]] call _tell;
        [format ["Couverture de %1 brûlée (%2) : %3 groupe(s) ennemi(s) alerté(s).", name _u, _why, count _done], "warn"] call _zeus;
        ["add", ["COUVERTURE", format ["Couverture de %1 brûlée au %2 (%3)", name _u, mapGridPosition _u, _why], format ["burn|%1", netId _u]]] call comspec_sse_fnc_idAar;
        ["comspec_sse_idCoverBurnt", [_u, _why]] call CBA_fnc_localEvent;
        true
    };
    case "suspect": {
        _a params [["_u", objNull, [objNull]], ["_pts", 0], ["_why", ""]];
        private _s = [_u] call _stateOf;
        if ((count _s) isEqualTo 0 || {!(_s get "on")}) exitWith { false };
        if (_pts >= 1000) exitWith { ["burn", [_u, _why]] call comspec_sse_fnc_idCover };
        _s set ["susp", ((_s get "susp") + _pts) max 0];
        if (_why isNotEqualTo "") then { (_s get "why") pushBackUnique _why; };
        if ((_s get "susp") >= _limit) exitWith { ["burn", [_u, ["soupçons accumulés", _why] select (_why isNotEqualTo "")]] call comspec_sse_fnc_idCover };
        // À mi-chemin : le joueur sent qu'il attire l'attention (vibreur, une fois par 30 s).
        if ((_s get "susp") >= _limit * 0.5 && {(time - (_s get "warned")) > 30}) then {
            _s set ["warned", time];
            [_u, "coverState", ["warn", _why]] call _tell;
        };
        true
    };
    case "fired": {
        _a params [["_u", objNull, [objNull]]];
        private _s = [_u] call _stateOf;
        if ((count _s) isEqualTo 0 || {!(_s get "on")}) exitWith { false };
        private _w = currentWeapon _u;
        private _sup = ((_u weaponAccessories _w) param [0, ""]) isNotEqualTo "";
        private _real = _s get "side";
        private _heard = (_u nearEntities ["CAManBase", [250, 40] select _sup]) findIf { alive _x && {[side group _x, _real] call BIS_fnc_sideIsEnemy} } >= 0;
        if (_heard) exitWith { ["burn", [_u, "coup de feu"]] call comspec_sse_fnc_idCover };
        ["suspect", [_u, 30, "coup de feu entendu au loin"]] call comspec_sse_fnc_idCover
    };
    case "answer": {
        _a params [["_u", objNull, [objNull]], ["_ok", false]];
        private _s = [_u] call _stateOf;
        if ((count _s) isEqualTo 0) exitWith { false };
        (_s get "answers") params ["_g", "_n"];
        _s set ["answers", [_g + ([0, 1] select _ok), _n + 1]];
        if (!_ok) then { ["suspect", [_u, 20, "réponse qui ne colle pas à sa légende"]] call comspec_sse_fnc_idCover; };
        true
    };
    case "score": {
        _a params [["_u", objNull, [objNull]]];
        if (isNull _u) exitWith { [0, []] };
        private _why = [];
        private _todo = [];
        private _L = ["get", [_u]] call comspec_sse_fnc_idLegend;
        // Papiers (35) : la pièce de la légende, un seul nom, sa qualité.
        private _docs = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
        // Papiers de l'autre identité (réelle cachée) : ne comptent qu'à la fouille, pas dans les papiers présentés.
        private _activeRole = ["get", [_u]] call comspec_sse_fnc_idActive;
        _docs = _docs select { (_x getOrDefault ["role", ""]) in ["", _activeRole] };
        private _names = [];
        { _names pushBackUnique toUpper format ["%1 %2", _x getOrDefault ["last", ""], _x getOrDefault ["first", ""]]; } forEach _docs;
        private _paper = 0;
        if (_docs isEqualTo []) then { _why pushBack "sans papiers"; _todo pushBack ["sans papiers", 35]; } else {
            _paper = 35 * ((((_L get "quality") max 20) min 100) / 100);
            if ((35 - _paper) >= 12) then { _todo pushBack ["papiers douteux", round (35 - _paper)]; };
            if ((count _names) > 1) then { _paper = _paper - 20; _why pushBack "papiers à plusieurs noms"; _todo pushBack ["papiers à plusieurs noms", 20]; };
        };
        // Légende (25) : réponses justes aux questions.
        private _s = [_u] call _stateOf;
        (_s getOrDefault ["answers", [0, 0]]) params ["_g", "_n"];
        private _leg = if (_n > 0) then { 25 * (_g / _n) } else { 25 * (((_L get "quality") max 40) / 100) };
        if (_n > 0 && {_g < _n}) then { _why pushBack format ["%1 réponse(s) fausse(s) sur %2", _n - _g, _n]; _todo pushBack ["réponses fausses", round (25 - _leg)]; };
        // Équipement (25) et comportement (15).
        private _gear = 25;
        // Ce qui se voit encore aujourd'hui seulement ; les points repris s'arrêtent aux 25 de l'équipement.
        { _x params ["_t", "_p"]; private _lost = (_p min 15) min (_gear max 0); _gear = _gear - (_p min 15); _why pushBack _t; _todo pushBack [_t, _lost]; } forEach ([_u] call _visible);
        private _beh = 15 * (1 - (((_s getOrDefault ["susp", 0]) / (_limit max 1)) min 1));
        if ((15 - _beh) >= 1) then { _todo pushBack ["soupçons", round (15 - _beh)]; };
        if ([_u] call _inForbidden) then { _beh = _beh - 8; _why pushBack "en zone interdite"; _todo pushBack ["en zone interdite", 8]; };
        { _why pushBackUnique _x; } forEach (_s getOrDefault ["why", []]);
        [round (((_paper max 0) + _leg + (_gear max 0) + (_beh max 0)) max 0 min 100), _why, _todo]
    };
    case "state": { _a params [["_u", objNull]]; [_u] call _stateOf };
    case "list": { values _covers };
    case "zone": {
        _a params [["_pos", [0, 0, 0]], ["_r", 0], ["_name", ""]];
        if (_r <= 0) exitWith {
            private _l = _zones apply { [(_x select 0) distance2D _pos, _x] };
            _l sort true;
            if (_l isEqualTo [] || {((_l select 0) select 0) > 500}) exitWith { ["Aucune zone interdite à moins de 500 m.", "warn"] };
            _zones deleteAt (_zones find ((_l select 0) select 1));
            ["Zone interdite retirée.", "info"]
        };
        _zones pushBack [_pos, _r, [_name, format ["Zone %1", (count _zones) + 1]] select (_name isEqualTo "")];
        [format ["Zone interdite aux couvertures : %1 m autour de %2.", round _r, mapGridPosition _pos], "info"]
    };
    case "tick": {
        private _live = (values _covers) select { _x get "on" };
        if (_live isEqualTo []) exitWith {
            if (!isNil "comspec_sse_idCoverPfh") then { [comspec_sse_idCoverPfh] call CBA_fnc_removePerFrameHandler; comspec_sse_idCoverPfh = nil; };
        };
        private _wit = missionNamespace getVariable ["comspec_sse_id_coverWitness", 50];
        {
            private _s = _x;
            private _u = _s get "unit";
            call {
                if (isNull _u || {!alive _u}) exitWith { _s set ["on", false]; };
                private _real = _s get "side";
                // Témoins : ennemis vivants, conscients, qui le voient (ligne de vue).
                private _eyes = (_u nearEntities ["CAManBase", _wit * 2]) select {
                    alive _x && {_x isNotEqualTo _u} && {!(_x getVariable ["ACE_isUnconscious", false])} && {[side group _x, _real] call BIS_fnc_sideIsEnemy}
                        && {([_x, "VIEW", _u] checkVisibility [eyePos _x, aimPos _u]) > 0.2}
                };
                private _seenAt = { params ["_d"]; (_eyes findIf { (_x distance _u) <= _d }) >= 0 };
                private _ev = false;
                {
                    _x params ["_t", "_p"];
                    private _d = switch (true) do { case (_p >= 1000): { _wit }; case (_p >= 35): { _wit * 0.6 }; default { _wit * 0.35 }; };
                    if ([_d] call _seenAt) then { _ev = true; ["suspect", [_u, _p, _t]] call comspec_sse_fnc_idCover; };
                } forEach ([_u] call _visible);
                if (!(_s get "on")) exitWith {};
                if ([_u] call _inForbidden) then { _ev = true; ["suspect", [_u, [4, 30] select ([100] call _seenAt), "zone interdite"]] call comspec_sse_fnc_idCover; };
                // Rien de suspect pendant 2 s : les soupçons retombent doucement.
                if (!_ev) then { _s set ["susp", ((_s get "susp") - 3) max 0]; };
            };
        } forEach _live;
        // Morts ou disparus : retirés.
        { if (isNull ((_covers get _x) get "unit") || {!alive ((_covers get _x) get "unit")}) then { _covers deleteAt _x; }; } forEach (keys _covers);
    };
    default { nil };
}
