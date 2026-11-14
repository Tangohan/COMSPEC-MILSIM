/*
    COMSPEC Lobby : script de l'écran des rôles (RscDisplayMultiplayerSetup), lancé par le onLoad de l'écran (RscLobby.hpp), AVANT
    toute mission et sans CBA : il n'utilise ni CfgFunctions, ni macros, ni réglages CBA.

    Ce qu'il fait, sans jamais piloter le moteur (la liste des rôles, le pool, les camps, les boutons restent ceux du jeu) :
      - vérifie que les contrôles attendus existent ; sinon il ne touche à rien et le jeu reste maître de l'écran ;
      - suit le poste choisi dans la liste (idc 109) et remplit le dossier : portrait, nom, prénom, grade, unité, fonction,
        qualifications ;
      - marque « CODE » à droite des postes protégés et, pour un poste protégé, collecte le code (pavé à l'écran) ;
      - garde le bouton « continuer » éteint tant que le code d'un poste protégé n'est pas complet (jamais pour l'hôte).
    Le code n'est PAS vérifié ici : avant la mission aucun message ne peut partir vers le serveur. Il est gardé dans
    uiNamespace (COMSPEC_LOBBY_Pin = [étiquette, code]) puis envoyé au serveur à l'arrivée en jeu (fn_clientCheck), qui seul le contrôle.

    Étiquette d'un poste : dans le texte du rôle (Eden, « Description du rôle ») : [A-1] ou [A-1|P] (P : poste protégé par un code).
    Identité d'un poste, par ordre de priorité :
      1. uiNamespace COMSPEC_LOBBY_Cards (HashMap étiquette -> HashMap) : cartes données par un autre module (Athena) ;
      2. configuration de la mission (description.ext) :
           class ComspecLobby { class Slots { class A1 { tag = "A-1"; surname = "Vasseur"; given = "Thomas"; rank = "Sergent-chef";
             unit = "ALPHA"; role = "Chef de groupe"; quals = "Chef de groupe"; portrait = "images\a1.paa"; pin = 1; }; }; };
      3. le texte du rôle tel quel.
    Clés d'une carte : surname, given, rank, unit, role, quals, portrait (chemin d'image lisible par le jeu), source (« ATHENA »...).
    Journal : lignes [COMSPEC LOBBY] au RPT, dont les premières lignes de la liste (texte, donnée, valeur, image) : elles
    montrent ce que le moteur fournit réellement à cet écran.
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};
private _log = { diag_log ("[COMSPEC LOBBY] " + _this) };

// ------------------------------------------------------------------ 1. contrôles indispensables
private _need = [109, 114, 104, 118, 128, 1, 9100, 9101, 9102, 9103, 9104, 9105, 9110, 9111, 9112, 9113, 9114, 9115, 9116, 9117, 9120, 9121, 9122, 9123, 9124, 9125, 9130, 9139, 9140];
private _missing = _need select { isNull (_display displayCtrl _x) };
if (_missing isNotEqualTo []) exitWith { (format ["contrôles manquants %1 : l'écran du jeu est laissé tel quel", _missing]) call _log };
("écran ouvert, " + str (count _need) + " contrôles trouvés") call _log;

private _c = { _display displayCtrl _this };
private _roles = 109 call _c;
private _cont = 1 call _c;
private _gold = [0.94, 0.65, 0.23, 1];

// Textes fixes du dossier.
(9100 call _c) ctrlSetText "";
(9110 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Grade"));
(9111 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Unit"));
(9112 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Role"));
(9113 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Quals"));
(9120 call _c) ctrlSetText (toUpper (localize "STR_COMSPEC_LOBBY_Pin"));

// ------------------------------------------------------------------ 2. étiquette et identité d'un poste
// _text -> [étiquette, protégé] ; « [A-1|P] Chef de groupe » -> ["A-1", true]
private _tagOf = {
    params [["_text", ""]];
    private _a = _text find "[";
    private _b = _text find "]";
    if (_a < 0 || {_b <= _a}) exitWith { ["", false] };
    private _parts = (_text select [_a + 1, _b - _a - 1]) splitString "|";
    if (_parts isEqualTo []) exitWith { ["", false] };
    [trim (_parts select 0), ("P" in (_parts select [1, count _parts])) || {"p" in (_parts select [1, count _parts])}]
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
                if ((getNumber (_cfg >> "pin")) > 0) then { _h set ["pin", true]; };
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
        private _name = trim ([_text, _text select [_rb + 1]] select (_rb >= 0));
        _h set ["surname", [_name, "-"] select (_name isEqualTo "")];
    };
    _h
};

// ------------------------------------------------------------------ 3. état de l'écran (uiNamespace : lu par les touches du pavé)
uiNamespace setVariable ["COMSPEC_LOBBY_Display", _display];
uiNamespace setVariable ["COMSPEC_LOBBY_Buf", ""];
uiNamespace setVariable ["COMSPEC_LOBBY_Tag", ""];
uiNamespace setVariable ["COMSPEC_LOBBY_Prot", false];

private _render = {
    private _d = uiNamespace getVariable ["COMSPEC_LOBBY_Display", displayNull];
    if (isNull _d) exitWith {};
    private _buf = uiNamespace getVariable ["COMSPEC_LOBBY_Buf", ""];
    private _prot = uiNamespace getVariable ["COMSPEC_LOBBY_Prot", false];
    {
        private _i = _forEachIndex;
        (_d displayCtrl _x) ctrlSetText ([ "", "*" ] select (_i < count _buf));
    } forEach [9121, 9122, 9123, 9124];
    private _msg = if (!_prot) then { localize "STR_COMSPEC_LOBBY_NoPin" } else {
        if ((count _buf) >= 4) then { localize "STR_COMSPEC_LOBBY_PinSaved" } else { localize "STR_COMSPEC_LOBBY_PinNeeded" }
    };
    (_d displayCtrl 9125) ctrlSetText _msg;
    (_d displayCtrl 9125) ctrlSetTextColor ([[1, 1, 1, 0.55], [0.94, 0.65, 0.23, 1]] select (_prot && {(count _buf) >= 4}));
    // Pavé : utile seulement pour un poste protégé.
    { (_d displayCtrl _x) ctrlShow _prot; (_d displayCtrl _x) ctrlEnable _prot; } forEach [9130, 9131, 9132, 9133, 9134, 9135, 9136, 9137, 9138, 9139, 9140];
    (_d displayCtrl 9120) ctrlSetTextColor ([[1, 1, 1, 0.25], [0.94, 0.65, 0.23, 1]] select _prot);
};
uiNamespace setVariable ["COMSPEC_LOBBY_render", _render];

// Touches du pavé : ["digit", "3"] | ["back"] | ["clear"].
uiNamespace setVariable ["COMSPEC_LOBBY_ui", {
    params ["_op", ["_arg", ""]];
    if (!(uiNamespace getVariable ["COMSPEC_LOBBY_Prot", false])) exitWith {};
    private _buf = uiNamespace getVariable ["COMSPEC_LOBBY_Buf", ""];
    switch (_op) do {
        case "digit": { if ((count _buf) < 4) then { _buf = _buf + _arg; }; };
        case "back": { _buf = _buf select [0, ((count _buf) - 1) max 0]; };
        case "clear": { _buf = ""; };
    };
    uiNamespace setVariable ["COMSPEC_LOBBY_Buf", _buf];
    // Code complet : gardé avec l'étiquette du poste, pour l'envoi au serveur à l'arrivée en jeu.
    if ((count _buf) isEqualTo 4) then {
        uiNamespace setVariable ["COMSPEC_LOBBY_Pin", [uiNamespace getVariable ["COMSPEC_LOBBY_Tag", ""], _buf]];
    } else {
        uiNamespace setVariable ["COMSPEC_LOBBY_Pin", ["", ""]];
    };
    call (uiNamespace getVariable ["COMSPEC_LOBBY_render", {}]);
}];

// ------------------------------------------------------------------ 4. boucle de l'écran (suivi du poste choisi, repères, verrou du bouton)
private _lastSig = "";
private _decoSig = "";
private _gated = false;
private _dumped = false;
call _render;
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

    // Repère « CODE » à droite des postes protégés (le moteur peut refaire les lignes : on repasse quand la liste change).
    private _dsig = format ["%1|%2", _size, count (toArray (_roles lbText 0))];
    if (_dsig isNotEqualTo _decoSig) then {
        _decoSig = _dsig;
        for "_i" from 0 to (_size - 1) do {
            ([_roles lbText _i] call _tagOf) params ["_t", "_p"];
            private _prot = _p || {_t isNotEqualTo "" && {(([_t, _roles lbText _i] call _identity) getOrDefault ["pin", false])}};
            if (_prot) then {
                _roles lbSetTextRight [_i, toUpper (localize "STR_COMSPEC_LOBBY_Locked")];
                _roles lbSetColorRight [_i, _gold];
            };
        };
    };

    // Poste choisi : nouvelle carte.
    private _sig = format ["%1|%2", _sel, _text];
    if (_sig isNotEqualTo _lastSig) then {
        _lastSig = _sig;
        ([_text] call _tagOf) params ["_tag", "_flag"];
        private _card = [_tag, _text] call _identity;
        private _prot = _flag || {_card getOrDefault ["pin", false]};
        if (_tag isNotEqualTo (uiNamespace getVariable ["COMSPEC_LOBBY_Tag", ""])) then {
            uiNamespace setVariable ["COMSPEC_LOBBY_Buf", ""];
            uiNamespace setVariable ["COMSPEC_LOBBY_Pin", ["", ""]];
        };
        uiNamespace setVariable ["COMSPEC_LOBBY_Tag", _tag];
        uiNamespace setVariable ["COMSPEC_LOBBY_Prot", _prot && {_tag isNotEqualTo ""}];

        (9101 call _c) ctrlSetText _tag;
        (9104 call _c) ctrlSetText (toUpper (_card getOrDefault ["surname", "-"]));
        (9105 call _c) ctrlSetText (toUpper (_card getOrDefault ["given", ""]));
        (9114 call _c) ctrlSetText (_card getOrDefault ["rank", ""]);
        (9115 call _c) ctrlSetText (_card getOrDefault ["unit", ""]);
        (9116 call _c) ctrlSetText (_card getOrDefault ["role", ""]);
        (9117 call _c) ctrlSetText (_card getOrDefault ["quals", ""]);
        private _pic = _card getOrDefault ["portrait", ""];
        (9102 call _c) ctrlSetText ([_pic, "#(argb,8,8,3)color(0.04,0.06,0.05,1)"] select (_pic isEqualTo ""));
        (9103 call _c) ctrlSetText (toUpper (_card getOrDefault ["source", [localize "STR_COMSPEC_LOBBY_PortraitLocal", ""] select (_pic isEqualTo "")]));
        call _render;
    };

    // Verrou du bouton « continuer » : seulement pour un poste protégé dont le code n'est pas complet, jamais pour l'hôte.
    private _needPin = (uiNamespace getVariable ["COMSPEC_LOBBY_Prot", false]) && {!isServer} && {(count (uiNamespace getVariable ["COMSPEC_LOBBY_Buf", ""])) < 4};
    if (_needPin) then {
        if (ctrlEnabled _cont) then { _cont ctrlEnable false; _gated = true; };
    } else {
        if (_gated) then { _cont ctrlEnable true; _gated = false; };
    };
    uiSleep 0.12;
};

// Écran fermé : l'état propre à l'écran est retiré (le code saisi reste, pour l'envoi au serveur).
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_LOBBY_Display", "COMSPEC_LOBBY_ui", "COMSPEC_LOBBY_render", "COMSPEC_LOBBY_Buf", "COMSPEC_LOBBY_Prot"];
"écran fermé" call _log;
