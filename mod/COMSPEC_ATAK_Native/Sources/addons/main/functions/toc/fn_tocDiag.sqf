/*
    Export de diagnostic du TOC (feuille de route TOC, Z2) : une seule fonction relève salles, écrans, flux, état du
    poste, postes tenus, journal, sauvegarde, performances et versions dans un bloc de texte. Le bloc est écrit dans le
    journal COMSPEC (une ligne par ligne, une fois), copié dans le presse-papiers du demandeur et montré dans une
    fenêtre à copier. Aucun nom ni identifiant de joueur dans l'export. Sans Athena ni téléphone, aucune requête réseau
    hors du jeu.
    Accès : action Zeus (menu contextuel ZEN « ATAK · TOC »), touche CBA « Exporter le diagnostic du TOC » (sans touche
    par défaut), action ACE sur soi « Poste TOC » (fn_tocStation « menu »). Droit « diag » (fn_tocAccess).
    Modes :
      ["collect"]                 serveur : lignes du relevé (tableau de textes)
      ["local"]                   client : lignes propres à ce joueur (images par seconde, écrans dessinés, version)
      ["run"]                     client : demande le relevé au serveur ; la réponse est copiée, écrite et montrée
      ["journal"]                 client : demande le journal des actions (fn_tocJournal) et le montre dans la fenêtre
      ["show", [titre, texte]]    client : fenêtre de lecture avec le texte à copier
      ["clip", texte]             client : presse-papiers (jeu, puis DLL du mod en multijoueur)
      ["initClient"]              client : touche CBA, action Zeus, réponses du serveur
    Événement local : comspec_atak_toc_diag [texte] à chaque relevé reçu.
*/
params [["_mode", "run"], ["_a", []]];
private _sides = ["WEST", "EAST", "GUER", "CIV"];
private _T = { _this call comspec_atak_native_fnc_tr };
switch (_mode) do {
    case "collect": {
        if (!isServer) exitWith { [] };
        private _tocTrusted = true;
        private _out = [];
        private _put = { _out pushBack _this; };
        format ["DIAGNOSTIC DU TOC · %1Z", ["zulu"] call comspec_atak_native_fnc_tocRoom] call _put;
        format ["Version du serveur : %1 · API du TOC %2", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", "?"], ["version"] call comspec_atak_native_fnc_tocApi] call _put;
        format ["Carte %1 · mission %2 · %3 · ACE %4 · ZEN %5", worldName, missionName, ["serveur dédié", "partie hébergée"] select hasInterface, ["non", "oui"] select (!isNil "ace_interact_menu_fnc_createAction"), ["non", "oui"] select (!isNil "zen_context_menu_fnc_addAction")] call _put;
        format ["Joueurs %1 · unités %2 · groupes %3 · images par seconde du serveur %4 (min %5)", count allPlayers, count allUnits, count allGroups, round diag_fps, round diag_fpsMin] call _put;
        // Réglages du TOC (valeurs en cours).
        "RÉGLAGES" call _put;
        {
            format ["  %1 = %2", _x, missionNamespace getVariable ["comspec_atak_native_" + _x, "?"]] call _put;
        } forEach ["toc_enabled", "toc_persist", "toc_access_mode", "toc_chief_rank", "toc_journal_max", "toc_journal_log", "toc_cam_max", "toc_cam_range"];
        // Salles et écrans.
        private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x };
        private _halls = ["dump"] call comspec_atak_native_fnc_tocHall;
        format ["SALLES : %1 · ÉCRANS : %2 (dont %3 éteints, %4 dalles de mur)", count _halls, count _scr, { _x getVariable ["COMSPEC_ATAK_TocOff", false] } count _scr, { !isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull]) } count _scr] call _put;
        {
            _x params ["_id", "_nm", "_sd", "_sc", "_props"];
            private _lk = ["locked", _id] call comspec_atak_native_fnc_tocAccess;
            format ["  salle %1 « %2 » %3 · %4 écran(s) · %5 scène(s) · %6 objet(s) · %7", _id, _nm, [_sd, "tous camps"] select (_sd isEqualTo ""), count (["screens", _id] call comspec_atak_native_fnc_tocHall), count _sc, count _props, ["libre", "verrouillée"] select (_lk select 0)] call _put;
        } forEach _halls;
        private _views = createHashMap;
        {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            private _k = format ["%1 %2", [_c param [1, ""], "-"] select ((_c param [1, ""]) isEqualTo ""), _c param [3, "?"]];
            _views set [_k, (_views getOrDefault [_k, 0]) + 1];
        } forEach _scr;
        { format ["  écrans %1 : %2", _x, _y] call _put; } forEach _views;
        format ["  caméras en direct demandées : %1 · mosaïques : %2 · sans salle : %3", { (_x getVariable ["COMSPEC_ATAK_TocCam", ""]) isNotEqualTo "" } count _scr, { (count (_x getVariable ["COMSPEC_ATAK_TocMosaic", []])) >= 5 } count _scr, { (_x getVariable ["COMSPEC_ATAK_TocHall", ""]) isEqualTo "" && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} } count _scr] call _put;
        // État du poste et flux, par camp.
        private _ev = missionNamespace getVariable ["COMSPEC_ATAK_TocEvents", createHashMap];
        if !(_ev isEqualType createHashMap) then { _ev = createHashMap; };
        {
            private _st = ["state", _x] call comspec_atak_native_fnc_tocRoom;
            private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _x], []];
            private _sd = _x;
            format ["POSTE %1 : révision %2 · chronos %3 · main courante %4 · exécution %5 · demandes %6 · pertes %7 · FLASH %8", _x, _st param [0, 0], count (_st param [1, []]), count (_st param [3, []]), count (_st param [4, []]), count (_st param [5, []]), count (_st param [8, []]), ["non", "oui"] select ((_st param [6, []]) isNotEqualTo [])] call _put;
            format ["  flux : %1 nœud(s), %2 événement(s) gardé(s), publié il y a %3 s", count (_feed param [1, []]), count (_ev getOrDefault [_x, []]), if ((_feed param [0, -1]) < 0) then { "-" } else { str round (serverTime - (_feed select 0)) }] call _put;
            format ["  postes tenus : %1 sur %2 · relèves en attente : %3 · lignes au journal : %4", { (_x select 6) isNotEqualTo "" } count (["list", _sd] call comspec_atak_native_fnc_tocStation), count (["list", _sd] call comspec_atak_native_fnc_tocStation),
                { (_x param [1, ""]) isEqualTo _sd } count (missionNamespace getVariable ["COMSPEC_ATAK_TocHandoverPending", []]), count (["read", [_sd, 500]] call comspec_atak_native_fnc_tocJournal)] call _put;
        } forEach _sides;
        // Sauvegarde.
        (["info"] call comspec_atak_native_fnc_tocPersist) params [["_fmt", 0], ["_date", []], ["_size", 0], ["_bak", false]];
        format ["SAUVEGARDE : format %1 · écrite le %2 · %3 caractères · copie de secours %4 · reprise faite %5", _fmt, _date, _size, ["non", "oui"] select _bak, ["non", "oui"] select (missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false])] call _put;
        format ["PRÉRÉGLAGES : %1 dans le profil du serveur, %2 dans la mission", count (["list", "profile"] call comspec_atak_native_fnc_tocPresets), count (["list", "mission"] call comspec_atak_native_fnc_tocPresets)] call _put;
        _out
    };
    case "local": {
        if (!hasInterface) exitWith { [] };
        private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x };
        [
            format ["CE JOUEUR : version %1 · images par seconde %2 (min %3)", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", "?"], round diag_fps, round diag_fpsMin],
            format ["  écrans connus %1 · dessinés ici %2 · à moins de 100 m %3 · thème %4", count _scr, { (_x getVariable ["COMSPEC_ATAK_TocPainted", []]) isNotEqualTo [] } count _scr, { (_x distance player) < 100 } count _scr, ["variant"] call comspec_atak_native_fnc_tocTheme],
            format ["  rôle %1 · droits de la salle : %2", ["roleOf", [player, ""]] call comspec_atak_native_fnc_tocAccess, ["mode"] call comspec_atak_native_fnc_tocAccess]
        ]
    };
    case "run": {
        if (!hasInterface) exitWith { false };
        ["runSrv", player] remoteExecCall ["comspec_atak_native_fnc_tocDiag", 2];
        true
    };
    case "runSrv": {
        if (!isServer) exitWith { false };
        (["actor", _a] call comspec_atak_native_fnc_tocAccess) params ["_u"];
        if (isNull _u) exitWith { false };
        if !(["check", [_u, "diag", "", "diag.export", ""]] call comspec_atak_native_fnc_tocAccess) exitWith { false };
        private _l = ["collect"] call comspec_atak_native_fnc_tocDiag;
        // Une ligne par ligne du relevé, une seule fois, dans le journal COMSPEC.
        { ["INFO", "TOC", _x] call comspec_atak_native_fnc_log; } forEach _l;
        ["recv", _l] remoteExecCall ["comspec_atak_native_fnc_tocDiag", owner _u];
        true
    };
    case "recv": {
        if (!hasInterface || {isMultiplayer && {isRemoteExecuted} && {!(remoteExecutedOwner in [0, 2])}} || {!(_a isEqualType [])}) exitWith { false };
        private _txt = ((_a select { _x isEqualType "" }) + (["local"] call comspec_atak_native_fnc_tocDiag)) joinString endl;
        uiNamespace setVariable ["COMSPEC_ATAK_TocDiagText", _txt];
        ["clip", _txt] call comspec_atak_native_fnc_tocDiag;
        ["comspec_atak_toc_diag", [_txt]] call CBA_fnc_localEvent;
        ["show", ["Diagnostic du TOC", _txt]] call comspec_atak_native_fnc_tocDiag;
        true
    };
    case "journal": {
        if (!hasInterface) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocJournalShow", true];
        ["get", ["", 200]] call comspec_atak_native_fnc_tocJournal
    };
    case "clip": {
        if !(_a isEqualType "") exitWith { false };
        copyToClipboard _a;
        // En multijoueur copyToClipboard est sans effet : la DLL du mod écrit le presse-papiers Windows, par morceaux.
        if (isMultiplayer && {!isNil "comspec_atak_native_fnc_extensionCall"}) then {
            private _b = ["ClipBegin"] call comspec_atak_native_fnc_extensionCall;
            if (_b isEqualType "" && {(_b select [0, 2]) isEqualTo "OK"}) then {
                for "_i" from 0 to ((count _a) - 1) step 2000 do {
                    forceUnicode 0;
                    ["ClipAppend", [_a select [_i, 2000]]] call comspec_atak_native_fnc_extensionCall;
                };
                ["ClipCommit"] call comspec_atak_native_fnc_extensionCall;
            };
        };
        true
    };
    case "show": {
        _a params [["_title", ""], ["_text", ""]];
        if (!hasInterface) exitWith { false };
        disableSerialization;
        private _parent = findDisplay 312;
        if (isNull _parent) then { _parent = findDisplay 46; };
        if (isNull _parent) exitWith { false };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        private _w = safeZoneW * 0.56;
        private _h = safeZoneH * 0.7;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _rowH = safeZoneH * 0.034;
        private _gap = safeZoneH * 0.008;
        private _mk = {
            params ["_cls", "_pos"];
            private _c = _d ctrlCreate [_cls, -1];
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };
        private _bg = ["COMSPEC_RscPanel", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor (["rgba", ["bg1", 0.97, "auto"]] call comspec_atak_native_fnc_tocTheme);
        private _head = ["COMSPEC_RscText", [_x0, _y0, _w, _rowH * 1.3]] call _mk;
        _head ctrlSetBackgroundColor (["rgba", ["bg3", 1, "auto"]] call comspec_atak_native_fnc_tocTheme);
        _head ctrlSetTextColor (["rgba", ["text", 1, "auto"]] call comspec_atak_native_fnc_tocTheme);
        _head ctrlSetText ("  " + (_title call _T));
        private _body = ["COMSPEC_RscEditMulti", [_x0 + _gap, _y0 + _rowH * 1.3 + _gap, _w - 2 * _gap, _h - _rowH * 3.2 - 2 * _gap]] call _mk;
        _body ctrlSetText _text; // i18n:brut (texte déjà composé dans la langue du joueur)
        _body ctrlSetTextColor (["rgba", ["text", 1, "auto"]] call comspec_atak_native_fnc_tocTheme);
        _body ctrlSetBackgroundColor (["rgba", ["bg0", 1, "auto"]] call comspec_atak_native_fnc_tocTheme);
        _d setVariable ["text", _text];
        private _by = _y0 + _h - _rowH * 1.4;
        private _note = ["COMSPEC_RscText", [_x0 + _gap, _by, _w * 0.5, _rowH]] call _mk;
        _note ctrlSetTextColor (["rgba", ["muted", 1, "auto"]] call comspec_atak_native_fnc_tocTheme);
        private _copy = ["COMSPEC_RscButtonPrimary", [_x0 + _w - _gap - _w * 0.2, _by, _w * 0.2, _rowH]] call _mk;
        _copy ctrlSetText ("COPIER" call _T);
        private _close = ["COMSPEC_RscButton", [_x0 + _w - 2 * _gap - _w * 0.4, _by, _w * 0.2, _rowH]] call _mk;
        _close ctrlSetText ("FERMER" call _T);
        _d setVariable ["note", _note];
        _close ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
        _copy ctrlAddEventHandler ["ButtonClick", {
            private _disp = ctrlParent (_this select 0);
            ["clip", _disp getVariable ["text", ""]] call comspec_atak_native_fnc_tocDiag;
            (_disp getVariable ["note", controlNull]) ctrlSetText ("Copié : collez-le avec Ctrl+V" call comspec_atak_native_fnc_tr);
        }];
        true
    };
    case "initClient": {
        if (!hasInterface || {missionNamespace getVariable ["COMSPEC_ATAK_TocDiagInit", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocDiagInit", true];
        // Journal demandé pour lecture (action ACE, action Zeus) : montré dans la fenêtre dès sa réception.
        ["comspec_atak_toc_journal", {
            params [["_sd", ""], ["_l", []]];
            if !(missionNamespace getVariable ["COMSPEC_ATAK_TocJournalShow", false]) exitWith {};
            missionNamespace setVariable ["COMSPEC_ATAK_TocJournalShow", false];
            private _txt = if (_l isEqualTo []) then { "Aucune action au journal." call comspec_atak_native_fnc_tr } else { ["text", _l] call comspec_atak_native_fnc_tocJournal };
            ["show", ["Journal des actions du TOC", _txt]] call comspec_atak_native_fnc_tocDiag;
        }] call CBA_fnc_addEventHandler;
        ["COMSPEC ATAK", "TocDiag", ["Exporter le diagnostic du TOC", "Relève salles, écrans, flux, postes, sauvegarde, performances et versions du TOC, l'écrit dans le journal COMSPEC et le copie dans le presse-papiers. Réservé au chef de poste et à l'administrateur Zeus. Exemple : un écran reste noir, vous appuyez, vous collez le texte dans le salon support."], { ["run"] call comspec_atak_native_fnc_tocDiag; true }, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
        if (!isNil "zen_context_menu_fnc_addAction") then {
            private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
            [["comspec_atak_toc", "ATAK · TOC", _icon, {}, { true }] call comspec_atak_native_fnc_zenAction, [], 6] call zen_context_menu_fnc_addAction;
            [["comspec_atak_toc_diag", "Exporter le diagnostic du TOC", "", { ["run"] call comspec_atak_native_fnc_tocDiag; }, { true }] call comspec_atak_native_fnc_zenAction, ["comspec_atak_toc"], 0] call zen_context_menu_fnc_addAction;
            [["comspec_atak_toc_journal", "Journal des actions du TOC", "", { ["journal"] call comspec_atak_native_fnc_tocDiag; }, { true }] call comspec_atak_native_fnc_zenAction, ["comspec_atak_toc"], 0] call zen_context_menu_fnc_addAction;
            [["comspec_atak_toc_relief", "Postes et relèves du TOC", "", { ["open"] call comspec_atak_native_fnc_tocHandover; }, { true }] call comspec_atak_native_fnc_zenAction, ["comspec_atak_toc"], 0] call zen_context_menu_fnc_addAction;
        };
    };
    default { false };
};
