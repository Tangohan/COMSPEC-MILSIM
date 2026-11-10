// Téléphone : objet, écran de verrouillage, code, chaque app du lanceur, thèmes.
if !("telephone" call AT_fnc_groupe) exitWith {};
["tel_objet", {
    if (["comspec_atak_native_fnc_hasDevice", "comspec_atak_native_fnc_open"] call AT_fnc_nil) exitWith {};
    if ([player] call comspec_atak_native_fnc_hasDevice) then { "accès déjà ouvert sans l'objet (équipement de départ ou réglage)" call AT_fnc_info; };
    player addItem "COMSPEC_ATAK_Phone";
    [("COMSPEC_ATAK_Phone" in (items player)), "objet téléphone non donné"] call AT_fnc_check;
    [[player] call comspec_atak_native_fnc_hasDevice, "fn_hasDevice faux avec le téléphone sur soi"] call AT_fnc_check;
    call AT_fnc_done;
}] call AT_fnc_run;

["tel_verrouillage_code", {
    if (["comspec_atak_native_fnc_lockScreen", "comspec_atak_native_fnc_phoneOwn", "comspec_atak_native_fnc_phonePad"] call AT_fnc_nil) exitWith {};
    ["setPin", ["1234", "autotest"]] call comspec_atak_native_fnc_phoneOwn;
    missionNamespace setVariable ["COMSPEC_ATAK_PinOk", false];
    missionNamespace setVariable ["COMSPEC_ATAK_LockPending", true];
    [true] call comspec_atak_native_fnc_open;
    [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, "le téléphone ne s'ouvre pas"] call AT_fnc_check;
    [[{ uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false] }, 10] call AT_fnc_wait, "pas d'écran de verrouillage à l'ouverture"] call AT_fnc_check;
    ["verrouillage"] call AT_fnc_shot;
    [["needPin"] call comspec_atak_native_fnc_phoneOwn, "code enregistré mais non demandé"] call AT_fnc_check;
    ["unlock"] call comspec_atak_native_fnc_lockScreen;
    uiSleep 1;
    [["up"] call comspec_atak_native_fnc_phonePad, "clavier du code absent"] call AT_fnc_check;
    ["code_clavier"] call AT_fnc_shot;
    { ["key", _x] call comspec_atak_native_fnc_phonePad; } forEach ["9", "9", "9", "9"];
    ["ok"] call comspec_atak_native_fnc_phonePad;
    uiSleep 0.5;
    [uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false], "code faux accepté"] call AT_fnc_check;
    ["code_faux"] call AT_fnc_shot;
    for "_i" from 1 to 4 do { ["del"] call comspec_atak_native_fnc_phonePad; };
    { ["key", _x] call comspec_atak_native_fnc_phonePad; } forEach ["1", "2", "3", "4"];
    ["ok"] call comspec_atak_native_fnc_phonePad;
    [[{ !(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) }, 5] call AT_fnc_wait, "bon code refusé : toujours verrouillé"] call AT_fnc_check;
    ["deverrouille"] call AT_fnc_shot;
    ["setPin", ["", ""]] call comspec_atak_native_fnc_phoneOwn;
    uiSleep 0.5;
    [!(["hasPin"] call comspec_atak_native_fnc_phoneOwn), "code non retiré"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Une étape par app : une app qui casse n'empêche pas la suivante.
{
    private _app = _x;
    if (_app getOrDefault ["wip", false]) then {
        [format ["app_%1", _app get "page"], { "app en chantier (wip)" call AT_fnc_skip; }] call AT_fnc_run;
    } else {
        [format ["app_%1", _app get "page"], {
            params ["_app"];
            private _p = _app get "page";
            private _ok = [_p, 2] call AT_fnc_page;
            [_ok, format ["%1 : page active « %2 », téléphone ouvert %3", _app get "name",
                (call AT_fnc_etat) getOrDefault ["activePage", ""], !isNull ([] call comspec_atak_native_fnc_display)]] call AT_fnc_check;
            [([] call comspec_atak_native_fnc_display) displayCtrl 88518, "titre de l'app"] call AT_fnc_tient;
            [format ["app_%1", _p]] call AT_fnc_shot;
            call AT_fnc_done;
        }, 30, [_app]] call AT_fnc_run;
    };
} forEach (if (isNil "comspec_atak_native_fnc_appList") then { [] } else { ([] call comspec_atak_native_fnc_appList) select { !((_x get "page") in ["PILOT", "FIRESQ"]) } }); // écrans posés sur la carte : scripts\pilote.sqf

["tel_themes", {
    if (["comspec_atak_native_fnc_theme"] call AT_fnc_nil) exitWith {};
    {
        ["set", _x] call comspec_atak_native_fnc_theme;
        uiSleep 1;
        [(([] call comspec_atak_native_fnc_theme) get "name") isEqualTo _x, format ["thème %1 non appliqué", _x]] call AT_fnc_check;
        private _t = toLower _x;
        ["LAUNCHER"] call AT_fnc_page;
        [format ["theme_%1_lanceur", _t]] call AT_fnc_shot;
        ["MAP", 2] call AT_fnc_page;
        [format ["theme_%1_carte", _t]] call AT_fnc_shot;
        ["SETTINGS"] call AT_fnc_page;
        [format ["theme_%1_reglages", _t]] call AT_fnc_shot;
    } forEach ["DARK", "DAY", "NVG"];
    ["set", "DARK"] call comspec_atak_native_fnc_theme;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

// Écran de verrouillage (4.1.1) : bouton tonal à l'accent, dans les trois thèmes, en portrait (mini) et en paysage (plein écran).
["tel_verrouillage_themes", {
    if (["comspec_atak_native_fnc_lockScreen", "comspec_atak_native_fnc_theme", "comspec_atak_native_fnc_titleFit"] call AT_fnc_nil) exitWith {};
    private _mode0 = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    {
        _x params ["_mode", "_orient", "_nom"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        [true] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, format ["téléphone non ouvert (%1)", _nom]] call AT_fnc_check;
        uiSleep 1.5;
        {
            ["set", _x] call comspec_atak_native_fnc_theme;
            uiSleep 1;
            ["show"] call comspec_atak_native_fnc_lockScreen;
            uiSleep 0.8;
            [uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false], format ["écran de verrouillage absent (%1, %2)", _x, _nom]] call AT_fnc_check;
            [uiNamespace getVariable ["COMSPEC_ATAK_LockBtn", controlNull], format ["bouton de déverrouillage (%1, %2)", _x, _nom]] call AT_fnc_tient;
            [format ["verrouillage_%1_%2", toLower _x, _nom]] call AT_fnc_shot;
            ["unlock"] call comspec_atak_native_fnc_lockScreen;
            uiSleep 0.6;
            [!(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]), format ["toujours verrouillé (%1, %2)", _x, _nom]] call AT_fnc_check;
        } forEach ["DARK", "DAY", "NVG"];
    } forEach [["MINI", "PORTRAIT", "portrait"], ["FULL", "LANDSCAPE", "paysage"]];
    "bouton de déverrouillage : rectangle tonal à l'accent, centré, aligné avec CARTE et MESSAGES" call AT_fnc_visuel;
    ["set", "DARK"] call comspec_atak_native_fnc_theme;
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode0];
    profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient0];
    [true] call comspec_atak_native_fnc_open;
    uiSleep 1;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

// App CAS lisible (4 onglets dont COURT, appel de feu rempli ou vide), dans les trois thèmes, en portrait (mini) et en paysage (plein écran).
["tel_cas_lisibilite_themes", {
    if (["comspec_atak_native_fnc_pageFires", "comspec_atak_native_fnc_firesCff", "comspec_atak_native_fnc_theme"] call AT_fnc_nil) exitWith {};
    private _mode0 = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    private _f = [] call comspec_atak_native_fnc_firesState;
    private _show = { ["FIRES"] call comspec_atak_native_fnc_navigate; uiSleep 0.4; ["FIRES"] call comspec_atak_native_fnc_pageRender; uiSleep 0.6; };
    {
        _x params ["_mode", "_orient", "_nom"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        [true] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, format ["téléphone non ouvert (%1)", _nom]] call AT_fnc_check;
        uiSleep 1.5;
        {
            private _th = _x;
            ["set", _th] call comspec_atak_native_fnc_theme;
            uiSleep 1;
            {
                _f set ["tab", _x];
                call _show;
                [format ["cas_%1_%2_%3", toLower _th, _x, _nom]] call AT_fnc_shot;
            } forEach ["nine", "five", "cff"];
            // Appel de feu rempli : champs, boutons choisis (crochets), solution calculée.
            _f set ["tab", "cff"];
            { _f set _x; } forEach [["mode", "POLAR"], ["from", "ME"], ["az", "1600"], ["dist", "600"], ["rounds", "3"], ["enemy", "infanterie"], ["ammo", "HE"]];
            call _show;
            ["compute"] call comspec_atak_native_fnc_firesAction;
            uiSleep 0.8;
            [format ["cas_%1_cff_rempli_%2", toLower _th, _nom]] call AT_fnc_shot;
            ["clearTgt"] call comspec_atak_native_fnc_firesAction;
            call _show;
            // FIRES court (carte du téléphone) : sélecteur de destinataires ouvert.
            if (["can"] call comspec_atak_native_fnc_firesQuick) then {
                ["FIRESQ"] call comspec_atak_native_fnc_navigate;
                uiSleep 1.2;
                ["picker"] call comspec_atak_native_fnc_firesQuick;
                uiSleep 0.8;
                [format ["cas_%1_court_%2", toLower _th, _nom]] call AT_fnc_shot;
                ["picker"] call comspec_atak_native_fnc_firesQuick;
            };
        } forEach ["DARK", "DAY", "NVG"];
    } forEach [["MINI", "PORTRAIT", "portrait"], ["FULL", "LANDSCAPE", "paysage"]];
    "app CAS : texte lisible sur tous les boutons et champs, onglet actif entre crochets, aides avec exemples, solution vide expliquée, panneau de droite entier, journal lisible" call AT_fnc_visuel;
    ["set", "DARK"] call comspec_atak_native_fnc_theme;
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode0];
    profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient0];
    [true] call comspec_atak_native_fnc_open;
    uiSleep 1;
    call AT_fnc_done;
}, 240] call AT_fnc_run;

