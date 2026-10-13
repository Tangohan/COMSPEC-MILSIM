/*
    Contrôle de l'installation : un main.pbo ou une DLL périmés laissent le téléphone sans journal ni connexion,
    sans erreur visible (ex. un ancien Addons\comspec_atak_native_main.pbo chargé à la place du main.pbo à jour).
    Chaque anomalie est notée au journal et signalée une fois par lancement du jeu dans les notifications du téléphone.
      ["start"]  depuis XEH_postInitClient : contrôle local, puis comparaison avec Athena au démarrage et toutes les 2 h
      ["local"]  - deux PBO pour le même addon (allAddonsInfo), code et config de versions différentes (PBO mélangés)
                 - DLL COMSPEC Link absente, ou de version différente de celle livrée avec ce main.pbo
      ["remote"] dernières versions publiées sur Athena (GET /api/atak/version, commande DLL LatestVersions,
                 COMSPEC Link 2.0.78+) : « mise à jour disponible » si le mod ou la DLL sont plus anciens
    Anomalies : missionNamespace « COMSPEC_ATAK_Integrity » = [[clé, niveau, texte], ...] (app Debug, onglet DLL).
    Versions du site : missionNamespace « COMSPEC_ATAK_LatestRemote » = [atak, link, diag_tickTime].
*/
params [["_mode", "start"]];

// -1, 0, 1 : "1.9.0" < "1.10.0".
private _cmp = {
    params ["_a", "_b"];
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
    [_level, _text, 12, 60] call comspec_atak_native_fnc_notify;
    // Téléphone fermé ou cassé par le PBO périmé lui-même : la ligne reste visible dans le chat.
    if (_level isEqualTo "ERROR") then { systemChat format ["[COMSPEC ATAK] %1", _text call comspec_atak_native_fnc_tr]; };
};
// Version de la DLL chargée, "" si elle ne répond pas. « OK|COMSPECExtension 2.0.78 ».
private _dllVersion = {
    private _v = "COMSPECExtension" callExtension ["GetExtensionVersion", []];
    if (_v isEqualType []) then { _v = _v param [0, ""]; };
    if !(_v isEqualType "") then { _v = str _v; };
    if ((_v select [0, 3]) isNotEqualTo "OK|") exitWith { "" };
    private _w = _v splitString " |";
    _w param [(count _w) - 1, ""]
};
private _code = missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", ""];

switch (toLower _mode) do {
    case "start": {
        [{ ["local"] call comspec_atak_native_fnc_integrityCheck; }, [], 15] call CBA_fnc_waitAndExecute;
        [] spawn {
            sleep 30;
            // Joueur qui n'a pas encore choisi le service Athéna : on attend son choix ; « Non » : aucun appel réseau.
            waitUntil { sleep 10; ([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "" };
            while { ([] call comspec_atak_native_fnc_athenaMode) isEqualTo "on" } do {
                ["remote"] call comspec_atak_native_fnc_integrityCheck;
                sleep 7200;
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
            ["code", "ERROR", format ["Le main.pbo chargé (config %1) n'a pas d'empreinte de version : il date d'avant la 1.66.0. Remplacez-le par le main.pbo à jour.", _cfg]] call _raise;
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
                ["dll", "WARNING", "La DLL native de COMSPEC ATAK ne répond pas : pas de connexion Athena. Vérifiez COMSPECATAKNativeExtension_x64.dll dans @COMSPEC_ATAK_Native."] call _raise;
            };
        };
        ["INFO", "INTEGRITY", format ["Contrôle de l'installation : code %1, config %2, %3 anomalie(s)", _code, _cfg, count (missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []])]] call comspec_atak_native_fnc_log;
    };
    case "remote": {
        if (!canSuspend) exitWith { ["remote"] spawn comspec_atak_native_fnc_integrityCheck; };
        private _bridge = [] call comspec_atak_native_fnc_bridge;
        private _url = if (_bridge && {!isNil "comspec_overwatch_connect_fnc_portalUrl"}) then { [] call comspec_overwatch_connect_fnc_portalUrl } else { profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"] };
        if (!(_url isEqualType "") || {((toLower trim _url) find "http") isNotEqualTo 0}) then { _url = "https://athena.ttrd.fr/public"; };
        private _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select _bridge;
        private _r = ([_ext, "LatestVersions", [trim _url]] call comspec_atak_native_fnc_extAwait) param [0, ""];
        if !(_r isEqualType "") then { _r = str _r; };
        // DLL plus ancienne que 2.0.78 (réponse vide) : déjà signalée par le contrôle local.
        private _cells = _r splitString "|";
        if ((_cells param [0, ""]) isNotEqualTo "OK") exitWith {
            ["INFO", "INTEGRITY", format ["Dernières versions sur %1 : %2", _url, [_r, "commande inconnue de la DLL"] select (_r isEqualTo "")]] call comspec_atak_native_fnc_log;
        };
        _cells params ["", ["_atak", ""], ["_link", ""]];
        missionNamespace setVariable ["COMSPEC_ATAK_LatestRemote", [_atak, _link, diag_tickTime]];
        ["INFO", "INTEGRITY", format ["Dernières versions sur Athena : ATAK %1, COMSPEC Link %2 (chargés : %3, %4)", _atak, _link, _code, [[] call _dllVersion, "-"] select !_bridge]] call comspec_atak_native_fnc_log;
        if (_atak isNotEqualTo "" && {_code isNotEqualTo ""} && {([_code, _atak] call _cmp) < 0}) then {
            ["update_atak", "WARNING", format ["Mise à jour disponible : COMSPEC ATAK %1 (vous avez la %2). Mettez le mod à jour sur Steam ou reconstruisez le main.pbo.", _atak, _code]] call _raise;
        };
        if (_bridge && {_link isNotEqualTo ""}) then {
            private _have = call _dllVersion;
            if (_have isNotEqualTo "" && {([_have, _link] call _cmp) < 0}) then {
                ["update_link", "WARNING", format ["Mise à jour disponible : DLL COMSPEC Link %1 (vous avez la %2). Mettez le mod à jour ou recompilez la DLL (build_mod.bat).", _link, _have]] call _raise;
            };
        };
    };
};
true
