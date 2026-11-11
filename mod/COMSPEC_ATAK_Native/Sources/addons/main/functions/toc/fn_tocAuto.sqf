/*
    Écran TOC qui se règle tout seul à la pose (demande de TTRD, 4.7.0 : « l'écran devrait se setup tout seul à la
    pose »). Tout écran ou poste PC du mod, posé par n'importe quel moyen (objet d'Eden, objet de la liste du Zeus,
    module, salle complète, script createVehicle, reprise d'une partie, joueur arrivé en cours de partie), est désigné
    sans geste : inscrit au registre, affichage par défaut selon son format, lié à la salle la plus proche (créée
    s'il n'y en a pas : « TOC 1 »), image et actions lancées par la boucle habituelle de chaque joueur (fn_tocScreen
    « tick », « aceInit », « actions »), gardé d'une partie à l'autre par le serveur.

    Une seule entrée, « setup », rejouable sans effet : un écran déjà désigné (module, salle complète, attributs Eden,
    reprise) n'est jamais touché.
    Qui règle : le serveur s'il a le mod à jour ; sinon (serveur plus ancien ou sans le mod, fn_tocConfig « mode »
    en repli) le poste où l'objet est local (celui qui l'a posé), et pour un objet tenu par le serveur le premier
    joueur par identifiant. Rien n'attend une réponse du serveur.
    Déclenchement : événement de création de la classe de base des écrans (COMSPEC_ATAK_Screen_Base3D : écrans, murs,
    bandeaux, postes PC) et du PC portable de TOC, sur chaque machine, objets déjà présents compris ; plus un passage
    toutes les 30 s sur la machine qui règle, pour un objet que l'événement aurait manqué.
    Journal, une ligne par écran : « [COMSPEC TOC] écran | classe=… salle=… vue=… ok | échec raison ».

    Modes :
      ["init"]            chaque machine (XEH_postInitClient, fn_tocScreen « initServer »), une fois
      ["roots"]           classes de base suivies
      ["queue", objet]    objet créé : réglage demandé après le délai de pose
      ["mine", objet]     ce poste est-il celui qui règle cet objet ?
      ["view", objet]     [affichage par défaut, poste TOC ?, nom] selon le format de l'écran
      ["setup", objet]    réglage (sans effet si déjà fait)
      ["sweep"]           objets que l'événement de création aurait manqués
*/
params [["_mode", "init"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocAuto;
private _roots = ["COMSPEC_ATAK_Screen_Base3D", "COMSPEC_ATAK_TocLaptop"];
private _journal = {
    params ["_txt"];
    diag_log text ("[COMSPEC TOC] " + _txt);
    if (!isNil "comspec_overwatch_connect_fnc_log") then { ["INFO", "ATAK TOC", _txt] call comspec_overwatch_connect_fnc_log; };
};
private _designated = { params ["_o"]; (count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 };

switch (_mode) do {
    case "roots": { +_roots };
    case "init": {
        if (is3DEN || {missionNamespace getVariable ["COMSPEC_ATAK_TocAutoInit", false]}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocAutoInit", true];
        {
            // Héritage compris, objets déjà créés compris (mission, reprise, joueur arrivé en cours de partie).
            [_x, "init", { ["queue", _this select 0] call comspec_atak_native_fnc_tocAuto; }, true, [], true] call CBA_fnc_addClassEventHandler;
        } forEach _roots;
        [{ ["sweep"] call comspec_atak_native_fnc_tocAuto; }, 30] call CBA_fnc_addPerFrameHandler;
        true
    };
    case "queue": {
        private _o = _a;
        if (!(_o isEqualType objNull) || {isNull _o} || {is3DEN}) exitWith { false };
        if (_o getVariable ["COMSPEC_ATAK_TocAutoQ", false]) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_TocAutoQ", true];
        // Délai de pose : le module, la salle complète, les attributs Eden (3 s) et la reprise (3 s) règlent d'abord ;
        // au lancement, la version du serveur doit aussi être arrivée chez le joueur.
        private _wait = [2, 9] select (time < 10);
        // Objet tenu par une autre machine sur un serveur ancien : le premier joueur attend que son propriétaire ait passé.
        if (!isServer && {!local _o}) then { _wait = _wait + 6; };
        [{ ["setup", _this] call comspec_atak_native_fnc_tocAuto; }, _o, _wait] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "mine": {
        private _o = _a;
        if (isServer) exitWith { true };
        if (!hasInterface || {isNull player}) exitWith { false };
        if ((((["mode"] call comspec_atak_native_fnc_tocConfig) select 0)) isNotEqualTo "fallback") exitWith { false };
        if (local _o) exitWith { true };
        private _ids = (allPlayers select { !isNull _x }) apply { getPlayerUID _x };
        _ids sort true;
        (_ids param [0, getPlayerUID player]) isEqualTo (getPlayerUID player)
    };
    case "view": {
        private _o = _a;
        private _cfg = configOf _o;
        private _label = (getText (_cfg >> "displayName")) select [0, 32];
        // Poste PC fixe : console du poste (PIP) sur son moniteur principal, et il ouvre le PC du poste.
        if ((getNumber (_cfg >> "comspec_tocPost")) > 0) exitWith {
            private _pv = getText (_cfg >> "comspec_tocPostView");
            [[_pv, "TOC"] select (_pv isEqualTo ""), true, _label]
        };
        // Format de la dalle (fn_tocScreen « format ») : écran 16:9 : carte et flux ; bandeau 4:1 : horloges ZULU et
        // locale ; plaque : nom de l'escouade ; bandeau LED : texte ; colonne d'un écran incurvé : sa colonne.
        private _f = ["format", _o] call comspec_atak_native_fnc_tocScreen;
        if !(_f isEqualType [] && {(count _f) >= 6}) exitWith { ["SPLIT", true, _label] };
        [_f select 5, (_f select 3) isEqualTo "STD", _label]
    };
    case "setup": {
        private _o = _a;
        if (!(_o isEqualType objNull) || {isNull _o} || {!alive _o}) exitWith { false };
        if !(["mine", _o] call _fn) exitWith { false };
        if (_o getVariable ["COMSPEC_ATAK_TocAutoDone", false]) exitWith { true };
        private _cls = typeOf _o;
        private _cfg = configOf _o;
        // Jamais réglés ici : dalle relais d'un mur (son hôte la règle), toile de projection (pilotée par son
        // projecteur), écran déjà pris pour les diapositives du Briefing, objet dont les attributs Eden décident.
        if (!isNull (_o getVariable ["COMSPEC_ATAK_TocHost", objNull]) || {(getNumber (_cfg >> "comspec_projBoard")) > 0}
            || {(count (_o getVariable ["COMSPEC_ATAK_Screen", []])) >= 5} || {(_o getVariable ["COMSPEC_ATAK_EdScreenMode", ""]) isEqualTo "cast"}) exitWith {
            _o setVariable ["COMSPEC_ATAK_TocAutoDone", true];
            false
        };
        _o setVariable ["COMSPEC_ATAK_TocAutoDone", true];
        private _side = _o getVariable ["COMSPEC_ATAK_TocSide", ""];
        if !(_side isEqualType "") then { _side = ""; };
        private _screen = (getNumber (_cfg >> "comspec_screen")) > 0;
        private _was = [_o] call _designated;
        private _view = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""];
        // 1. Désignation : seulement si personne ne l'a faite (module, salle complète, Eden, reprise, ACE).
        if (_screen && {!_was}) then {
            (["view", _o] call _fn) params ["_dv", "_toc", "_label"];
            _view = _dv;
            ["add", [_o, _label, _side, 0, _dv, _toc, false]] call comspec_atak_native_fnc_tocScreen;
        };
        if (_screen && {!([_o] call _designated)}) exitWith {
            [format ["écran | classe=%1 salle=aucune vue=%2 échec désignation refusée (voir plus haut dans le journal)", _cls, _view]] call _journal;
            false
        };
        // 2. Salle : celle qu'il a déjà, sinon la plus proche, sinon une nouvelle (« TOC 1 »).
        private _hall = ["auto", [_o, _side]] call comspec_atak_native_fnc_tocHall;
        if !(_hall isEqualType "") then { _hall = ""; };
        private _hn = (["get", _hall] call comspec_atak_native_fnc_tocHall) param [1, _hall];
        [format ["écran | classe=%1 salle=%2 vue=%3 %4", _cls, [_hn, "aucune"] select (_hall isEqualTo ""), [_view, "poste"] select (!_screen),
            ["ok", "ok (déjà réglé)"] select _was]] call _journal;
        true
    };
    case "sweep": {
        // Objet que l'événement de création aurait manqué (classe sans événement étendu, création avant le chargement).
        if (is3DEN || {!isServer && {(((["mode"] call comspec_atak_native_fnc_tocConfig) select 0)) isNotEqualTo "fallback"}}) exitWith { 0 };
        private _n = 0;
        {
            {
                if !(_x getVariable ["COMSPEC_ATAK_TocAutoQ", false]) then { _n = _n + 1; ["queue", _x] call _fn; };
            } forEach (allMissionObjects _x);
        } forEach _roots;
        _n
    };
    default { false };
};
