/*
    Dossier des captures d'écran (photos du téléphone, du drone, de la Galerie, des fiches, capture de la carte).
    Arma écrit chaque capture dans le dossier Screenshots du profil utilisé ; les DLL (COMSPEC Link et DLL native) la
    cherchent dans cet ordre, sans sous-dossier : (a) le dossier du réglage « Dossier des captures d'écran » s'il est
    rempli, (b) Screenshots du profil Arma (nom du profil transmis ici), (c) Documents\Arma 3\Screenshots et OneDrive.
      ["apply"] call comspec_atak_native_fnc_shotDir   réglage et profil transmis aux deux DLL (mémoire, immédiat)
      ["check", afficher] spawn comspec_atak_native_fnc_shotDir   vérification du dossier (disque, hors fil du jeu)
      ["text"] call comspec_atak_native_fnc_shotDir    dernière vérification en une ligne (Réglages › À propos)
      ["open"] call comspec_atak_native_fnc_shotDir    ouvre Documents\Arma 3 - COMSPEC\Captures dans l'explorateur
    Les photos du mod sont recopiées dans Documents\Arma 3 - COMSPEC\Captures et envoyées de là ; une fois reçues par
    Athena, leur original est retiré de Screenshots (Arma n'écrit plus de capture au-delà de 250 Mo par dossier).
    Résultat de la vérification : uiNamespace COMSPEC_ATAK_ShotDirInfo =
      [origine, dossier, existe, lisible, nombre d'images, dernière capture, âge en s, refus du réglage, réglage, dossiers, instant,
       taille du dossier en Mo (-1 inconnue), dossier Captures (« - » : impossible à créer)].
*/
params [["_mode", "apply"], ["_show", false]];
if (!hasInterface) exitWith { "" };

private _whyText = {
    params ["_e"];
    switch (_e) do {
        case "network": { "dossier réseau refusé : choisissez un dossier de ce PC" };
        case "not_absolute": { "chemin incomplet : indiquez le chemin complet, par exemple D:\Arma\Captures" };
        case "parent": { "« .. » n'est pas accepté dans le chemin" };
        case "drive_root": { "la racine d'un disque n'est pas acceptée : choisissez un dossier" };
        case "too_long": { "chemin trop long" };
        case "invalid": { "caractères interdits dans le chemin" };
        case "missing": { "dossier introuvable" };
        default { "" };
    }
};

