/*
    Nom affiché d'une unité dans tout le TOC (retour de TTRD : « les écrans TOC, on voit les pseudos Steam dans GROUPE
    et non les identifiants ATHENA »). Seule fonction à appeler pour écrire qui est qui : GROUPES, ORBAT, SQUAD,
    étiquettes de carte, flux, demandes, tableau radio, listes de la console, tableau des écrans du Zeus, EFFECTIF.
    Jamais `name _unite` ni `profileName` dans un affichage du TOC.
    Ordre, pour un joueur :
      1. son indicatif Athena : indicatif de son ATAK ou de son nœud, indicatif choisi dans C2 / Groupe, indicatif de son
         compte relié (fn_unitCallsign : variables déjà publiées par le mod, aucune requête) ;
      2. sinon la désignation du jeu, groupe et rang dans le groupe : « Alpha 1-1 · 2 », suivie de « (non lié) » si son
         compte n'est pas relié à Athena ;
      3. sinon son rôle (fn_displayRole) ;
      4. en dernier recours seulement, et seulement si le réglage « Afficher le pseudo Steam » est coché : son pseudo.
    Pour une IA : son indicatif s'il existe, sinon groupe et rang ; jamais « Fusilier 3 » ni le nom tiré au sort.
    Réglage du serveur comspec_atak_native_toc_show_steam (décoché par défaut) : coché, le pseudo est ajouté entre
    parenthèses après le nom affiché, « VIPER 2 (Tetar) ».
    Aucune requête réseau : tout vient de variables déjà présentes sur l'unité.

    Appels :
      [unité] call comspec_atak_native_fnc_tocDisplayName                      -> texte (40 caractères au plus)
      ["pick", [IA, indicatif, compte relié, groupe, rang, rôle, pseudo, afficher le pseudo]]   pure -> texte
      ["linked", unité]                                                        -> vrai si le compte du joueur est relié
      ["selfTest"]                                                             -> [réussis, noms des cas en échec]
    Exemple : private _nom = [leader _groupe] call comspec_atak_native_fnc_tocDisplayName;
*/
params [["_a", objNull], ["_b", []]];
private _fn = comspec_atak_native_fnc_tocDisplayName;
if (_a isEqualType "") exitWith {
    switch (_a) do {
        case "pick": {
            _b params [["_isAi", false], ["_cs", ""], ["_linked", false], ["_grp", ""], ["_slot", 0], ["_role", ""], ["_steam", ""], ["_show", false]];
            private _des = ["", format ["%1 · %2", _grp, _slot]] select (_grp isNotEqualTo "" && {_slot > 0});
            if (_des isEqualTo "") then { _des = _grp; };
            if (_isAi) exitWith { ([_cs, _des, "IA"] select { _x isNotEqualTo "" }) select 0 };
            private _out = switch (true) do {
                case (_cs isNotEqualTo ""): { _cs };
                case (_des isNotEqualTo ""): { _des + (["", " (non lié)"] select !_linked) };
                case (_role isNotEqualTo ""): { _role + (["", " (non lié)"] select !_linked) };
                case (_show && {_steam isNotEqualTo ""}): { _steam };
                default { "Opérateur" };
            };
            // Pseudo demandé par le réglage : ajouté, jamais à la place de l'indicatif.
            if (_show && {_steam isNotEqualTo ""} && {_out isNotEqualTo _steam}) then { _out = format ["%1 (%2)", _out, _steam]; };
            _out
        };
        case "linked": {
            if (!(_b isEqualType objNull) || {isNull _b}) exitWith { false };
            private _has = { private _v = _b getVariable [_this, ""]; _v isEqualType "" && {(trim _v) isNotEqualTo ""} };
            // Compte relié : indicatif ou rattachement ORBAT d'Athena publiés sur l'unité ; pour ce joueur, son profil chargé.
            ("COMSPEC_CallsignPublic" call _has) || {"COMSPEC_Callsign" call _has} || {"COMSPEC_ATAK_Orbat" call _has}
                || {hasInterface && {_b isEqualTo player} && {private _pu = missionNamespace getVariable ["comspec_profile_unit", ""]; _pu isEqualType "" && {_pu isNotEqualTo ""}}}
        };
        case "selfTest": {
            // ---- CAS NOM (JSON) : [nom, [IA, indicatif, relié, groupe, rang, rôle, pseudo, afficher le pseudo], attendu].
            private _casesName = [
                ["indicatif Athena d'abord", [false, "VIPER 2", true, "Alpha 1-1", 2, "Chef d'équipe", "Tetar", false], "VIPER 2"],
                ["sans indicatif, compte relié : groupe et rang", [false, "", true, "Alpha 1-1", 2, "Chef d'équipe", "Tetar", false], "Alpha 1-1 · 2"],
                ["sans téléphone ni compte relié : groupe, rang et marque", [false, "", false, "Alpha 1-1", 2, "", "Tetar", false], "Alpha 1-1 · 2 (non lié)"],
                ["sans groupe : le rôle", [false, "", true, "", 0, "Pilote", "Tetar", false], "Pilote"],
                ["rien du tout, pseudo interdit", [false, "", false, "", 0, "", "Tetar", false], "Opérateur"],
                ["rien du tout, pseudo permis : dernier recours", [false, "", false, "", 0, "", "Tetar", true], "Tetar"],
                ["pseudo permis : ajouté après l'indicatif", [false, "VIPER 2", true, "Alpha 1-1", 2, "", "Tetar", true], "VIPER 2 (Tetar)"],
                ["pseudo permis, sans indicatif", [false, "", false, "Alpha 1-1", 3, "", "Tetar", true], "Alpha 1-1 · 3 (non lié) (Tetar)"],
                ["IA avec indicatif", [true, "SENTINEL", false, "Bravo 2", 1, "Fusilier", "Jean Martin", true], "SENTINEL"],
                ["IA : groupe et rang, jamais la classe ni le nom", [true, "", false, "Bravo 2", 3, "Fusilier", "Jean Martin", true], "Bravo 2 · 3"],
                ["IA sans groupe", [true, "", false, "", 0, "Fusilier", "Jean Martin", false], "IA"]
            ];
            // ---- FIN CAS NOM
            private _ok = 0;
            private _bad = [];
            {
                _x params ["_name", "_args", "_want"];
                if ((["pick", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _name; };
            } forEach _casesName;
            [_ok, _bad]
        };
        default { "" };
    }
};
if (!(_a isEqualType objNull) || {isNull _a}) exitWith { "" };
private _u = _a;
private _isAi = !isPlayer _u;
private _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign;
if !(_cs isEqualType "") then { _cs = ""; };
private _g = group _u;
private _grp = ["", groupId _g] select (!isNull _g);
private _slot = ((units _g) find _u) + 1;
private _role = "";
private _steam = "";
if (!_isAi) then {
    _role = [_u, "role"] call comspec_atak_native_fnc_displayRole;
    if !(_role isEqualType "") then { _role = ""; };
    _steam = name _u;
};
(["pick", [_isAi, trim _cs, ["linked", _u] call _fn, _grp, _slot, _role, _steam, (missionNamespace getVariable ["comspec_atak_native_toc_show_steam", false]) isEqualTo true]] call _fn) select [0, 40]
