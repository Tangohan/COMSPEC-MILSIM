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
      3. le texte du rôle tel quel.
    Clés d'une carte : surname, given, rank, unit, role, quals, portrait (chemin d'image lisible par le jeu), source (« ATHENA »...).
    Journal : lignes [COMSPEC LOBBY] au RPT, dont les premières lignes de la liste (texte, donnée, valeur, image).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};
private _log = { diag_log ("[COMSPEC LOBBY] " + _this) };

// ------------------------------------------------------------------ 1. contrôles indispensables
private _need = [109, 114, 104, 118, 128, 1, 9100, 9101, 9102, 9103, 9104, 9105, 9110, 9111, 9112, 9113, 9114, 9115, 9116, 9117];
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
        _h set ["surname", [_name, "-"] select (_name isEqualTo "")];
    };
    _h
};

// ------------------------------------------------------------------ 3. boucle de l'écran (suivi du poste choisi)
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
        (9102 call _c) ctrlSetText ([_pic, "#(argb,8,8,3)color(0.04,0.06,0.05,1)"] select (_pic isEqualTo ""));
        (9103 call _c) ctrlSetText (toUpper (_card getOrDefault ["source", [localize "STR_COMSPEC_LOBBY_PortraitLocal", ""] select (_pic isEqualTo "")]));
    };

    uiSleep 0.12;
};

"écran fermé" call _log;