// Garde-fou du téléphone (fn_deviceGuard) : données reprises vérifiées, page caméra jamais rouverte seule, mode sans échec.
["tel_garde_fou", {
    if (["comspec_atak_native_fnc_deviceGuard"] call AT_fnc_nil) exitWith {};
    private _lastPage = profileNamespace getVariable "COMSPEC_ATAK_LastPage";
    private _offline = profileNamespace getVariable "COMSPEC_ATAK_OfflineSave";
    profileNamespace setVariable ["COMSPEC_ATAK_LastPage", "LIVECAM"];
    [(["restorePage"] call comspec_atak_native_fnc_deviceGuard) isEqualTo "MAP", "page caméra rouverte toute seule"] call AT_fnc_check;
    [(profileNamespace getVariable ["COMSPEC_ATAK_GuardStep", []]) isNotEqualTo [], "marqueur d'étape non posé"] call AT_fnc_check;
    ["end"] call comspec_atak_native_fnc_deviceGuard;
    [(profileNamespace getVariable ["COMSPEC_ATAK_GuardStep", []]) isEqualTo [], "marqueur d'étape non effacé"] call AT_fnc_check;
    // Sauvegarde illisible : écartée, pas d'erreur.
    profileNamespace setVariable ["COMSPEC_ATAK_OfflineSave", ["autotest", "", "pas une liste"]];
    [(["sweep"] call comspec_atak_native_fnc_deviceGuard) >= 1, "sauvegarde hors ligne illisible gardée"] call AT_fnc_check;
    // Mode sans échec : aucun flux tant que le joueur ne le lève pas.
    profileNamespace setVariable ["COMSPEC_ATAK_SafeCam", ["autotest"]];
    [!(["camOk", [player, "unit"]] call comspec_atak_native_fnc_deviceGuard), "flux accepté en mode sans échec"] call AT_fnc_check;
    ["safeOff"] call comspec_atak_native_fnc_deviceGuard;
    [!(["safe"] call comspec_atak_native_fnc_deviceGuard), "mode sans échec non levé"] call AT_fnc_check;
    [(["r2t", 300] call comspec_atak_native_fnc_deviceGuard) isEqualTo 256, "taille de texture non vérifiée"] call AT_fnc_check;
    profileNamespace setVariable ["COMSPEC_ATAK_LastPage", _lastPage];
    profileNamespace setVariable ["COMSPEC_ATAK_OfflineSave", _offline];
    call AT_fnc_done;
}, 30] call AT_fnc_run;

