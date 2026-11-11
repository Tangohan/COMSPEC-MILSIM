/*
    Extensions de documents, côté serveur : liste des classes d'extensions coupées, calculée ici et diffusée à tous dans
    comspec_sse_docExtOff (tableau de classes en minuscules, lu par comspec_sse_fnc_idDocTypes), avec l'évènement CBA
    comspec_sse_docExtOffChanged [liste] pour que chaque machine vide son cache (XEH_postInit).
      ["init"]                  -> calcule et diffuse (lancement, réglage CBA changé)
      ["set", [classe, actif]]  -> choix du Zeus / administrateur (remoteExec depuis la fenêtre ; demandeur vérifié par
                                   comspec_sse_fnc_isAuthority sur remoteExecutedOwner) : garde, recalcule, diffuse
      ["saved"]                 -> choix gardés [[classe, actif]…] (profil du serveur, comspec_sse_docExtOffSaved)
    Liste coupée = extensions coupées par défaut (defaultOff = 1, ex. l'extension d'exemple livrée avec le SSE) et
    réglage comspec_sse_docExt_off, corrigés par les choix gardés ; toutes les extensions si
    comspec_sse_docExt_enabled est décoché. Renvoie la liste diffusée (init, set) ; false si refusé ou hors serveur.
*/
params [["_op", "init", [""]], ["_a", [], [[]]]];
if (!isServer) exitWith { false };
if (isNil "comspec_sse_fnc_idDocTypes") exitWith { false };

private _saved = profileNamespace getVariable ["comspec_sse_docExtOffSaved", []];
if !(_saved isEqualType []) then { _saved = []; };
if (_op isEqualTo "saved") exitWith { _saved };

private _publish = {
    private _all = (["extensions"] call comspec_sse_fnc_idDocTypes) apply { toLower (_x select 0) };
    private _off = [];
    if (missionNamespace getVariable ["comspec_sse_docExt_enabled", true]) then {
        _off = ((missionNamespace getVariable ["comspec_sse_docExt_off", ""]) splitString ",; ") apply { toLower (trim _x) };
        _off = (_off select { _x isNotEqualTo "" }) + (["defaultOff"] call comspec_sse_fnc_idDocTypes);
        // Choix faits dans la fenêtre (gardés d'une partie à l'autre) : ils l'emportent sur le réglage.
        {
            _x params [["_cls", "", [""]], ["_on", true, [true]]];
            _cls = toLower _cls;
            if (_on) then { _off = _off - [_cls]; } else { _off pushBackUnique _cls; };
        } forEach _saved;
    } else {
        _off = +_all;
    };
    _off = _off arrayIntersect _off;
    missionNamespace setVariable ["comspec_sse_docExtOff", _off, true];
    // Toutes les machines, serveur et hôte compris (XEH_postInit) : cache de fn_idDocTypes vidé, fenêtre relue.
    ["comspec_sse_docExtOffChanged", [_off]] call CBA_fnc_globalEvent;
    diag_log format ["[COMSPEC SSE] Extensions de documents : %1 chargée(s), coupées : %2", count _all, _off];
    _off
};

switch (_op) do {
    case "init": {
        missionNamespace setVariable ["comspec_sse_docExtReady", true];
        call _publish
    };
    case "set": {
        _a params [["_cls", "", [""]], ["_on", true, [true]]];
        _cls = toLower (trim _cls);
        // Demandeur : un client (identifiant > 2) doit être Zeus ou administrateur ; le serveur lui-même (hôte, local) passe.
        private _who = remoteExecutedOwner;
        private _allowed = _who <= 2;
        if (!_allowed) then {
            private _unit = (allPlayers select { (owner _x) isEqualTo _who }) param [0, objNull];
            _allowed = [_unit] call comspec_sse_fnc_isAuthority;
        };
        if (!_allowed) exitWith {
            diag_log format ["[COMSPEC SSE] Extensions : demande refusée (client %1, pas Zeus ni administrateur)", _who];
            false
        };
        private _known = (["extensions"] call comspec_sse_fnc_idDocTypes) apply { toLower (_x select 0) };
        if (_cls isEqualTo "" || {!(_cls in _known)}) exitWith { false };
        _saved = _saved select { (toLower (_x param [0, ""])) isNotEqualTo _cls };
        _saved pushBack [_cls, _on];
        profileNamespace setVariable ["comspec_sse_docExtOffSaved", _saved];
        saveProfileNamespace;
        call _publish
    };
    default { false };
};