switch (_mode) do {
    case "apply": {
        private _dir = missionNamespace getVariable ["comspec_atak_native_shot_dir", ""];
        if !(_dir isEqualType "") then { _dir = ""; };
        private _args = [trim _dir, profileName];
        // Les deux DLL cherchent des captures (photos : COMSPEC Link ; capture de la carte : DLL native).
        private _r = ["Shots.Config", _args] call comspec_atak_native_fnc_extensionCall;
        if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) then { "COMSPECExtension" callExtension ["Shots.Config", _args]; };
        uiNamespace setVariable ["COMSPEC_ATAK_ShotDirInfo", nil];
        if ((_r select [0, 4]) isEqualTo "ERR|") then {
            private _why = [_r select [4]] call _whyText;
            ["WARNING", "PHOTO", format ["Dossier des captures refusé (%1) : %2", _dir, _r]] call comspec_atak_native_fnc_log;
            // Une seule notification par valeur refusée (réglage appliqué au chargement et à chaque changement).
            if (_why isNotEqualTo "" && {(uiNamespace getVariable ["COMSPEC_ATAK_ShotDirWarned", ""]) isNotEqualTo _dir}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_ShotDirWarned", _dir];
                ["WARNING", format ["Dossier des captures non retenu : %1. Le dossier du profil Arma est utilisé.", _why], 8, 40, "SETTINGS"] call comspec_atak_native_fnc_notify;
            };
        };
        _r
    };
    case "check": {
        if (!canSuspend) exitWith { ["check", _show] spawn comspec_atak_native_fnc_shotDir; "" };
        ["apply"] call comspec_atak_native_fnc_shotDir;
        private _raw = (["COMSPECATAKNativeExtension", "Shots.Check", [], 15] call comspec_atak_native_fnc_extAwait) param [0, ""];
        if !(_raw isEqualType "") then { _raw = ""; };
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_ShotDirInfo", []];
            if (_show) then { hint (("Vérification du dossier des captures impossible : DLL du téléphone absente ou bloquée.") call comspec_atak_native_fnc_tr); };
            ""
        };
        (((_raw select [3]) splitString "|")) params [["_src", "none"], ["_dir", "-"], ["_ex", "0"], ["_rd", "0"], ["_n", "0"], ["_last", "-"], ["_age", "-1"], ["_uerr", "-"], ["_user", "-"], ["_dirs", "-"], ["_mb", "-1"], ["_cap", "?"]];
        private _info = [_src, _dir, _ex isEqualTo "1", _rd isEqualTo "1", parseNumber _n, _last, parseNumber _age, _uerr, _user, _dirs, diag_tickTime, parseNumber _mb, _cap];
        uiNamespace setVariable ["COMSPEC_ATAK_ShotDirInfo", _info];
        private _txt = ["text"] call comspec_atak_native_fnc_shotDir;
        ["INFO", "PHOTO", format ["Dossier des captures : %1 (%2) existe=%3 lisible=%4 images=%5 dernière=%6 refus=%7 · ordre : %8", _dir, _src, _ex, _rd, _n, _last, _uerr, _dirs]] call comspec_atak_native_fnc_log;
        if (_show) then {
            hint ((_txt) call comspec_atak_native_fnc_tr);
            [["WARNING", "INFO"] select (_ex isEqualTo "1" && {_rd isEqualTo "1"} && {(parseNumber _mb) < 200} && {_cap isNotEqualTo "-"}), _txt, 10, 40, "SETTINGS"] call comspec_atak_native_fnc_notify;
        } else {
            // Dossier près de la limite d'Arma (250 Mo) : prévenir une fois par partie, même sans vérification demandée.
            if ((parseNumber _mb) >= 200 && {!(uiNamespace getVariable ["COMSPEC_ATAK_ShotDirFullWarned", false])}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_ShotDirFullWarned", true];
                ["WARNING", format ["Dossier des captures presque plein : %1 Mo sur 250. Arma n'y écrira plus de photo au-delà : videz %2.", round (parseNumber _mb), _dir], 12, 40, "PHOTOS"] call comspec_atak_native_fnc_notify;
            };
        };
        // Réglages › À propos ouvert : la ligne « Dossier des captures » se met à jour.
        // Réglages › À propos ou Galerie ouverts : la ligne du dossier se met à jour.
        private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if (_page in ["SETTINGS", "GALLERY"]) then {
            [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call comspec_atak_native_fnc_nextFrame;
        };
        _txt
    };
    case "text": {
        private _info = uiNamespace getVariable ["COMSPEC_ATAK_ShotDirInfo", nil];
        if (isNil "_info") exitWith { "pas encore vérifié" };
        if (_info isEqualTo []) exitWith { "vérification impossible (DLL du téléphone absente)" };
        _info params ["_src", "_dir", "_ex", "_rd", "_n", "_last", "_age", "_uerr", "_user", "", "", ["_mb", -1], ["_cap", "?"]];
        private _from = switch (_src) do {
            case "user": { "dossier du réglage" };
            case "profile": { format ["profil Arma « %1 »", profileName] };
            case "default": { "dossier par défaut" };
            default { "aucun dossier trouvé" };
        };
        private _state = switch (true) do {
            case (!_ex): { "pas encore créé (aucune capture prise avec ce profil)" };
            case (!_rd): { "illisible (droits Windows)" };
            case (_n isEqualTo 0): { "aucune image" };
            default {
                private _ago = switch (true) do {
                    case (_age < 0): { "date inconnue" };
                    case (_age < 120): { format ["il y a %1 s", round _age] };
                    case (_age < 7200): { format ["il y a %1 min", round (_age / 60)] };
                    case (_age < 172800): { format ["il y a %1 h", round (_age / 3600)] };
                    default { format ["il y a %1 j", round (_age / 86400)] };
                };
                format ["%1 image(s), dernière : %2 (%3)", _n, _last, _ago]
            };
        };
        // Morceaux séparés par « · » : chacun est traduit seul (fn_tr).
        private _parts = [format ["Dossier des captures : %1 (%2)", _dir, _from], _state];
        if (_uerr isNotEqualTo "-" && {_user isNotEqualTo "-"}) then {
            _parts pushBack format ["Réglage « %1 » non retenu : %2", _user, [_uerr] call _whyText];
        };
        // Taille (limite d'Arma 250 Mo par dossier) et dossier Captures (absent avec une DLL plus ancienne : « ? »).
        if (_mb >= 200) then { _parts pushBack format ["Presque plein : %1 Mo sur 250, Arma n'y écrira plus de photo au-delà", round _mb]; } else {
            if (_mb >= 0) then { _parts pushBack format ["%1 Mo sur 250", round _mb]; };
        };
        if (_cap isEqualTo "-") then {
            _parts pushBack "Dossier Captures COMSPEC impossible à créer (Documents en lecture seule ?) : envoi depuis Screenshots";
        } else {
            if (_cap isNotEqualTo "?") then { _parts pushBack format ["Photos envoyées gardées dans %1", _cap]; };
        };
        _parts joinString " · "
    };
    case "open": {
        private _r = ["Shots.OpenCaptures"] call comspec_atak_native_fnc_extensionCall;
        if !(_r isEqualType "") then { _r = ""; };
        if ((_r select [0, 3]) isEqualTo "OK|") then {
            ["INFO", format ["Dossier des photos ouvert : %1", _r select [3]], 6, 40, "PHOTOS"] call comspec_atak_native_fnc_notify;
        } else {
            ["WARNING", "Dossier Captures COMSPEC indisponible (Documents en lecture seule ou DLL du téléphone trop ancienne).", 8, 40, "PHOTOS"] call comspec_atak_native_fnc_notify;
        };
        _r
    };
    default { "" };
};
