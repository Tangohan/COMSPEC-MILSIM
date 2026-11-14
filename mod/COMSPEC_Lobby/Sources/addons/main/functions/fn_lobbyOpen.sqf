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
         COMSPEC_LOBBY_Self : indicatif, nom, unité, qualifications, portrait) : c'est VOTRE dossier, montré sur le poste choisi
         quand la mission ne donne pas d'identité à ce poste ;
      4. le texte du rôle tel quel.
    Portrait : image d'un PBO, ou image téléchargée par COMSPEC Link. Arma refuse en texture une image hors PBO (sauf
    -filePatching) : elle passe alors par le navigateur intégré (idc 9106), lue par COMSPEC Link (ImageDataChunk « local: »,
    sans réseau). Sans COMSPEC ATAK, pas de portrait Athena : le cadre reste vide.
    Clés d'une carte : surname, given, rank, unit, role, quals, portrait (chemin d'image lisible par le jeu), source (« ATHENA »...).
    Journal : lignes [COMSPEC LOBBY] au RPT, dont les premières lignes de la liste (texte, donnée, valeur, image).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};
private _log = { diag_log ("[COMSPEC LOBBY] " + _this) };

// ------------------------------------------------------------------ 1. contrôles indispensables
private _need = [109, 114, 104, 118, 128, 1, 9100, 9101, 9102, 9103, 9104, 9105, 9110, 9111, 9112, 9106, 9113, 9114, 9115, 9116, 9117];
private _missing = _need select { isNull (_display displayCtrl _x) };
if (_missing isNotEqualTo []) exitWith { (format ["contrôles manquants %1 : l'écran du jeu est laissé tel quel", _missing]) call _log };
("écran ouvert, " + str (count _need) + " contrôles trouvés") call _log;

private _c = { _display displayCtrl _this };
private _roles = 109 call _c;

// Textes fixes du dossier.
(9100 call _c) ctrlSetText "";
(9110 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Grade"));
(9111 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Unit"));
(9112 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Role"));
(9113 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Quals"));

(9106 call _c) ctrlShow false;

// Carte Athena du joueur local (posée par COMSPEC ATAK dans le profil) : tableau de paires [clé, valeur].
private _self = createHashMap;
{
    if (_x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType ""}) then { _self set [_x select 0, _x select 1]; };
} forEach (profileNamespace getVariable ["COMSPEC_LOBBY_Self", []]);
if ((_self getOrDefault ["surname", ""]) isEqualTo "") then { _self = createHashMap; };
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
            // Poste sans identité donnée par la mission : le dossier est celui du joueur, la fonction celle du poste.
            { _h set [_x, _y]; } forEach _self;
            if ((_h getOrDefault ["role", ""]) isEqualTo "") then { _h set ["role", _name]; };
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
private _b64Of = {
    params [["_p", ""]];
    private _b64 = "";
    private _mime = "jpeg";
    private _off = 0;
    private _err = "";
    for "_n" from 0 to 60 do {
        private _r = "COMSPECExtension" callExtension ["ImageDataChunk", ["local:" + _p, "lobby_portrait", _off toFixed 0]];
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if !(_r isEqualType "") then { _r = ""; };
        private _f = _r splitString "|";
        if ((_f param [0, ""]) isNotEqualTo "OK") exitWith { _err = [_f param [1, "erreur"], "COMSPEC Link absent"] select (_r isEqualTo ""); };
        _b64 = _b64 + (_f param [3, ""]);
        _off = parseNumber (_f param [2, "0"]);
        if ((count _f) > 4) then { _mime = _f select 4; };
        if (_off >= parseNumber (_f param [1, "0"])) exitWith {};
    };
    if (_err isEqualTo "" && {_b64 isEqualTo ""}) then { _err = "image vide"; };
    if (_err isEqualTo "") then { [_mime, _b64] } else { ["", _err] }
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
    };
    // Image hors PBO refusée en texture : navigateur intégré.
    _pc ctrlSetText _blank;
    if !(_pic in _webCache) then {
        private _r = [_pic] call _b64Of;
        _webCache set [_pic, _r];
        (if ((_r select 0) isEqualTo "") then { format ["portrait indisponible (%1)", _r select 1] } else { format ["portrait par le navigateur intégré (%1 caractères)", count (_r select 1)] }) call _log;
    };
    (_webCache get _pic) params ["_m", "_b"];
    if (_m isEqualTo "") exitWith { _wb ctrlShow false; };
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
};

// ------------------------------------------------------------------ 4. boucle de l'écran (suivi du poste choisi)
private _lastSig = "";
private _dumped = false;
while { !isNull _display } do {
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
        (9114 call _c) ctrlSetText (_card getOrDefault ["rank", ""]);
        (9115 call _c) ctrlSetText (_card getOrDefault ["unit", ""]);
        (9116 call _c) ctrlSetText (_card getOrDefault ["role", ""]);
        (9117 call _c) ctrlSetText (_card getOrDefault ["quals", ""]);
        private _pic = _card getOrDefault ["portrait", ""];
        [_pic] call _portrait;
        (9103 call _c) ctrlSetText (toUpper (_card getOrDefault ["source", [localize "STR_COMSPEC_LOBBY_PortraitLocal", ""] select (_pic isEqualTo "")]));
    };

    uiSleep 0.12;
};

uiNamespace setVariable ["COMSPEC_LOBBY_webPush", nil];
uiNamespace setVariable ["COMSPEC_LOBBY_wba", nil];
"écran fermé" call _log;
