/*
    Garde-fou du téléphone (docs/bugs/2026-11-08-atak-plantage-camera-ip-boucle.md). Joueur dont le client plantait dès
    qu'il reprenait un téléphone, après un plantage sur une caméra IP. Tout ce que le téléphone rejoue passe ici :
      - la page rouverte : jamais une page caméra (caméra et texture r2t créées avant que le joueur ait vu l'écran),
        jamais une page en quarantaine ;
      - les étapes à risque (première sortie du téléphone, démarrage d'un flux) laissent un marqueur écrit sur le disque
        AVANT l'étape, effacé après 20 s sans incident. Marqueur retrouvé au lancement suivant : le jeu s'est arrêté
        pendant l'étape, mode sans échec de la caméra et proposition de réparer ;
      - les données du profil sont vérifiées avant usage : entrée illisible ignorée, ligne [WARN] au journal.
    Le mode compromis (COMSPEC_ATAK_CompromiseView) et l'identifiant du terminal ne sont jamais effacés par la réparation.
    Profil : COMSPEC_ATAK_GuardStep ([étape, page]), COMSPEC_ATAK_SafeCam ([raison]), COMSPEC_ATAK_PageFails ([[page, essais]...]),
             COMSPEC_ATAK_Quarantine ([pages]).
    Modes :
      ["boot"]                   XEH_postInitClient : purge demandée, marqueur laissé par un plantage, vérification du profil
      ["sweep"]                  vérifie les données du profil ; renvoie le nombre d'entrées écartées
      ["restorePage"]            page à rouvrir à la création du display (fn_displayLoad)
      ["opened", [page, t0]]     fin du dessin d'arrivée (fn_displayLoad) : durée mesurée
      ["begin", [étape, page]]   marqueur écrit sur le disque, effacé 20 s plus tard par ["end"]
      ["end"]                    fin de l'étape à risque
      ["page", page]             bool : page utilisable (pas en quarantaine)
      ["fail", [page, raison]]   essai compté ; au second, page en quarantaine
      ["safe"]                   bool : mode sans échec de la caméra actif
      ["safeOff"]                le joueur réactive la caméra
      ["camOk", [objet, genre]]  bool : source vidéo valide ("fixed" caméra IP, "unit" porteur, "uav" drone) ; sinon
                                 notification et ligne [WARN], et rien n'est créé
      ["r2t", taille]            côté de texture r2t valide (puissance de 2, 64 à 1024), sinon 256
      ["camName", objet]         nom de caméra sûr pour un texte structuré (variable publique venue d'un autre client)
      ["offlineOk", sauvegarde]  bool : forme de COMSPEC_ATAK_OfflineSave valide, et pas de mode sans échec
      ["repair", origine]        « Réparer mes données » ; renvoie le nombre d'éléments écartés
      ["quarantined"]            pages en quarantaine
*/
params [["_mode", "page"], ["_arg", ""]];
private _fn = comspec_atak_native_fnc_deviceGuard;
// Pages qui créent une caméra et une texture r2t dès leur dessin : ouvertes seulement par un choix du joueur.
#define CAM_PAGES ["LIVECAM", "VIEW3D"]
#define MAX_FAILS 2
#define SLOW_MS 2500
#define STEP_CLEAR_S 20
#define MARK_MIN_GAP_S 60
private _list = { params ["_k"]; private _v = profileNamespace getVariable [_k, []]; if (_v isEqualType []) then { _v } else { [] } };
private _warn = { params ["_msg"]; ["WARN", "ATAK", _msg] call comspec_atak_native_fnc_log; };
// Liste de lignes [[clé, valeur]...] utilisable par createHashMapFromArray.
private _pairsOk = { params ["_l"]; _l isEqualType [] && {(_l findIf { !(_x isEqualType []) || {(count _x) isNotEqualTo 2} }) < 0} };

