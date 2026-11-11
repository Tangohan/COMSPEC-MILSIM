/*
    Salle TOC persistante (fn_tocScreen, fn_tocRoom) : gardée par le serveur d'une partie à l'autre, sur la même carte et
    la même mission (profil du serveur, COMSPEC_ATAK_TocSave_<carte>_<mission>), si le réglage « Garder les écrans TOC »
    est coché. Personne n'a à remonter la salle :
      - chaque écran : classe, position et orientation (déplacé au Zeus compris), nom, camp, affichage, cadrage de la
        carte, satellite et escouade montrés, texte de l'affichage TEXTE (fn_tocText), source de la caméra en direct (drone par sa classe, caméra fixe par son nom,
        caméra poitrine par le joueur : reprise dès qu'elle revient en jeu) ;
      - écrans posés ou désignés en cours de partie (module Zeus, ACE) : recréés à leur place ;
      - écrans de la mission (Eden) : retrouvés à leur place (même classe à 1,5 m), leur réglage remis ;
      - état du poste par camp (fn_tocRoom) : chronos (repris à leur valeur), satellites (position, zoom, image, suivi),
        main courante, liste d'exécution, demandes, pertes et résultats ;
      - flux des écrans (comptes rendus, messages du poste) : repris, grisé « partie précédente ».
    Écriture continue : 3 s après chaque changement (écran, affichage, chrono, satellite, main courante…), relevé des
    positions toutes les 15 s (écran déplacé au Zeus), dernière écriture en fin de mission. Le fichier du profil, lui,
    est écrit au plus une fois par minute (fn_saveProfile) et tout de suite en fin de mission.
    Dessins et plans (carte de l'ATAK et du PC du poste, fn_tocDraw) : marqueurs de joueur (canal « arma », réglage par
    défaut des marqueurs) avec leur style, plan partagé de chaque camp ; remis s'ils n'existent pas déjà.
    Avant la 4.4.0 : seuls les écrans (classe, position, nom, camp, affichage, cadrage) et le flux étaient gardés,
    10 s après un changement d'écran.
    Modes (serveur) : ["save"] (écriture regroupée) | ["write"] | ["restore"] | ["clear"] | ["watch"] (toutes les 15 s)
    Format 3 (4.12.0, feuille de route TOC n° 10) : même tableau, mêmes rangs que le format 2 publié
    [format, date, écrans, flux, poste, salles, [marqueurs, plans]], et à la suite : journal des actions (fn_tocJournal),
    postes nommés (fn_tocStation), relèves (fn_tocHandover), méta [version du mod, carte, mission, écritures]. Table
    _fields : un nouveau champ s'ajoute toujours à la fin, jamais au milieu. Une sauvegarde plus ancienne (formats 1 et
    2) est complétée à la lecture (« migrate ») ; une sauvegarde d'un mod plus récent (format 4 et plus) est lue pour
    ce que ce mod connaît et réécrite avec ses champs inconnus intacts.
    Copies de secours dans le profil du serveur : <clé>_bak (la sauvegarde telle qu'elle était au lancement de cette
    partie) et <clé>_v2 (la dernière sauvegarde d'avant le format 3, gardée une fois).
    Modes ajoutés : ["migrate", données] (pur, rejoué par tests/Unit/AtakTocPersistFormatTest.php) | ["fields"]
      | ["info"] -> [format, date, taille en caractères, copie de secours présente]
      | ["export"] -> texte « COMSPEC-TOC-SAVE:3: » + tableau | ["import", texte] (reprise au prochain lancement)
      | ["rollback"] (remet la copie de secours, reprise au prochain lancement)
*/
params [["_mode", "save"], ["_a", []]];
if (!isServer) exitWith {};
// Modes qui remplacent ou lisent toute la sauvegarde : jamais par un appel distant d'un joueur.
if (_mode in ["clear", "import", "rollback", "export"] && {isRemoteExecuted} && {isMultiplayer} && {isNil "_tocTrusted"}) exitWith { false };
private _key = format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName];
private _on = missionNamespace getVariable ["comspec_atak_native_toc_persist", true];
// Source de la caméra d'un écran, décrite pour être retrouvée à la partie suivante (un netId ne survit pas).
private _camWant = {
    params ["_o"];
    private _src = objectFromNetId (_o getVariable ["COMSPEC_ATAK_TocCam", ""]);
    if (isNull _src) exitWith { _o getVariable ["COMSPEC_ATAK_TocCamWant", []] };
    switch (true) do {
        case (unitIsUAV _src): { ["UAV", typeOf _src] };
        case (_src isKindOf "Man"): { ["MAN", getPlayerUID _src] };
        default { ["CAM", _src getVariable ["COMSPEC_ATAK_CamName", ""]] };
    }
};
// ---- TABLE _fields (JSON) : champs de la sauvegarde [rang, nom, format d'arrivée, valeur par défaut]. Les rangs publiés ne bougent jamais.
private _fields = [
[0, "format", 1, 3], [1, "date", 1, []], [2, "screens", 1, []], [3, "feed", 1, []], [4, "room", 2, []], [5, "halls", 2, []], [6, "draw", 2, []],
[7, "journal", 3, []], [8, "stations", 3, []], [9, "handover", 3, []], [10, "meta", 3, []]
];
// ---- FIN
private _format = 3;
private _migrate = {
    params [["_d", []]];
    if !(_d isEqualType [] && {(count _d) >= 4}) exitWith { [] };
    private _from = _d select 0;
    if !(_from isEqualType 0) then { _from = 1; };
    // Sauvegarde d'un mod plus récent : rendue telle quelle (les rangs connus n'ont pas bougé).
    if (_from > _format) exitWith { +_d };
    private _out = +_d;
    {
        _x params ["_i", "", "", "_def"];
        if ((count _out) <= _i || {isNil { _out select _i }}) then { _out set [_i, +_def]; };
    } forEach _fields;
    _out set [0, _format];
    _out
};
switch (_mode) do {
    case "fields": { +_fields };
    case "migrate": { [_a] call _migrate };
    case "info": {
        private _d = profileNamespace getVariable [_key, []];
        if !(_d isEqualType []) then { _d = []; };
        [_d param [0, 0], _d param [1, []], count str _d, (profileNamespace getVariable [_key + "_bak", []]) isNotEqualTo []]
    };
    case "export": { "COMSPEC-TOC-SAVE:3:" + (str ([profileNamespace getVariable [_key, []]] call _migrate)) };
    case "import": {
        private _head = "COMSPEC-TOC-SAVE:3:";
        if !(_a isEqualType "" && {(_a find _head) isEqualTo 0}) exitWith { false };
        private _raw = _a select [count _head];
        if !((_raw select [0, 1]) isEqualTo "[" && {(_raw select [(count _raw) - 1]) isEqualTo "]"}) exitWith { false };
        private _d = [parseSimpleArray _raw] call _migrate;
        if (_d isEqualTo []) exitWith { false };
        // La sauvegarde en place devient la copie de secours ; l'importée est reprise au prochain lancement de la mission.
        profileNamespace setVariable [_key + "_bak", profileNamespace getVariable [_key, []]];
        profileNamespace setVariable [_key, _d];
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveFrozen", true];
        [true] call comspec_atak_native_fnc_saveProfile;
        private _tocTrusted = true;
        ["add", ["", "", "MISSION", "save.import", format ["format %1", _d select 0], true]] call comspec_atak_native_fnc_tocJournal;
        true
    };
    case "rollback": {
        private _bak = profileNamespace getVariable [_key + "_bak", []];
        if !(_bak isEqualType [] && {(count _bak) >= 4}) exitWith { false };
        profileNamespace setVariable [_key, _bak];
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveFrozen", true];
        [true] call comspec_atak_native_fnc_saveProfile;
        true
    };
    case "save": {
        // Rien avant la reprise : la sauvegarde de la partie précédente ne doit pas être écrasée par une liste vide.
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveQueued", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", true];
        [{ ["write"] call comspec_atak_native_fnc_tocPersist; }, [], 3] call comspec_atak_native_fnc_waitAndExec;
    };
    case "write": {
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", false];
        if (!_on || {!(missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])}) exitWith {};
        // Sauvegarde importée ou remise depuis la copie de secours : plus rien n'est écrit par-dessus jusqu'au prochain lancement.
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveFrozen", false]) exitWith {};
        // Relais des dalles d'un mur : gardés dans l'écran hôte (un réglage par dalle), jamais comme écrans à part.
        private _scr = ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} }) apply {
            private _pans = ((_x getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x }) apply {
                private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
                [_x getVariable ["COMSPEC_ATAK_TocPanel", 0], [_c param [3, "SPLIT"], _c param [8, 0], _c param [9, ""], [_x] call _camWant, _x getVariable ["COMSPEC_ATAK_TocMosaic", []], _x getVariable ["COMSPEC_ATAK_TocOff", false], _x getVariable ["COMSPEC_ATAK_TocText", []], _x getVariable ["COMSPEC_ATAK_TocBc", []]]]
            };
            _pans sort true;
            [typeOf _x, getPosASL _x, vectorDir _x, vectorUp _x, _x getVariable ["COMSPEC_ATAK_TocScr", []], [_x] call _camWant, _pans apply { _x select 1 }, _x getVariable ["COMSPEC_ATAK_TocMosaic", []],
                [_x getVariable ["COMSPEC_ATAK_TocUid", ""], _x getVariable ["COMSPEC_ATAK_TocHall", ""], _x getVariable ["COMSPEC_ATAK_TocOff", false], _x getVariable ["COMSPEC_ATAK_TocSurf", []],
                    _x getVariable ["COMSPEC_ATAK_TocOpt", []], _x getVariable ["COMSPEC_ATAK_TocText", []], _x getVariable ["COMSPEC_ATAK_TocBc", []]]]
        };
        // Flux de la partie en cours seulement (celui de la partie précédente a déjà été gardé une fois).
        private _ev = [];
        // Lu directement : fn_tocFeed se termine par true, ["events"] renvoyait un booléen (erreur foreach en boucle, ATAK figé).
        private _evMap = missionNamespace getVariable ["COMSPEC_ATAK_TocEvents", createHashMap];
        if !(_evMap isEqualType createHashMap) then { _evMap = createHashMap; };
        { _ev pushBack [_x, (_y select { !(_x param [8, false]) }) select [0 max ((count _y) - 30)]]; } forEach _evMap;
        // Dessins et plans (carte de l'ATAK et du PC du poste) : marqueurs de joueur avec leur style, plan de chaque camp.
        private _mk = (allMapMarkers select { (_x find "_USER_DEFINED") isEqualTo 0 }) apply {
            [_x, markerType _x, markerPos _x, markerShape _x, markerColor _x, markerText _x, markerPolyline _x, markerDir _x, markerSize _x, markerAlpha _x, markerBrush _x,
                missionNamespace getVariable [format ["COMSPEC_ATAK_SK_%1", _x regexReplace ["[^A-Za-z0-9]", "_"]], []]]
        };
        private _plans = ["WEST", "EAST", "GUER", "CIV"] apply { [_x, missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", _x], []]] };
        // Format 3 : rangs 0 à 6 du format 2 publié, nouveaux champs à la suite (table _fields). Champs inconnus d'une
        // sauvegarde plus récente (rang 11 et au-delà) : recopiés tels quels, avec son numéro de format.
        private _tocTrusted = true;
        private _prev = profileNamespace getVariable [_key, []];
        if !(_prev isEqualType []) then { _prev = []; };
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_TocSaveWrites", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_TocSaveWrites", _n];
        private _fmtPrev = _prev param [0, 0];
        if !(_fmtPrev isEqualType 0) then { _fmtPrev = 0; };
        private _save = [_format max _fmtPrev, systemTimeUTC, _scr, _ev, ["dump"] call comspec_atak_native_fnc_tocRoom, ["dump"] call comspec_atak_native_fnc_tocHall, [_mk, _plans],
            ["dump"] call comspec_atak_native_fnc_tocJournal, ["dump"] call comspec_atak_native_fnc_tocStation, ["dump"] call comspec_atak_native_fnc_tocHandover,
            [missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""], worldName, missionName, _n]];
        profileNamespace setVariable [_key, _save + (_prev select [count _fields])];
        ["comspec_atak_toc_saved", [_format, _n]] call CBA_fnc_localEvent;
        missionNamespace setVariable ["COMSPEC_ATAK_TocPosSig", hashValue (_scr apply { [_x select 0, (_x select 1) apply { round _x }, (_x select 2) apply { (round (_x * 50)) / 50 }] })];
        // Écriture du profil du serveur regroupée (fn_saveProfile : une par minute au plus, tout de suite en fin de mission).
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "clear": {
        profileNamespace setVariable [_key, nil];
        [] call comspec_atak_native_fnc_saveProfile;
    };
    case "watch": {
        // Écran déplacé ou tourné au Zeus : sauvegardé. Caméra d'une partie précédente : reprise dès que sa source revient.
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} };
        private _sig = hashValue (_list apply { [typeOf _x, (getPosASL _x) apply { round _x }, (vectorDir _x) apply { (round (_x * 50)) / 50 }] });
        if (_sig isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_TocPosSig", ""])) then { ["save"] call comspec_atak_native_fnc_tocPersist; };
        {
            private _o = _x;
            private _w = _o getVariable ["COMSPEC_ATAK_TocCamWant", []];
            if ((count _w) < 2 || {alive objectFromNetId (_o getVariable ["COMSPEC_ATAK_TocCam", ""])}) then { continue };
            private _side = ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]);
            _w params ["_k", "_id"];
            private _src = switch (_k) do {
                case "UAV": { (allUnitsUAV select { alive _x && {(typeOf _x) isEqualTo _id} && {_side isEqualTo "" || {(str side group _x) isEqualTo _side}} }) param [0, objNull] };
                case "MAN": { (allPlayers select { alive _x && {(getPlayerUID _x) isEqualTo _id} }) param [0, objNull] };
                default { ((missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { _x isEqualType objNull && {!isNull _x} && {(_x getVariable ["COMSPEC_ATAK_CamName", ""]) isEqualTo _id} }) param [0, objNull] };
            };
            if (!isNull _src) then {
                _o setVariable ["COMSPEC_ATAK_TocCam", netId _src, true];
                _o setVariable ["COMSPEC_ATAK_TocCamWant", nil];
                ["INFO", "TOC", format ["Écran TOC %1 : caméra de la partie précédente reprise (%2)", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "?"], _w]] call comspec_atak_native_fnc_log;
            };
        } forEach _list;
    };
    case "restore": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocRestored", true];
        // Fin de mission : dernière écriture.
        { addMissionEventHandler [_x, { ["write"] call comspec_atak_native_fnc_tocPersist; [true] call comspec_atak_native_fnc_saveProfile; }]; } forEach ["Ended", "MPEnded"];
        if (!_on) exitWith {};
        [{ ["watch"] call comspec_atak_native_fnc_tocPersist; }, 15] call COMSPEC_ATAK_PfhSpread;
        // Dessins posés, changés ou effacés (téléphones, PC du poste) : sauvegardés comme le reste.
        { addMissionEventHandler [_x, { ["save"] call comspec_atak_native_fnc_tocPersist; }]; } forEach ["MarkerCreated", "MarkerDeleted", "MarkerUpdated"];
        private _tocTrusted = true;
        ["init"] call comspec_atak_native_fnc_tocAccess;
        private _raw = profileNamespace getVariable [_key, []];
        // Copies de secours : la sauvegarde telle qu'elle est au lancement, et une fois la dernière d'avant le format 3.
        if (_raw isEqualType [] && {(count _raw) >= 4}) then {
            profileNamespace setVariable [_key + "_bak", +_raw];
            if ((_raw select 0) isEqualType 0 && {(_raw select 0) < _format} && {(profileNamespace getVariable [_key + "_v2", []]) isEqualTo []}) then {
                profileNamespace setVariable [_key + "_v2", +_raw];
                ["INFO", "TOC", format ["Sauvegarde de la salle TOC au format %1 : copie de secours gardée, passage au format %2", _raw select 0, _format]] call comspec_atak_native_fnc_log;
            };
        };
        private _data = [_raw] call _migrate;
        if !(_data isEqualType [] && {(count _data) >= 4}) exitWith {};
        _data params ["", "", ["_scr", []], ["_ev", []], ["_room", []], ["_halls", []], ["_draw", []]];
        // Dessins et plans de la partie précédente : remis s'ils n'existent pas déjà.
        _draw params [["_mk", []], ["_plans", []]];
        {
            _x params [["_n", ""], ["_ty", "mil_dot"], ["_pos", [0, 0, 0]], ["_sh", "ICON"], ["_col", "Default"], ["_txt", ""], ["_pl", []], ["_dir", 0], ["_sz", [1, 1]], ["_al", 1], ["_br", "Solid"], ["_sk", []]];
            if (_n isEqualTo "" || {(markerShape _n) isNotEqualTo ""}) then { continue };
            private _m = createMarker [_n, _pos];
            if (_m isEqualTo "") then { continue };
            _m setMarkerShapeLocal _sh;
            if (_sh isEqualTo "POLYLINE" && {(count _pl) >= 4}) then { _m setMarkerPolylineLocal _pl; } else { _m setMarkerTypeLocal _ty; };
            _m setMarkerColorLocal _col; _m setMarkerTextLocal _txt; _m setMarkerDirLocal _dir; _m setMarkerSizeLocal _sz; _m setMarkerBrushLocal _br;
            _m setMarkerAlpha _al;
            if ((count _sk) > 0) then { missionNamespace setVariable [format ["COMSPEC_ATAK_SK_%1", _m regexReplace ["[^A-Za-z0-9]", "_"]], _sk, true]; };
        } forEach _mk;
        {
            _x params [["_sd", ""], ["_pv", []]];
            private _var = format ["COMSPEC_ATAK_Plan_%1", _sd];
            if (_pv isEqualType [] && {((_pv param [1, []]) isNotEqualTo [])} && {((missionNamespace getVariable [_var, []]) param [1, []]) isEqualTo []}) then {
                missionNamespace setVariable [_var, _pv, true];
            };
        } forEach _plans;
        // Salles d'abord (mobilier et PC recréés), les écrans s'y relient ensuite.
        ["load", _halls] call comspec_atak_native_fnc_tocHall;
        private _n = 0;
        {
            _x params [["_cls", ""], ["_pos", [0, 0, 0]], ["_vd", [0, 1, 0]], ["_vu", [0, 0, 1]], ["_cfg", []], ["_cam", []], ["_pans", []], ["_mos", []], ["_ids", []]];
            if (!(isClass (configFile >> "CfgVehicles" >> _cls)) || {(count _cfg) < 8}) then { continue };
            private _o = ((nearestObjects [ASLToAGL _pos, [_cls], 1.5]) select { alive _x }) param [0, objNull];
            if (isNull _o) then {
                // Écran de la mission disparu de la mission (Eden modifié) : pas recréé.
                if !(_cfg select 7) then { continue };
                _o = createVehicle [_cls, ASLToAGL _pos, [], 0, "CAN_COLLIDE"];
                _o setPosASL _pos;
                _o setVectorDirAndUp [_vd, _vu];
                _o setVariable ["COMSPEC_ATAK_TocOwned", true, true];
                { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
            } else {
                // Écran de la mission déplacé au Zeus à la partie précédente : remis où il était.
                if ((_o distance2D (ASLToAGL _pos)) > 0.05 || {(vectorDir _o) vectorDistance _vd > 0.02}) then { _o setPosASL _pos; _o setVectorDirAndUp [_vd, _vu]; };
            };
            // Mur à plusieurs dalles : contenu de chaque dalle remis à la création de ses relais.
            if (_pans isEqualType [] && {(count _pans) > 0}) then { _o setVariable ["COMSPEC_ATAK_TocPanelCfg", _pans]; };
            ["add", [_o, _cfg select 0, _cfg select 1, _cfg select 2, _cfg select 3, _cfg select 6, _cfg select 7, _cfg param [8, 0], _cfg param [9, ""]]] call comspec_atak_native_fnc_tocScreen;
            // Cadrage gardé (demi-largeur, centre).
            private _c = +(_o getVariable ["COMSPEC_ATAK_TocScr", []]);
            if ((count _c) >= 8) then { _c set [4, _cfg select 4]; _c set [5, _cfg select 5]; _o setVariable ["COMSPEC_ATAK_TocScr", _c, true]; };
            if (_cam isEqualType [] && {(count _cam) >= 2}) then { _o setVariable ["COMSPEC_ATAK_TocCamWant", _cam]; };
            if (_mos isEqualType [] && {(count _mos) >= 5}) then { _o setVariable ["COMSPEC_ATAK_TocMosaic", _mos, true]; };
            // Identifiant stable, salle, allumage.
            // Surface choisie à la désignation (écran d'un autre mod, fenêtre Zeus) : gardée.
            // Champs publiés gardés à leur index ; nouveaux champs ajoutés à la fin (4.8.0 : diffusion, fn_tocBroadcast).
            _ids params [["_uid", ""], ["_hall", ""], ["_off", false], ["_surf", []], ["_opt", []], ["_txt", []], ["_bc", []]];
            if (_bc isEqualType [] && {(count _bc) >= 4}) then { _o setVariable ["COMSPEC_ATAK_TocBc", ["sanitize", _bc] call comspec_atak_native_fnc_tocBroadcast, true]; };
            // Luminosité et distance d'affichage de l'écran (fn_tocConfig) : gardées.
            if (_opt isEqualType [] && {(count _opt) >= 2}) then { _o setVariable ["COMSPEC_ATAK_TocOpt", _opt, true]; };
            // Affichage TEXTE : messages, couleurs, vitesse (nettoyés à nouveau : la sauvegarde vient du profil).
            if (_txt isEqualType [] && {(count _txt) >= 5}) then { _o setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _txt] call comspec_atak_native_fnc_tocText, true]; };
            if (_surf isEqualType [] && {(count _surf) >= 3}) then { _o setVariable ["COMSPEC_ATAK_TocSurf", _surf, true]; };
            if (_uid isNotEqualTo "") then { _o setVariable ["COMSPEC_ATAK_TocUid", _uid, true]; };
            _o setVariable ["COMSPEC_ATAK_TocHall", _hall, true];
            _o setVariable ["COMSPEC_ATAK_TocOff", _off, true];
            _n = _n + 1;
        } forEach _scr;
        private _hm = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _hm set [_x select 0, _x select 1]; }; } forEach _ev;
        ["setEvents", _hm] call comspec_atak_native_fnc_tocFeed;
        ["load", _room] call comspec_atak_native_fnc_tocRoom;
        // Champs du format 3 (vides pour une sauvegarde plus ancienne) : journal, postes nommés, relèves.
        ["load", _data param [7, []]] call comspec_atak_native_fnc_tocJournal;
        ["load", _data param [8, []]] call comspec_atak_native_fnc_tocStation;
        ["load", _data param [9, []]] call comspec_atak_native_fnc_tocHandover;
        ["INFO", "TOC", format ["Salle TOC reprise de la partie précédente : %1 écran(s), état du poste de %2 camp(s)", _n, count _room]] call comspec_atak_native_fnc_log;
    };
};
true
