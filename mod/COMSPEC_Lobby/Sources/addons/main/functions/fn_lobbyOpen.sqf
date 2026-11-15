/*
    COMSPEC Lobby : script de l'écran des rôles (RscDisplayMultiplayerSetup), lancé par le onLoad de l'écran (RscLobby.hpp), AVANT
    toute mission et sans CBA : il n'utilise ni CfgFunctions, ni macros, ni réglages CBA.

    Ce qu'il fait, sans jamais piloter le moteur (la liste des rôles, le pool, les camps, les boutons restent ceux du jeu) :
      - vérifie que les contrôles attendus existent ; sinon il ne touche à rien et le jeu reste maître de l'écran ;
      - suit le poste choisi dans la liste (idc 109) et remplit le dossier : portrait, nom, prénom, grade, unité, fonction,
        qualifications.

    Étiquette d'un poste : dans le texte du rôle (Eden, « Description du rôle ») : [A-1] Chef de groupe.
    Identité d'un poste, par ordre de priorité :
      1. uiNamespace COMSPEC_LOBBY_Cards (HashMap étiquette -> HashMap) : cartes données par un autre module (Athena) ;
      2. configuration de la mission (description.ext) :
           class ComspecLobby { class Slots { class A1 { tag = "A-1"; surname = "Vasseur"; given = "Thomas"; rank = "Sergent-chef";
             unit = "ALPHA"; role = "Chef de groupe"; quals = "Chef de groupe"; portrait = "images\a1.paa"; }; }; };
      3. la carte Athena du joueur local, gardée dans le profil Arma par COMSPEC ATAK à chaque partie (profileNamespace
         COMSPEC_LOBBY_Self : indicatif, nom, grade, unité, fonction, qualifications, portrait) : c'est VOTRE dossier, montré
         sur le poste choisi quand la mission ne donne pas d'identité à ce poste. La fonction est celle du membre dans sa
         communauté (fiche Effectifs d'Athena), JAMAIS le texte du poste : sans fonction sur Athena, la ligne est masquée ;
      4. le texte du rôle tel quel.
    Portrait : image d'un PBO, ou image téléchargée par COMSPEC Link dans comspec_cache (dossier du jeu), lue en texture
    par son chemin relatif. Une image qu'Arma ne peut pas lire en texture (autre dossier) passe par le navigateur intégré
    (idc 9106), lue par COMSPEC Link (ImageDataChunk « local: », sans réseau) : JPEG ou PNG seulement, donc pour une
    texture .paa c'est la copie JPEG du site que « Synchroniser » télécharge. Sans portrait, le cadre dit pourquoi.
    Lignes du dossier : une ligne sans valeur est masquée et les suivantes remontent ; les qualifications qui ne tiennent
    pas sur la ligne sont coupées, avec le nombre de celles qui restent.
    Clés d'une carte : surname, given, rank, unit, role, quals, portrait (chemin d'image lisible par le jeu), source (« ATHENA »...).
    Journal : lignes [COMSPEC LOBBY] au RPT, dont les premières lignes de la liste (texte, donnée, valeur, image).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};
private _log = { diag_log ("[COMSPEC LOBBY] " + _this) };

// ------------------------------------------------------------------ 1. contrôles indispensables
private _need = [109, 114, 104, 118, 128, 1, 9107, 9108, 9101, 9102, 9103, 9104, 9105, 9110, 9111, 9112, 9106, 9113, 9114, 9115, 9116, 9117];
private _missing = _need select { isNull (_display displayCtrl _x) };
if (_missing isNotEqualTo []) exitWith { (format ["contrôles manquants %1 : l'écran du jeu est laissé tel quel", _missing]) call _log };
("écran ouvert, " + str (count _need) + " contrôles trouvés") call _log;

private _c = { _display displayCtrl _this };
private _roles = 109 call _c;

// Textes fixes du dossier.
(9108 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Sync"));
(9110 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Grade"));
(9111 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Unit"));
(9112 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Role"));
(9113 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Quals"));

(9106 call _c) ctrlShow false;

// Carte Athena du joueur local (posée par COMSPEC ATAK dans le profil) : tableau de paires [clé, valeur].
private _self = createHashMap;
private _loadSelf = {
    private _h = createHashMap;
    {
        if (_x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType ""}) then { _h set [_x select 0, _x select 1]; };
    } forEach (profileNamespace getVariable ["COMSPEC_LOBBY_Self", []]);
    if ((_h getOrDefault ["surname", ""]) isEqualTo "") then { _h = createHashMap; };
    _h
};
_self = call _loadSelf;
(format ["carte Athena du joueur : %1", ["absente", "présente"] select ((count _self) > 0)]) call _log;

// ------------------------------------------------------------------ 2. étiquette et identité d'un poste
// _text -> étiquette ; « [A-1] Chef de groupe » -> "A-1"
private _tagOf = {
    params [["_text", ""]];
    private _a = _text find "[";
    private _b = _text find "]";
    if (_a < 0 || {_b <= _a}) exitWith { "" };
    private _parts = (_text select [_a + 1, _b - _a - 1]) splitString "|";
    if (_parts isEqualTo []) exitWith { "" };
    trim (_parts select 0)
};
// _étiquette, _texte du rôle -> HashMap de la carte (jamais vide : au pire le texte du rôle en nom)
private _identity = {
    params ["_tag", "_text"];
    private _h = createHashMap;
    private _keys = ["surname", "given", "rank", "unit", "role", "quals", "portrait"];
    private _slots = missionConfigFile >> "ComspecLobby" >> "Slots";
    if (_tag isNotEqualTo "" && {isClass _slots}) then {
        {
            if ((getText (_x >> "tag")) isEqualTo _tag) exitWith {
                private _cfg = _x;
                { _h set [_x, getText (_cfg >> _x)]; } forEach _keys;
                    };
        } forEach ("true" configClasses _slots);
    };
    private _cards = uiNamespace getVariable ["COMSPEC_LOBBY_Cards", createHashMap];
    if (_cards isEqualType createHashMap && {_tag isNotEqualTo ""} && {_tag in _cards}) then {
        private _card = _cards get _tag;
        if (_card isEqualType createHashMap) then { { _h set [_x, _y]; } forEach _card; };
    };
    if ((_h getOrDefault ["surname", ""]) isEqualTo "") then {
        private _rb = _text find "]";
        private _name = trim (((([_text, _text select [_rb + 1]] select (_rb >= 0)) splitString "@") select [0, 1]) joinString "");
        if ((count _self) > 0) then {
            // Poste sans identité donnée par la mission : le dossier est celui du joueur, fonction comprise (celle du
            // membre sur Athena). Le texte du poste n'y entre jamais : il reste dans la liste des rôles.
            { _h set [_x, _y]; } forEach _self;
        } else {
            _h set ["surname", [_name, "-"] select (_name isEqualTo "")];
        };
    };
    _h
};

// ------------------------------------------------------------------ 3. portrait
// Image lisible en texture par Arma ? (PBO, texture procédurale, ou fichier lisible : -filePatching). Renvoie le chemin ou "".
private _texOf = {
    params [["_p", ""]];
    if (_p isEqualTo "") exitWith { "" };
    if ((_p select [0, 1]) isEqualTo "\" || {(_p select [0, 2]) isEqualTo "#("}) exitWith { _p };
    private _cands = [_p];
    private _i = (toLower _p) find "comspec_cache/";
    if (_i >= 0) then { _cands pushBack (((_p select [_i]) splitString "/") joinString "\"); };
    private _res = "";
    // fileExists et non getTextureInfo : sur un fichier illisible, getTextureInfo ouvre une fenêtre « Picture ... not found ».
    // Seulement les chemins relatifs : fileExists refuse un chemin absolu et l'écrit au journal à chaque appel.
    { if ((_x find ":") < 0 && {fileExists _x}) exitWith { _res = _x; }; } forEach _cands;
    _res
};
// Image d'un fichier local en base64, par COMSPEC Link (aucun réseau). Renvoie [type MIME, base64] ou ["", raison].
// L'image est lue par morceaux de 5 940 octets (jusqu'à 61 appels à COMSPEC Link). Elle est gardée pour tout le
// lancement du jeu (uiNamespace COMSPEC_LOBBY_ImgKeep : chemin -> [taille, premier morceau, type, base64]) : à chaque
// retour sur l'écran des rôles, un seul appel (le premier morceau) vérifie que le fichier est le même (même taille,
// même début) ; avant, toute l'image était relue et réencodée à chaque ouverture de l'écran. Fichier remplacé (nouvelle
// photo, « Synchroniser ») : relu en entier. Les morceaux sont assemblés une seule fois (joinString), plus par « + » à
// chaque morceau (la chaîne entière était recopiée à chaque fois).
private _b64Of = {
    params [["_p", ""]];
    private _keep = uiNamespace getVariable "COMSPEC_LOBBY_ImgKeep";
    if (isNil "_keep" || {!(_keep isEqualType createHashMap)}) then {
        _keep = createHashMap;
        uiNamespace setVariable ["COMSPEC_LOBBY_ImgKeep", _keep];
    };
    (_keep getOrDefault [_p, [-1, "", "", ""]]) params ["_keptTotal", "_keptHead", "_keptMime", "_keptB64"];
    private _parts = [];
    private _mime = "jpeg";
    private _off = 0;
    private _total = 0;
    private _err = "";
    private _same = false;
    for "_n" from 0 to 60 do {
        private _r = "COMSPECExtension" callExtension ["ImageDataChunk", ["local:" + _p, "lobby_portrait", _off toFixed 0]];
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if !(_r isEqualType "") then { _r = ""; };
        private _f = _r splitString "|";
        if ((_f param [0, ""]) isNotEqualTo "OK") exitWith { _err = [_f param [1, "erreur"], "COMSPEC Link absent"] select (_r isEqualTo ""); };
        _total = parseNumber (_f param [1, "0"]);
        // Premier morceau identique à celui de l'image gardée, même taille : même fichier, rien d'autre à lire.
        if (_n isEqualTo 0 && {_total isEqualTo _keptTotal} && {_keptB64 isNotEqualTo ""} && {(_f param [3, ""]) isEqualTo _keptHead}) exitWith { _same = true; };
        _parts pushBack (_f param [3, ""]);
        _off = parseNumber (_f param [2, "0"]);
        if ((count _f) > 4) then { _mime = _f select 4; };
        if (_off >= _total) exitWith {};
    };
    if (_same) exitWith { [_keptMime, _keptB64] };
    private _b64 = _parts joinString "";
    if (_err isEqualTo "" && {_b64 isEqualTo ""}) then { _err = "image vide"; };
    if (_err isNotEqualTo "") exitWith { ["", _err] };
    _keep set [_p, [_total, _parts select 0, _mime, _b64]];
    [_mime, _b64]
};
// ctrlWebBrowserAction passe par compile : HEMTT (vérification du SQF au build) ne connaît pas encore cette commande.
uiNamespace setVariable ["COMSPEC_LOBBY_wba", compile "(_this select 0) ctrlWebBrowserAction (_this select 1)"];
// Envoie l'image gardée sur le contrôle à la page (par morceaux : une très longue chaîne JavaScript passe mal d'un bloc).
uiNamespace setVariable ["COMSPEC_LOBBY_webPush", {
    params [["_wb", controlNull]];
    if (isNull _wb) exitWith {};
    (_wb getVariable ["COMSPEC_LOBBY_Img", ["", ""]]) params ["_m", "_b"];
    [_wb, ["ExecJS", "if(window.cs){cs.reset();}"]] call (uiNamespace getVariable "COMSPEC_LOBBY_wba");
    if (_b isEqualTo "") exitWith {};
    for "_o" from 0 to ((count _b) - 1) step 100000 do {
        [_wb, ["ExecJS", "if(window.cs){cs.add('" + (_b select [_o, 100000]) + "');}"]] call (uiNamespace getVariable "COMSPEC_LOBBY_wba");
    };
    [_wb, ["ExecJS", "if(window.cs){cs.show('" + _m + "');}"]] call (uiNamespace getVariable "COMSPEC_LOBBY_wba");
}];
private _webCache = createHashMap;
private _blank = "#(argb,8,8,3)color(0.04,0.06,0.05,1)";
private _portrait = {
    params [["_pic", ""]];
    private _pc = 9102 call _c;
    private _wb = 9106 call _c;
    private _tex = [_pic] call _texOf;
    if (_pic isEqualTo "" || {_tex isNotEqualTo ""}) exitWith {
        _wb ctrlShow false;
        _pc ctrlSetText ([_tex, _blank] select (_tex isEqualTo ""));
        _pic isNotEqualTo ""
    };
    // Image hors PBO refusée en texture : navigateur intégré.
    _pc ctrlSetText _blank;
    if !(_pic in _webCache) then {
        private _r = [_pic] call _b64Of;
        _webCache set [_pic, _r];
        (if ((_r select 0) isEqualTo "") then { format ["portrait indisponible (%1)", _r select 1] } else { format ["portrait par le navigateur intégré (%1 caractères)", count (_r select 1)] }) call _log;
    };
    (_webCache get _pic) params ["_m", "_b"];
    if (_m isEqualTo "") exitWith { _wb ctrlShow false; false };
    _wb setVariable ["COMSPEC_LOBBY_Img", [_m, _b]];
    _wb ctrlShow true;
    if (_wb getVariable ["COMSPEC_LOBBY_Ready", false]) then {
        [_wb] call (uiNamespace getVariable "COMSPEC_LOBBY_webPush");
    } else {
        if !(_wb getVariable ["COMSPEC_LOBBY_Eh", false]) then {
            _wb setVariable ["COMSPEC_LOBBY_Eh", true];
            _wb ctrlAddEventHandler ["PageLoaded", {
                params ["_wb"];
                _wb setVariable ["COMSPEC_LOBBY_Ready", true];
                [_wb] call (uiNamespace getVariable ["COMSPEC_LOBBY_webPush", {}]);
            }];
            [_wb, ["LoadFile", "z\comspec_lobby\addons\main\data\portrait.html"]] call (uiNamespace getVariable "COMSPEC_LOBBY_wba");
        };
    };
    true
};
// Lignes du dossier (libellé, valeur) : positions de la config, gardées pour faire remonter les lignes quand l'une est vide.
private _rows = [[9110, 9114], [9111, 9115], [9112, 9116], [9113, 9117]];
private _rowPos = _rows apply { [ctrlPosition ((_x select 0) call _c), ctrlPosition ((_x select 1) call _c)] };
// Qualifications (séparées par « · ») coupées à la largeur de la ligne : « A · B +3 ». Jamais de débordement sur le pool.
private _fitQuals = {
    params [["_txt", ""], ["_ctrl", controlNull]];
    private _items = ((_txt splitString "·") apply { trim _x }) select { _x isNotEqualTo "" };
    if (_items isEqualTo []) exitWith { "" };
    private _max = ((ctrlPosition _ctrl) select 2) * 0.96;
    private _size = ctrlFontHeight _ctrl;
    private _w = { _this getTextWidth ["RobotoCondensed", _size] };
    private _out = "";
    private _n = 0;
    {
        private _more = (count _items) - _n - 1;
        private _try = ([_out, _out + " · "] select (_n > 0)) + _x;
        if (((_try + ([format [" +%1", _more], ""] select (_more isEqualTo 0))) call _w) > _max) exitWith {};
        _out = _try;
        _n = _n + 1;
    } forEach _items;
    if (_n isEqualTo 0) exitWith {
        // Même la première ne tient pas : coupée, avec le nombre des autres.
        private _first = _items select 0;
        private _tail = ["…", format ["… +%1", (count _items) - 1]] select ((count _items) > 1);
        while { (count _first) > 1 && {((_first + _tail) call _w) > _max} } do { _first = _first select [0, (count _first) - 1]; };
        _first + _tail
    };
    _out + ([format [" +%1", (count _items) - _n], ""] select (_n isEqualTo (count _items)))
};

// ------------------------------------------------------------------ 4. bouton « Synchroniser »
// Redemande tout de suite la carte du joueur à Athena, par COMSPEC Link, sans attendre la prochaine partie. Possible
// seulement si COMSPEC Link a déjà une session Athena dans ce lancement du jeu (une partie jouée) et si le SteamID est connu
// (gardé par COMSPEC ATAK : rien ne permet de le lire avant une mission). Sinon le message dit quoi faire.
// L'appel bloque le jeu le temps de la réponse du site (quelques secondes au plus) : il part d'un clic du joueur.
// Un seul passage à la fois : chaque clic lançait sa propre synchronisation, et dix clics d'impatience enchaînaient dix
// séries d'appels bloquants (carte, portrait). Un clic pendant qu'une synchronisation tourne est ignoré.
uiNamespace setVariable ["COMSPEC_LOBBY_SyncBusy", false];
uiNamespace setVariable ["COMSPEC_LOBBY_sync", {
    if (uiNamespace getVariable ["COMSPEC_LOBBY_SyncBusy", false]) exitWith {};
    uiNamespace setVariable ["COMSPEC_LOBBY_SyncBusy", true];
    [] spawn {
        private _t0 = diag_tickTime;
        [] call (uiNamespace getVariable ["COMSPEC_LOBBY_syncRun", {}]);
        uiNamespace setVariable ["COMSPEC_LOBBY_SyncBusy", false];
        // Durée du passage (appels à COMSPEC Link compris : ce sont eux qui bloquent l'écran, voir ci-dessus).
        diag_log format ["[COMSPEC LOBBY][PERF] Synchroniser : %1 ms", round ((diag_tickTime - _t0) * 1000)];
    };
}];
uiNamespace setVariable ["COMSPEC_LOBBY_syncRun", {
    disableSerialization;
    private _d = uiNamespace getVariable ["COMSPEC_LOBBY_Display", displayNull];
    if (isNull _d) exitWith {};
    private _say = { (_d displayCtrl 9107) ctrlSetText (localize _this); };
    private _uid = profileNamespace getVariable ["COMSPEC_LOBBY_Uid", ""];
    private _tenant = profileNamespace getVariable ["COMSPEC_LOBBY_Tenant", ""];
    if !(_uid isEqualType "") then { _uid = ""; };
    if !(_tenant isEqualType "") then { _tenant = ""; };
    if (_uid isEqualTo "") exitWith { "STR_COMSPEC_LOBBY_SyncNoUid" call _say; };
    "STR_COMSPEC_LOBBY_SyncRunning" call _say;
    uiSleep 0.05;
    private _ext = {
        private _t = diag_tickTime;
        private _r = "COMSPECExtension" callExtension _this;
        // Appel direct à COMSPEC Link : l'écran est figé pendant sa durée. Écrit au journal au-dessus de 20 ms.
        private _ms = round ((diag_tickTime - _t) * 1000);
        if (_ms >= 20) then { diag_log format ["[COMSPEC LOBBY][PERF] DLL %1 : %2 ms (direct)", _this param [0, ""], _ms]; };
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if !(_r isEqualType "") then { _r = ""; };
        _r
    };
    private _raw = ["GetPlayerAvatarInfo", [_uid, _tenant]] call _ext;
    if (_raw isEqualTo "") exitWith { "STR_COMSPEC_LOBBY_SyncNoAtak" call _say; };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
        diag_log ("[COMSPEC LOBBY] synchronisation refusée : " + (_raw select [0, 60]));
        "STR_COMSPEC_LOBBY_SyncNoLink" call _say;
    };
    // Colonnes séparées par des tabulations, vides gardées : nom, indicatif, adresse du portrait, unité… puis « Q: » (qualifications).
    private _cols = [];
    private _rest = _raw select [3];
    private _tab = toString [9];
    private _i = _rest find _tab;
    while {_i >= 0} do { _cols pushBack (_rest select [0, _i]); _rest = _rest select [_i + 1]; _i = _rest find _tab; };
    _cols pushBack _rest;
    private _col = { private _v = _cols param [_this, ""]; ["", _v] select !((_v select [0, 2]) in ["Q:", "S:"]) };
    private _name = 0 call _col;
    private _cs = 1 call _col;
    private _url = 2 call _col;
    if ((_url find "/") < 0) then { _url = ""; };
    private _q = _cols findIf { (_x select [0, 2]) isEqualTo "Q:" };
    private _qn = if (_q < 0) then { "" } else { ((((_cols select _q) select [2]) splitString ";") apply { (_x splitString "~") param [0, ""] }) joinString " · " };
    // Carte déjà gardée : portrait, grade et fonction repris quand la réponse ne les donne pas.
    private _old = createHashMap;
    { if (_x isEqualType [] && {(_x param [0, ""]) isEqualType ""} && {(_x param [1, ""]) isEqualType ""}) then { _old set [_x param [0, ""], _x param [1, ""]]; }; } forEach (profileNamespace getVariable ["COMSPEC_LOBBY_Self", []]);
    private _pic = _old getOrDefault ["portrait", ""];
    if (_url isEqualTo "") then { _pic = ""; } else {
        private _get = {
            params ["_u"];
            private _dl = (["DownloadBriefingSlideImage", [_u, format ["avatar_%1_%2", _uid, hashValue _u]]] call _ext) splitString "|";
            if ((_dl param [0, ""]) isEqualTo "OK" && {(_dl param [1, ""]) isNotEqualTo ""}) then { ((_dl select 1) splitString (toString [92])) joinString "/" } else { "" }
        };
        private _got = [_url] call _get;
        // Texture .paa rangée hors du dossier du jeu (Arma ne la lit pas, et le navigateur intégré ne lit pas le .paa) :
        // la copie JPEG du site (même adresse, .jpg) est prise à la place, pour le navigateur intégré.
        private _lg = toLower _got;
        private _rel = _lg find "comspec_cache/";
        private _readable = _rel >= 0 && {fileExists (((_got select [_rel]) splitString "/") joinString (toString [92]))};
        if (_got isNotEqualTo "" && {!_readable} && {(_lg select [((count _lg) - 4) max 0]) isEqualTo ".paa"} && {((toLower _url) select [((count _url) - 4) max 0]) isEqualTo ".paa"}) then {
            private _web = [(_url select [0, (count _url) - 4]) + ".jpg"] call _get;
            if (_web isNotEqualTo "") then { _got = _web; };
        };
        if (_got isNotEqualTo "") then { _pic = _got; };
    };
    // Grade et fonction du membre dans sa communauté : gardés en mémoire par COMSPEC Link depuis la liaison Athena
    // (GetAuthState, sans réseau ; cellule « - » = vide). Liaison pas prête : les valeurs déjà gardées restent.
    private _rank = _old getOrDefault ["rank", ""];
    private _role = _old getOrDefault ["role", ""];
    private _auth = (["GetAuthState", []] call _ext) splitString _tab;
    if ((_auth param [1, ""]) isEqualTo "READY") then {
        private _cell = { private _v = _auth param [_this, "-"]; ["", _v] select (_v isNotEqualTo "-") };
        _rank = 9 call _cell;
        _role = 17 call _cell;
    };
    private _status = "";
    { if ((_x select [0, 2]) isEqualTo "S:") exitWith { _status = _x select [2]; }; } forEach _cols;
    profileNamespace setVariable ["COMSPEC_LOBBY_Self", [
        ["surname", [_cs, _name] select (_cs isEqualTo "")], ["given", ["", _name] select (_cs isNotEqualTo "")],
        ["rank", _rank], ["unit", 3 call _col], ["role", _role], ["quals", _qn], ["source", "ATHENA"], ["portrait", _pic],
        ["portraitState", ["", "none"] select (_url isEqualTo "" && {_status isEqualTo "no_portrait"})]
    ]];
    saveProfileNamespace;
    uiNamespace setVariable ["COMSPEC_LOBBY_Reload", true];
    "STR_COMSPEC_LOBBY_SyncDone" call _say;
    diag_log "[COMSPEC LOBBY] synchronisation faite : carte du joueur mise à jour";
}];
uiNamespace setVariable ["COMSPEC_LOBBY_Display", _display];

// ------------------------------------------------------------------ 5. boucle de l'écran (suivi du poste choisi)
private _lastSig = "";
private _dumped = false;
while { !isNull _display } do {
    // Carte mise à jour par « Synchroniser » : relue, et le dossier du poste choisi est redessiné.
    if (uiNamespace getVariable ["COMSPEC_LOBBY_Reload", false]) then {
        uiNamespace setVariable ["COMSPEC_LOBBY_Reload", false];
        _self = call _loadSelf;
        _webCache = createHashMap;
        _lastSig = "";
    };
    private _sel = lbCurSel _roles;
    private _size = lbSize _roles;
    private _text = if (_sel >= 0 && {_sel < _size}) then { _roles lbText _sel } else { "" };

    // Journal : la liste telle que le moteur la fournit, une seule fois, dès qu'elle contient des lignes.
    if (!_dumped && {_size > 0}) then {
        _dumped = true;
        (format ["liste des rôles : %1 ligne(s)", _size]) call _log;
        for "_i" from 0 to ((_size min 40) - 1) do {
            (format ["  ligne %1 : texte=""%2"" donnée=""%3"" valeur=%4 image=""%5""", _i, _roles lbText _i, _roles lbData _i, _roles lbValue _i, _roles lbPicture _i]) call _log;
        };
    };

    // Poste choisi : nouvelle carte.
    private _sig = format ["%1|%2", _sel, _text];
    if (_sig isNotEqualTo _lastSig) then {
        _lastSig = _sig;
        private _tag = [_text] call _tagOf;
        private _card = [_tag, _text] call _identity;
        (9101 call _c) ctrlSetText _tag;
        (9104 call _c) ctrlSetText (toUpper (_card getOrDefault ["surname", "-"]));
        (9105 call _c) ctrlSetText (toUpper (_card getOrDefault ["given", ""]));
        // Lignes : grade, unité, fonction (celle du membre, jamais le poste), qualifications. Vide = ligne masquée.
        private _vals = [
            trim (_card getOrDefault ["rank", ""]), trim (_card getOrDefault ["unit", ""]), trim (_card getOrDefault ["role", ""]),
            [_card getOrDefault ["quals", ""], 9117 call _c] call _fitQuals
        ];
        private _slot = 0;
        {
            _x params ["_li", "_vi"];
            private _v = _vals select _forEachIndex;
            private _show = _v isNotEqualTo "";
            { (_x call _c) ctrlShow _show; } forEach [_li, _vi];
            if (_show) then {
                (_rowPos select _slot) params ["_lp", "_vp"];
                (_li call _c) ctrlSetPosition _lp;
                (_li call _c) ctrlCommit 0;
                (_vi call _c) ctrlSetPosition _vp;
                (_vi call _c) ctrlCommit 0;
                (_vi call _c) ctrlSetText _v;
                _slot = _slot + 1;
            };
        } forEach _rows;
        private _pic = _card getOrDefault ["portrait", ""];
        private _shown = [_pic] call _portrait;
        // Sous le portrait : l'origine quand il s'affiche, sinon « sans photo » sur son propre dossier, et la raison sur
        // la ligne d'état (aucun portrait sur Athena, ou image pas lisible ici : « Synchroniser » la reprend). Un message
        // de synchronisation déjà affiché n'est pas remplacé.
        private _isSelf = (_card getOrDefault ["source", ""]) isEqualTo "ATHENA";
        (9103 call _c) ctrlSetText (toUpper (switch (true) do {
            case (_shown): { _card getOrDefault ["source", localize "STR_COMSPEC_LOBBY_PortraitLocal"] };
            case (_isSelf): { localize "STR_COMSPEC_LOBBY_PortraitNone" };
            default { "" };
        }));
        private _hints = [localize "STR_COMSPEC_LOBBY_PortraitHintNone", localize "STR_COMSPEC_LOBBY_PortraitHintRetry"];
        private _st = 9107 call _c;
        if ((ctrlText _st) isEqualTo "" || {(ctrlText _st) in _hints}) then {
            _st ctrlSetText (if (_shown || {!_isSelf}) then { "" } else { _hints select !(_pic isEqualTo "" && {(_card getOrDefault ["portraitState", ""]) isEqualTo "none"}) });
        };
    };

    uiSleep 0.12;
};

uiNamespace setVariable ["COMSPEC_LOBBY_webPush", nil];
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_LOBBY_wba", "COMSPEC_LOBBY_sync", "COMSPEC_LOBBY_syncRun", "COMSPEC_LOBBY_Display", "COMSPEC_LOBBY_Reload"];
"écran fermé" call _log;
