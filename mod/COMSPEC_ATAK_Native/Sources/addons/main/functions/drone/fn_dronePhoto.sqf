/*
    Clichés pris avec la caméra du drone (app Drone, VISEUR PHOTO), complétés avant l'envoi vers Athena.
    Viseur photo (vue SHOT de fn_pageDrone) : on vise comme dans la vue caméra (glisser, flèches, molette et + / -),
    on marque une cible (MARQUER UNE CIBLE, même marquage LOCK), on surligne d'un cadre ou d'un cercle jaune (4 au plus,
    missionNamespace COMSPEC_ATAK_DroneShotMarks : [[genre, u0, v0, u1, v1]...] en fractions de l'image), puis on prend.
    Arma ne sait capturer que l'écran du joueur (commande screenshot) : l'écran bascule ~0,4 s sur une caméra posée
    exactement comme la nacelle (même pose, zoom et vision), téléphone et HUD masqués, les surlignages posés sur l'écran
    (worldToScreen des rayons de leurs coins), le PNG est écrit dans Documents\Arma 3\Screenshots (nom COMSPEC_DRONE_…),
    puis la vue revient. Rien ne part tant que le pilote n'a pas validé la fiche (titre obligatoire), ouverte en fenêtre plein écran
    ("modal") : aperçu en grand, titre, description, SSE (rattaché au dossier SSE actif), confidentiel.
      ["view"] / ["viewExit"] ouvre / quitte le viseur ; ["tool", "BOX" | "CIRCLE"] outil de surlignage (bascule) ;
      ["markAdd", [u0, v0, u1, v1]] surlignage tracé ; ["markClear"] les efface ;
      ["take"]               prend le cliché, ouvre sa fiche (fenêtre de transmission) ;
      ["modal", [largeur, hauteur]]  dessine la fenêtre de transmission de la fiche ouverte (fn_pageDrone) ;
      ["save"]               garde la fiche en cours (champs du formulaire) ; ["flag", "sse" | "conf"] bascule un drapeau ;
      ["send", n°]           envoie le cliché n° (fiche enregistrée d'abord) au débit simulé du téléphone, par la chaîne
                             recon de COMSPEC Link (fn_captureReconImage, appareil "UAV", flux drone:<netId>) ;
      ["keep"]               garde le cliché en cours sur le téléphone sans l'envoyer ;
      ["edit", n°]           rouvre la fiche d'un cliché ; ["forget", n°] l'oublie (second appui) et supprime le fichier ;
      ["list"]               → clichés, le plus récent d'abord : [[n°, entrée]...] ;
      ["caption", entrée]    → légende envoyée à Athena ;
      ["meta"]               → true si COMSPEC Link (2.0.66+) envoie la fiche en champs réels.
    Entrée (tableau, profil COMSPEC_ATAK_DronePhotos, 40 au plus) :
      [fichier, titre, description, SSE, confidentiel, grille, heure, drone, état, dossier SSE, hauteur sol, cap, date,
       position ASL du drone, pose de la prise de vue [position ASL, direction, haut, champ, vision, surlignages]]
      état : "DRAFT" (fiche ouverte), "LOCAL" (gardé), "SENDING", "SENT", "FAILED".
    COMSPEC Link 2.0.66+ : titre, description, SSE (+ dossier), confidentiel, source « drone » et nom du drone partent
    en champs réels de NotifyNewPhoto (8e paramètre de fn_captureReconImage), avec la position et le cap du drone ;
    légende courte. L'envoi est suivi dans le journal des envois de la DLL (« Photo drone », page Files d'envoi).
    DLL plus ancienne (elle ignorerait ces champs) : la fiche est tassée dans la légende
    ([CONFIDENTIEL] [SSE dossier] Titre : description (drone, grille, hauteur, cap, heure)), position du pilote.
*/
params [["_act", "list"], ["_arg", -1]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
if !(_lib isEqualType []) then { _lib = []; };
private _store = { profileNamespace setVariable ["COMSPEC_ATAK_DronePhotos", _lib]; saveProfileNamespace; };
// Page redessinée : app Drone, ou app Galerie ( : les clichés du drone y sont envoyés et supprimés aussi).
private _render = { [{ private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_pg in ["DRONE", "GALLERY"]) then { [_pg] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
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
// COMSPEC Link 2.0.66 ou plus (champs de la fiche dans NotifyNewPhoto) ? Version relue une fois par partie.
private _metaOk = {
    private _k = missionNamespace getVariable "COMSPEC_ATAK_PhotoMetaOk";
    if (!isNil "_k") exitWith { _k };
    private _v = "COMSPECExtension" callExtension ["GetExtensionVersion", []];
    if (_v isEqualType []) then { _v = _v param [0, ""]; };
    if !(_v isEqualType "") then { _v = str _v; };
    // « OK|COMSPECExtension 2.0.66 » ; vide : DLL pas encore chargée, on redemandera.
    if (_v isEqualTo "") exitWith { false };
    private _w = _v splitString " |";
    private _n = ((_w param [(count _w) - 1, ""]) splitString ".") apply { parseNumber _x };
    _n params [["_a", 0], ["_b", 0], ["_c", 0]];
    private _ok = (_a > 2) || {_a == 2 && {(_b > 0) || {_c >= 66}}};
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoMetaOk", _ok];
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

// Aperçu de la fiche : une caméra posée là où était celle du cliché (même direction, même champ, même vision) rend
// sur sa propre cible (anneau de 4 noms comspec_droneprev0..3 : une cible coupée puis relancée donnait une image noire).
// Elle ne tourne que tant que la fiche est ouverte sur la page Drone (vérifié chaque seconde). "" sans pose connue.
private _prevTex = {
    params [["_pose", []]];
    _pose params [["_pos", []], ["_dir", []], ["_up", []], ["_fov", 0.7], ["_vis", 0]];
    if ((count _pos) < 3 || {(count _dir) < 3} || {!isPiPEnabled}) exitWith { "" };
    private _key = str [_pos, _dir];
    private _pc = missionNamespace getVariable ["COMSPEC_ATAK_DronePrevCam", objNull];
    if (!isNull _pc && {(([_pc, "key", ""] call comspec_atak_native_fnc_camVar)) isEqualTo _key}) exitWith { ([_pc, "tex", ""] call comspec_atak_native_fnc_camVar) };
    if (!isNull _pc) then { _pc cameraEffect ["Terminate", "Back", ([_pc, "r2t", ""] call comspec_atak_native_fnc_camVar)]; camDestroy _pc; };
    private _n = ((missionNamespace getVariable ["COMSPEC_ATAK_DronePrevN", 0]) + 1) mod 4;
    missionNamespace setVariable ["COMSPEC_ATAK_DronePrevN", _n];
    private _name = format ["comspec_droneprev%1", _n];
    _pc = "camera" camCreate (ASLToAGL _pos);
    _pc cameraEffect ["Internal", "Back", _name];
    _pc setPosASL _pos;
    _pc setVectorDirAndUp [_dir, _up];
    _pc camSetFov _fov;
    _pc camCommit 0;
    _name setPiPEffect [_vis];
    // Texture 512 (1024 auparavant) : l'aperçu tient dans la fiche, quatre fois moins de pixels redessinés à chaque image.
    private _tex = format ["#(argb,512,512,1)r2t(%1,1.7778)", _name];
    [_pc, "key", _key, true] call comspec_atak_native_fnc_camVar;
    [_pc, "r2t", _name, true] call comspec_atak_native_fnc_camVar;
    [_pc, "tex", _tex, true] call comspec_atak_native_fnc_camVar;
    missionNamespace setVariable ["COMSPEC_ATAK_DronePrevCam", _pc];
    if ((missionNamespace getVariable ["COMSPEC_ATAK_DronePrevPfh", -1]) < 0) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DronePrevPfh", [{
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_st getOrDefault ["dronePhEdit", -1]) >= 0 && {(_st getOrDefault ["activePage", ""]) isEqualTo "DRONE"} && {!isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])}) exitWith {};
            private _c = missionNamespace getVariable ["COMSPEC_ATAK_DronePrevCam", objNull];
            if (!isNull _c) then { _c cameraEffect ["Terminate", "Back", ([_c, "r2t", ""] call comspec_atak_native_fnc_camVar)]; camDestroy _c; };
            missionNamespace setVariable ["COMSPEC_ATAK_DronePrevCam", objNull];
            [missionNamespace getVariable ["COMSPEC_ATAK_DronePrevPfh", -1]] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_DronePrevPfh", -1];
        }, 1] call CBA_fnc_addPerFrameHandler];
    };
    _tex
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
    // Viseur photo (vue SHOT de l'app Drone) : caméra ouverte au besoin, outils et surlignages remis à zéro.
    case "view": {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (isNull _d) exitWith { ["WARNING", "Aucun drone appairé"] call _say; };
        if ((["camBind"] call comspec_atak_native_fnc_droneAction) isEqualTo "") exitWith { ["WARNING", "Caméra du drone indisponible"] call _say; };
        _s set ["droneShotView", true];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneShotTool", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotMarks", []];
        call _render;
    };
    case "viewExit": {
        _s set ["droneShotView", false];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneShotTool", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotMarks", []];
        call _render;
    };
    // Outil de surlignage : "BOX" (cadre) ou "CIRCLE" (cercle), un second appui le repose (la souris tourne la caméra).
    case "tool": {
        private _t = uiNamespace getVariable ["COMSPEC_ATAK_DroneShotTool", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneShotTool", [_arg, ""] select (_t isEqualTo _arg)];
        // Tracer et marquer au clic ne vont pas ensemble : le marquage s'arrête.
        if (_t isNotEqualTo _arg) then { uiNamespace setVariable ["COMSPEC_ATAK_DroneMarkMode", false]; };
        call _render;
    };
    // Surlignage tracé : [u0, v0, u1, v1] en fractions de l'image (un simple clic ne trace rien) ; 4 au plus.
    case "markAdd": {
        _arg params ["_u0", "_v0", "_u1", "_v1"];
        private _t = uiNamespace getVariable ["COMSPEC_ATAK_DroneShotTool", ""];
        if (_t isEqualTo "" || {(abs (_u1 - _u0)) < 0.015 && {(abs (_v1 - _v0)) < 0.015}}) exitWith { call _render; };
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_DroneShotMarks", []];
        if ((count _m) < 4) then { _m pushBack [_t, _u0 min _u1, _v0 min _v1, _u0 max _u1, _v0 max _v1]; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotMarks", _m];
        if ((count _m) >= 4) then { uiNamespace setVariable ["COMSPEC_ATAK_DroneShotTool", ""]; };
        call _render;
    };
    case "markClear": { missionNamespace setVariable ["COMSPEC_ATAK_DroneShotMarks", []]; call _render; };
    case "take": {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (isNull _d || {isNull _cam}) exitWith { ["WARNING", "Ouvrez la caméra du drone pour prendre un cliché"] call _say; };
        if !((["link"] call comspec_atak_native_fnc_droneAction) select 2) exitWith { ["WARNING", "Drone hors liaison : pas d'image à photographier"] call _say; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false] || {missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]} || {missionNamespace getVariable ["COMSPEC_WebUavPhotoBusy", false]}) exitWith { ["INFO", "Cliché déjà en cours"] call _say; };
        call _save;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneShotBusy", true];
        [_d, _cam, +(missionNamespace getVariable ["COMSPEC_ATAK_DroneShotMarks", []])] spawn {
            params ["_d", "_cam", "_marks"];
            disableSerialization;
            private _hud = shownHUD;
            if (!isNil "ace_interact_menu_fnc_hideMenu") then { [] call ace_interact_menu_fnc_hideMenu; };
            // Écran du téléphone masqué le temps du cliché (il serait capturé par-dessus l'image).
            private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
            private _hidden = [];
            if (!isNull _phone) then { _hidden = (allControls _phone) select { ctrlShown _x }; { _x ctrlShow false; } forEach _hidden; };
            // Caméra plein écran : posée et orientée chaque image comme la nacelle (boucle de fn_droneAction).
            private _zoom = (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) max 1;
            private _shot = "camera" camCreate (getPosATL _cam);
            _shot attachTo [_d, ([_cam, "off", [0, 0.3, -0.3]] call comspec_atak_native_fnc_camVar)];
            _shot setVectorDirAndUp [_d vectorWorldToModelVisual (vectorDirVisual _cam), _d vectorWorldToModelVisual (vectorUpVisual _cam)];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneShotCam", _shot];
            _shot cameraEffect ["Internal", "Back"];
            _shot camSetFov (0.7 / _zoom);
            _shot camCommit 0;
            private _vision = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
            if (_vision isEqualTo 1) then { camUseNVG true; };
            if (_vision isEqualTo 2) then { true setCamUseTI 0; };
            showHUD false;
            uiSleep 0.3;
            // Surlignages du viseur posés sur l'écran capturé : chaque coin est un rayon de la caméra (même champ que
            // l'image du viseur, 16/9), ramené à l'écran par worldToScreen.
            private _host = [_phone, findDisplay 46] select (isNull _phone);
            private _over = [];
            private _tv = 0.7 / _zoom;
            {
                _x params ["_kind", "_u0", "_v0", "_u1", "_v1"];
                private _pts = [[_u0, _v0], [_u1, _v1]] apply {
                    _x params ["_u", "_v"];
                    worldToScreen (_shot modelToWorldVisual [(_u - 0.5) * 2 * _tv * 1.7778 * 100, 100, (0.5 - _v) * 2 * _tv * 100])
                };
                if ((_pts select 0) isNotEqualTo [] && {(_pts select 1) isNotEqualTo []} && {!isNull _host}) then {
                    (_pts select 0) params ["_ax", "_ay"];
                    (_pts select 1) params ["_bx", "_by"];
                    private _sx = _ax min _bx;
                    private _sy = _ay min _by;
                    private _sw = abs (_bx - _ax);
                    private _sh = abs (_by - _ay);
                    private _tw = pixelW * 4;
                    private _th = pixelH * 4;
                    private _rects = if (_kind isEqualTo "BOX") then { [[_sx, _sy, _sw, _th], [_sx, _sy + _sh - _th, _sw, _th], [_sx, _sy, _tw, _sh], [_sx + _sw - _tw, _sy, _tw, _sh]] } else { [] };
                    {
                        private _c = _host ctrlCreate ["RscText", -1];
                        _c ctrlSetPosition _x;
                        _c ctrlSetBackgroundColor [1, 0.85, 0.1, 0.95];
                        _c ctrlCommit 0;
                        _over pushBack _c;
                    } forEach _rects;
                    if (_kind isEqualTo "CIRCLE") then {
                        private _c = _host ctrlCreate ["RscPicture", -1];
                        _c ctrlSetText "\A3\ui_f\data\map\markers\military\circle_CA.paa"; // i18n:brut
                        _c ctrlSetTextColor [1, 0.85, 0.1, 0.95];
                        _c ctrlSetPosition [_sx, _sy, _sw, _sh];
                        _c ctrlCommit 0;
                        _over pushBack _c;
                    };
                };
            } forEach _marks;
            if ((count _over) > 0) then { uiSleep 0.05; };
            private _file = format ["COMSPEC_DRONE_%1_%2.png", (floor diag_tickTime) toFixed 0, (floor random 99999) toFixed 0];
            // Pose de la prise de vue, gardée pour l'aperçu de la fiche (caméra posée au même endroit, même champ).
            private _pose = [getPosASLVisual _shot, vectorDirVisual _shot, vectorUpVisual _shot, 0.7 / _zoom, _vision, _marks];
            private _r = screenshot _file;
            private _ok = !(_r isEqualType true) || {_r};
            // Le fichier est écrit en fin d'image : on garde la vue deux images de plus.
            uiSleep 0.15;
            { ctrlDelete _x; } forEach _over;
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
                [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd, "DRAFT", "", round ((getPosATL _d) select 2), round (getDirVisual _d) mod 360, format ["%1-%2-%3", _yy, _mo, _dd], getPosASL _d, _pose];
            while { (count _lib) > 40 } do { _lib deleteAt 0; };
            profileNamespace setVariable ["COMSPEC_ATAK_DronePhotos", _lib];
            saveProfileNamespace;
            // Fiche du cliché : fenêtre de transmission plein écran ; le viseur se referme, surlignages oubliés.
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            _s set ["dronePhEdit", (count _lib) - 1];
            _s set ["droneShotView", false];
            uiNamespace setVariable ["COMSPEC_ATAK_DroneShotTool", ""];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneShotMarks", []];
            ["log", format ["Cliché pris en %1 (%2)%3, fiche à compléter avant l'envoi", (_lib select ((count _lib) - 1)) select 5, _file, ["", format [", %1 surlignage(s)", count _marks]] select ((count _marks) > 0)]] call comspec_atak_native_fnc_droneAction;
            if !(_s getOrDefault ["interactive", false]) then {
                ["INFO", "Cliché pris : prenez le téléphone en main (Ctrl+Maj+U) pour compléter sa fiche et l'envoyer", 6, 40] call comspec_atak_native_fnc_notify;
            } else { ["SUCCESS", "Cliché pris : complétez sa fiche, puis ENVOYER", 4, 40] call comspec_atak_native_fnc_notify; };
            [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
    // Fenêtre de transmission plein écran (à la place de toute la page Drone) : aperçu en grand, champs lisibles,
    // SSE et confidentiel, puis SUPPRIMER / ANNULER (garder sans envoyer) / ENVOYER. Tient sans défilement.
    case "modal": {
        disableSerialization;
        _arg params ["_bw", "_bh"];
        if (_cur < 0 || {_cur >= count _lib}) exitWith {};
        private _e = _lib select _cur;
        _e params ["_file", "_title", "_desc", "_sse", "_cf", "_grid", "_clock", "_drone", "_state", ["_case", ""], ["_alt", 0], ["_hdg", 0], ["_date", ""], "", ["_pose", []]];
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _pad = (_l get "pad") * 2;
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text] call comspec_atak_native_fnc_pageCtrl };
        ([] call comspec_atak_native_fnc_accent) params ["", "_accHex"];
        private _bgc = ["COMSPEC_RscText", [0, 0, _bw, _bh]] call _mk;
        _bgc ctrlSetBackgroundColor [0.02, 0.025, 0.022, 0.98];
        private _w = _bw - 2 * _pad;
        private _y = _pad / 2;
        // En-tête : titre de la fenêtre et prise de vue (date, heure, drone, grille, hauteur, cap).
        private _hd = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 2.4]] call _mk;
        _hd ctrlEnable false;
        _hd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.1' color='%1'>TRANSMETTRE LE CLICHÉ</t><br/><t size='0.8' color='#8a9a93'>%2 %3 · %4 · %5 · %6 m sol · cap %7°</t>",
            _accHex, _date, _clock, _drone, _grid, _alt, _hdg]) call comspec_atak_native_fnc_tr);
        private _hh = (ctrlTextHeight _hd) max (_fs * 1.2);
        _hd ctrlSetPosition [_pad, _y, _w, _hh];
        _hd ctrlCommit 0;
        _y = _y + _hh + _pad / 3;
        // Hauteurs : boutons en bas, champs, puis l'aperçu prend ce qui reste (jusqu'à 45 % de l'écran).
        private _btnH = _font * 1.6;
        private _yb = _bh - _pad / 2 - _btnH;
        private _lblH = _fs * 1.15;
        private _editH = _font * 1.5;
        private _togH = _font * 1.45;
        private _capH = _fs * 2.2;
        private _memoL = 3;
        private _fieldsH = { 2 * _lblH + _editH + (_font * 1.15 * _memoL + _font * 0.3) + _togH + _capH + _pad * 1.4 };
        private _avail = _yb - _y - (call _fieldsH);
        if (_avail < _bh * 0.2) then { _memoL = 2; _avail = _yb - _y - (call _fieldsH); };
        private _ph = (_avail max (_bh * 0.12)) min (_bh * 0.45);
        private _pw = _ph / (0.5625 * _ratio);
        if (_pw > _w) then { _pw = _w; _ph = _pw * 0.5625 * _ratio; };
        private _px = (_bw - _pw) / 2;
        private _pf = ["COMSPEC_RscText", [_px - pixelW * 2, _y - pixelH * 2, _pw + pixelW * 4, _ph + pixelH * 4]] call _mk;
        _pf ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.5];
        private _pb = ["COMSPEC_RscText", [_px, _y, _pw, _ph]] call _mk;
        _pb ctrlSetBackgroundColor [0.08, 0.09, 0.085, 1];
        private _tex = [_pose] call _prevTex;
        if (_tex isNotEqualTo "") then {
            ["RscPicture", [_px, _y, _pw, _ph], _tex] call _mk;
            // Surlignages du cliché redessinés sur l'aperçu.
            {
                _x params ["_kind", "_u0", "_v0", "_u1", "_v1"];
                private _sx = _px + _u0 * _pw;
                private _sy = _y + _v0 * _ph;
                private _sw = (_u1 - _u0) * _pw;
                private _sh = (_v1 - _v0) * _ph;
                if (_kind isEqualTo "BOX") then {
                    { private _c = ["COMSPEC_RscText", _x] call _mk; _c ctrlSetBackgroundColor [1, 0.85, 0.1, 0.95]; } forEach [
                        [_sx, _sy, _sw, pixelH * 2], [_sx, _sy + _sh - pixelH * 2, _sw, pixelH * 2], [_sx, _sy, pixelW * 2, _sh], [_sx + _sw - pixelW * 2, _sy, pixelW * 2, _sh]];
                } else {
                    private _c = ["RscPicture", [_sx, _sy, _sw, _sh], "\A3\ui_f\data\map\markers\military\circle_CA.paa"] call _mk;
                    _c ctrlSetTextColor [1, 0.85, 0.1, 0.95];
                };
            } forEach (_pose param [5, []]);
            private _cap = ["COMSPEC_RscStructuredText", [_px, _y + _ph - _fs * 1.1, _pw, _fs * 1.1]] call _mk;
            _cap ctrlEnable false;
            _cap ctrlSetStructuredText parseText (("<t size='0.72' align='right' shadow='2' shadowColor='#000000' color='#e8f0ea'>APERÇU · même cadrage, vue du moment</t>") call comspec_atak_native_fnc_tr);
        } else {
            private _na = ["COMSPEC_RscStructuredText", [_px, _y + _ph * 0.38, _pw, _fs * 2.4]] call _mk;
            _na ctrlEnable false;
            _na ctrlSetStructuredText parseText ((format ["<t align='center' size='0.85' color='#8a9a93'>Aperçu indisponible pour ce cliché<br/><t size='0.75'>%1</t></t>", _file]) call comspec_atak_native_fnc_tr);
        };
        _y = _y + _ph + _pad / 2;
        // Champs : titre, description (relus par ["save"] avant tout redessin).
        private _fields = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
        private _lb1 = ["COMSPEC_RscLabel", [_pad, _y, _w, _lblH], "TITRE (obligatoire, 60 caractères)"] call _mk;
        _lb1 ctrlSetFontHeight (_fs * 0.9);
        _y = _y + _lblH;
        private _ed = ["COMSPEC_RscEdit", [_pad, _y, _w, _editH], _title] call _mk;
        _ed ctrlSetFontHeight _font;
        _fields set ["dronePhTitle", _ed];
        _y = _y + _editH + _pad / 3;
        private _lb2 = ["COMSPEC_RscLabel", [_pad, _y, _w, _lblH], "DESCRIPTION (ce que montre le cliché)"] call _mk;
        _lb2 ctrlSetFontHeight (_fs * 0.9);
        _y = _y + _lblH;
        private _memoH = _font * 1.15 * _memoL + _font * 0.3;
        private _md = ["COMSPEC_RscEditMulti", [_pad, _y, _w, _memoH], _desc] call _mk;
        _md ctrlSetFontHeight _font;
        _fields set ["dronePhDesc", _md];
        uiNamespace setVariable ["COMSPEC_ATAK_Form", _fields];
        _y = _y + _memoH + _pad / 3;
        // SSE et confidentiel : deux bascules côte à côte, vert = oui (rouge pour confidentiel).
        private _tw = (_w - _pad / 2) / 2;
        {
            _x params ["_lbl", "_on", "_flag", "_onCol", "_tip"];
            private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_tw + _pad / 2), _y, _tw, _togH], format ["%1 : %2", _lbl, ["NON", "OUI"] select _on]] call _mk;
            _b ctrlSetFontHeight (_fs * 0.95);
            if (_on) then { _b ctrlSetBackgroundColor _onCol; _b ctrlSetTextColor [1, 1, 1, 1]; } else { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; };
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            [_b, compile format ["['flag', %1] call comspec_atak_native_fnc_dronePhoto;", str _flag]] call comspec_atak_native_fnc_clickNext;
        } forEach [
            ["SSE", _sse, "sse", [0.85, 0.55, 0.12, 1], "Exploitation de site : rattache le cliché au dossier SSE actif (app SSE)"],
            ["CONFIDENTIEL", _cf, "conf", [0.78, 0.2, 0.16, 1], "Marqué [CONFIDENTIEL] en tête de la légende sur Athena"]
        ];
        _y = _y + _togH + _pad / 3;
        private _active = missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""];
        if !(_active isEqualType "") then { _active = ""; };
        private _sseTxt = if (_sse) then { [format ["Dossier SSE : %1", [_active, _case] select (_case isNotEqualTo "")], "SSE sans dossier (aucun dossier actif dans l'app SSE)"] select (([_active, _case] select (_case isNotEqualTo "")) isEqualTo "") } else { "" };
        private _cp = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _capH]] call _mk;
        _cp ctrlEnable false;
        _cp ctrlSetStructuredText parseText ((format ["<t size='0.75' color='#8a9a93'>%1%2Légende envoyée : %3</t>", _sseTxt, ["", " · "] select (_sseTxt isNotEqualTo ""), ["caption", _e] call comspec_atak_native_fnc_dronePhoto]) call comspec_atak_native_fnc_tr);
        // Boutons du bas.
        private _del = _s getOrDefault ["dronePhDel", [-1, -10]];
        private _delOn = (_del select 0) isEqualTo _cur && {diag_tickTime - (_del select 1) < 4};
        private _bws = [0.27, 0.27, 0.46] apply { (_w - _pad) * _x };
        private _bx = _pad;
        {
            _x params ["_lbl", "_code", "_cls", "_tip", ["_en", true]];
            private _b = [_cls, [_bx, _yb, _bws select _forEachIndex, _btnH], _lbl] call _mk;
            _b ctrlSetFontHeight (_fs * 1.0);
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            _b ctrlEnable _en;
            [_b, _code] call comspec_atak_native_fnc_clickNext;
            if (_forEachIndex isEqualTo 0 && {_delOn}) then { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 1]; };
            _bx = _bx + (_bws select _forEachIndex) + _pad / 2;
        } forEach [
            [["SUPPRIMER", "CONFIRMER"] select _delOn, compile format ["['forget', %1] call comspec_atak_native_fnc_dronePhoto;", _cur], "COMSPEC_RscButton", "Supprime le cliché du téléphone (second appui pour confirmer)"],
            ["ANNULER", { ["keep"] call comspec_atak_native_fnc_dronePhoto; }, "COMSPEC_RscButton", "Ferme la fiche sans envoyer : le cliché reste sur le téléphone (onglet PHOTOS)"],
            ["ENVOYER À ATHENA", { ['send', -1] call comspec_atak_native_fnc_dronePhoto; }, "COMSPEC_RscButtonPrimary", "Envoie le cliché et sa fiche vers Athena", _state isNotEqualTo "SENDING"]
        ];
        // Titre vide : le curseur y est déjà, on peut écrire tout de suite.
        if (_title isEqualTo "" && {_s getOrDefault ["interactive", false]}) then { ctrlSetFocus _ed; };
        true
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
        // Titre obligatoire : sans lui, la fiche se rouvre au lieu de partir (rien n'arrive sur Athena ni Discord).
        if (((_lib select _i) select 1) isEqualTo "") exitWith {
            _s set ["dronePhEdit", _i];
            _s set ["droneTab", "PHOTO"];
            ["WARNING", "Donnez un titre au cliché avant de l'envoyer"] call _say;
            call _render;
        };
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Envoi impossible : COMSPEC Link doit être chargé (le cliché reste sur le téléphone)"] call _say; };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WARNING", "Envoi impossible : connectez-vous d'abord à Athena (le cliché reste sur le téléphone)"] call _say; };
        private _e = _lib select _i;
        _e set [8, "SENDING"];
        call _store;
        if (_i isEqualTo _cur) then { _s set ["dronePhEdit", -1]; };
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _feed = format ["drone:%1", [netId _d, "phone"] select (isNull _d)];
        // Environ 600 Ko : avec un débit faible, l'envoi prend du temps (file du téléphone si aucun réseau).
        // Fiche en champs réels (COMSPEC Link 2.0.66+) : [titre, description, SSE, dossier, confidentiel, source, drone,
        // position ASL, cap] ; sinon légende tassée.
        _e params ["", "_title", "_desc", "_sse", "_conf", "", "", "_drone", "", ["_case", ""], "", ["_hdg", -1], "", ["_pos", []], ["_shotPose", []]];
        private _withMeta = call _metaOk;
        // Détails de la prise de vue (relais Discord) : vision, zoom de la nacelle, axe visé, version de l'ATAK.
        _shotPose params [["_spPos", []], ["_spDir", []], "", ["_spFov", 0.7], ["_spVision", 0]];
        private _extra = [["vision", ["normal", "nv", "ti"] select ((_spVision max 0) min 2)], ["zoom", (0.7 / (_spFov max 0.01)) toFixed 1],
            ["atak", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]]];
        private _meta = [[], [[_title, "Cliché drone"] select (_title isEqualTo ""), _desc, _sse, ["", _case] select _sse, _conf, "drone", _drone,
            [_pos, _spPos] select ((count _pos) < 3 && {(count _spPos) >= 3}), _hdg, _extra, _spDir]] select _withMeta;
        [{
            params ["_file", "_caption", "_feed", "_meta"];
            private _ok = if (isNil "comspec_overwatch_connect_fnc_captureReconImage") then { false } else {
                [_file, _caption, "UAV", _feed, true, false, true, _meta] call comspec_overwatch_connect_fnc_captureReconImage
            };
            private _lib = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
            private _j = _lib findIf { (_x select 0) isEqualTo _file };
            if (_j >= 0) then { (_lib select _j) set [8, ["FAILED", "SENT"] select (_ok isEqualTo true)]; saveProfileNamespace; };
            [["WARNING", "SUCCESS"] select (_ok isEqualTo true), ["Cliché du drone non envoyé : voir le détail dans Photos", "Cliché du drone en cours d'envoi vers Athena"] select (_ok isEqualTo true), 4, 30] call comspec_atak_native_fnc_notify;
            ["log", format ["Cliché %1 : %2", _file, ["envoi refusé", "envoyé vers Athena"] select (_ok isEqualTo true)], ["#e5483a", "#5cc76b"] select (_ok isEqualTo true)] call comspec_atak_native_fnc_droneAction;
            [{ private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_pg in ["DRONE", "GALLERY"]) then { [_pg] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        }, [_e select 0, [[_e] call _caption, [_e] call _shortCaption] select _withMeta, _feed, _meta], "Cliché drone", 600] call comspec_atak_native_fnc_netSend;
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
