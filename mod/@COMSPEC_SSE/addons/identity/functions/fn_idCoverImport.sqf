/*
    Import d'une couverture générée sur Athena (app « Ma couverture » du téléphone ATAK, bouton IMPORTER MA COUVERTURE).
    LOCAL : le fichier comspec-couverture-CVR-XXXXXXXX.json est lu dans Documents\Arma 3 - COMSPEC par la DLL COMSPEC Link
    (ReadCoverFile, ReadCoverFileChunk). Aucune requête réseau, aucun appel à Athena, aucune autorisation d'Athena requise :
    le jeu n'a pas besoin d'être connecté. Rien du fichier n'est exécuté ni interprété ; les textes restent des textes
    (échappés à l'affichage par l'app). Contrat du fichier : docs/ATAK_COUVERTURE_IMPORT.md.
    Ne touche ni aux profils d'identité du registre SSE, ni aux papiers, ni à fn_radioGuard : la couverture importée
    s'ajoute à la légende du joueur (fn_idLegend) champ par champ ; « Oublier l'import » revient à la légende d'avant.
    ["run"]                 JOUEUR  lit le fichier, le valide (la DLL, puis ici), le garde dans le profil du joueur
                            (profileNamespace) et l'envoie au serveur. Résultat : comspec_sse_idCoverImportSt (HashMap
                            kind ok | err, text, ref, at), la page est redessinée.
    ["use", [HashMap]]      JOUEUR  couverture déjà nettoyée (« clean ») devenue l'active : profil, envoi au serveur, page
                            redessinée. Appelé par « run » et par la fenêtre Mes couvertures sur Athena (fn_idCoverAthena,
                            synchronisation depuis Athena : seule autre voie, par le réseau, derrière la licence)
    ["resend"]              JOUEUR  renvoie l'import gardé au serveur (connexion, JIP, respawn : le registre du serveur ne
                            garde rien d'une mission à l'autre)
    ["forget"]              JOUEUR  oublie l'import (profil et serveur)
    ["clean", [HashMap]] -> HashMap   SERVEUR ET JOUEUR : champs connus, textes coupés à leur taille, caractères de contrôle
                            retirés ; vide (HashMap vide) sans nom
    ["serverSet", [unité, HashMap]] · ["serverForget", [unité]]   SERVEUR : registre « imports » par Steam ID
*/
params [["_op", "", [""]], ["_a", []]];

private _txt = {
    params ["_v", "_max"];
    if !(_v isEqualType "") exitWith { "" };
    trim ((toString ((toArray _v) select { _x >= 32 && {_x isNotEqualTo 127} })) select [0, _max])
};
private _list = { params ["_v", "_max"]; if (_v isEqualType []) then { _v select [0, _max] } else { [] } };

if (_op isEqualTo "clean") exitWith {
    _a params [["_m", createHashMap, [createHashMap]]];
    private _o = createHashMap;
    {
        _o set [_x, [_m getOrDefault [_x, ""], _y] call _txt];
    } forEach (createHashMapFromArray [
        ["name", 160], ["first_name", 80], ["last_name", 80], ["pob", 120], ["country", 80], ["address", 255], ["city", 120],
        ["profession", 120], ["employer", 190], ["phone", 60], ["travel", 255], ["family", 190],
        ["dob", 10], ["sex", 1], ["lang", 2], ["country_code", 16], ["ref", 16], ["generated_at", 20], ["expires_at", 20]
    ]);
    if ((_o get "name") isEqualTo "") exitWith { createHashMap };
    if !((_o get "lang") in ["fr", "en"]) then { _o set ["lang", "fr"]; };
    _o set ["history", ([_m getOrDefault ["history", []], 8] call _list) apply { [_x, 255] call _txt }];
    _o set ["contacts", ([_m getOrDefault ["contacts", []], 10] call _list) apply {
        private _r = [_x, 3] call _list;
        [[_r param [0, ""], 160] call _txt, [_r param [1, ""], 60] call _txt, [_r param [2, ""], 60] call _txt]
    }];
    _o set ["docs", ([_m getOrDefault ["docs", []], 10] call _list) apply {
        private _r = [_x, 5] call _list;
        [[_r param [0, ""], 20] call _txt, [_r param [1, ""], 80] call _txt, [_r param [2, ""], 40] call _txt,
            [_r param [3, ""], 10] call _txt, [_r param [4, ""], 10] call _txt]
    }];
    _o
};

if (_op isEqualTo "serverSet") exitWith {
    if (!isServer) exitWith { false };
    _a params [["_u", objNull, [objNull]], ["_m", createHashMap, [createHashMap]]];
    private _uid = if (isNull _u) then { "" } else { getPlayerUID _u };
    private _clean = ["clean", [_m]] call comspec_sse_fnc_idCoverImport;
    if (_uid isEqualTo "" || {(count _clean) isEqualTo 0}) exitWith { false };
    private _reg = ["init"] call comspec_sse_fnc_idRegistry;
    private _imports = _reg getOrDefault ["imports", createHashMap];
    _reg set ["imports", _imports];
    _imports set [_uid, _clean];
    ["reset", [_u]] call comspec_sse_fnc_idLegend;
    true
};