// Compromission validée : nouvel identifiant d'appareil, l'ancien n'est plus le terminal actif ; ordre pour un autre terminal ignoré.
["tel_nouvel_appareil", {
    if (["comspec_atak_native_fnc_compromise", "comspec_overwatch_connect_fnc_getTerminalUid"] call AT_fnc_nil) exitWith {};
    private _uid = [] call comspec_overwatch_connect_fnc_getTerminalUid;
    private _gen = profileNamespace getVariable "comspec_overwatch_terminal_gen";
    private _kept = profileNamespace getVariable "COMSPEC_ATAK_NewDevice";
    ["newDevice", ["AUTOTEST", "autotest", "OW-UN-AUTRE"]] call comspec_atak_native_fnc_compromise;
    [(profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""]) isEqualTo _uid, "ordre pour un autre terminal appliqué"] call AT_fnc_check;
    ["newDevice", ["AUTOTEST", "autotest", _uid]] call comspec_atak_native_fnc_compromise;
    private _new = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];
    [_new isNotEqualTo "" && {_new isNotEqualTo _uid}, "identifiant inchangé après le remplacement"] call AT_fnc_check;
    [(["previous", "AUTOTEST"] call comspec_atak_native_fnc_compromise) isEqualTo _uid, "ancien terminal oublié"] call AT_fnc_check;
    [(["deviceStatus"] call comspec_atak_native_fnc_compromise) isNotEqualTo [], "aucun message au joueur"] call AT_fnc_check;
    ["identifiant : " + _uid + " -> " + _new] call AT_fnc_info;
    profileNamespace setVariable ["comspec_overwatch_terminal_uid", _uid];
    profileNamespace setVariable ["comspec_overwatch_terminal_gen", if (isNil "_gen") then { nil } else { _gen }];
    profileNamespace setVariable ["COMSPEC_ATAK_NewDevice", if (isNil "_kept") then { nil } else { _kept }];
    call AT_fnc_done;
}, 30] call AT_fnc_run;

// Icône de la radio MPU5 dans la barre d'état : code et texture cohérents, icône « transmet » seulement si la radio est reliée et sert.
["tel_icone_mpu", {
    if (["comspec_atak_native_fnc_mpuLink", "comspec_atak_native_fnc_statusUpdate"] call AT_fnc_nil) exitWith {};
    private _b = ["badge"] call comspec_atak_native_fnc_mpuLink;
    [_b isEqualType [] && {(count _b) isEqualTo 4}, "badge MPU : format inattendu"] call AT_fnc_check;
    _b params ["_code", "_img", "_col", "_tip"];
    [_code in ["off", "idle", "cable", "wifi", "tx", "cut"], format ["badge MPU : code inconnu (%1)", _code]] call AT_fnc_check;
    [(_code isEqualTo "off") isEqualTo (_img isEqualTo ""), "badge MPU : texture absente ou en trop"] call AT_fnc_check;
    [(_code isEqualTo "off") isEqualTo (_tip isEqualTo ""), "badge MPU : infobulle absente ou en trop"] call AT_fnc_check;
    // Sans radio reliée et en service, la marque d'émission ne doit rien changer.
    private _st = ["status"] call comspec_atak_native_fnc_mpuLink;
    uiNamespace setVariable ["COMSPEC_ATAK_MpuTxUntil", 0];
    ["txMark", [2]] call comspec_atak_native_fnc_mpuLink;
    private _marked = (uiNamespace getVariable ["COMSPEC_ATAK_MpuTxUntil", 0]) > diag_tickTime;
    [_marked isEqualTo ((_st getOrDefault ["kind", ""]) isEqualTo "mpu" && {_st getOrDefault ["ok", false]}), "marque d'émission posée sans radio reliée en service"] call AT_fnc_check;
    uiNamespace setVariable ["COMSPEC_ATAK_MpuTxUntil", 0];
    call AT_fnc_done;
}] call AT_fnc_run;