switch (_mode) do {
    case "quarantined": { (["COMSPEC_ATAK_Quarantine"] call _list) select { _x isEqualType "" } };
    case "page": {
        if !(_arg isEqualType "") exitWith { false };
        !((toUpper _arg) in (["quarantined"] call _fn))
    };
    case "fail": {
        _arg params [["_page", ""], ["_why", ""]];
        if !(_page isEqualType "" && {_page isNotEqualTo ""}) exitWith { false };
        _page = toUpper _page;
        private _fails = (["COMSPEC_ATAK_PageFails"] call _list) select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType 0} };
        private _i = _fails findIf { (_x select 0) isEqualTo _page };
        private _n = 1;
        if (_i < 0) then { _fails pushBack [_page, 1]; } else { _n = ((_fails select _i) select 1) + 1; (_fails select _i) set [1, _n]; };
        profileNamespace setVariable ["COMSPEC_ATAK_PageFails", _fails];
        private _q = ["quarantined"] call _fn;
        if (_n >= MAX_FAILS && {!(_page in _q)}) then {
            _q pushBack _page;
            profileNamespace setVariable ["COMSPEC_ATAK_Quarantine", _q];
            [format ["Quarantaine : app %1 désactivée après %2 échec(s) (%3)", _page, _n, _why]] call _warn;
            ["WARNING", format ["App %1 mise en quarantaine après %2 échecs : elle ne se rouvre plus toute seule (Réglages > Réparer mes données)", _page, _n], 8, 60] call comspec_atak_native_fnc_notify;
        } else {
            [format ["App %1 : échec %2/%3 (%4)", _page, _n, MAX_FAILS, _why]] call _warn;
        };
        [] call comspec_atak_native_fnc_saveProfile;
        true
    };
    case "restorePage": {
        private _page = profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"];
        if !(_page isEqualType "") then { ["Dernière page illisible : le téléphone rouvre la carte"] call _warn; _page = "MAP"; };
        _page = toUpper _page;
        if (_page in CAM_PAGES) then {
            ["INFO", "ATAK", format ["Dernière page %1 (caméra) : le téléphone rouvre la carte", _page]] call comspec_atak_native_fnc_log;
            _page = "MAP";
        };
        if !(["page", _page] call _fn) then {
            [format ["Dernière page %1 en quarantaine : le téléphone rouvre la carte", _page]] call _warn;
            _page = "MAP";
        };
        ["begin", ["PHONE", _page]] call _fn;
        _page
    };
    case "opened": {
        _arg params [["_page", ""], ["_t0", diag_tickTime]];
        private _ms = round ((diag_tickTime - _t0) * 1000);
        if (_ms > SLOW_MS && {_page isEqualType ""} && {_page isNotEqualTo ""}) then {
            ["fail", [_page, format ["dessin d'arrivée en %1 ms", _ms]]] call _fn;
        };
        true
    };
    case "begin": {
        _arg params [["_step", "PHONE"], ["_page", ""]];
        // Déjà couvert (marqueur encore en mémoire) : rien à réécrire.
        private _at = uiNamespace getVariable ["COMSPEC_ATAK_GuardMarkAt", -1e9];
        if (diag_tickTime - _at < MARK_MIN_GAP_S && {(profileNamespace getVariable ["COMSPEC_ATAK_GuardStep", []]) isNotEqualTo []}) exitWith { true };
        uiNamespace setVariable ["COMSPEC_ATAK_GuardMarkAt", diag_tickTime];
        profileNamespace setVariable ["COMSPEC_ATAK_GuardStep", [_step, _page]];
        // Sur le disque avant l'étape : une écriture regroupée n'aurait pas survécu au plantage.
        [true] call comspec_atak_native_fnc_saveProfile;
        [{ ["end"] call comspec_atak_native_fnc_deviceGuard; }, [], STEP_CLEAR_S] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "end": {
        if ((profileNamespace getVariable ["COMSPEC_ATAK_GuardStep", []]) isEqualTo []) exitWith { false };
        profileNamespace setVariable ["COMSPEC_ATAK_GuardStep", []];
        ["dirty"] call comspec_atak_native_fnc_profileSave;
        true
    };
    case "safe": {
        private _v = profileNamespace getVariable ["COMSPEC_ATAK_SafeCam", []];
        _v isEqualType [] && {_v isNotEqualTo []}
    };
    case "safeOff": {
        profileNamespace setVariable ["COMSPEC_ATAK_SafeCam", nil];
        [] call comspec_atak_native_fnc_saveProfile;
        ["INFO", "ATAK", "Mode sans échec de la caméra levé par le joueur"] call comspec_atak_native_fnc_log;
        true
    };
    case "boot": {
        if (!hasInterface) exitWith { false };
        if (missionNamespace getVariable ["comspec_atak_native_purge_camlinks", false]) then { ["repair", "réglage « Réparer mes données à chaque lancement »"] call _fn; };
        ["sweep"] call _fn;
        private _mark = profileNamespace getVariable ["COMSPEC_ATAK_GuardStep", []];
        if (_mark isEqualType [] && {_mark isNotEqualTo []}) then {
            profileNamespace setVariable ["COMSPEC_ATAK_GuardStep", []];
            _mark params [["_step", "?", [""]], ["_page", "", [""]]];
            private _why = format ["jeu arrêté pendant l'étape %1 %2", _step, _page];
            [_why] call _warn;
            profileNamespace setVariable ["COMSPEC_ATAK_SafeCam", [_why]];
            if (_page isNotEqualTo "") then { ["fail", [_page, _why]] call _fn; };
            [] call comspec_atak_native_fnc_saveProfile;
        };
        if (["safe"] call _fn) then {
            [{ ["WARNING", "Le jeu s'est arrêté pendant le chargement du téléphone. Caméras en mode sans échec. Pour nettoyer : Réglages > À propos > Réparer mes données.", 14, 70] call comspec_atak_native_fnc_notify; }, [], 20] call comspec_atak_native_fnc_waitAndExec;
        };
        true
    };
    case "sweep": {
        private _n = 0;
        private _page = profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "MAP"];
        if !(_page isEqualType "" && {(count _page) <= 32}) then {
            ["Profil : dernière page illisible, ignorée"] call _warn;
            profileNamespace setVariable ["COMSPEC_ATAK_LastPage", nil];
            _n = _n + 1;
        };
        private _off = profileNamespace getVariable ["COMSPEC_ATAK_OfflineSave", []];
        if (_off isNotEqualTo [] && {!(["offlineShape", _off] call _fn)}) then {
            ["Profil : sauvegarde hors ligne illisible, ignorée"] call _warn;
            profileNamespace setVariable ["COMSPEC_ATAK_OfflineSave", nil];
            _n = _n + 1;
        };
        private _cv = profileNamespace getVariable ["COMSPEC_ATAK_CompromiseView", []];
        if (_cv isNotEqualTo [] && {!(_cv isEqualType [] && {(_cv param [0, 0]) isEqualType ""} && {[_cv param [1, 0]] call _pairsOk})}) then {
            ["Profil : état de compromission illisible, ignoré"] call _warn;
            profileNamespace setVariable ["COMSPEC_ATAK_CompromiseView", nil];
            _n = _n + 1;
        };
        if (_n > 0) then { [] call comspec_atak_native_fnc_saveProfile; };
        _n
    };
    case "offlineShape": {
        if !(_arg isEqualType [] && {(_arg param [0, 0]) isEqualType ""}) exitWith { false };
        _arg params ["", "", ["_p2p", []], ["_msgs", []], ["_inbox", []], ["_tasks", []], ["_alerts", []]];
        if !(_msgs isEqualType [] && {_alerts isEqualType []} && {(count _msgs) <= 500} && {(count _alerts) <= 500}) exitWith { false };
        if !(_p2p isEqualType [] && {_inbox isEqualType []} && {_tasks isEqualType []}) exitWith { false };
        if ((_p2p findIf { !([_x] call _pairsOk) }) >= 0 || {(_inbox findIf { !([_x] call _pairsOk) }) >= 0}) exitWith { false };
        (_tasks findIf { !(_x isEqualType [] && {(count _x) isEqualTo 2} && {[_x select 1] call _pairsOk}) }) < 0
    };
    case "offlineOk": {
        if (["safe"] call _fn) exitWith { ["INFO", "ATAK", "Mode sans échec : sauvegarde hors ligne non rechargée"] call comspec_atak_native_fnc_log; false };
        private _ok = ["offlineShape", _arg] call _fn;
        if (!_ok) then { ["Sauvegarde hors ligne illisible : ignorée"] call _warn; };
        _ok
    };
    case "r2t": {
        private _s = [_arg, 0] select !(_arg isEqualType 0);
        if (_s in [64, 128, 256, 512, 1024]) exitWith { _s };
        [format ["Texture r2t de %1 px refusée (puissance de 2 entre 64 et 1024 attendue) : 256 px", _arg]] call _warn;
        256
    };
    case "camName": {
        private _t = if (_arg isEqualType objNull && {!isNull _arg}) then { _arg getVariable ["COMSPEC_ATAK_CamName", "CAM"] } else { "CAM" };
        if !(_t isEqualType "") exitWith { "CAM" };
        (((_t select [0, 24]) splitString "<>&""'") joinString "")
    };
    case "camOk": {
        _arg params [["_obj", objNull], ["_kind", "unit"]];
        private _why = switch (true) do {
            case !(_obj isEqualType objNull): { "source illisible" };
            case (isNull _obj): { "source absente" };
            case (["safe"] call _fn): { "mode sans échec" };
            case (!isNull (uiNamespace getVariable ["ace_arsenal_display", displayNull]) || {!isNull (uiNamespace getVariable ["BIS_fnc_arsenal_display", displayNull])}): { "arsenal ouvert" };
            case (_kind isEqualTo "fixed" && {!(_obj isKindOf "COMSPEC_ATAK_CameraObj")}): { format ["objet %1 : pas une caméra IP", typeOf _obj] };
            case (_kind isEqualTo "fixed" && {["dead", _obj] call comspec_atak_native_fnc_camAction}): { "caméra détruite" };
            case (_kind isEqualTo "unit" && {!(_obj isKindOf "CAManBase")}): { format ["objet %1 : pas un porteur", typeOf _obj] };
            case (_kind isEqualTo "unit" && {!alive _obj}): { "porteur mort" };
            case (_kind isEqualTo "uav" && {!unitIsUAV _obj}): { format ["objet %1 : pas un drone", typeOf _obj] };
            case (_kind isEqualTo "uav" && {!alive _obj}): { "drone détruit" };
            case (isNull (findDisplay 46)): { "pas encore en jeu" };
            default { "" };
        };
        if (_why isNotEqualTo "") then {
            ["WARN", "CAMERA", format ["Flux %1 refusé : %2 (aucune caméra ni texture créée)", _kind, _why]] call comspec_atak_native_fnc_log;
            ["WARNING", format ["Vidéo live : flux indisponible (%1)", _why call comspec_atak_native_fnc_tr], 4, 30] call comspec_atak_native_fnc_notify;
        };
        _why isEqualTo ""
    };
    case "repair": {
        private _from = [_arg, "?"] select !(_arg isEqualType "");
        private _n = 0;
        // Flux vidéo en cours et boucles associées.
        if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull])) then { _n = _n + 1; };
        [] call comspec_atak_native_fnc_livecamStop;
        // Pages, quarantaine, marqueur, sauvegarde hors ligne. Jamais le mode compromis ni l'identifiant du terminal.
        {
            if !((profileNamespace getVariable [_x, []]) in [[], "MAP", ""]) then { _n = _n + 1; };
            profileNamespace setVariable [_x, nil];
        } forEach ["COMSPEC_ATAK_LastPage", "COMSPEC_ATAK_GuardStep", "COMSPEC_ATAK_SafeCam", "COMSPEC_ATAK_PageFails", "COMSPEC_ATAK_Quarantine", "COMSPEC_ATAK_OfflineSave"];
        { uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_VideoPath", "COMSPEC_ATAK_VideoTier", "COMSPEC_ATAK_FeedDrop", "COMSPEC_ATAK_LivecamOsd", "COMSPEC_ATAK_LinkAlly"];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        { if ((_s getOrDefault [_x, ""]) isNotEqualTo "") then { _n = _n + 1; }; _s deleteAt _x; } forEach ["livecamTarget", "livecamShare", "livecamRename", "livecamPan", "mapCam", "shareTo", "shareMethod"];
        missionNamespace setVariable ["COMSPEC_ATAK_Offline", nil];
        [true] call comspec_atak_native_fnc_saveProfile;
        // Caches du dossier Documents (mis de côté par la DLL, pas supprimés) : hors de la pile d'appel du bouton.
        [_from, _n] spawn {
            params ["_from", "_n"];
            private _r = ["COMSPECExtension", "RepairLocalData", []] call comspec_atak_native_fnc_extAwait;
            private _out = _r param [0, ""];
            if !(_out isEqualType "") then { _out = ""; };
            private _files = if ((_out select [0, 3]) isEqualTo "OK|") then { parseNumber (_out select [3]) } else { 0 };
            ["INFO", "ATAK", format ["Données réparées (%1) : %2 élément(s) du profil, %3 fichier(s) de cache mis de côté (%4)", _from, _n, _files, _out]] call comspec_atak_native_fnc_log;
            ["SUCCESS", format ["Données réparées : %1 élément(s) écarté(s). Rangez puis rouvrez le téléphone.", _n + _files], 6, 40] call comspec_atak_native_fnc_notify;
        };
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; };
        _n
    };
    default { false };
};
