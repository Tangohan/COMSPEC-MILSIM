/*
    Contrôle de l'installation : un main.pbo ou une DLL périmés laissent le téléphone sans journal ni connexion,
    sans erreur visible (ex. un ancien Addons\comspec_atak_native_main.pbo chargé à la place du main.pbo à jour).
    Chaque anomalie est notée au journal et signalée une fois par lancement du jeu dans les notifications du téléphone.
      ["start"]  depuis XEH_postInitClient : contrôle local, puis comparaison avec Athena au démarrage et toutes les 30 min
                 (dès que le service Athéna passe sur « Oui », même en cours de partie)
      ["local"]  - deux PBO pour le même addon (allAddonsInfo), code et config de versions différentes (PBO mélangés)
                 - DLL COMSPEC Link absente, ou de version différente de celle livrée avec ce main.pbo
      ["remote"] dernières versions publiées sur Athena (GET /api/atak/version, commande DLL LatestVersions,
                 COMSPEC Link 2.0.78+, résumé joueur 2.0.85+) : « mise à jour disponible » si le mod ou la DLL sont
                 plus anciens. Bandeau et entrée du centre de notifications une fois par nouvelle version (profil),
                 carte épinglée en tête des Notifications tant que la mise à jour n'est pas faite.
                 Chaque décision est notée au journal (module UPDATE).
      ["remote", "1.0.0"] (console de debug, dans un spawn) : comme si le mod installé était la 1.0.0, pour voir la
                 notification sans changer de main.pbo ; le profil n'est pas modifié.
    Anomalies : missionNamespace « COMSPEC_ATAK_Integrity » = [[clé, niveau, texte], ...] (app Debug, onglet DLL).
    Versions du site : missionNamespace « COMSPEC_ATAK_LatestRemote » = [atak, link, diag_tickTime].
*/
params [["_mode", "start"], ["_simulate", ""]];

// -1, 0, 1 : "1.9.0" < "1.10.0". Seuls MAJEUR.MINEUR.CORRECTIF comptent : une empreinte de build après un espace
// (« 1.94.0 - 2026.10.14 a1b2c3d ») est ignorée (docs/VERSIONING.md).
private _cmp = {
    params ["_a", "_b"];
    _a = (_a splitString " ") param [0, ""];
    _b = (_b splitString " ") param [0, ""];
    private _va = (_a splitString ".") apply { parseNumber _x };
    private _vb = (_b splitString ".") apply { parseNumber _x };
    private _r = 0;
    for "_i" from 0 to (((count _va) max (count _vb)) - 1) do {
        if (_r isNotEqualTo 0) exitWith {};
        private _p = _va param [_i, 0];
        private _q = _vb param [_i, 0];
        if (_p < _q) then { _r = -1; };
        if (_p > _q) then { _r = 1; };
    };
    _r
};
// Une notification par anomalie et par lancement du jeu (uiNamespace survit aux changements de mission).
private _raise = {
    params ["_key", "_level", "_text"];
    private _all = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
    private _i = _all findIf { (_x select 0) isEqualTo _key };
    if (_i >= 0) then { _all set [_i, [_key, _level, _text]]; } else { _all pushBack [_key, _level, _text]; };
    missionNamespace setVariable ["COMSPEC_ATAK_Integrity", _all];
    ["ERROR", "INTEGRITY", _text] call comspec_atak_native_fnc_log;
    private _seen = uiNamespace getVariable ["COMSPEC_ATAK_IntegritySeen", []];
    if ((_key + "|" + _text) in _seen) exitWith {};
    _seen pushBack (_key + "|" + _text);
    uiNamespace setVariable ["COMSPEC_ATAK_IntegritySeen", _seen];
    // Rien sur l'écran de jeu : journal (RPT) ci-dessus et notification dans le téléphone.
    [_level, _text, 12, 60] call comspec_atak_native_fnc_notify;
};
// Version de la DLL chargée, "" si elle ne répond pas. « OK|COMSPECExtension 2.0.78 ».
// Version de la DLL chargée, "" si elle ne répond pas (fn_extVersion, relue à chaque contrôle).
private _dllVersion = { [true] call comspec_atak_native_fnc_extVersion };
private _code = missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", ""];

