/*
    Écrans TOC persistants (fn_tocScreen) : gardés par le serveur d'une partie à l'autre, sur la même carte et la même
    mission (profil du serveur, COMSPEC_ATAK_TocSave_<carte>_<mission>), si le réglage « Garder les écrans TOC » est coché.
      - écrans posés en cours de partie (module Zeus, objet créé par le module) : recréés à leur place, s'ils sont d'une
        classe d'écran du jeu admise (celles de la pose, fn_tocScreen « classes ») ;
      - écrans de la mission (Eden), et tout objet désigné qui existe encore : retrouvés à leur place (même classe à
        1,5 m), jamais recréés, leur affichage et leur cadrage remis ;
      - flux des écrans (comptes rendus, messages du poste) : repris, grisé « partie précédente ».
    Pendant la partie, le registre et l'état des écrans sont aussi des variables publiques, pour l'affichage : les
    joueurs qui rejoignent ou réapparaissent retrouvent les mêmes écrans.
    Lot mpauth3 (SEC-033) : « write » lisait le registre public COMSPEC_ATAK_TocScreens et l'état public
    COMSPEC_ATAK_TocScr, que tout client peut écrire, et « restore » recréait alors toute classe de CfgVehicles aux
    parties suivantes. La sauvegarde lit maintenant la copie que le serveur tient seul (fn_tocScreen, localNamespace
    COMSPEC_ATAK_TocSrv, jamais diffusée), et « restore » ne recrée qu'une classe d'écran admise, par « spawn ».
    Sauvegardes déjà écrites : même format (version 1), relues ; une entrée à recréer d'une autre classe, ou mal formée,
    est ignorée (journal du serveur) et disparaît à la prochaine écriture. ["clear"] efface la sauvegarde.
    Modes (serveur) : ["save"] (écriture regroupée, 10 s après le dernier changement) | ["write"] | ["restore"] | ["clear"]
*/
params [["_mode", "save"]];
if (!isServer) exitWith {};
private _key = format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName];
private _on = missionNamespace getVariable ["comspec_atak_native_toc_persist", true];
switch (_mode) do {
    case "save": {
        // Rien avant la reprise : la sauvegarde de la partie précédente ne doit pas être écrasée par une liste vide.
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveQueued", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", true];
        [{ ["write"] call comspec_atak_native_fnc_tocPersist; }, [], 10] call CBA_fnc_waitAndExecute;
    };
    case "write": {
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", false];
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        // Écrans et état : copie du serveur seule (fn_tocScreen), jamais les variables publiques.
        private _scr = ((localNamespace getVariable ["COMSPEC_ATAK_TocSrv", []]) select { !isNull (_x select 0) && {alive (_x select 0)} }) apply {
            _x params ["_o", "_c"];
            [typeOf _o, getPosASL _o, vectorDir _o, vectorUp _o, +_c]
        };
        // Flux de la partie en cours seulement (celui de la partie précédente a déjà été gardé une fois).
        private _ev = [];
        { _ev pushBack [_x, (_y select { !(_x param [8, false]) }) select [0 max ((count _y) - 30)]]; } forEach (["events"] call comspec_atak_native_fnc_tocFeed);
        profileNamespace setVariable [_key, [1, systemTimeUTC, _scr, _ev]];
        // Écriture du profil du serveur regroupée (fn_saveProfile : une par minute au plus, tout de suite en fin de mission).
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "clear": {
        profileNamespace setVariable [_key, nil];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "restore": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocRestored", true];
        // Fin de mission : dernière écriture.
        { addMissionEventHandler [_x, { ["write"] call comspec_atak_native_fnc_tocPersist; [true] call comspec_atak_native_fnc_saveProfile; }]; } forEach ["Ended", "MPEnded"];
        if (!_on) exitWith {};
        private _data = profileNamespace getVariable [_key, []];
        if !(_data isEqualType [] && {(count _data) >= 4}) exitWith {};
        _data params ["", "", ["_scr", []], ["_ev", []]];
        private _n = 0;
        // Classes que « restore » peut recréer : les écrans du jeu proposés à la pose (fn_tocScreen « classes »).
        private _admitted = (["classes"] call comspec_atak_native_fnc_tocScreen) apply { _x select 1 };
        {
            _x params [["_cls", ""], ["_pos", [0, 0, 0]], ["_vd", [0, 1, 0]], ["_vu", [0, 0, 1]], ["_cfg", []]];
            // [nom, camp, index, affichage, demi-largeur, centre, poste TOC, posé en jeu] ; entrée mal formée : ignorée.
            if (!(_cls isEqualType "") || {!(isClass (configFile >> "CfgVehicles" >> _cls))} || {!(_cfg isEqualType [])} || {!(_cfg isEqualTypeParams ["", "", 0, "", 0, [], true, true])}) then { continue };
            private _o = ((nearestObjects [ASLToAGL _pos, [_cls], 1.5]) select { alive _x }) param [0, objNull];
            if (isNull _o) then {
                // Écran de la mission disparu de la mission (Eden modifié) : pas recréé.
                if !(_cfg select 7) then { continue };
                // Écran posé en jeu : recréé seulement d'une classe d'écran admise, par « spawn » (objet marqué créé par le
                // mod, côté serveur, et rendu au Zeus), puis remis à sa place exacte.
                if !(_cls in _admitted) then {
                    ["WARNING", "TOC", format ["Écran TOC de la sauvegarde non recréé : la classe %1 n'est pas un écran du jeu admis", _cls]] call comspec_atak_native_fnc_log;
                    continue
                };
                _o = ["spawn", [ASLToAGL _pos, 0, _cls, _cfg select 0, _cfg select 1, _cfg select 3, _cfg select 6]] call comspec_atak_native_fnc_tocScreen;
                if (isNull _o) then { continue };
                _o setPosASL _pos;
                _o setVectorDirAndUp [_vd, _vu];
            };
            ["add", [_o, _cfg select 0, _cfg select 1, _cfg select 2, _cfg select 3, _cfg select 6, _cfg select 7]] call comspec_atak_native_fnc_tocScreen;
            // Cadrage gardé (demi-largeur, centre), remis comme une demande d'affichage du serveur.
            if ((_cfg select 4) > 0) then { ["view", [_o, "zoom", _cfg select 4]] call comspec_atak_native_fnc_tocScreen; };
            if ((count (_cfg select 5)) >= 2) then { ["view", [_o, "center", _cfg select 5]] call comspec_atak_native_fnc_tocScreen; };
            _n = _n + 1;
        } forEach _scr;
        private _hm = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _hm set [_x select 0, _x select 1]; }; } forEach _ev;
        ["setEvents", _hm] call comspec_atak_native_fnc_tocFeed;
        ["INFO", "TOC", format ["Écrans TOC repris de la partie précédente : %1", _n]] call comspec_atak_native_fnc_log;
    };
};
true
