/*
    Clichés pris avec la caméra du drone (app Drone, bouton PHOTO), complétés avant l'envoi vers Athena.
    Arma ne sait capturer que l'écran du joueur (commande screenshot) : l'écran bascule ~0,4 s sur une caméra posée
    exactement comme la nacelle (même pose, zoom et vision), téléphone et HUD masqués, le PNG est écrit dans
    Documents\Arma 3\Screenshots (nom COMSPEC_DRONE_…), puis la vue revient. Rien ne part tant que le pilote n'a pas
    validé la fiche : titre, description, SSE (rattaché au dossier SSE actif), confidentiel.
      ["take"]               prend le cliché, ouvre sa fiche (onglet PHOTOS de l'app) ;
      ["save"]               garde la fiche en cours (champs du formulaire) ; ["flag", "sse" | "conf"] bascule un drapeau ;
      ["send", n°]           envoie le cliché n° (fiche enregistrée d'abord) au débit simulé du téléphone, par la DLL
                             native (commande NativePhoto, appareil "UAV", flux drone:<netId>), sinon par la chaîne
                             recon d'Overwatch (fn_captureReconImage) ;
      ["keep"]               garde le cliché en cours sur le téléphone sans l'envoyer ;
      ["edit", n°]           rouvre la fiche d'un cliché ; ["forget", n°] l'oublie (second appui) et supprime le fichier ;
      ["list"]               → clichés, le plus récent d'abord : [[n°, entrée]...] ;
      ["caption", entrée]    → légende envoyée à Athena ;
      ["meta"]               → true si la DLL native peut envoyer la fiche en champs réels.
    Entrée (tableau, profil COMSPEC_ATAK_DronePhotos, 40 au plus) :
      [fichier, titre, description, SSE, confidentiel, grille, heure, drone, état, dossier SSE, hauteur sol, cap, date,
       position ASL du drone]
      état : "DRAFT" (fiche ouverte), "LOCAL" (gardé), "SENDING", "SENT", "FAILED".
    DLL native (COMSPECATAKNativeExtension, NativePhoto.cs) : titre, description, SSE (+ dossier), confidentiel, source
    « drone » et nom du drone partent en champs réels, avec la position et le cap du drone ; légende courte.
    Repli (DLL native plus ancienne : réponse vide, ou pas de session Athena utilisable par elle) : chaîne recon
    d'Overwatch, dont NotifyNewPhoto ne porte que la légende : la fiche y est tassée
    ([CONFIDENTIEL] [SSE dossier] Titre : description (drone, grille, hauteur, cap, heure)), position du pilote.
*/
params [["_act", "list"], ["_arg", -1]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
if !(_lib isEqualType []) then { _lib = []; };
private _store = { profileNamespace setVariable ["COMSPEC_ATAK_DronePhotos", _lib]; saveProfileNamespace; };
// Page redessinée : app Drone, ou app Photos (galerie : les clichés du drone y sont envoyés et supprimés aussi).
private _render = { [{ private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_pg in ["DRONE", "PHOTOS"]) then { [_pg] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
// Texte libre : sans retour à la ligne, barre verticale ni guillemets (légende transmise en champ de la DLL).
private _clean = { params ["_t", ["_max", 80]]; (trim (toString ((toArray _t) select { !(_x in [10, 13, 34, 124]) }))) select [0, _max] };
private _cur = _s getOrDefault ["dronePhEdit", -1];
// Fiche ouverte : champs du formulaire relus (absents de l'écran : on garde ce qui est connu).
private _save = {
    if (_cur < 0 || {_cur >= count _lib}) exitWith {};
    private _e = _lib select _cur;
    private _t = ["dronePhTitle", "§"] call comspec_atak_native_fnc_formValue;
    private _dsc = ["dronePhDesc", "§"] call comspec_atak_native_fnc_formValue;
    if (_t isNotEqualTo "§") then { _e set [1, [_t, 60] call _clean]; };
    if (_dsc isNotEqualTo "§") then { _e set [2, [_dsc, 400] call _clean]; };
    call _store;
};
// Identifiants Athena pour la DLL native : servent seulement si elle n'a pas sa propre session (avec COMSPEC Link,
// la session est dans la DLL d'Overwatch ; une clé communauté connue est alors transmise, sinon repli).
private _auth = {
    private _pick = { params ["_m", "_p"]; private _v = missionNamespace getVariable [_m, ""]; if (!(_v isEqualType "") || {_v isEqualTo ""}) then { _v = profileNamespace getVariable [_p, ""]; }; [_v, ""] select !(_v isEqualType "") };
    private _url = ["comspec_overwatch_api_url", "comspec_overwatch_saved_api_url"] call _pick;
    if (_url isEqualTo "") then { _url = profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"]; };
    private _map = missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]];
    [_url, ["comspec_overwatch_api_key", "comspec_overwatch_saved_api_key"] call _pick, ["comspec_overwatch_tenant_id", "comspec_overwatch_saved_tenant_id"] call _pick, getPlayerUID player, str (round ([_map, 1] select !(_map isEqualType 0)))]
};
// DLL native prête pour NativePhoto ? (vide : DLL plus ancienne). Relu au plus toutes les 20 s.
private _metaOk = {
    private _c = missionNamespace getVariable ["COMSPEC_ATAK_NativePhotoOk", [false, -100]];
    if (diag_tickTime - (_c select 1) < 20) exitWith { _c select 0 };
    private _ok = ((["NativePhoto", ["probe"] + (call _auth)] call comspec_atak_native_fnc_extensionCall) select [0, 8]) isEqualTo "OK|ready";
    missionNamespace setVariable ["COMSPEC_ATAK_NativePhotoOk", [_ok, diag_tickTime]];
    _ok
};
// Légende courte (fiche en champs réels) : prise de vue seulement.
private _shortCaption = {
    params ["_e"];
    _e params ["", "", "", "", "", "_grid", "_clock", "_drone", "", "", ["_alt", 0], ["_hdg", 0]];
    format ["Drone %1, grille %2, %3 m sol, cap %4°, %5", _drone, _grid, _alt, _hdg, _clock]
};
private _caption = {
    params ["_e"];
    _e params ["", "_title", "_desc", "_sse", "_conf", "_grid", "_clock", "_drone", "", ["_case", ""], ["_alt", 0], ["_hdg", 0]];
    private _tags = [];
    if (_conf) then { _tags pushBack "[CONFIDENTIEL]"; };
    if (_sse) then { _tags pushBack (["[SSE]", format ["[SSE %1]", _case]] select (_case isNotEqualTo "")); };
    private _head = [_title, "Cliché drone"] select (_title isEqualTo "");
    if (_desc isNotEqualTo "") then { _head = format ["%1 : %2", _head, _desc]; };
    trim format ["%1 %2 (drone %3, grille %4, %5 m sol, cap %6°, %7)", _tags joinString " ", _head, _drone, _grid, _alt, _hdg, _clock]
};

switch (_act) do {
    case "list": {
        private _out = [];
        for "_i" from ((count _lib) - 1) to 0 step -1 do { _out pushBack [_i, _lib select _i]; };
        _out
    };
    case "caption": { [[_arg] call _caption, [_arg] call _shortCaption] select (call _metaOk) };
    case "meta": { call _metaOk };
    case "save": { call _save; };
    case "flag": {
        call _save;
        if (_cur < 0 || {_cur >= count _lib}) exitWith {};
        private _e = _lib select _cur;
        private _i = [4, 3] select (_arg isEqualTo "sse");
        _e set [_i, !(_e select _i)];
        // SSE : rattaché au dossier actif de l'élément (app SSE), relu au moment du choix.
        if (_i isEqualTo 3) then { _e set [9, [missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""], ""] select !(_e select 3)]; };
        call _store;
        call _render;
    };
    case "take": {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (isNull _d || {isNull _cam}) exitWith { ["WARNING", "Ouvrez la caméra du drone pour prendre un cliché"] call _say; };
        if !((["link"] call comspec_atak_native_fnc_droneAction) select 2) exitWith { ["WARNING", "Drone hors liaison : pas d'image à photographier"] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false] || {missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]} || {missionNamespace getVariable ["COMSPEC_WebUavPhotoBusy", false]}) exitWith { ["INFO", "Cliché déjà en cours"] call _say; };
        call _save;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotBusy", true];
        [_d, _cam] spawn {
            params ["_d", "_cam"];
            disableSerialization;
            private _hud = shownHUD;
            if (!isNil "ace_interact_menu_fnc_hideMenu") then { [] call ace_interact_menu_fnc_hideMenu; };
            // Écran du téléphone masqué le temps du cliché (il serait capturé par-dessus l'image).
            private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
            private _hidden = [];
            if (!isNull _phone) then { _hidden = (allControls _phone) select { ctrlShown _x }; { _x ctrlShow false; } forEach _hidden; };
            // Caméra plein écran : posée et orientée chaque image comme la nacelle (boucle de fn_droneAction).
            private _shot = "camera" camCreate (getPosATL _cam);
            _shot attachTo [_d, _cam getVariable ["off", [0, 0.3, -0.3]]];
            _shot setVectorDirAndUp [_d vectorWorldToModelVisual (vectorDirVisual _cam), _d vectorWorldToModelVisual (vectorUpVisual _cam)];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneShotCam", _shot];
            _shot cameraEffect ["Internal", "Back"];
            _shot camSetFov (0.7 / ((missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) max 1));
            _shot camCommit 0;
            private _vision = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
            if (_vision isEqualTo 1) then { camUseNVG true; };
            if (_vision isEqualTo 2) then { true setCamUseTI 0; };
            showHUD false;
            uiSleep 0.3;
            private _file = format ["COMSPEC_DRONE_%1_%2.png", (floor diag_tickTime) toFixed 0, (floor random 99999) toFixed 0];
            private _r = screenshot _file;
            private _ok = !(_r isEqualType true) || {_r};
            // Le fichier est écrit en fin d'image : on garde la vue deux images de plus.
            uiSleep 0.15;
            camUseNVG false;
            false setCamUseTI 0;
            _shot cameraEffect ["Terminate", "Back"];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneShotCam", objNull];
            camDestroy _shot;
            { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
            showHUD _hud;
            // La vue plein écran a pu dérégler le rendu de la nacelle : caméra et cible neuves au prochain dessin.
            private _c2 = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
            if (!isNull _c2) then { _c2 setVariable ["rendering", false]; };
            missionNamespace setVariable ["COMSPEC_ATAK_DroneShotBusy", false];
            if (!_ok) exitWith {
                ["WARNING", "Cliché refusé par le jeu : passez la qualité HDR au moins sur Moyen (options vidéo), puis recommencez", 6, 40] call comspec_atak_native_fnc_notify;
                [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            };
            playSound "ClickSoft";
            private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
            if !(_lib isEqualType []) then { _lib = []; };
            (date) params ["_yy", "_mo", "_dd"];
            _lib pushBack [_file, "", "", false, false, [getPosASL _d, 8] call comspec_atak_native_fnc_gridRef, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString,
                [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd, "DRAFT", "", round ((getPosATL _d) select 2), round (getDirVisual _d) mod 360, format ["%1-%2-%3", _yy, _mo, _dd], getPosASL _d];
            while { (count _lib) > 40 } do { _lib deleteAt 0; };
            profileNamespace setVariable ["COMSPEC_ATAK_DronePhotos", _lib];
            saveProfileNamespace;
            // Fiche du cliché : onglet PHOTOS, menus visibles (l'image se réduit au-dessus).
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            _s set ["dronePhEdit", (count _lib) - 1];
            _s set ["droneTab", "PHOTO"];
            _s set ["droneCamSplit", true];
            ["log", format ["Cliché pris en %1 (%2), fiche à compléter avant l'envoi", (_lib select ((count _lib) - 1)) select 5, _file]] call comspec_atak_native_fnc_droneAction;
            if !(_s getOrDefault ["interactive", false]) then {
                ["INFO", "Cliché pris : prenez le téléphone en main (Ctrl+Maj+U) pour compléter sa fiche et l'envoyer", 6, 40] call comspec_atak_native_fnc_notify;
            } else { ["SUCCESS", "Cliché pris : complétez sa fiche, puis ENVOYER", 4, 40] call comspec_atak_native_fnc_notify; };
            [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
    case "keep": {
        call _save;
        if (_cur >= 0 && {_cur < count _lib}) then {
            private _e = _lib select _cur;
            if ((_e select 8) isEqualTo "DRAFT") then { _e set [8, "LOCAL"]; };
            call _store;
            ["INFO", "Cliché gardé sur le téléphone (onglet PHOTOS : ENVOYER quand vous voulez)"] call _say;
        };
        _s set ["dronePhEdit", -1];
        call _render;
    };
    case "edit": { call _save; _s set ["dronePhEdit", _arg]; _s set ["droneTab", "PHOTO"]; call _render; };
    case "send": {
        call _save;
        private _i = [_arg, _cur] select (_arg isEqualTo -1);
        if (_i < 0 || {_i >= count _lib}) exitWith {};
        // DLL native prête : envoi direct. Sinon la chaîne d'Overwatch doit être chargée et connectée.
        private _nativeOk = call _metaOk;
        if (!_nativeOk && {!([] call comspec_atak_native_fnc_bridge)}) exitWith { ["WARNING", "Envoi impossible : connectez-vous d'abord à Athena (le cliché reste sur le téléphone)"] call _say; };
        if (!_nativeOk && {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])}) exitWith { ["WARNING", "Envoi impossible : connectez-vous d'abord à Athena (le cliché reste sur le téléphone)"] call _say; };
        private _e = _lib select _i;
        _e set [8, "SENDING"];
        call _store;
        if (_i isEqualTo _cur) then { _s set ["dronePhEdit", -1]; };
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _feed = format ["drone:%1", [netId _d, "phone"] select (isNull _d)];
        // Environ 600 Ko : avec un débit faible, l'envoi prend du temps (file du téléphone si aucun réseau).
        // Fiche en champs réels par la DLL native : ["send", identifiants (5), fichier, auteur, x, y, z, grille, cap,
        // altitude, légende, unité, camp, mission, appareil, heure, flux, titre, description, SSE, dossier, confidentiel,
        // source, drone] (voir NativePhoto.cs). Heure vide : la DLL met l'heure réelle.
        _e params ["_f", "_title", "_desc", "_sse", "_conf", "_grid", "", "_drone", "", ["_case", ""], ["_alt", 0], ["_hdg", 0], "", ["_pos", []]];
        private _author = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { [] call comspec_overwatch_connect_fnc_getCallsign } else { "" };
        if !(_author isEqualType "" && {_author isNotEqualTo ""}) then { _author = name player; };
        private _side = switch (side group player) do { case east: { "EAST" }; case independent: { "GUER" }; case civilian: { "CIV" }; default { "WEST" }; };
        private _xyz = if ((count _pos) >= 3) then { [str (_pos select 0), str (_pos select 1), str (_pos select 2)] } else { ["", "", ""] };
        private _native = ["send"] + (call _auth) + [_f, _author] + _xyz + [_grid, str _hdg, str _alt, [_e] call _shortCaption, _feed, _side,
            missionNamespace getVariable ["COMSPEC_MissionId", "op_1"], "UAV", "", _feed,
            [_title, "Cliché drone"] select (_title isEqualTo ""), _desc, ["0", "1"] select _sse, ["", _case] select _sse, ["0", "1"] select _conf, "drone", _drone];
        [{
            params ["_file", "_caption", "_feed", "_native"];
            // DLL native d'abord ; réponse vide (DLL plus ancienne) ou refus : repli sur Overwatch, fiche dans la légende.
            private _r = ["NativePhoto", _native] call comspec_atak_native_fnc_extensionCall;
            private _viaNative = (_r select [0, 3]) isEqualTo "OK|";
            private _ok = _viaNative || {!isNil "comspec_overwatch_connect_fnc_captureReconImage" && {[_file, _caption, "UAV", _feed, true, false, true] call comspec_overwatch_connect_fnc_captureReconImage}};
            private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
            private _j = _lib findIf { (_x select 0) isEqualTo _file };
            if (_j >= 0) then { (_lib select _j) set [8, ["FAILED", ["SENT", "SENDING"] select _viaNative] select (_ok isEqualTo true)]; saveProfileNamespace; };
            // Envoi natif : état réel relu auprès de la DLL (fichier trouvé, POST accepté) pendant 2 min au plus.
            if (_viaNative) then {
                [_file] spawn {
                    params ["_file"];
                    private _st = "OK|queued";
                    private _until = diag_tickTime + 120;
                    while { diag_tickTime < _until && {(_st select [0, 9]) isEqualTo "OK|queued"} } do {
                        uiSleep 2;
                        _st = ["NativePhoto", ["status", _file]] call comspec_atak_native_fnc_extensionCall;
                    };
                    private _done = (_st select [0, 11]) isEqualTo "OK|uploaded" || {(_st select [0, 12]) isEqualTo "OK|duplicate"};
                    private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
                    private _j = _lib findIf { (_x select 0) isEqualTo _file };
                    if (_j >= 0) then { (_lib select _j) set [8, ["FAILED", "SENT"] select _done]; saveProfileNamespace; };
                    if (!_done) then { ["WARNING", format ["Cliché du drone non reçu par Athena (%1)", (_st splitString "|") param [1, "délai dépassé"]], 5, 40] call comspec_atak_native_fnc_notify; };
                    [{ private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_pg in ["DRONE", "PHOTOS"]) then { [_pg] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
                };
            };
            [["WARNING", "SUCCESS"] select (_ok isEqualTo true), ["Cliché du drone non envoyé : voir le détail dans Photos", "Cliché du drone en cours d'envoi vers Athena"] select (_ok isEqualTo true), 4, 30] call comspec_atak_native_fnc_notify;
            ["log", format ["Cliché %1 : %2", _file, ["envoi refusé", "envoyé vers Athena"] select (_ok isEqualTo true)], ["#e5483a", "#5cc76b"] select (_ok isEqualTo true)] call comspec_atak_native_fnc_droneAction;
            [{ private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_pg in ["DRONE", "PHOTOS"]) then { [_pg] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        }, [_e select 0, [_e] call _caption, _feed, _native], "Cliché drone", 600] call comspec_atak_native_fnc_netSend;
        ["INFO", "Cliché du drone : envoi lancé"] call _say;
        call _render;
    };
    case "forget": {
        if (_arg < 0 || {_arg >= count _lib}) exitWith {};
        // Second appui dans les 4 s pour confirmer.
        private _c = _s getOrDefault ["dronePhDel", [-1, -10]];
        if ((_c select 0) isNotEqualTo _arg || {diag_tickTime - (_c select 1) > 4}) exitWith {
            _s set ["dronePhDel", [_arg, diag_tickTime]];
            ["WARNING", "Appuyez encore pour supprimer ce cliché du téléphone"] call _say;
            call _render;
        };
        _s set ["dronePhDel", [-1, -10]];
        private _file = (_lib select _arg) select 0;
        // Fichier du poste : effacé par la DLL (dossiers COMSPEC et Screenshots seulement).
        private _find = { ((uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []]) select { (toLower (_x select 1)) isEqualTo (toLower _file) }) param [0, []] };
        private _path = call _find;
        if (_path isEqualTo []) then { ["list"] call comspec_atak_native_fnc_photoLibrary; _path = call _find; };
        if (_path isEqualTo []) then { _path = [_file]; };
        "COMSPECExtension" callExtension ["DeleteLocalFile", [_path select 0]];
        _lib deleteAt _arg;
        call _store;
        if (_cur isEqualTo _arg) then { _s set ["dronePhEdit", -1]; } else { if (_cur > _arg) then { _s set ["dronePhEdit", _cur - 1]; }; };
        ["INFO", "Cliché supprimé"] call _say;
        call _render;
    };
};