switch (toLower _mode) do {
    case "start": {
        [{ ["local"] call comspec_atak_native_fnc_integrityCheck; }, [], 15] call CBA_fnc_waitAndExecute;
        [] spawn {
            sleep 30;
            // Service Athéna pas encore choisi : on attend son choix ; « Non » : aucun appel réseau. La boucle suit le
            // réglage pendant toute la partie (avant, un « Non » ou un choix tardif coupait la vérification jusqu'à la mission suivante).
            private _was = "?";
            private _next = 0;
            while { true } do {
                private _mode = [] call comspec_atak_native_fnc_athenaMode;
                if (_mode isNotEqualTo _was) then {
                    ["INFO", "UPDATE", switch (_mode) do {
                        case "on": { "Vérification des mises à jour : service Athéna sur « Oui », contrôle maintenant puis toutes les 30 min" };
                        case "off": { "Vérification des mises à jour désactivée : service Athéna sur « Non », aucun appel réseau (Réglages > Service Athéna pour l'activer)" };
                        default { "Vérification des mises à jour en attente : service Athéna pas encore choisi" };
                    }] call comspec_atak_native_fnc_log;
                    if (_mode isEqualTo "on") then { _next = 0; };
                    _was = _mode;
                };
                if (_mode isEqualTo "on" && {diag_tickTime >= _next}) then {
                    _next = diag_tickTime + 1800;
                    ["remote"] call comspec_atak_native_fnc_integrityCheck;
                };
                sleep 15;
            };
        };
    };
    case "local": {
        // 1. Deux PBO pour le même addon : Arma n'en lit qu'un, souvent le plus ancien.
        {
            _x params ["_prefix", "_file"];
            private _hits = allAddonsInfo select {
                private _p = toLower (_x param [0, ""]);
                if ((_p select [0, 1]) isEqualTo "\") then { _p = _p select [1]; };
                if ((_p select [(count _p) - 1, 1]) isEqualTo "\") then { _p = _p select [0, (count _p) - 1]; };
                _p isEqualTo _prefix
            };
            if ((count _hits) > 1) then {
                ["INFO", "INTEGRITY", format ["%1 chargé %2 fois : %3", _prefix, count _hits, _hits]] call comspec_atak_native_fnc_log;
                [format ["dup_%1", _file], "ERROR", format ["Deux %1 de COMSPEC ATAK sont chargés (%2 copies). Supprimez l'ancien (ex. Addons\comspec_atak_native_%1 ou un dossier @ en double) et relancez Arma.", _file, count _hits]] call _raise;
            };
        } forEach [["z\comspec_atak_native\addons\main", "main.pbo"], ["z\comspec_atak_native\addons\towers", "towers.pbo"]];

        // 2. Code et config d'âges différents : fichiers lus dans un PBO, config dans un autre.
        private _cfg = getText (configFile >> "CfgPatches" >> "comspec_atak_native_main" >> "versionStr");
        if (_code isEqualTo "") then {
            ["code", "ERROR", format ["Le main.pbo chargé (config %1) n'a pas d'empreinte de version : il est trop ancien. Remplacez-le par le main.pbo à jour.", _cfg]] call _raise;
        } else {
            if (_cfg isNotEqualTo _code) then {
                ["code", "ERROR", format ["main.pbo incohérent : code %1, config %2. Deux versions du mod sont mélangées : gardez un seul main.pbo à jour et relancez Arma.", _code, _cfg]] call _raise;
            };
        };

        // 3. DLL COMSPEC Link : absente (bloquée par BattlEye, non copiée) ou d'une autre version que ce main.pbo.
        if ([] call comspec_atak_native_fnc_bridge) then {
            private _have = call _dllVersion;
            private _want = missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""];
            if (_have isEqualTo "") exitWith {
                ["dll", "ERROR", "La DLL COMSPEC Link ne répond pas : plus de connexion Athena ni de journal. Vérifiez COMSPECExtension_x64.dll dans @COMSPEC_ATAK_Native (BattlEye désactivé) et relancez Arma."] call _raise;
            };
            if (_want isEqualTo "") exitWith {};
            private _c = [_have, _want] call _cmp;
            if (_c < 0) exitWith {
                ["dll", "WARNING", format ["DLL COMSPEC Link %1 chargée, %2 attendue par ce main.pbo : recompilez la DLL (build_mod.bat) ou mettez le mod à jour, puis relancez Arma.", _have, _want]] call _raise;
            };
            if (_c > 0) then {
                ["dll", "WARNING", format ["DLL COMSPEC Link %1 plus récente que ce main.pbo (qui attend %2) : le main.pbo est périmé, reconstruisez-le ou mettez le mod à jour.", _have, _want]] call _raise;
            };
        } else {
            private _r = "COMSPECATAKNativeExtension" callExtension ["Ping", []];
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if (!(_r isEqualType "") || {(_r select [0, 3]) isNotEqualTo "OK|"}) then {
                ["dll", "WARNING", "La DLL native de COMSPEC ATAK ne répond pas : pas de connexion Athena. BattlEye actif ? Désactivez-le dans le lanceur Arma 3 et relancez le jeu ; sinon vérifiez COMSPECATAKNativeExtension_x64.dll dans @COMSPEC_ATAK_Native."] call _raise;
            };
        };
        ["INFO", "INTEGRITY", format ["Contrôle de l'installation : code %1, config %2, %3 anomalie(s)", _code, _cfg, count (missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []])]] call comspec_atak_native_fnc_log;
    };
    case "remote": {
        if (!canSuspend) exitWith { ["remote", _simulate] spawn comspec_atak_native_fnc_integrityCheck; };
        private _say = { ["INFO", "UPDATE", _this] call comspec_atak_native_fnc_log; };
        private _bridge = [] call comspec_atak_native_fnc_bridge;
        private _url = "https://athena-comspec.eu/public";
        private _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select _bridge;
        private _have = [_code, _simulate] select (_simulate isNotEqualTo "");
        format ["Vérification des mises à jour : demande des dernières versions à Athena (ATAK installé %1%2, DLL %3)",
            _have, ["", " simulé depuis la console"] select (_simulate isNotEqualTo ""), _ext] call _say;
        private _r = ([_ext, "LatestVersions", [trim _url]] call comspec_atak_native_fnc_extAwait) param [0, ""];
        if !(_r isEqualType "") then { _r = str _r; };
        private _cells = _r splitString "|";
        if ((_cells param [0, ""]) isNotEqualTo "OK") exitWith {
            // DLL plus ancienne que 2.0.78 (réponse vide) : déjà signalée par le contrôle local.
            format ["Vérification des mises à jour impossible : %1. Aucune notification, nouvel essai dans 30 min", [
                format ["réponse « %1 » de %2", _r, _url],
                "la DLL ne connaît pas la commande LatestVersions (DLL trop ancienne)"
            ] select (_r isEqualTo "")] call _say;
        };
        _cells params ["", ["_atak", ""], ["_link", ""], ["_sumFr", ""], ["_sumEn", ""]];
        missionNamespace setVariable ["COMSPEC_ATAK_LatestRemote", [_atak, _link, diag_tickTime]];
        ["INFO", "INTEGRITY", format ["Dernières versions sur Athena : ATAK %1, COMSPEC Link %2 (chargés : %3, %4)", _atak, _link, _code, [[] call _dllVersion, "-"] select !_bridge]] call comspec_atak_native_fnc_log;

        // Une seule notification par nouvelle version, d'une partie à l'autre (profil) ; la carte épinglée reste.
        private _notified = profileNamespace getVariable ["COMSPEC_ATAK_UpdateNotified", []];
        if !(_notified isEqualType []) then { _notified = []; };
        private _offer = {
            params ["_key", "_text", "_what"];
            private _all = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
            private _i = _all findIf { (_x select 0) isEqualTo _key };
            if (_i >= 0) then { _all set [_i, [_key, "WARNING", _text]]; } else { _all pushBack [_key, "WARNING", _text]; };
            missionNamespace setVariable ["COMSPEC_ATAK_Integrity", _all];
            if (_what in _notified && {_simulate isEqualTo ""}) exitWith {
                format ["%1 : notification déjà montrée pour cette version, gardée épinglée en tête des Notifications", _what] call _say;
            };
            if !(profileNamespace getVariable ["COMSPEC_ATAK_Notifications", true]) then {
                format ["%1 : notifications du téléphone coupées dans les réglages, pas de bandeau (entrée gardée dans les Notifications)", _what] call _say;
            } else {
                format ["%1 : bandeau et entrée dans les Notifications", _what] call _say;
            };
            ["WARNING", _text, 12, 60] call comspec_atak_native_fnc_notify;
            if (_simulate isEqualTo "") then {
                _notified pushBackUnique _what;
                while { (count _notified) > 20 } do { _notified deleteAt 0; };
                profileNamespace setVariable ["COMSPEC_ATAK_UpdateNotified", _notified];
                [] call comspec_atak_native_fnc_saveProfile;
            };
        };
        private _clear = {
            params ["_key"];
            private _all = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
            private _i = _all findIf { (_x select 0) isEqualTo _key };
            if (_i >= 0) then { _all deleteAt _i; missionNamespace setVariable ["COMSPEC_ATAK_Integrity", _all]; };
        };

        // 1. Le mod (main.pbo).
        private _atakNew = false;
        switch (true) do {
            case (_atak isEqualTo ""): { "ATAK : Athena ne publie pas de version, rien à comparer" call _say; };
            case (_have isEqualTo ""): { "ATAK : version installée inconnue (main.pbo sans empreinte, déjà signalé), comparaison impossible" call _say; };
            default {
                private _c = [_have, _atak] call _cmp;
                if (_c >= 0) exitWith {
                    ["update_atak"] call _clear;
                    format [["ATAK %1 à jour (même version qu'Athena) : pas de notification", "ATAK %1 plus récent qu'Athena (%2) : version de développement, pas de notification"] select (_c > 0), _have, _atak] call _say;
                };
                _atakNew = true;
                // Résumé joueur de la dernière version, dans la langue du téléphone (DLL 2.0.85+).
                private _sum = [_sumEn, _sumFr] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr");
                if (_sum isEqualTo "") then { _sum = _sumFr; };
                format ["ATAK %1 installé, %2 publié sur Athena : mise à jour disponible (%3)", _have, _atak,
                    ["résumé joueur reçu", "pas de résumé joueur : DLL avant 2.0.85 ou version sans résumé"] select (_sum isEqualTo "")] call _say;
                private _text = if (_sum isEqualTo "") then {
                    format ["Mise à jour ATAK disponible : version %1 (vous avez la %2). Pour l'installer : quittez Arma, laissez Steam mettre à jour COMSPEC ATAK (Workshop > Abonnements), puis relancez.", _atak, _have]
                } else {
                    format ["Mise à jour ATAK disponible : version %1 (vous avez la %2). %3 Pour l'installer : quittez Arma, laissez Steam mettre à jour COMSPEC ATAK (Workshop > Abonnements), puis relancez.", _atak, _have, _sum]
                };
                ["update_atak", _text, format ["ATAK %1", _atak]] call _offer;
            };
        };

        // 2. La DLL COMSPEC Link : livrée avec le mod, une mise à jour ATAK l'apporte aussi (une seule notification).
        if (!_bridge || {_link isEqualTo ""}) exitWith {
            if (_bridge) then { "COMSPEC Link : Athena ne publie pas de version, rien à comparer" call _say; };
        };
        private _dll = call _dllVersion;
        if (_dll isEqualTo "") exitWith { "COMSPEC Link : DLL muette (déjà signalé), comparaison impossible" call _say; };
        if (([_dll, _link] call _cmp) >= 0) exitWith {
            ["update_link"] call _clear;
            format ["COMSPEC Link %1 à jour (Athena : %2) : pas de notification", _dll, _link] call _say;
        };
        if (_atakNew) exitWith {
            ["update_link"] call _clear;
            format ["COMSPEC Link %1 installé, %2 publié : arrive avec la mise à jour ATAK, pas de seconde notification", _dll, _link] call _say;
        };
        format ["COMSPEC Link %1 installé, %2 publié sur Athena : mise à jour disponible", _dll, _link] call _say;
        ["update_link", format ["Mise à jour Link disponible : DLL %1 (vous avez la %2). Pour l'installer : quittez Arma, laissez Steam mettre à jour COMSPEC ATAK (Workshop > Abonnements), puis relancez.", _link, _dll], format ["Link %1", _link]] call _offer;
    };
};
true
