/*
    Photo de profil Athena d'un joueur, téléchargée par la DLL d'Overwatch (profil site → image locale).
    Cache disque : l'image téléchargée reste dans le dossier de cache de la DLL, et le profil Arma garde
    [uid, adresse, chemin local] (COMSPEC_ATAK_AvatarDisk, 200 derniers). D'une partie à l'autre le portrait
    s'affiche tout de suite depuis ce cache ; une vérification par partie demande l'adresse au site et ne
    retélécharge que si elle a changé (le nom de la vignette change à chaque nouveau portrait) ou si le fichier
    manque (la DLL rend le fichier existant de la clé avatar_<uid>_<empreinte de l'adresse> sans requête).
    Renvoie le chemin local (cache), sinon "" et lance la demande en arrière-plan (nouvel essai au plus toutes
    les 2 min). Sans Overwatch : toujours "".
    Un échec n'est plus muet : raison en français dans le journal (RPT, app Debug) et, pour son propre portrait,
    une notification (une par raison et par partie, tant que la raison ne change pas). L'image en cache reste
    affichée pendant un échec ; elle n'est retirée que si le site dit qu'il n'y a plus de portrait.
    Params : [unité]
*/
params [["_unit", player]];
if (isNull _unit || {!isPlayer _unit} || {!([] call comspec_atak_native_fnc_bridge)}) exitWith { "" };
private _uid = getPlayerUID _unit;
if (_uid isEqualTo "") exitWith { "" };
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Avatars", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Avatars", _cache];
// Première demande de la session de jeu : reprendre l'image du cache disque, même hors ligne.
if !(_uid in _cache) then {
    private _disk = profileNamespace getVariable ["COMSPEC_ATAK_AvatarDisk", []];
    private _i = _disk findIf { (_x param [0, ""]) isEqualTo _uid };
    _cache set [_uid, [if (_i < 0) then { "" } else { (_disk select _i) param [2, ""] }, -1e9]];
};
(_cache get _uid) params ["_path", "_triedAt"];
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { _path };
// Déjà vérifié auprès du site pendant cette session : rien à redemander.
if (_path isNotEqualTo "" && {_uid in (uiNamespace getVariable ["COMSPEC_ATAK_AvatarChecked", []])}) exitWith { _path };
if (diag_tickTime - _triedAt < 120) exitWith { _path };
// Un seul téléchargement à la fois : les appels à la DLL bloquent le jeu le temps de la requête (même en spawn),
// et une page de groupe lançait un appel par joueur à chaque rendu.
if (missionNamespace getVariable ["COMSPEC_ATAK_AvatarBusy", false]) exitWith { _path };
missionNamespace setVariable ["COMSPEC_ATAK_AvatarBusy", true];
_cache set [_uid, [_path, diag_tickTime]];
[_uid] spawn {
    params ["_uid"];
    // Échec : raison en français dans le journal (RPT + app Debug) et, pour son propre portrait, une notification
    // par raison et par partie. Nouvel essai après _retry secondes (2 min par défaut).
    private _fail = {
        params ["_why", ["_retry", 120], ["_type", "WARNING"]];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Avatars", createHashMap];
        _cache set [_uid, [(_cache getOrDefault [_uid, [""]]) select 0, diag_tickTime + _retry - 120]];
        uiNamespace setVariable ["COMSPEC_ATAK_Avatars", _cache];
        // Journal seulement quand la raison change (la page de groupe redemande chaque joueur toutes les 2 min).
        private _whyMap = uiNamespace getVariable ["COMSPEC_ATAK_AvatarWhy", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_AvatarWhy", _whyMap];
        if ((_whyMap getOrDefault [_uid, ""]) isEqualTo _why) exitWith {};
        _whyMap set [_uid, _why];
        private _self = _uid isEqualTo (getPlayerUID player);
        ["WARNING", "PORTRAIT", format ["Portrait opérateur non affiché (%1) : %2", [_uid, "joueur local"] select _self, _why]] call comspec_atak_native_fnc_log;
        if (_self) then { [_type, format ["Portrait opérateur non affiché : %1", _why], 6, 15] call comspec_atak_native_fnc_notify; };
    };
    // Cache disque (profil Arma) : [uid, adresse, chemin] ; chemin "" = retirer l'entrée.
    private _store = {
        params ["_url", "_p"];
        private _disk = (profileNamespace getVariable ["COMSPEC_ATAK_AvatarDisk", []]) select { (_x param [0, ""]) isNotEqualTo _uid };
        if (_p isNotEqualTo "") then { _disk pushBack [_uid, _url, _p]; };
        while {count _disk > 200} do { _disk deleteAt 0; };
        profileNamespace setVariable ["COMSPEC_ATAK_AvatarDisk", _disk];
        saveProfileNamespace;
    };
    // Image prête (ou retirée) : mémoire de session, vérification faite, redessin des pages qui l'affichent.
    private _done = {
        params ["_p"];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_Avatars", createHashMap];
        private _old = (_cache getOrDefault [_uid, [""]]) select 0;
        _cache set [_uid, [_p, diag_tickTime]];
        uiNamespace setVariable ["COMSPEC_ATAK_Avatars", _cache];
        private _checked = uiNamespace getVariable ["COMSPEC_ATAK_AvatarChecked", []];
        _checked pushBackUnique _uid;
        uiNamespace setVariable ["COMSPEC_ATAK_AvatarChecked", _checked];
        (uiNamespace getVariable ["COMSPEC_ATAK_AvatarWhy", createHashMap]) deleteAt _uid;
        if (_old isEqualTo _p) exitWith {};
        // Redessiner la page qui attend l'image (le volet rapide suit seul : sa signature inclut le portrait).
        private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if (_page in ["ATHENA", "GROUP", "PROFILE", "CHAT", "DATING"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call CBA_fnc_execNextFrame; };
    };
    call {
        private _res = { params ["_r"]; if (_r isEqualType []) then { _r param [0, ""] } else { _r } };
        private _tenant = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
        private _raw = [(["COMSPECExtension", "GetPlayerAvatarInfo", [_uid, _tenant]] call comspec_atak_native_fnc_extAwait)] call _res;
        if !(_raw isEqualType "") then { _raw = str _raw; };
        private _info = _raw splitString "|";
        if ((_info param [0, ""]) isNotEqualTo "OK") exitWith {
            private _err = _info param [1, "réponse vide de la DLL"];
            [switch (true) do {
                case (_err isEqualTo "not_found"): { "aucun compte Athena lié à ce SteamID" };
                case (_err isEqualTo "unauthorized"): { "accès refusé par Athena" };
                case (_err isEqualTo "no_tenant"): { "communauté non identifiée, reliez le compte Athena en jeu" };
                case (_err isEqualTo "timeout"): { "Athena ne répond pas" };
                case (_err isEqualTo "invalid_response"): { "réponse d'Athena illisible" };
                case ((_err select [0, 5]) isEqualTo "http_"): { format ["erreur du serveur Athena (%1)", _err select [5]] };
                default { format ["échec de la demande du profil (%1)", _err] };
            }] call _fail;
        };
        // Découpe aux tabulations en gardant les colonnes vides (splitString les fusionne et décalait l'adresse
        // de l'image vers une autre colonne quand le nom ou l'indicatif étaient vides).
        private _payload = _raw select [3];
        private _tab = toString [9];
        private _cols = [];
        private _i = _payload find _tab;
        while {_i >= 0} do {
            _cols pushBack (_payload select [0, _i]);
            _payload = _payload select [_i + 1];
            _i = _payload find _tab;
        };
        _cols pushBack _payload;
        // Qualifications (colonne « Q: », DLL récente) : [[nom, niveau, état, expiration], ...] ; absente = DLL ancienne.
        private _q = _cols findIf { (_x select [0, 2]) isEqualTo "Q:" };
        private _quals = uiNamespace getVariable ["COMSPEC_ATAK_Quals", createHashMap];
        _quals set [_uid, if (_q < 0) then { "olddll" } else { (((_cols select _q) select [2]) splitString ";") apply { private _f = _x splitString "~"; [_f param [0, ""], _f param [1, ""], _f param [2, ""], _f param [3, ""]] } }];
        uiNamespace setVariable ["COMSPEC_ATAK_Quals", _quals];
        // Raison donnée par le site quand il n'envoie pas d'image (colonne « S: », COMSPEC Link 2.0.82+).
        private _st = _cols findIf { (_x select [0, 2]) isEqualTo "S:" };
        private _status = if (_st < 0) then { "" } else { (_cols select _st) select [2] };
        private _url = _cols param [2, ""];
        if ((_url select [0, 2]) in ["Q:", "S:"] || {(_url find "/") < 0}) then { _url = ""; };
        if (_url isEqualTo "") exitWith {
            // Plus de portrait sur le site : retirer l'image gardée en cache (les initiales reviennent).
            if (_status isEqualTo "no_portrait") then { ["", ""] call _store; [""] call _done; };
            [switch (_status) do {
                case "no_portrait": { "aucun portrait opérateur sur votre fiche Athena (Mon compte > Portrait)" };
                case "source_missing": { "le fichier du portrait manque sur le serveur Athena, renvoyez-le depuis Mon compte" };
                case "unsupported_format": { "le serveur Athena ne peut pas convertir ce portrait en JPEG (envoyez un JPG)" };
                case "write_failed": { "le serveur Athena ne peut pas écrire la vignette JPEG (droits du dossier uploads/portraits/atak)" };
                case "": { "Athena n'a renvoyé aucune image, sans raison (site ou COMSPEC Link pas à jour)" };
                default { format ["Athena n'a renvoyé aucune image (%1)", _status] };
            }, [120, 600] select (_status isEqualTo "no_portrait"), ["WARNING", "INFO"] select (_status isEqualTo "no_portrait")] call _fail;
        };
        // Clé de cache = joueur + empreinte de l'adresse : la DLL (2.0.82+) rend tout de suite le fichier déjà
        // téléchargé pour cette adresse, et ne retélécharge que si l'adresse a changé ou si le fichier manque.
        private _dlRaw = [(["COMSPECExtension", "DownloadBriefingSlideImage", [_url, format ["avatar_%1_%2", _uid, hashValue _url]]] call comspec_atak_native_fnc_extAwait)] call _res;
        if !(_dlRaw isEqualType "") then { _dlRaw = str _dlRaw; };
        private _dl = _dlRaw splitString "|";
        if ((_dl param [0, ""]) isNotEqualTo "OK" || {(_dl param [1, ""]) isEqualTo ""}) exitWith {
            private _err = _dl param [1, "réponse vide de la DLL"];
            [switch (true) do {
                case (_err isEqualTo "not_found"): { format ["image introuvable sur le serveur (404) : %1", _url] };
                case (_err isEqualTo "unauthorized"): { "téléchargement de l'image refusé par le serveur" };
                case (_err isEqualTo "invalid_image"): { "le serveur n'a pas renvoyé une image JPEG ou PNG" };
                case (_err isEqualTo "empty_image"): { "image vide reçue du serveur" };
                case (_err isEqualTo "no_writable_cache_dir"): { "aucun dossier local inscriptible pour ranger l'image (dossier du mod ou TEMP)" };
                case ((_err select [0, 13]) isEqualTo "write_failed_"): { format ["écriture de l'image en local impossible (%1)", _err select [13]] };
                case (_err in ["timeout", "network", "unavailable"]): { format ["téléchargement de l'image impossible (%1)", _err] };
                default { format ["téléchargement de l'image impossible (%1)", _err] };
            }] call _fail;
        };
        // Chemin local normalisé (\ → /) comme pour les diapositives ; Arma n'affiche que .jpg / .paa :
        // sinon on garde le logo (une photo PNG laissait un cadre vide).
        private _p = ((_dl select 1) splitString (toString [92])) joinString "/";
        private _lp = toLower _p;
        if !((_lp select [(count _lp) - 4]) in [".jpg", "jpeg", ".paa"]) exitWith {
            [format ["image reçue au format %1, illisible par Arma (JPEG attendu, site pas à jour ?)", _lp select [(count _lp) - 4]]] call _fail;
        };
        [_url, _p] call _store;
        [_p] call _done;
        ["INFO", "PORTRAIT", format ["Portrait opérateur téléchargé et gardé en cache (%1) : %2", _uid, _p]] call comspec_atak_native_fnc_log;
    };
    missionNamespace setVariable ["COMSPEC_ATAK_AvatarBusy", false];
};
_path