if (_op isEqualTo "serverForget") exitWith {
    if (!isServer) exitWith { false };
    _a params [["_u", objNull, [objNull]]];
    private _uid = if (isNull _u) then { "" } else { getPlayerUID _u };
    if (_uid isEqualTo "") exitWith { false };
    private _reg = ["init"] call comspec_sse_fnc_idRegistry;
    (_reg getOrDefault ["imports", createHashMap]) deleteAt _uid;
    ["reset", [_u]] call comspec_sse_fnc_idLegend;
    true
};

if (!hasInterface) exitWith { false };
private _st = missionNamespace getVariable ["comspec_sse_idCoverImportSt", createHashMap];
missionNamespace setVariable ["comspec_sse_idCoverImportSt", _st];
private _redraw = {
    ["send", ["coverSheet", [player, player]]] call comspec_sse_fnc_idClient;
    ["act", "synced"] call comspec_sse_fnc_idCoverPage;
};
private _fail = {
    params ["_text"];
    _st set ["kind", "err"];
    _st set ["text", _text];
    call _redraw;
    false
};

switch (_op) do {
    case "run": {
        private _err = createHashMapFromArray [
            ["unknown", "Le module COMSPEC Link de ce poste est trop ancien pour importer un fichier. Mettez-le à jour."],
            ["no_file", "Aucun fichier de couverture dans Documents\Arma 3 - COMSPEC. Téléchargez-le d'abord sur Athena (Ma couverture)."],
            ["bad_name", "Nom de fichier refusé."],
            ["too_large", "Fichier trop gros pour une couverture (256 Kio au plus)."],
            ["json", "Ce fichier n'est pas un fichier de couverture valide."],
            ["keys", "Ce fichier n'est pas un fichier de couverture valide."],
            ["format", "Ce fichier n'est pas un fichier de couverture COMSPEC."],
            ["version", "Ce fichier vient d'une version plus récente d'Athena. Mettez le mod à jour."],
            ["header", "Fichier de couverture abîmé ou incomplet. Téléchargez-le de nouveau."],
            ["expired", "Ce fichier de couverture a expiré. Générez-en un nouveau sur Athena."],
            ["hash", "Fichier de couverture abîmé ou modifié. Téléchargez-le de nouveau."],
            ["cover", "Le contenu de ce fichier est refusé. Générez-le de nouveau sur Athena."],
            ["read", "Le fichier n'a pas pu être lu."]
        ];
        private _call = {
            private _r = "COMSPECExtension" callExtension _this;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = str _r; };
            _r
        };
        private _head = ["ReadCoverFile", []] call _call;
        if ((_head select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _code = if ((_head select [0, 4]) isEqualTo "ERR|") then { _head select [4] } else { "unknown" };
            [(_err getOrDefault [_code, _err get "read"])] call _fail
        };
        private _p = _head splitString "|";
        private _n = parseNumber (_p param [1, "0"]);
        private _buf = "";
        private _ok = _n > 0 && {_n <= 50};
        if (_ok) then {
            for "_i" from 0 to (_n - 1) do {
                private _c = ["ReadCoverFileChunk", [str _i]] call _call;
                if ((_c select [0, 3]) isNotEqualTo "OK|") exitWith { _ok = false; };
                _buf = _buf + (_c select [3]);
            };
        };
        private _root = if (_ok) then { parseSimpleArray _buf } else { [] };
        if !(_ok && {_root isEqualType []} && {_root isNotEqualTo []}) exitWith { [_err get "read"] call _fail };
        private _m = createHashMapFromArray (_root select { _x isEqualType [] && {count _x isEqualTo 2} && {(_x select 0) isEqualType ""} });
        private _cover = ["clean", [_m]] call comspec_sse_fnc_idCoverImport;
        if ((count _cover) isEqualTo 0) exitWith { [_err get "cover"] call _fail };
        ["use", [_cover]] call comspec_sse_fnc_idCoverImport
    };
    case "use": {
        _a params [["_cover", createHashMap, [createHashMap]]];
        if ((count _cover) isEqualTo 0) exitWith { false };
        profileNamespace setVariable ["comspec_sse_idCoverImport", _cover];
        saveProfileNamespace;
        missionNamespace setVariable ["comspec_sse_idCoverImportSent", false];
        _st set ["kind", "ok"];
        _st set ["text", format [("Couverture importée : %1." call comspec_sse_fnc_tr), _cover get "name"]];
        _st set ["ref", _cover getOrDefault ["ref", ""]];
        ["resend"] call comspec_sse_fnc_idCoverImport;
        call _redraw;
        true
    };
    case "resend": {
        private _cover = profileNamespace getVariable ["comspec_sse_idCoverImport", createHashMap];
        if !(_cover isEqualType createHashMap) exitWith { false };
        if ((count _cover) isEqualTo 0 || {missionNamespace getVariable ["comspec_sse_idCoverImportSent", false]}) exitWith { false };
        if (isNull player || {(getPlayerUID player) isEqualTo ""}) exitWith { false };
        missionNamespace setVariable ["comspec_sse_idCoverImportSent", true];
        ["send", ["coverImport", [player, _cover]]] call comspec_sse_fnc_idClient;
        true
    };
    case "forget": {
        profileNamespace setVariable ["comspec_sse_idCoverImport", nil];
        saveProfileNamespace;
        missionNamespace setVariable ["comspec_sse_idCoverImportSent", false];
        _st set ["kind", "ok"];
        _st set ["text", "Import oublié : votre légende redevient celle d'avant." call comspec_sse_fnc_tr];
        ["send", ["coverForget", [player]]] call comspec_sse_fnc_idClient;
        call _redraw;
        true
    };
    default { false };
};
