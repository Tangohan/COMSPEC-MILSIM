/*
    Notification MPU à l'écran pendant l'alternat (retour de jeu : « afficher le réseau MPU quand on presse CapsLock et qu'on parle »).
    Petit encadré sur l'écran du jeu (calque RscTitles COMSPEC_RscTitleMpuHud, pas dans le téléphone), juste au-dessus du cadre d'émission d'ACRE2 :
      - émission (alternat ACRE2, COMSPEC_ATAK_PttMine ; TFAR, COMSPEC_ATAK_VoiceTx) dont la voix passe par le maillage (fn_mpuVoice "enabled" : réglage,
        relais, garde radio, liaison réaliste ; radio pontée et en service, COMSPEC_ATAK_VoiceMe ; au moins un correspondant ponté joint sur la même
        fréquence) : « MPU · voix par le maillage », puis « 2 sauts · 3 correspondants » (plus court chemin, nombre de correspondants) ;
        sinon rien, ou, réglage « Dire pourquoi » coché, une ligne grise « MPU : non utilisée (aucun relais) » (première étape en échec de fn_mpuDiag) ;
      - réception (COMSPEC_ATAK_PttHeard filtré par fn_mpu5 "speakers" ; TFAR : COMSPEC_ATAK_TfRxActive) d'un correspondant ponté joint par le
        maillage (fn_mpuVoice "linkOf", même règle que la voix) : « DUPONT via MPU », puis « 3 sauts » ; plusieurs : le plus récent, « +N ».
    Place : cadre d'ACRE2 lu dans son code (acre_sys_list, 2.14) : 0,352 x 0,128 (unités d'écran), coin bas droit (safeZoneX + safeZoneW - 0,352,
    safeZoneY + safeZoneH - 0,128), déplaçable dans la disposition de l'interface d'Arma (profil IGUI_grid_ACRE_notification_X / Y), notifications
    empilées vers le haut (5 cases, acre_sys_list_hintBuffer : lu, jamais écrit). L'encadré se pose au-dessus de la plus haute case occupée, de la case
    RELAIS ATAK si elle est au-dessus (fn_relayTxBadge), plus le décalage du joueur. Sans ACRE2 : bas droit, 12 % de la hauteur d'écran au-dessus du bord.
    Coût nul au repos : aucune boucle, seulement des événements (ACRE2, fn_mpuVoice pour TFAR) ; pendant une émission entendue ou faite, une boucle de
    0,25 s (CBA_fnc_addPerFrameHandler), retirée 0,4 s après la fin. Aucune requête réseau, rien de diffusé : état propre à ce client.
    ACRE2 et TFAR ne sont lus que par fn_radioGuard ("read") et par les fonctions du mod qui passent par lui (fn_mpuVoice, fn_mpu5, fn_mpuDiag).
    Réglages joueur : comspec_atak_native_mpu_hud (aussi dans Réglages > Matériel et réseau du téléphone), _why, _dy (% de l'écran).
    Mode test (autotest, uiNamespace COMSPEC_ATAK_MpuHudTest) : l'état voix posé à la main suffit (réglage serveur, relais et liaison non exigés),
    les émetteurs entendus sont lus dans COMSPEC_ATAK_PttHeard sans filtre de fréquence.
      "init" ; "kick" ; "tick" ; "stop" ; "model" -> [mode "tx" | "rx" | "why" | "", ligne 1, ligne 2, activité] ; "why" -> cause courte
      "hidden" -> vrai si l'écran du jeu est caché ; "place" [lignes] -> [x, y, w, h, hauteur de ligne] ; "draw" [modèle] ; "hide"
*/
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _layer = "COMSPEC_ATAK_MpuHud";
private _tr = { _this call comspec_atak_native_fnc_tr };
private _esc = { ((_this regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
private _hopTxt = { params ["_h"]; _h = (round _h) max 1; [format [("%1 sauts") call _tr, _h], ("1 saut") call _tr] select (_h isEqualTo 1) };
switch (_act) do {
    case "init": {
        if (!hasInterface) exitWith {};
        // Événements d'ACRE2 : lecture seule, rien n'est appelé chez lui. Image suivante : fn_mpu5 a noté l'émission (COMSPEC_ATAK_PttMine, PttHeard).
        { [_x, { [{ ["kick"] call comspec_atak_native_fnc_mpuHud; }] call comspec_atak_native_fnc_nextFrame; }] call CBA_fnc_addEventHandler; } forEach ["acre_startedSpeaking", "acre_stoppedSpeaking", "acre_remoteStartedSpeaking", "acre_remoteStoppedSpeaking"];
    };
    case "kick": {
        if (!hasInterface || {!((missionNamespace getVariable ["comspec_atak_native_mpu_hud", true]) isEqualTo true)}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_MpuHudAt", diag_tickTime];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudPfh", [{ ["tick"] call comspec_atak_native_fnc_mpuHud; }, 0.25] call CBA_fnc_addPerFrameHandler];
        };
        ["tick"] call comspec_atak_native_fnc_mpuHud;
    };
    case "tick": {
        private _set = (missionNamespace getVariable ["comspec_atak_native_mpu_hud", true]) isEqualTo true;
        private _m = if (_set) then { ["model"] call comspec_atak_native_fnc_mpuHud } else { ["", "", "", false] };
        _m params ["_mode", "", "", "_active"];
        if (_active) then { missionNamespace setVariable ["COMSPEC_ATAK_MpuHudAt", diag_tickTime]; };
        private _hid = ["hidden"] call comspec_atak_native_fnc_mpuHud;
        if (_mode isNotEqualTo "" && {!_hid}) then {
            ["draw", _m] call comspec_atak_native_fnc_mpuHud;
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudDrawn", diag_tickTime];
        } else {
            // Fin de l'alternat : l'encadré reste 0,4 s, puis s'efface. Écran caché ou réglage décoché : tout de suite.
            if (_hid || {!_set} || {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudDrawn", -100]) >= 0.4}) then { ["hide"] call comspec_atak_native_fnc_mpuHud; };
        };
        // Plus personne n'émet depuis 0,4 s : la boucle s'arrête (coût zéro au repos).
        if (!_set || {!_active && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudAt", -100]) >= 0.4}}) then {
            ["hide"] call comspec_atak_native_fnc_mpuHud;
            private _h = missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1];
            if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudPfh", -1];
        };
    };
    case "stop": {
        private _h = missionNamespace getVariable ["COMSPEC_ATAK_MpuHudPfh", -1];
        if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
        missionNamespace setVariable ["COMSPEC_ATAK_MpuHudPfh", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_MpuHudShown", false];
        if (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudUp", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudUp", false];
            (_layer call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        };
    };
    case "model": {
        private _none = ["", "", "", false];
        private _test = (uiNamespace getVariable ["COMSPEC_ATAK_MpuHudTest", false]) isEqualTo true;
        if (!_test && {!(["read"] call comspec_atak_native_fnc_radioGuard)}) exitWith { _none };
        private _mine = uiNamespace getVariable ["COMSPEC_ATAK_PttMine", ["", 0]];
        if !(_mine isEqualType []) then { _mine = []; };
        private _myId = _mine param [0, ""];
        if !(_myId isEqualType "") then { _myId = ""; };
        private _tx = missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []];
        private _tfTx = _tx isEqualType [] && {(_tx param [0, objNull]) isEqualType objNull} && {!isNull (_tx param [0, objNull])};
        private _heardRaw = uiNamespace getVariable ["COMSPEC_ATAK_PttHeard", []];
        if !(_heardRaw isEqualType []) then { _heardRaw = []; };
        private _tfRx = missionNamespace getVariable ["COMSPEC_ATAK_TfRxActive", createHashMap];
        if !(_tfRx isEqualType createHashMap) then { _tfRx = createHashMap; };
        if (_myId isEqualTo "" && {!_tfTx} && {_heardRaw isEqualTo []} && {(count _tfRx) isEqualTo 0}) exitWith { _none };
        // Voix par le maillage autorisée (réglage, relais, garde radio, liaison réaliste) : relu au plus chaque seconde.
        (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudOn", []]) params [["_onAt", -100], ["_onV", false]];
        if (diag_tickTime - _onAt > 1) then {
            _onV = (["enabled"] call comspec_atak_native_fnc_mpuVoice) isEqualTo true;
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudOn", [diag_tickTime, _onV]];
        };
        private _on = _test || {_onV};
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []], "", "", ["_peers", []]];
        if !(_me isEqualType []) then { _me = []; };
        if !(_peers isEqualType []) then { _peers = []; };
        // Correspondants pontés joints par le maillage, sur la même fréquence : [indicatif, sauts, distance, même fréquence].
        private _joined = _peers select { _x isEqualType [] && {(count _x) >= 4} && {(_x select 1) isEqualType 0} && {(_x select 3) isEqualTo true} };
        // Émission : la mienne passe avant ce que j'entends.
        if (_myId isNotEqualTo "" || {_tfTx}) exitWith {
            // ACRE2 : seule la radio reliée à la MPU5 part dans le maillage. TFAR : fn_mpuVoice ne pose COMSPEC_ATAK_VoiceTx que pour elle.
            private _same = _tfTx || {(_me param [0, ""]) isEqualTo "acre" && {(_me param [1, ""]) isEqualTo _myId}};
            if (_on && {_me isNotEqualTo []} && {_same} && {_joined isNotEqualTo []}) exitWith {
                private _hs = _joined apply { _x select 1 };
                _hs sort true;
                private _n = count _joined;
                ["tx", ("MPU · voix par le maillage") call _tr,
                    format ["%1 · %2", [_hs select 0] call _hopTxt, [format [("%1 correspondants") call _tr, _n], ("1 correspondant") call _tr] select (_n isEqualTo 1)], true]
            };
            if !((missionNamespace getVariable ["comspec_atak_native_mpu_hud_why", false]) isEqualTo true) exitWith { ["", "", "", true] };
            private _cause = switch (true) do {
                case (!_on || {_me isEqualTo []}): { ["why"] call comspec_atak_native_fnc_mpuHud };
                case (!_same): { ("autre radio que celle reliée") call _tr };
                default { ("aucun correspondant relié joint") call _tr };
            };
            ["why", format [("MPU : non utilisée (%1)") call _tr, _cause], "", true]
        };
        // Réception : ma radio pontée et en service, émetteur ponté, joint par le maillage, sur sa radio pontée, entendu sur une de mes fréquences.
        private _heard = [];
        if (_on && {_me isNotEqualTo []}) then {
            private _sp = [];
            if (_heardRaw isNotEqualTo []) then {
                _sp = if (_test) then {
                    _heardRaw select { _x isEqualType [] && {(count _x) >= 3} } apply { [_x select 0, _x select 1, _x select 2] }
                } else {
                    (["speakers"] call comspec_atak_native_fnc_mpu5) select { (_x select 0) isNotEqualTo player } apply { [_x select 0, _x select 1, diag_tickTime - (_x select 4)] }
                };
            };
            {
                _x params [["_u", objNull], ["_id", ""], ["_t0", 0]];
                if (!(_u isEqualType objNull) || {isNull _u} || {_u isEqualTo player}) then { continue };
                private _pp = ["voice", _u] call comspec_atak_native_fnc_meshRead;
                if (_pp isEqualTo [] || {(_pp select 0) isEqualTo "acre" && {(_pp select 1) isNotEqualTo _id}}) then { continue };
                private _h = ["linkOf", [_u]] call comspec_atak_native_fnc_mpuVoice;
                if (!(_h isEqualType 0) || {_h < 0}) then { continue };
                if (!(_t0 isEqualType 0) || {!finite _t0}) then { _t0 = 0; };
                _heard pushBack [_u, _h, _t0];
            } forEach _sp;
            // TFAR : émissions des correspondants pontés sur ma fréquence (fn_mpuVoice "tfRx").
            {
                private _u = objectFromNetId _x;
                if (isNull _u || {_u isEqualTo player} || {(_heard findIf { (_x select 0) isEqualTo _u }) >= 0}) then { continue };
                private _h = ["linkOf", [_u]] call comspec_atak_native_fnc_mpuVoice;
                if (_h isEqualType 0 && {_h >= 0}) then { _heard pushBack [_u, _h, [0, _y] select (_y isEqualType 0)]; };
            } forEach _tfRx;
        };
        if (_heard isEqualTo []) exitWith { ["", "", "", true] };
        // Le plus récent (début d'émission le plus tard).
        private _best = 0;
        { if ((_x select 2) > ((_heard select _best) select 2)) then { _best = _forEachIndex; }; } forEach _heard;
        (_heard select _best) params ["_u", "_h"];
        private _cs = [_u, false] call comspec_atak_native_fnc_unitCallsign;
        if (!(_cs isEqualType "") || {_cs isEqualTo ""}) then { _cs = name _u; };
        private _more = (count _heard) - 1;
        ["rx", format [("%1 via MPU") call _tr, toUpper _cs] + (["", format ["  +%1", _more]] select (_more > 0)), [_h] call _hopTxt, true]
    };
    case "why": {
        // Cause courte : première étape en échec de l'assistant de dépannage (fn_mpuDiag, lecture locale, cache 1 s), sans camarade choisi.
        private _first = ["first", [["steps", [""]] call comspec_atak_native_fnc_mpuDiag]] call comspec_atak_native_fnc_mpuDiag;
        _first params [["_id", ""], ["_st", ""], "", "", "", ["_v", ""]];
        private _c = switch (_id) do {
            case "setting": { "réglage du serveur décoché" };
            case "relay": { "aucun relais" };
            case "real": { "liaison réaliste coupée" };
            case "radio": { "radio non reconnue" };
            case "ts": { "TeamSpeak non relié" };
            case "mpu": { switch (_v) do { case "aucune MPU5": { "aucune MPU5" }; case "éteinte": { "MPU5 éteinte" }; case "batterie vide": { "batterie vide" }; case "objet manquant": { "objet requis manquant" }; default { "MPU5 en démarrage" }; } };
            case "bridge": { "radio non reliée à la MPU5" };
            case "mesh": { ["hors maillage", "signal trop faible"] select (_v isEqualTo "signal coupé") };
            default { "en attente du maillage" };
        };
        if (_st isEqualTo "ATTENTION" && {_id isNotEqualTo "mesh"}) then { _c = "en attente du maillage"; };
        _c call _tr
    };
    case "hidden": {
        // Carte d'Arma ouverte, téléphone en main plein écran, écran du jeu caché (cinématique, interface masquée), Zeus, spectateur, mort.
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        isNull (findDisplay 46) || {visibleMap} || {!((shownHUD) param [0, true])} || {!alive player} || {!isNull (findDisplay 312)} || {!isNull (findDisplay 60492)}
            || {(missionNamespace getVariable ["ace_spectator_isSet", false]) isEqualTo true}
            || {!isNull ([] call comspec_atak_native_fnc_display) && {_s getOrDefault ["interactive", false]} && {(toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"])) isEqualTo "FULL"}}
    };
    case "place": {
        private _lines = _arg param [0, 2];
        private _fh = 0.028 max (pixelH * 13);
        private _w = 0.352;
        private _h = _fh * ([1.3, 2.4] select (_lines > 1)) + 0.012;
        private _num = { params ["_v", "_d"]; [_d, _v] select (_v isEqualType 0 && {finite _v}) };
        private _acre = ((["compat"] call comspec_atak_native_fnc_radioGuard) param [0, "absent"]) isNotEqualTo "absent";
        private _x0 = safeZoneX + safeZoneW - _w;
        private _top = safeZoneY + safeZoneH - 0.12 * safeZoneH;
        if (_acre) then {
            // Cadre d'ACRE2 et ses notifications empilées vers le haut (au moins la case d'émission « TX »).
            private _ax = [profileNamespace getVariable ["IGUI_grid_ACRE_notification_X", safeZoneX + safeZoneW - 0.352], safeZoneX + safeZoneW - 0.352] call _num;
            private _ay = [profileNamespace getVariable ["IGUI_grid_ACRE_notification_Y", safeZoneY + safeZoneH - 0.128], safeZoneY + safeZoneH - 0.128] call _num;
            private _slots = 1;
            private _hb = missionNamespace getVariable ["acre_sys_list_hintBuffer", []];
            if (_hb isEqualType [] && {(count _hb) <= 8}) then { { if (_x isEqualType [] && {_x isNotEqualTo []}) then { _slots = _slots max (_forEachIndex + 1); }; } forEach _hb; };
            _x0 = _ax;
            _top = _ay - (_slots - 1) * 0.128;
        };
        _top = _top - 0.006;
        // Case RELAIS ATAK posée au-dessus du cadre ACRE (fn_relayTxBadge, choix 1) : on monte d'autant.
        if (_acre && {(uiNamespace getVariable ["COMSPEC_ATAK_RelayTxUp", false]) isEqualTo true} && {(missionNamespace getVariable ["comspec_atak_native_relay_tx_badge_pos", 0]) isEqualTo 1}) then {
            _top = _top - (0.034 max (pixelH * 14)) * 1.5 - 0.006;
        };
        _top = _top - (([missionNamespace getVariable ["comspec_atak_native_mpu_hud_dy", 0], 0] call _num) / 100) * safeZoneH;
        private _y0 = ((_top - _h) max safeZoneY) min (safeZoneY + safeZoneH - _h);
        _x0 = (_x0 max safeZoneX) min (safeZoneX + safeZoneW - _w);
        [_x0, _y0, _w, _h, _fh]
    };
    case "draw": {
        _arg params [["_mode", ""], ["_l1", ""], ["_l2", ""]];
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudUp", false]) then {
            (_layer call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleMpuHud", "PLAIN", 0, false];
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudUp", true];
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudShown", false];
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudKey", []];
        };
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_MpuHudDisplay", displayNull];
        if (isNull _d) exitWith {};
        private _cs = [7771, 7772, 7773, 7774] apply { _d displayCtrl _x };
        _cs params ["_bg", "_bar", "_ico", "_txt"];
        private _lines = [1, 2] select (_l2 isNotEqualTo "");
        private _p = ["place", [_lines]] call comspec_atak_native_fnc_mpuHud;
        _p params ["_x0", "_y0", "_w", "_h", "_fh"];
        missionNamespace setVariable ["COMSPEC_ATAK_MpuHudPos", [_x0, _y0, _w, _h]];
        private _key = [_mode, _l1, _l2, _p];
        if (_key isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_MpuHudKey", []])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudKey", _key];
            private _col = switch (_mode) do { case "tx": { [0.36, 0.78, 0.42, 1] }; case "rx": { [0.30, 0.74, 0.95, 1] }; default { [0.55, 0.6, 0.57, 1] }; };
            private _is = (_h - 0.016) min (_fh * 1.6);
            private _iw = _is * 0.75;
            _bg ctrlSetPosition [_x0, _y0, _w, _h];
            _bar ctrlSetPosition [_x0, _y0, 0.004, _h];
            _ico ctrlSetPosition [_x0 + 0.01, _y0 + (_h - _is) / 2, _iw, _is];
            _txt ctrlSetPosition [_x0 + 0.016 + _iw, _y0 + 0.004, _w - 0.02 - _iw, _h - 0.006];
            _bar ctrlSetBackgroundColor _col;
            _ico ctrlSetText (["\z\comspec_atak_native\addons\main\data\mpu_tx.paa", "\z\comspec_atak_native\addons\main\data\mpu_idle.paa"] select (_mode isEqualTo "why"));
            _ico ctrlSetTextColor _col;
            _txt ctrlSetFontHeight _fh;
            _txt ctrlSetStructuredText parseText (if (_mode isEqualTo "why") then {
                format ["<t color='#9aa59f'>%1</t>", _l1 call _esc]
            } else {
                format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t><br/><t color='#d7dfda' size='0.92'>%3</t>", ["#5cc76b", "#4dbdf2"] select (_mode isEqualTo "rx"), _l1 call _esc, _l2 call _esc]
            });
            { _x ctrlCommit 0; } forEach _cs;
        };
        // Apparition douce (0,15 s).
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudShown", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MpuHudShown", true];
            { _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit 0.15; } forEach _cs;
        };
    };
    case "hide": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudShown", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_MpuHudShown", false];
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_MpuHudDisplay", displayNull];
        if (!isNull _d) then { { private _c = _d displayCtrl _x; _c ctrlSetFade 1; _c ctrlCommit 0.15; } forEach [7771, 7772, 7773, 7774]; };
        // Disparition douce, puis calque retiré s'il n'est pas revenu entre-temps.
        [{
            if (!(missionNamespace getVariable ["COMSPEC_ATAK_MpuHudShown", false]) && {missionNamespace getVariable ["COMSPEC_ATAK_MpuHudUp", false]}) then {
                missionNamespace setVariable ["COMSPEC_ATAK_MpuHudUp", false];
                ("COMSPEC_ATAK_MpuHud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
            };
        }, [], 0.2] call comspec_atak_native_fnc_waitAndExec;
    };
};