// Bilan de l'année et Vidéo YouTube (4.2.0) : analyse des liens (fonction pure, aucune requête), puis les deux pages dessinées
// sans réseau (mission en mode local : le bilan dit qu'Athena n'est pas relié, YouTube est coupé par défaut).
["tel_bilan_youtube", {
    if (["comspec_atak_native_fnc_youtube", "comspec_atak_native_fnc_yearReview", "comspec_atak_native_fnc_pageYearReview", "comspec_atak_native_fnc_pageYoutube"] call AT_fnc_nil) exitWith {};
    private _id = "dQw4w9WgXcQ";
    {
        _x params ["_url", "_want"];
        [(["id", _url] call comspec_atak_native_fnc_youtube) isEqualTo _want, format ["lien YouTube « %1 » : attendu « %2 »", _url, _want]] call AT_fnc_check;
    } forEach [
        ["https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s", _id], ["https://youtu.be/dQw4w9WgXcQ?si=abc", _id], ["https://www.youtube.com/embed/dQw4w9WgXcQ", _id],
        ["https://youtube.com/shorts/dQw4w9WgXcQ", _id], ["m.youtube.com/watch?v=dQw4w9WgXcQ#t=5", _id], [_id, _id],
        ["https://evil.com/watch?v=dQw4w9WgXcQ", ""], ["https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ", ""], ["https://youtube.com@evil.com/watch?v=dQw4w9WgXcQ", ""],
        ["https://www.youtube.com/watch?v=dQw4w9WgXc", ""], ["https://www.youtube.com/watch?v=dQw4w9WgXcQ%22", ""], ["https://www.youtube.com/watch?v=dQw4w9WgXc'", ""], ["", ""]
    ];
    [(["embed", _id] call comspec_atak_native_fnc_youtube) isEqualTo "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1", "adresse du lecteur"] call AT_fnc_check;
    [(["embed", "x""><script>"] call comspec_atak_native_fnc_youtube) isEqualTo "", "identifiant invalide accepté pour le lecteur"] call AT_fnc_check;
    [!(["enabled"] call comspec_atak_native_fnc_youtube), "lecture YouTube active par défaut (doit être coupée)"] call AT_fnc_check;
    [true] call comspec_atak_native_fnc_open;
    [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, "le téléphone ne s'ouvre pas"] call AT_fnc_check;
    ["YEARREVIEW"] call comspec_atak_native_fnc_navigate;
    uiSleep 1.5;
    ["bilan_annee"] call AT_fnc_shot;
    ["YOUTUBE"] call comspec_atak_native_fnc_navigate;
    uiSleep 1;
    ["video_youtube_coupee"] call AT_fnc_shot;
    "Bilan de l'année : message d'état lisible, sans erreur ; Vidéo YouTube : message « désactivée sur ce serveur »" call AT_fnc_visuel;
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Comptes rendus, onglet NOUVEAU (retour de jeu 4.1.1) : les lignes « person » (icône + titre + bouton RÉDIGER) n'avaient plus
// de texte. Cause : COMSPEC_ATAK_Th (jetons du thème) n'était posé qu'au calcul du thème, mis en cache dans uiNamespace ;
// à la deuxième mission de la session (menu principal puis partie), le cache servait et COMSPEC_ATAK_Th restait indéfini.
// L'étape refait cet état (variable effacée, cache chaud, preInit rejoué) puis vérifie chaque texte structuré de la page.
["tel_comptes_rendus_textes", {
    if (["comspec_atak_native_fnc_theme", "comspec_atak_native_fnc_pageReports", "comspec_atak_native_fnc_reportTypes"] call AT_fnc_nil) exitWith {};
    [] call comspec_atak_native_fnc_theme;
    missionNamespace setVariable ["COMSPEC_ATAK_Th", nil];
    [] call comspec_atak_native_fnc_theme; // ce que fait le preInit d'une deuxième mission (cache uiNamespace déjà rempli)
    private _thOk = !isNil "COMSPEC_ATAK_Th" && {(COMSPEC_ATAK_Th getOrDefault ["hexTextMuted", ""]) isNotEqualTo ""};
    [_thOk, "COMSPEC_ATAK_Th indéfini quand le cache du thème est déjà rempli (deuxième mission de la session)"] call AT_fnc_check;
    private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RepUi", createHashMap];
    _ui set ["tab", "NEW"];
    _ui set ["type", ""];
    uiNamespace setVariable ["COMSPEC_ATAK_RepUi", _ui];
    private _ok = ["REPORTS", 2] call AT_fnc_page;
    [_ok, "app Comptes rendus non ouverte"] call AT_fnc_check;
    private _d = [] call comspec_atak_native_fnc_display;
    private _grp = controlNull;
    {
        _x params ["_n", "_c"];
        if ((_n find "form|REPORTS|") isEqualTo 0 && {!isNull _c}) exitWith { _grp = _c; };
    } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []]);
    [!isNull _grp, "liste des comptes rendus introuvable (groupe form|REPORTS)"] call AT_fnc_check;
    // Tous les textes structurés de la liste (titres de section, lignes « person ») : affichés et non vides.
    private _txts = if (isNull _d || {isNull _grp}) then { [] } else {
        (allControls _d) select { (ctrlParentControlsGroup _x) isEqualTo _grp && {(ctrlType _x) isEqualTo 13} && {ctrlShown _x} }
    };
    private _vides = _txts select { (ctrlTextHeight _x) <= 0 };
    private _nTypes = count (([] call comspec_atak_native_fnc_reportTypes) select { (_x select 3) isNotEqualTo "" });
    (format ["%1 texte(s) structuré(s) dans la liste, %2 vide(s), %3 type(s) de C.R. à fiche", count _txts, count _vides, _nTypes]) call AT_fnc_info;
    [(count _txts) >= _nTypes, format ["%1 texte(s) structuré(s) seulement pour %2 type(s) de C.R.", count _txts, _nTypes]] call AT_fnc_check;
    [(count _vides) isEqualTo 0, format ["%1 ligne(s) de la liste sans texte (titre, étiquette et aide invisibles)", count _vides]] call AT_fnc_check;
    ["reports_nouveau"] call AT_fnc_shot;
    if (!isNull _grp) then { _grp ctrlSetScrollValues [1, -1]; };
    uiSleep 0.5;
    ["reports_nouveau_bas"] call AT_fnc_shot;
    "Comptes rendus > NOUVEAU : chaque ligne a son titre (« Bilan des dégâts (BDA) »…), son étiquette colorée et son aide grisée" call AT_fnc_visuel;
    // Build sans le correctif : thème recalculé pour que les étapes suivantes ne se heurtent pas au même défaut.
    if (isNil "COMSPEC_ATAK_Th") then {
        uiNamespace setVariable ["COMSPEC_ATAK_ThemeCache", ["", createHashMap]];
        [] call comspec_atak_native_fnc_theme;
    };
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// BFT, capture 4.2.0 : en vue ORBAT, le symbole MIL-STD de la ligne choisie recouvrait le début de l'indicatif (« VB1 »), la
// fiche de droite restait vide (« < 1 min » non échappé cassait son texte structuré). Une image de cellule du tableau est
// dessinée à la hauteur de ligne (0,040 × safeZoneH) : la case de chaque niveau (colonnes 0 à 2) doit finir avant la colonne
// du texte (3). Aucun texte de la page (message, en-têtes, légende) ne doit chevaucher le tableau ; la fiche a du texte ;
// les boutons d'action ne coupent pas leur libellé. Vue ORBAT dans les deux orientations, puis vue groupe (filtre GROUPE) et vue LISTE.
["tel_bft_colonnes", {
    if (["comspec_atak_native_fnc_pageBft", "comspec_atak_native_fnc_bftOrbat", "comspec_atak_native_fnc_orientationToggle"] call AT_fnc_nil) exitWith {};
    private _view0 = profileNamespace getVariable ["COMSPEC_ATAK_BftView", "LIST"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _filt0 = _st getOrDefault ["bftFilter", "ALL"];
    private _verifie = {
        params ["_nom"];
        private _live = uiNamespace getVariable ["COMSPEC_ATAK_BftLive", createHashMap];
        (_live getOrDefault ["ctrls", []]) params [["_head", controlNull], ["_list", controlNull], ["_det", controlNull]];
        [!isNull _list, format ["%1 : tableau du BFT introuvable", _nom]] call AT_fnc_check;
        if (isNull _list) exitWith {};
        // Cases des images (chevron, symbole) avant la colonne du texte.
        private _cols = lnbGetColumnsPosition _list;
        (ctrlPosition _list) params ["_lx", "_ly", "_lw", "_lh"];
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _iconW = (0.040 * safeZoneH) * 1.04 / _ratio; // mesuré sur la capture : décalage 0,17 + largeur 0,87 hauteur de ligne
        private _txtX = _lx + _lw * (_cols param [3, 0]);
        {
            private _fin = _lx + _lw * (_cols param [_x, 0]) + _iconW;
            [_fin <= _txtX + pixelW, format ["%1 : l'image du niveau %2 finit à %3, le texte commence à %4 (chevauchement)", _nom, _x, _fin toFixed 4, _txtX toFixed 4]] call AT_fnc_check;
        } forEach [0, 1, 2];
        // Colonnes dans l'ordre et dans le tableau.
        private _tri = +_cols;
        _tri sort true;
        [(_cols isEqualTo _tri) && {(_cols param [6, 2]) < 1}, format ["%1 : colonnes en désordre ou hors du tableau %2", _nom, _cols]] call AT_fnc_check;
        // Textes de la page (message, en-têtes, légende) : aucun ne chevauche le tableau.
        private _grp = ctrlParentControlsGroup _list;
        private _d = [] call comspec_atak_native_fnc_display;
        private _txts = (allControls _d) select { (ctrlParentControlsGroup _x) isEqualTo _grp && {ctrlShown _x} && {(ctrlType _x) in [0, 13]} && {_x isNotEqualTo _det} && {_x isNotEqualTo _head} };
        {
            (ctrlPosition _x) params ["_x2", "_y2", "_w2", "_h2"];
            private _sur = _x2 < (_lx + _lw - pixelW) && {(_x2 + _w2) > (_lx + pixelW)} && {_y2 < (_ly + _lh - pixelH)} && {(_y2 + _h2) > (_ly + pixelH)};
            [!_sur, format ["%1 : le texte « %2 » chevauche le tableau", _nom, (ctrlText _x) select [0, 40]]] call AT_fnc_check;
        } forEach _txts;
        // Fiche de la ligne choisie : jamais vide.
        [!isNull _det && {(ctrlTextHeight _det) > 0}, format ["%1 : fiche de droite vide", _nom]] call AT_fnc_check;
        // Boutons d'action : libellé entier.
        { if (ctrlShown _x) then { [_x, format ["%1 : bouton", _nom]] call AT_fnc_tient; }; } forEach (((_live getOrDefault ["bar", createHashMap]) getOrDefault ["btns", []]) + [((_live getOrDefault ["bar", createHashMap]) getOrDefault ["more", controlNull])]);
    };
    {
        _x params ["_view", "_filter", "_shot", ["_flip", false]];
        if (_flip) then { [] call comspec_atak_native_fnc_orientationToggle; uiSleep 1; };
        profileNamespace setVariable ["COMSPEC_ATAK_BftView", _view];
        _st set ["bftFilter", _filter];
        _st set ["bftFiltersOpen", false];
        _st set ["bftBuzz", ""];
        private _ok = ["BFT", 2] call AT_fnc_page;
        [_ok, format ["app BFT non ouverte (%1)", _shot]] call AT_fnc_check;
        if (_ok) then {
            ["BFT"] call comspec_atak_native_fnc_pageRender;
            uiSleep 0.8;
            [_shot] call _verifie;
            [_shot] call AT_fnc_shot;
        };
    } forEach [["ORBAT", "ALL", "bft_orbat"], ["ORBAT", "ALL", "bft_orbat_autre_orientation", true], ["ORBAT", "GROUP", "bft_equipe", true], ["LIST", "ALL", "bft_liste"]];
    profileNamespace setVariable ["COMSPEC_ATAK_BftView", _view0];
    _st set ["bftFilter", _filt0];
    if ((toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"])) isNotEqualTo (toUpper _orient0)) then { [] call comspec_atak_native_fnc_orientationToggle; };
    "BFT : symbole et indicatif côte à côte, chevrons gris, compteurs en toutes lettres (« 1 piste », « 1/1 aptes »), légende des états sous le tableau, fiche remplie à droite (ou dessous en portrait), bouton PLUS au lieu de « … »" call AT_fnc_visuel;
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

// ATAK 4.2.1, apps : contrôles d'une page en formulaire (fn_formRender) qui doivent tenir dans leur case : boutons
// (largeur du libellé) et textes structurés (hauteur du texte). Groupes relevés dans COMSPEC_ATAK_ScrollGroups.
AT_fnc_formTient = {
    params ["_nom"];
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith {};
    private _grps = (uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []]) apply { _x select 1 };
    { if ((ctrlParentControlsGroup _x) in _grps && {(ctrlType _x) in [1, 13]}) then { [_x, _nom] call AT_fnc_tient; }; } forEach (allControls _d);
};

// Tâches 4.2.1 : ordres triés (à traiter et prioritaires en tête, terminés en bas), filtre À TRAITER / TERMINÉS / TOUS,
// consigne longue coupée avec « … » et section TEXTE COMPLET. Trois ordres locaux injectés, rendus aux trois filtres.
["tel_taches_triees", {
    if (["comspec_atak_native_fnc_pageTasks", "comspec_atak_native_fnc_tasksAll"] call AT_fnc_nil) exitWith {};
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_Data", _data];
    private _old = _data getOrDefault ["localTasks", createHashMap];
    private _mk = {
        params ["_id", "_lbl", "_prio", "_stat", "_stamp", ["_pay", ""]];
        createHashMapFromArray [["id", _id], ["type", "MOVE"], ["typeLabel", _lbl], ["priority", _prio], ["status", _stat], ["issuer", "AUTOTEST"],
            ["target", ""], ["targetType", "all"], ["payload", _pay], ["stamp", _stamp], ["source", "local"]]
    };
    private _long = "";
    for "_i" from 1 to 14 do { _long = _long + format ["Phrase %1 de la consigne longue de l'autotest. ", _i]; };
    _data set ["localTasks", createHashMapFromArray [
        ["AT-1", ["AT-1", "AT ROUTINE", "ROUTINE", "NEW", 10] call _mk],
        ["AT-2", ["AT-2", "AT TERMINE", "FLASH", "DONE", 30] call _mk],
        ["AT-3", ["AT-3", "AT URGENT", "URGENT", "NEW", 5, _long] call _mk]
    ]];
    private _st = call AT_fnc_etat;
    private _f0 = _st getOrDefault ["taskFilter", "OPEN"];
    _st set ["orderCompose", false];
    _st set ["taskTxtOpen", []];
    {
        _x params ["_filt", "_open", "_shot"];
        _st set ["taskFilter", _filt];
        _st set ["taskTxtOpen", _open];
        private _ok = ["TASK", 1.5] call AT_fnc_page;
        [_ok, format ["app Tâches non ouverte (%1)", _shot]] call AT_fnc_check;
        if (_ok) then {
            ["TASK"] call comspec_atak_native_fnc_pageRender;
            uiSleep 0.6;
            [_shot] call AT_fnc_formTient;
            [_shot] call AT_fnc_shot;
        };
    } forEach [["ALL", [], "taches_tous"], ["ALL", ["AT-3"], "taches_texte_deplie"], ["OPEN", [], "taches_a_traiter"], ["DONE", [], "taches_terminees"]];
    "Tâches > TOUS : AT URGENT (PRIORITAIRE) en tête, puis AT ROUTINE, AT TERMINE en bas ; consigne d'AT URGENT coupée par « … » avec une section TEXTE COMPLET (dépliée : texte entier, REPLIER). À TRAITER : sans AT TERMINE. TERMINÉS : AT TERMINE seul" call AT_fnc_visuel;
    _data set ["localTasks", _old];
    _st set ["taskFilter", _f0];
    _st set ["taskTxtOpen", []];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// États vides 4.2.1 : composant « empty » (icône, titre, quoi faire) sur les apps sans données, en portrait puis dans
// l'autre orientation ; aucun libellé ni texte coupé.
["tel_etats_vides", {
    if (["comspec_atak_native_fnc_pageWaypoints", "comspec_atak_native_fnc_orientationToggle"] call AT_fnc_nil) exitWith {};
    private _st = call AT_fnc_etat;
    private _log0 = +(missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []]);
    private _wp0 = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
    private _loc0 = _data getOrDefault ["localTasks", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", []];
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", createHashMapFromArray [["pts", []], ["idx", 0], ["nav", false], ["from", ""]]];
    _data set ["localTasks", createHashMap];
    _st set ["taskFilter", "OPEN"];
    _st set ["logiTab", "MINE"];
    _st set ["trkFilter", "ALL"];
    private _apps = ([] call comspec_atak_native_fnc_appList) apply { _x get "page" };
    {
        private _flip = _x;
        if (_flip) then { [] call comspec_atak_native_fnc_orientationToggle; uiSleep 1; };
        {
            if !(_x in _apps) then { (format ["%1 absente de ce téléphone", _x]) call AT_fnc_info; continue };
            private _ok = [_x, 1.5] call AT_fnc_page;
            [_ok, format ["app %1 non ouverte", _x]] call AT_fnc_check;
            if (_ok) then {
                private _nom = format ["vide_%1%2", toLower _x, ["", "_autre_orientation"] select _flip];
                [_nom] call AT_fnc_formTient;
                [_nom] call AT_fnc_shot;
            };
        } forEach ["TASK", "WAYPOINTS", "ALERTS", "RECO", "SALUTE", "LOGI", "TRACKING", "DISCORD", "MEDICAL"];
    } forEach [false, true];
    [] call comspec_atak_native_fnc_orientationToggle;
    "États vides : chaque app montre une grande icône pâle, un titre et une phrase qui dit quoi faire (Tâches « Aucun ordre reçu », Itinéraire « Aucune étape », Alertes et SALUTE en bas de page, Logistique avec NOUVELLE DEMANDE…), rien de coupé dans les deux orientations" call AT_fnc_visuel;
    missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", _log0];
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _wp0];
    _data set ["localTasks", _loc0];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

// Recherche par nom 4.2.1 (Numéros > ANNUAIRE, Recherchés) : appliquée sur OK, effaçable, état vide « Aucun résultat pour … ».
["tel_recherche_nom", {
    if (["comspec_atak_native_fnc_pageNumbers", "comspec_atak_native_fnc_numbersAction"] call AT_fnc_nil) exitWith {};
    private _st = call AT_fnc_etat;
    _st set ["numTab", "DIR"];
    _st set ["numQ", ""];
    private _ok = ["NUMBERS", 1.5] call AT_fnc_page;
    [_ok, "app Numéros non ouverte"] call AT_fnc_check;
    if (_ok) then {
        private _e = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["numQ", controlNull];
        if (isNull _e) then {
            "annuaire vide (partie seule) : pas de champ de recherche, seulement l'état vide" call AT_fnc_info;
        } else {
            // Saisie puis OK : la recherche vient du champ, pas de la frappe.
            _e ctrlSetText "zzzz-autotest";
            ["search"] call comspec_atak_native_fnc_numbersAction;
            uiSleep 0.8;
            [(_st getOrDefault ["numQ", ""]) isEqualTo "zzzz-autotest", format ["recherche non prise du champ : « %1 »", _st getOrDefault ["numQ", ""]]] call AT_fnc_check;
            ["recherche_annuaire"] call AT_fnc_formTient;
            ["recherche_annuaire_aucun"] call AT_fnc_shot;
            ["clearQ"] call comspec_atak_native_fnc_numbersAction;
            uiSleep 0.8;
            [(_st getOrDefault ["numQ", "x"]) isEqualTo "", "EFFACER LA RECHERCHE n'efface pas le filtre"] call AT_fnc_check;
        };
        ["recherche_annuaire"] call AT_fnc_shot;
    };
    if (!isNil "comspec_atak_native_fnc_wantedAction" && {[] call comspec_atak_native_fnc_bridge}) then {
        _st set ["wantedOpen", ""];
        _st set ["wantedQ", "zzzz-autotest"];
        if (["WANTED", 1.5] call AT_fnc_page) then {
            ["WANTED"] call comspec_atak_native_fnc_pageRender;
            uiSleep 0.6;
            ["recherche_recherches"] call AT_fnc_formTient;
            ["recherche_recherches_aucun"] call AT_fnc_shot;
        };
        ["clearQ"] call comspec_atak_native_fnc_wantedAction;
        uiSleep 0.6;
        [(_st getOrDefault ["wantedQ", "x"]) isEqualTo "", "Recherchés : filtre non effacé"] call AT_fnc_check;
    } else { "Recherchés : sans COMSPEC Overwatch, pas de liste à filtrer" call AT_fnc_info; };
    "Recherche : champ « Rechercher un nom… » avec OK, bouton EFFACER LA RECHERCHE, état vide « Aucun résultat pour « zzzz-autotest » » avec TOUT AFFICHER" call AT_fnc_visuel;
    _st set ["numTab", "MINE"];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Annulation 4.2.1 : TOUT EFFACER (Itinéraire) et VIDER LE JOURNAL (Alertes) laissent 8 s pour ANNULER ; l'effacement de
// l'itinéraire n'est envoyé au groupe qu'à la fin des 8 s (copie marquée « envoyée » ensuite).
["tel_annulation", {
    if (["comspec_atak_native_fnc_wpAction", "comspec_atak_native_fnc_alertsAction", "comspec_atak_native_fnc_undoSnack"] call AT_fnc_nil) exitWith {};
    private _wp0 = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    private _share0 = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
    profileNamespace setVariable ["COMSPEC_ATAK_WpShare", 1];
    private _p = getPosATL player;
    private _pts = [[[(_p select 0) + 300, (_p select 1), 0], "AT 1", "CP"], [[(_p select 0) + 600, (_p select 1) + 200, 0], "AT 2", "OBJ"]];
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", createHashMapFromArray [["pts", +_pts], ["idx", 1], ["nav", true], ["from", ""]]];
    [["WAYPOINTS", 1.5] call AT_fnc_page, "app Itinéraire non ouverte"] call AT_fnc_check;
    ["clear"] call comspec_atak_native_fnc_wpAction;
    uiSleep 0.5;
    private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    private _u = missionNamespace getVariable ["COMSPEC_ATAK_WpUndo", []];
    [(count (_w getOrDefault ["pts", [0]])) isEqualTo 0, "TOUT EFFACER : étapes encore là"] call AT_fnc_check;
    [(count _u) >= 6 && {!(_u select 5)}, format ["copie d'annulation absente ou déjà envoyée au groupe : %1", _u]] call AT_fnc_check;
    [(count (uiNamespace getVariable ["COMSPEC_ATAK_UndoSnack", []])) > 0, "bandeau « Étapes effacées · ANNULER » absent"] call AT_fnc_check;
    ["annulation_itineraire_bandeau"] call AT_fnc_shot;
    ["undoClear", _u param [0, -1]] call comspec_atak_native_fnc_wpAction;
    uiSleep 0.5;
    _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    [(count (_w getOrDefault ["pts", []])) isEqualTo 2 && {(_w getOrDefault ["idx", 0]) isEqualTo 1} && {_w getOrDefault ["nav", false]}, "ANNULER : itinéraire, étape active ou navigation non rendus"] call AT_fnc_check;
    ["annulation_itineraire_rendu"] call AT_fnc_shot;
    // Sans annulation : l'effacement part au groupe au bout de 8 s, la copie est marquée envoyée puis oubliée.
    ["clear"] call comspec_atak_native_fnc_wpAction;
    uiSleep 9;
    _u = missionNamespace getVariable ["COMSPEC_ATAK_WpUndo", []];
    [(count _u) isEqualTo 0 || {_u select 5}, "fin des 8 s : effacement pas envoyé au groupe"] call AT_fnc_check;
    [(count (uiNamespace getVariable ["COMSPEC_ATAK_UndoSnack", []])) isEqualTo 0, "bandeau encore affiché après 8 s"] call AT_fnc_check;
    // Journal des alertes.
    private _log0 = +(missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []]);
    missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", [["TIC", "AT", getPosASL player, "000000", "autotest 1", "12:00"], ["TIC_CLEAR", "AT", getPosASL player, "000000", "autotest 2", "12:05"]]];
    [["ALERTS", 1.5] call AT_fnc_page, "app Alertes non ouverte"] call AT_fnc_check;
    ["clearLog"] call comspec_atak_native_fnc_alertsAction;
    uiSleep 0.5;
    [(count (missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", [0]])) isEqualTo 0, "VIDER LE JOURNAL : journal pas vidé"] call AT_fnc_check;
    ["annulation_journal_bandeau"] call AT_fnc_shot;
    ["undoClearLog", (missionNamespace getVariable ["COMSPEC_ATAK_AlertLogUndo", []]) param [0, -1]] call comspec_atak_native_fnc_alertsAction;
    uiSleep 0.5;
    [(count (missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []])) isEqualTo 2, "ANNULER : journal non rendu"] call AT_fnc_check;
    "Bandeau ambre « Étapes effacées » puis « Journal vidé » avec ANNULER en haut de l'écran, disparu au bout de 8 s ; après ANNULER l'itinéraire et le journal sont revenus" call AT_fnc_visuel;
    missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", _log0];
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _wp0];
    profileNamespace setVariable ["COMSPEC_ATAK_WpShare", _share0];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Statut et Itinéraire vivants 4.2.1 : minuterie de la page en route sur la page, arrêtée en la quittant.
["tel_pages_vivantes", {
    if (["comspec_atak_native_fnc_pageStatus", "comspec_atak_native_fnc_pageWaypoints"] call AT_fnc_nil) exitWith {};
    private _wp0 = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
    private _p = getPosATL player;
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", createHashMapFromArray [["pts", [[[(_p select 0) + 400, (_p select 1) + 400, 0], "AT 1", "CP"]]], ["idx", 0], ["nav", true], ["from", ""]]];
    {
        _x params ["_page", "_var"];
        [[_page, 2.5] call AT_fnc_page, format ["app %1 non ouverte", _page]] call AT_fnc_check;
        [!isNil { uiNamespace getVariable _var }, format ["%1 : minuterie de mise à jour absente", _page]] call AT_fnc_check;
        [format ["vivant_%1", toLower _page]] call AT_fnc_shot;
        ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
        uiSleep 2.5;
        [isNil { uiNamespace getVariable _var }, format ["%1 : minuterie encore active hors de la page", _page]] call AT_fnc_check;
    } forEach [["WAYPOINTS", "COMSPEC_ATAK_WpLive"], ["STATUS", "COMSPEC_ATAK_StatusLive"]];
    "Itinéraire et Statut : distance, cap, « il y a X s » et signal changent seuls sur la page (à vérifier en marchant), sans clignotement de la page" call AT_fnc_visuel;
    missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _wp0];
    call AT_fnc_done;
}, 40] call AT_fnc_run;

// ATAK 4.2.2, apps pensées pour le téléphone tenu à l'horizontale : textes et boutons d'un formulaire ni l'un sur
// l'autre ni hors du cadre (colonnes de fn_formRender, fn_layoutCols). Paires voisines seulement (triées par hauteur).
AT_fnc_formChevauche = {
    params ["_nom"];
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith {};
    private _grps = (uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []]) apply { _x select 1 };
    private _nKo = 0;
    {
        private _g = _x;
        private _gw = (ctrlPosition _g) select 2;
        private _els = [];
        {
            if ((ctrlParentControlsGroup _x) isEqualTo _g && {ctrlShown _x} && {(ctrlType _x) in [1, 13]}
                && {!((ctrlClassName _x) in ["COMSPEC_RscButtonOverlay", "COMSPEC_RscButtonInvisible"])}) then {
                (ctrlPosition _x) params ["_x0", "_y0", "_w0", "_h0"];
                if (_w0 > pixelW * 2 && {_h0 > pixelH * 2}) then { _els pushBack [_y0, _x0, _w0, _h0, _x]; };
            };
        } forEach (allControls _d);
        _els sort true;
        {
            _x params ["_y0", "_x0", "_w0", "_h0"];
            if (_x0 + _w0 > _gw + pixelW * 3 && {_nKo < 6}) then {
                _nKo = _nKo + 1;
                [false, format ["%1 : hors du cadre à droite (x %2 + %3 > %4)", _nom, _x0, _w0, _gw]] call AT_fnc_check;
            };
            for "_j" from (_forEachIndex + 1) to ((count _els) - 1) do {
                (_els select _j) params ["_y1", "_x1", "_w1", "_h1", "_c1"];
                if (_y1 >= _y0 + _h0) exitWith {};
                private _iw = ((_x0 + _w0) min (_x1 + _w1)) - (_x0 max _x1);
                private _ih = ((_y0 + _h0) min (_y1 + _h1)) - (_y0 max _y1);
                if (_iw > pixelW * 3 && {_ih > pixelH * 3} && {(_iw * _ih) > 0.3 * ((_w0 * _h0) min (_w1 * _h1))} && {_nKo < 6}) then {
                    _nKo = _nKo + 1;
                    [false, format ["%1 : deux éléments l'un sur l'autre (%2 / %3, y %4)", _nom, ctrlClassName (_x select 4), ctrlClassName _c1, _y0]] call AT_fnc_check;
                };
            };
        } forEach _els;
    } forEach _grps;
};

["tel_paysage_apps", {
    if (["comspec_atak_native_fnc_layoutCols"] call AT_fnc_nil) exitWith {};
    private _mode0 = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    private _scale0 = profileNamespace getVariable ["COMSPEC_ATAK_MiniScale", 1];
    private _apps = ["CHAT", "BFT", "TASK", "ALERTS", "MEDICAL", "REPORTS", "NETWORK", "STATUS", "WAYPOINTS", "SETTINGS", "PROFILE", "WANTED", "NUMBERS", "LOGI"];
    private _st = call AT_fnc_etat;
    {
        _x params ["_mode", "_orient", "_scale", "_nom"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        profileNamespace setVariable ["COMSPEC_ATAK_MiniScale", _scale];
        [true] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, format ["téléphone non ouvert (%1)", _nom]] call AT_fnc_check;
        uiSleep 1.2;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _bw = ((_l get "body") select 2);
        format ["%1 : corps %2 px de large, %3 caractère(s), colonnes section %4, info %5, maître-détail %6", _nom, round (_bw / pixelW), round (_bw / (_l get "colChar")),
            [_bw, "section"] call comspec_atak_native_fnc_layoutCols, [_bw, "info"] call comspec_atak_native_fnc_layoutCols, ([_bw, "split"] call comspec_atak_native_fnc_layoutCols) isNotEqualTo []] call AT_fnc_info;
        {
            private _pg = _x;
            _st set ["netDiag", false];
            private _ok = [_pg, 1.2] call AT_fnc_page;
            [_ok, format ["app %1 non ouverte (%2)", _pg, _nom]] call AT_fnc_check;
            if (_ok) then {
                [_pg] call comspec_atak_native_fnc_pageRender;
                uiSleep 0.5;
                private _n = format ["paysage_%1_%2", toLower _pg, _nom];
                [_n] call AT_fnc_formTient;
                [_n] call AT_fnc_formChevauche;
                [_n] call AT_fnc_shot;
            };
        } forEach _apps;
        // Dépannage MPU (volet de l'app Réseau).
        _st set ["netDiag", true];
        ["NETWORK"] call comspec_atak_native_fnc_pageRender;
        uiSleep 0.6;
        [format ["paysage_mpudiag_%1", _nom]] call AT_fnc_formTient;
        [format ["paysage_mpudiag_%1", _nom]] call AT_fnc_formChevauche;
        [format ["paysage_mpudiag_%1", _nom]] call AT_fnc_shot;
        _st set ["netDiag", false];
    } forEach [
        ["FULL", "LANDSCAPE", 1, "plein"],
        ["MINI", "LANDSCAPE", 1.3, "mini_paysage_grand"],
        ["MINI", "LANDSCAPE", 0.7, "mini_paysage_petit"],
        ["MINI", "PORTRAIT", 1.3, "mini_portrait_grand"],
        ["MINI", "PORTRAIT", 0.7, "mini_portrait_petit"]
    ];
    // Mode petit porté (pas en main) : barre d'app sans titre ni bouton sur la carte, la carte touche la barre d'état.
    {
        _x params ["_orient", "_scale"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", "MINI"];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        profileNamespace setVariable ["COMSPEC_ATAK_MiniScale", _scale];
        [true] call comspec_atak_native_fnc_open;
        uiSleep 0.8;
        ["MAP"] call comspec_atak_native_fnc_navigate;
        uiSleep 0.8;
        [false] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, "téléphone porté non affiché"] call AT_fnc_check;
        uiSleep 1.5;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _gap = ((_l get "body") select 1) - (((_l get "status") select 1) + ((_l get "status") select 3));
        format ["mini porté %1 %2 : page %3, écart barre d'état / contenu %4 px", _orient, _scale, (call AT_fnc_etat) getOrDefault ["activePage", ""], round (_gap / pixelH)] call AT_fnc_info;
        if (((call AT_fnc_etat) getOrDefault ["activePage", ""]) isEqualTo "MAP") then {
            [_gap <= pixelH * 3, format ["mini porté %1 : bande vide de %2 px entre la barre d'état et la carte", _orient, round (_gap / pixelH)]] call AT_fnc_check;
        };
        [format ["paysage_mini_porte_carte_%1_%2", toLower _orient, _scale]] call AT_fnc_shot;
        [] call comspec_atak_native_fnc_close;
        uiSleep 0.5;
    } forEach [["LANDSCAPE", 1], ["LANDSCAPE", 0.7], ["PORTRAIT", 1]];
    "apps en paysage : sections et tuiles en deux colonnes (plein écran), une colonne en portrait et sur le petit téléphone, rien de coupé ni superposé ; mode petit porté sur la carte : pas de bande noire sous la barre d'état" call AT_fnc_visuel;
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode0];
    profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient0];
    profileNamespace setVariable ["COMSPEC_ATAK_MiniScale", _scale0];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    call AT_fnc_done;
}, 900] call AT_fnc_run;
