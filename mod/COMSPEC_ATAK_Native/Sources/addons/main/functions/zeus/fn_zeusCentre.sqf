/*
    Centre Zeus : point d'entrée unique sur l'écran Zeus (display 312). État des ATAK de tous les joueurs, étiquettes 3D,
    actions de fn_zeusMenu, état du serveur, outils du SSE. Réservé à qui a l'écran Zeus ; le relevé est vérifié par le serveur (fn_zeusState).
    Rien ne tourne tant que le panneau est fermé : le bouton est un simple contrôle ; la boucle (2 s) et le Draw3D n'existent que
    panneau ouvert et meurent avec l'écran Zeus.
    Sources : variables déjà publiques (COMSPEC_ATAK_Pub, Spk, MeshNode via fn_meshRead, NetUp, NetLoad, ZeusFx, SimSos, Airplane,
    Gateway du véhicule, COMSPEC_DroneLock, caméras) ; le reste vient de la fiche à la demande (fn_zeusState).
    Un état non reçu n'est pas affiché : la fiche écrit « relevé en attente » ; un relevé qui vieillit (> 45 s) est redemandé en entier.
    Panneau (4.2.2) : adossé au bord gauche de la liste de droite de Zeus (mesurée à l'ouverture), fond opaque, hauteur selon le
    nombre de joueurs, au-dessus des panneaux ZEN du bas. Quatre onglets : JOUEURS (recherche, filtres, liste, fiche, actions,
    cible joueur / sélection Zeus / liste), SERVEUR (état du serveur, fn_zeusState « srvScan »), ÉTIQUETTES (étiquettes 3D :
    mode compact ou détaillé, champs), OUTILS (fenêtres du mod et du SSE). Aucun texte coupé : chaque bouton mesure son texte
    (« fit ») et passe au libellé court avec l'infobulle longue.
    Fenêtre ouverte depuis le centre (« tool ») : le centre se replie le temps qu'elle est ouverte et revient à sa fermeture ;
    l'écran Zeus dessine ses propres contrôles au-dessus des dialogues (createDialog), le centre les recouvrait.
    Actions :
      "hook"    (fn_zeusClient, chaque seconde, écran Zeus ouvert) : pose le bouton et les infobulles de la liste des modules
      "toggle"  ouvre ou ferme le panneau (bouton, touche CBA ZeusCentre)      "tab" [clé] : onglet
      "close"   ferme le panneau        "closed" : écran Zeus fermé, tout s'arrête
      "hide" / "show" : replie / déplie   "tool" [code] : replie, exécute, revient à la fermeture de la fenêtre ouverte
      "refresh" lit les variables, recalcule la liste et les étiquettes (boucle de 2 s)   "server" : onglet SERVEUR
      "filter" [clé]   "select" [uid]   "act" [id de fn_zeusMenu]   "admin" fiche dans l'app Admin   "pin" retirer le code
      "camera"  caméra Zeus sur le joueur   "labels" étiquettes 3D oui / non   "labelCfg" [clé, valeur]   "draw" (Draw3D)
      "tips" infobulles de la liste des modules   "edge" -> bord gauche de la liste de droite de Zeus
*/
#define DISP (findDisplay 312)
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _ui = { uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMap] };
private _trZ = { _this call comspec_atak_native_fnc_tr };
private _esc = { [[_this, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
// Version MAJEUR.MINEUR.CORRECTIF d'un texte de version (« 4.2.0.AG - Washington · … » -> [4, 2, 0]) ; [] si absente.
private _verOf = {
    if !(_this isEqualType "") exitWith { [] };
    private _m = ((_this regexFind ["^[0-9]+\.[0-9]+\.[0-9]+"]) param [0, []]) param [0, []];
    if (_m isEqualTo []) then { [] } else { ((_m select 0) splitString ".") apply { parseNumber _x } }
};
// -1 : plus ancien que la référence, 0 : identique, 1 : plus récent, 2 : inconnu.
private _verCmp = {
    params ["_v", "_ref"];
    if (_v isEqualTo [] || {_ref isEqualTo []}) exitWith { 2 };
    private _r = 0;
    { if (_r isEqualTo 0) then { _r = [[-1, 1] select (_x > (_ref select _forEachIndex)), 0] select (_x isEqualTo (_ref select _forEachIndex)); }; } forEach _v;
    _r
};
private _refVer = {
    private _srv = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrv", [createHashMap, 0]]) select 0;
    private _v = (_srv getOrDefault ["atakVersion", ""]) call _verOf;
    if (_v isEqualTo []) then { _v = (missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]) call _verOf; };
    _v
};
private _ago = { if (_this < 60) then { format [("il y a %1 s") call _trZ, _this] } else { format [("il y a %1 min") call _trZ, floor (_this / 60)] } };
// Réglages des étiquettes 3D du Zeus, gardés dans son profil.
private _labelCfg = {
    private _d = createHashMapFromArray [["mode", "compact"], ["name", true], ["phone", true], ["bat", true], ["net", true], ["athena", true], ["acct", true], ["ver", true], ["tx", true], ["alerts", true]];
    private _s = profileNamespace getVariable ["COMSPEC_ATAK_ZeusLabelCfg", []];
    if (_s isEqualType []) then { { if (_x isEqualType [] && {(_x select 0) in _d}) then { _d set [_x select 0, _x select 1]; }; } forEach _s; };
    _d
};
switch (_act) do {
    case "edge": {
        // Bord gauche de la liste de droite de Zeus (arbres et listes affichés dans le tiers droit, groupes compris) : le Centre
        // s'y adosse sans la couvrir. Rien de trouvé : largeur habituelle de la liste de Zeus.
        private _d = DISP;
        private _h = safeZoneH;
        private _kw = pixelW / pixelH;
        private _right = safeZoneX + safeZoneW - 0.30 * _h * _kw;
        if (isNull _d) exitWith { _right };
        {
            if ((ctrlType _x) in [5, 12, 102] && {ctrlShown _x}) then {
                (ctrlPosition _x) params ["_px", "", "_pw"];
                private _g = ctrlParentControlsGroup _x;
                if (!isNull _g) then { _px = _px + ((ctrlPosition _g) select 0); if (!ctrlShown _g) then { _pw = 0; }; };
                if (_pw > 0.06 && {_px > safeZoneX + safeZoneW * 0.6} && {_px < safeZoneX + safeZoneW - 0.02} && {_px < _right}) then { _right = _px; };
            };
        } forEach (allControls _d);
        _right
    };
    case "hook": {
        private _d = DISP;
        if (isNull _d || {_d getVariable ["COMSPEC_ATAK_CentreHook", false]}) exitWith {};
        _d setVariable ["COMSPEC_ATAK_CentreHook", true];
        _d displayAddEventHandler ["Unload", { ["closed"] call comspec_atak_native_fnc_zeusCentre; }];
        // Double-clic : fenêtre de configuration des objets du mod (fn_zeusObject). Un seul gestionnaire par module de curateur.
        private _cur = getAssignedCuratorLogic player;
        if (!isNull _cur && {!(_cur getVariable ["COMSPEC_ATAK_CentreDbl", false])}) then {
            _cur setVariable ["COMSPEC_ATAK_CentreDbl", true];
            _cur addEventHandler ["CuratorObjectDoubleClicked", { ["double", [_this select 1]] call comspec_atak_native_fnc_zeusObject; }];
        };
        [{ ["tips"] call comspec_atak_native_fnc_zeusCentre; }, [], 2] call comspec_atak_native_fnc_waitAndExec;
        [{ ["tips"] call comspec_atak_native_fnc_zeusCentre; }, [], 8] call comspec_atak_native_fnc_waitAndExec;
        if !(missionNamespace getVariable ["comspec_atak_native_zeus_centre_btn", true]) exitWith {};
        private _h = safeZoneH;
        private _kw = pixelW / pixelH;
        private _right = ["edge"] call comspec_atak_native_fnc_zeusCentre;
        private _bw = 0.13 * _h * _kw;
        private _b = _d ctrlCreate ["COMSPEC_RscButton", -1];
        _b ctrlSetPosition [_right - _bw - 0.004 * _h * _kw, safeZoneY + 0.008 * _h, _bw, 0.034 * _h];
        _b ctrlSetFontHeight (0.021 * _h);
        _b ctrlSetText (("CENTRE ZEUS") call _trZ);
        _b ctrlSetTooltip (("État des ATAK, actions, état du serveur et outils Zeus") call _trZ);
        _b ctrlCommit 0;
        _b ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_atak_native_fnc_zeusCentre; }];
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreBtn", _b];
    };
    case "fit": {
        // Texte d'un bouton qui tient dans sa largeur : police réduite jusqu'à 0,0145 h, puis libellé court et infobulle longue.
        _arg params ["_c", "_long", ["_short", ""], ["_tip", ""]];
        private _h = safeZoneH;
        private _w = (ctrlPosition _c) select 2;
        private _try = {
            params ["_txt"];
            private _f = 0.019 * _h;
            _c ctrlSetText (_txt call _trZ);
            _c ctrlSetFontHeight _f;
            while { (ctrlTextWidth _c) > _w * 0.94 && {_f > 0.0145 * _h} } do { _f = _f - 0.0005 * _h; _c ctrlSetFontHeight _f; };
            (ctrlTextWidth _c) <= _w * 0.94
        };
        private _ok = [_long] call _try;
        private _full = [_tip, format ["%1. %2", _long call _trZ, _tip call _trZ]] select (_tip isNotEqualTo "" && {_short isNotEqualTo ""});
        if (!_ok && {_short isNotEqualTo ""}) then { [_short] call _try; _c ctrlSetTooltip ([_long call _trZ, _full] select (_tip isNotEqualTo "")); } else { if (_tip isNotEqualTo "") then { _c ctrlSetTooltip (_tip call _trZ); }; };
        _c ctrlCommit 0;
    };
    case "toggle": {
        private _d = DISP;
        if (isNull _d) exitWith { false };
        if ((count (call _ui)) > 0) exitWith { ["close"] call comspec_atak_native_fnc_zeusCentre; true };
        private _h = safeZoneH;
        private _kw = pixelW / pixelH;
        private _right = ["edge"] call comspec_atak_native_fnc_zeusCentre;
        private _w = 0.46 * _h * _kw;
        private _p = 0.004 * _h;
        private _pw = _p * _kw;
        private _x0 = (_right - _w - _pw) max (safeZoneX + 0.01);
        private _yTop = safeZoneY + 0.048 * _h;
        // Au-dessus des panneaux ZEN du bas (FPS serveur, corps) : rien sous 86 % de la hauteur.
        private _hMax = 0.81 * _h;
        private _nPl = { !(_x isKindOf "HeadlessClient_F") } count allPlayers;
        private _listH = ((_nPl max 3) min 10) * 0.027 * _h + 0.006 * _h;
        private _rowH = 0.030 * _h;
        // En-tête, onglets, recherche, filtres, liste, [fiche], deux rangées d'actions, rappel : neuf marges.
        private _fixed = 0.044 * _h + 2 * _rowH + 0.078 * _h + _listH + 9 * _p;
        private _cardH = ((_hMax - _fixed) max (0.20 * _h)) min (0.40 * _h);
        private _hPanel = (_fixed + _cardH) min _hMax;
        _cardH = _hPanel - _fixed;
        private _mk = {
            params ["_cls", "_pos", ["_txt", ""], ["_tip", ""], ["_ev", ""], ["_grp", controlNull]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlSetPosition _pos;
            if (_txt isNotEqualTo "") then { _c ctrlSetText (_txt call comspec_atak_native_fnc_tr); };
            if (_tip isNotEqualTo "") then { _c ctrlSetTooltip (_tip call comspec_atak_native_fnc_tr); };
            if (_cls isEqualTo "COMSPEC_RscButton") then { _c ctrlSetFontHeight (0.019 * _h); };
            _c ctrlCommit 0;
            if (_ev isNotEqualTo "") then { _c ctrlAddEventHandler ["ButtonClick", _ev]; };
            _c
        };
        private _ctrls = [];
        private _tabs = createHashMapFromArray [["players", []], ["server", []], ["labels", []], ["tools", []]];
        private _bg = ["COMSPEC_RscPanel", [_x0, _yTop, _w, _hPanel]] call _mk;
        _bg ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        _bg ctrlCommit 0;
        _ctrls pushBack _bg;
        // En-tête : emblème Athena, titre, compteurs, fermeture.
        private _y = _yTop + _p;
        private _logoH = 0.036 * _h;
        private _logo = ["COMSPEC_RscPicture", [_x0 + _pw, _y, _logoH * _kw, _logoH]] call _mk;
        _logo ctrlSetText "\z\comspec_atak_native\addons\main\data\logo_athena.paa";
        _logo ctrlSetTextColor [1, 1, 1, 1];
        _logo ctrlCommit 0;
        _ctrls pushBack _logo;
        private _closeW = 0.030 * _h * _kw;
        private _head = ["COMSPEC_RscStructuredText", [_x0 + 2 * _pw + _logoH * _kw, _y - 0.002 * _h, _w - 4 * _pw - _logoH * _kw - _closeW, 0.044 * _h]] call _mk;
        _ctrls pushBack _head;
        private _close = ["COMSPEC_RscButton", [_x0 + _w - _pw - _closeW, _y + 0.003 * _h, _closeW, 0.030 * _h], "X", "Fermer le Centre Zeus (Ctrl+Z, selon vos touches)", "['close'] call comspec_atak_native_fnc_zeusCentre;"] call _mk;
        _ctrls pushBack _close;
        _y = _y + 0.044 * _h;
        // Onglets.
        private _tb = createHashMap;
        private _tw = (_w - 5 * _pw) / 4;
        {
            _x params ["_k", "_lbl", "_short", "_tip"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_tw + _pw), _y, _tw, _rowH]] call _mk;
            ["fit", [_c, _lbl, _short, _tip]] call comspec_atak_native_fnc_zeusCentre;
            _c setVariable ["k", _k];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["tab", [_c getVariable "k"]] call comspec_atak_native_fnc_zeusCentre; }];
            _tb set [_k, _c];
            _ctrls pushBack _c;
        } forEach [
            ["players", "JOUEURS", "JOUEURS", "État des ATAK des joueurs, fiche et actions"],
            ["server", "SERVEUR", "SERVEUR", "État du serveur : FPS, versions, liaison Athena, paquet d'identité, maillage, journal"],
            ["labels", "ÉTIQUETTES", "ÉTIQ.", "Étiquettes 3D au-dessus des joueurs : mode compact ou détaillé, champs affichés"],
            ["tools", "OUTILS", "OUTILS", "Fenêtres du mod et du SSE, raccourcis"]
        ];
        _y = _y + _rowH + _p;
        private _yBody = _y;
        private _bodyH = _hPanel - (_yBody - _yTop) - _p;
        // --- Onglet JOUEURS -------------------------------------------------------------------------------------------
        private _search = ["COMSPEC_RscEdit", [_x0 + _pw, _y, _w - 2 * _pw, 0.028 * _h], "", "Recherche : pseudo, indicatif ou groupe"] call _mk;
        _search ctrlSetFontHeight (0.019 * _h);
        _search ctrlAddEventHandler ["KeyUp", { ["search"] call comspec_atak_native_fnc_zeusCentre; }];
        (_tabs get "players") pushBack _search;
        _y = _y + 0.028 * _h + _p;
        // Filtres : une rangée de six ; libellé court si la place manque.
        private _fb = createHashMap;
        private _fw = (_w - 7 * _pw) / 6;
        {
            _x params ["_k", "_lbl", "_short", "_tip"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_fw + _pw), _y, _fw, 0.028 * _h]] call _mk;
            _c setVariable ["k", _k];
            _c setVariable ["lbl", [_lbl, _short, _tip]];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["filter", [_c getVariable "k"]] call comspec_atak_native_fnc_zeusCentre; }];
            _fb set [_k, _c];
            (_tabs get "players") pushBack _c;
        } forEach [
            ["all", "TOUS", "TOUS", "Tous les joueurs"], ["link", "LIÉS", "LIÉS", "Session Athena ouverte (relevé reçu)"],
            ["nolink", "HORS LIAISON", "HORS", "Sans session Athena au dernier relevé reçu"],
            ["relay", "RELAIS", "REL.", "Porte une radio qui relaie, a posé une radio ou est à bord d'une passerelle"],
            ["spk", "HAUT-PARLEUR", "HP", "Musique en haut-parleur"], ["alert", "ALERTES", "ALERT.", "PANIQUE, téléphone détruit, code bloqué, SIM en secours, relevé jamais reçu"]
        ];
        _y = _y + 0.028 * _h + _p;
        private _list = ["COMSPEC_RscListBoxDense", [_x0 + _pw, _y, _w - 2 * _pw, _listH]] call _mk;
        _list ctrlSetBackgroundColor [0.025, 0.030, 0.027, 1];
        _list ctrlAddEventHandler ["LBSelChanged", {
            params ["_c", "_i"];
            if (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreFill", false]) exitWith {};
            ["select", [_c lbData _i]] call comspec_atak_native_fnc_zeusCentre;
        }];
        _list ctrlAddEventHandler ["LBDblClick", { ["camera"] call comspec_atak_native_fnc_zeusCentre; }];
        (_tabs get "players") pushBack _list;
        // État vide de la liste, posé dessus : désactivé pour laisser passer la souris, montré seulement sans ligne.
        private _empty = ["COMSPEC_RscStructuredText", [_x0 + 2 * _pw, _y + 0.006 * _h, _w - 4 * _pw, _listH - 0.012 * _h]] call _mk;
        _empty ctrlEnable false;
        (_tabs get "players") pushBack _empty;
        _y = _y + _listH + _p;
        private _grp = ["COMSPEC_RscControlsGroup", [_x0 + _pw, _y, _w - 2 * _pw, _cardH]] call _mk;
        (_tabs get "players") pushBack _grp;
        private _card = ["COMSPEC_RscStructuredText", [0, 0, _w - 2 * _pw - 0.018 * _h * _kw, _cardH], "", "", "", _grp] call _mk;
        _y = _y + _cardH + _p;
        // Actions rapides sur le joueur choisi.
        private _qw = (_w - 6 * _pw) / 5;
        {
            _x params ["_t", "_short", "_tip", "_code"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_qw + _pw), _y, _qw, _rowH], "", "", _code] call _mk;
            ["fit", [_c, _t, _short, _tip]] call comspec_atak_native_fnc_zeusCentre;
            (_tabs get "players") pushBack _c;
        } forEach [
            ["CAMÉRA", "CAM", "Place la caméra Zeus sur le joueur (double-clic dans la liste)", "['camera'] call comspec_atak_native_fnc_zeusCentre;"],
            ["COUPER LE SON", "MUET", "Coupe ses haut-parleurs", "['act', ['mute_on']] call comspec_atak_native_fnc_zeusCentre;"],
            ["RÉTABLIR LE SON", "SON", "Rétablit ses haut-parleurs", "['act', ['mute_off']] call comspec_atak_native_fnc_zeusCentre;"],
            ["RETIRER LE CODE", "CODE", "Retire le code de son téléphone et remet ses essais à zéro", "['pin'] call comspec_atak_native_fnc_zeusCentre;"],
            ["FICHE ADMIN", "ADMIN", "Ouvre sa fiche dans l'app Admin du téléphone", "['tool', [{ ['admin'] call comspec_atak_native_fnc_zeusCentre; }]] call comspec_atak_native_fnc_zeusCentre;"]
        ];
        _y = _y + _rowH + _p;
        // Catalogue des actions, cible, application.
        private _catW = (_w - 4 * _pw) * 0.50;
        private _tgtW = (_w - 4 * _pw) * 0.30;
        private _apW = (_w - 4 * _pw) * 0.20;
        private _cat = ["COMSPEC_RscCombo", [_x0 + _pw, _y, _catW, _rowH], "", "Action de fn_zeusMenu à appliquer à la cible"] call _mk;
        _cat ctrlSetFontHeight (0.018 * _h);
        {
            _x params ["_id", "_g", "_lbl", "_help", ["_modOnly", false]];
            if (!_modOnly) then {
                private _i = _cat lbAdd format ["%1 : %2", _g call _trZ, _lbl call _trZ];
                _cat lbSetData [_i, _id];
            };
        } forEach (["catalog"] call comspec_atak_native_fnc_zeusMenu);
        _cat lbSetCurSel 0;
        (_tabs get "players") pushBack _cat;
        private _tgt = ["COMSPEC_RscCombo", [_x0 + 2 * _pw + _catW, _y, _tgtW, _rowH], "", "À qui s'applique l'action"] call _mk;
        _tgt ctrlSetFontHeight (0.018 * _h);
        {
            _x params ["_k", "_lbl"];
            private _i = _tgt lbAdd (_lbl call _trZ);
            _tgt lbSetData [_i, _k];
        } forEach [["sel", "Joueur choisi"], ["zeus", "Sélection Zeus"], ["list", "Joueurs affichés"]];
        _tgt lbSetCurSel 0;
        (_tabs get "players") pushBack _tgt;
        private _ap = ["COMSPEC_RscButtonPrimary", [_x0 + 3 * _pw + _catW + _tgtW, _y, _apW, _rowH], "", "", "private _c = (uiNamespace getVariable 'COMSPEC_ATAK_ZeusCentreUi') get 'cat'; ['act', [_c lbData (lbCurSel _c)]] call comspec_atak_native_fnc_zeusCentre;"] call _mk;
        ["fit", [_ap, "APPLIQUER", "OK", "Applique l'action choisie à la cible (joueur choisi, sélection Zeus ou joueurs affichés)"]] call comspec_atak_native_fnc_zeusCentre;
        (_tabs get "players") pushBack _ap;
        _y = _y + _rowH + _p;
        private _keys = ["COMSPEC_RscStructuredText", [_x0 + _pw, _y, _w - 2 * _pw, 0.022 * _h]] call _mk;
        _keys ctrlSetStructuredText parseText (("<t size='0.7' color='#8a9a93'>Clic droit (ZEN) : ATAK · Contrôle Zeus, ATAK · Relais, ATAK · Maillage</t>") call _trZ);
        (_tabs get "players") pushBack _keys;
        // --- Onglet SERVEUR -------------------------------------------------------------------------------------------
        _y = _yBody;
        private _srvGrp = ["COMSPEC_RscControlsGroup", [_x0 + _pw, _y, _w - 2 * _pw, _bodyH - _rowH - _p]] call _mk;
        (_tabs get "server") pushBack _srvGrp;
        private _srvTxt = ["COMSPEC_RscStructuredText", [0, 0, _w - 2 * _pw - 0.018 * _h * _kw, _bodyH - _rowH - _p], "", "", "", _srvGrp] call _mk;
        private _sbW = (_w - 3 * _pw) / 2;
        private _sb1 = ["COMSPEC_RscButton", [_x0 + _pw, _yTop + _hPanel - _p - _rowH, _sbW, _rowH], "", "", "missionNamespace setVariable ['COMSPEC_ATAK_ZeusSrvAsked', -100]; ['refresh'] call comspec_atak_native_fnc_zeusCentre;"] call _mk;
        ["fit", [_sb1, "ACTUALISER", "MAJ", "Redemande l'état au serveur (sinon toutes les 5 s, onglet ouvert seulement)"]] call comspec_atak_native_fnc_zeusCentre;
        (_tabs get "server") pushBack _sb1;
        private _sb2 = ["COMSPEC_RscButton", [_x0 + 2 * _pw + _sbW, _yTop + _hPanel - _p - _rowH, _sbW, _rowH], "", "", "copyToClipboard (missionNamespace getVariable ['COMSPEC_ATAK_ZeusSrvText', '']); ['feedback', ['État du serveur copié dans le presse-papiers.']] call comspec_atak_native_fnc_zeusMenu;"] call _mk;
        ["fit", [_sb2, "COPIER", "COPIER", "Copie l'état du serveur en texte (pour un rapport)"]] call comspec_atak_native_fnc_zeusCentre;
        (_tabs get "server") pushBack _sb2;
        // --- Onglet ÉTIQUETTES ----------------------------------------------------------------------------------------
        _y = _yBody;
        private _lb = createHashMap;
        private _lbOn = ["COMSPEC_RscButton", [_x0 + _pw, _y, _w - 2 * _pw, _rowH], "", "", "['labels'] call comspec_atak_native_fnc_zeusCentre;"] call _mk;
        _lb set ["on", _lbOn];
        (_tabs get "labels") pushBack _lbOn;
        _y = _y + _rowH + _p;
        private _mw = (_w - 3 * _pw) / 2;
        {
            _x params ["_k", "_lbl", "_tip"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + _forEachIndex * (_mw + _pw), _y, _mw, _rowH], "", "", format ["['labelCfg', ['mode', '%1']] call comspec_atak_native_fnc_zeusCentre;", _k]] call _mk;
            ["fit", [_c, _lbl, "", _tip]] call comspec_atak_native_fnc_zeusCentre;
            _lb set ["mode_" + _k, _c];
            (_tabs get "labels") pushBack _c;
        } forEach [
            ["compact", "COMPACT", "Indicatif et nom, puis seulement ce qui cloche (alerte, hors liaison, téléphone éteint ou détruit, batterie faible, mod pas à jour, haut-parleur ou radio en émission). Exemple pour Adam, indicatif TA1, sans liaison Athena, deux lignes, le nom puis HORS LIAISON."],
            ["detailed", "DÉTAILLÉ", "Toutes les lignes cochées ci-dessous, pour chaque joueur à moins de 400 m. Exemple pour Adam, cinq lignes, le nom, le téléphone et la batterie, le signal et le nœud du maillage, la liaison Athena et le compte, la version du mod."]
        ];
        _y = _y + _rowH + _p;
        private _cw = (_w - 4 * _pw) / 3;
        {
            _x params ["_k", "_lbl", "_tip"];
            private _c = ["COMSPEC_RscButton", [_x0 + _pw + (_forEachIndex % 3) * (_cw + _pw), _y + (floor (_forEachIndex / 3)) * (_rowH + _p), _cw, _rowH], "", "", format ["['labelCfg', ['%1']] call comspec_atak_native_fnc_zeusCentre;", _k]] call _mk;
            _c setVariable ["lbl", [_lbl, _tip]];
            _lb set [_k, _c];
            (_tabs get "labels") pushBack _c;
        } forEach [
            ["name", "Indicatif et nom", "Indicatif tactique et pseudo du joueur (toujours en mode compact)"],
            ["phone", "Téléphone", "Écran rangé, porté ou en main ; allumé, éteint, fêlé ou détruit"],
            ["bat", "Batterie", "Niveau de batterie en %"],
            ["net", "Signal et maillage", "Barres de signal, nœud du maillage et distance à la passerelle"],
            ["athena", "Liaison Athena", "Connecté, hors liaison, jamais lié"],
            ["acct", "Compte Athena", "Indicatif ou pseudo du compte Athena relié"],
            ["ver", "Version du mod", "Version du mod du joueur, à jour ou pas par rapport au serveur"],
            ["tx", "Haut-parleur et radio", "Musique en haut-parleur, radio en émission"],
            ["alerts", "Alertes", "PANIQUE, téléphone détruit, code bloqué, SIM en secours"]
        ];
        _y = _y + 3 * (_rowH + _p);
        private _lbHelp = ["COMSPEC_RscStructuredText", [_x0 + _pw, _y, _w - 2 * _pw, _bodyH - (_y - _yBody)]] call _mk;
        _lbHelp ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Étiquettes dessinées au-dessus des joueurs, panneau ouvert seulement, taille constante à l'écran. Un équipage = une étiquette. Au-delà de 400 m, seul le point reste (les alertes restent écrites). Vert : tout va bien ; orange : à surveiller ; rouge : alerte ; gris : relevé en attente. Réglages gardés dans votre profil.</t>") call _trZ);
        (_tabs get "labels") pushBack _lbHelp;
        // --- Onglet OUTILS --------------------------------------------------------------------------------------------
        _y = _yBody;
        private _tools = [
            ["CONFIGURER L'OBJET", "CONFIGURER", "Fenêtre de l'objet sélectionné dans Zeus : écran, preuve, caisse (aussi par double-clic).", "['request', [((curatorSelected param [0, []]) select { !(_x isKindOf 'CAManBase') }) param [0, objNull]]] call comspec_atak_native_fnc_zeusObject;", true],
            ["ASSISTANT ATAK", "ASSISTANT", "Assistant de configuration du réalisme et du réseau, un choix expliqué à la fois.", "['open'] call comspec_atak_native_fnc_setupWizard;", true]
        ];
        if (!isNil "comspec_sse_fnc_aiConfigOpen") then { _tools pushBack ["IA DE LA ZONE (SSE)", "IA (SSE)", "Configuration des IA : profil Athena, identité, papiers, comportement, camp.", "[(curatorSelected param [0, []]) select { _x isKindOf 'CAManBase' && {!isPlayer _x} }, screenToWorld [0.5, 0.5]] call comspec_sse_fnc_aiConfigOpen;", true]; };
        if (!isNil "comspec_sse_fnc_editor") then { _tools pushBack ["ÉDITEUR SSE", "ÉDITEUR", "Éditeur de renseignement au centre de l'écran : sites, preuves, entités.", "['open', [screenToWorld [0.5, 0.5], objNull]] call comspec_sse_fnc_editor;", true]; };
        if (!isNil "comspec_sse_fnc_chartOpen") then { _tools pushBack ["TABLEAU DES LIENS", "LIENS", "Graphe des personnes, organisations, objets et données, vue Zeus.", "[true] call comspec_sse_fnc_chartOpen;", true]; };
        if (!isNil "comspec_sse_fnc_openGuide") then { _tools pushBack ["MANUEL SSE", "MANUEL", "Manuel du Zeus pour le SSE.", "['Zeus'] call comspec_sse_fnc_openGuide;", true]; };
        if (!isNil "comspec_atak_native_fnc_devMeshOverlay") then { _tools pushBack ["GRAPHE DU MAILLAGE (3D)", "MAILLAGE", "Superposition 3D du graphe du maillage de votre camp (lecture seule, graphe du serveur).", "uiNamespace setVariable ['COMSPEC_ATAK_MeshZeusSide', str side group player]; ['toggle'] call comspec_atak_native_fnc_devMeshOverlay;", false]; };
        private _tlW = (_w - 3 * _pw) * 0.42;
        {
            // Titre en _tTitle : SQF ne distingue pas _t de _trZ (la traduction), « call _trZ » plantait juste en dessous
            // (RPT du 11/11, fn_zeusCentre.sqf ligne 357, une erreur par outil à chaque ouverture de l'onglet).
            _x params ["_tTitle", "_short", "_desc", "_code", "_wrap"];
            private _run = [_code, format ["['tool', [{ %1 }]] call comspec_atak_native_fnc_zeusCentre;", _code]] select _wrap;
            private _c = ["COMSPEC_RscButton", [_x0 + _pw, _y, _tlW, 0.032 * _h], "", "", _run] call _mk;
            ["fit", [_c, _tTitle, _short, _desc]] call comspec_atak_native_fnc_zeusCentre;
            (_tabs get "tools") pushBack _c;
            private _dc = ["COMSPEC_RscStructuredText", [_x0 + 2 * _pw + _tlW, _y, _w - 3 * _pw - _tlW, 0.032 * _h]] call _mk;
            _dc ctrlSetStructuredText parseText ((format ["<t size='0.72' color='#b8c4bd'>%1</t>", _desc]) call _trZ);
            (_tabs get "tools") pushBack _dc;
            _y = _y + 0.032 * _h + _p;
        } forEach _tools;
        private _tHelp = ["COMSPEC_RscStructuredText", [_x0 + _pw, _y, _w - 2 * _pw, (_bodyH - (_y - _yBody)) max (0.03 * _h)]] call _mk;
        _tHelp ctrlSetStructuredText parseText (("<t size='0.72' color='#8a9a93'>Clic droit (ZEN) sur un joueur : ATAK · Contrôle Zeus ; sur un relais : ATAK · Relais ; sur le sol : ATAK · Maillage (brouilleurs, clés, météo radio). Les fenêtres ouvertes d'ici passent devant le centre, qui revient à leur fermeture.</t>") call _trZ);
        (_tabs get "tools") pushBack _tHelp;
        { _ctrls append _y; } forEach _tabs;
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMapFromArray [
            ["all", _ctrls], ["head", _head], ["tabs", _tabs], ["tabBtns", _tb], ["filters", _fb], ["list", _list], ["empty", _empty], ["card", _card], ["cat", _cat],
            ["tgt", _tgt], ["grp", _grp], ["search", _search], ["srvTxt", _srvTxt], ["srvGrp", _srvGrp], ["lb", _lb], ["x0", _x0], ["w", _w]
        ]];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDenied", false];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLabels", missionNamespace getVariable ["comspec_atak_native_zeus_centre_3d", true]];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreAsked", -100];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusSrvAsked", -100];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreHidden", false];
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLines", []];
        ["tab", [uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreTab", "players"]]] call comspec_atak_native_fnc_zeusCentre;
        ["refresh"] call comspec_atak_native_fnc_zeusCentre;
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePfh", [{ ["refresh"] call comspec_atak_native_fnc_zeusCentre; }, 2] call CBA_fnc_addPerFrameHandler];
        ["labels", [true]] call comspec_atak_native_fnc_zeusCentre;
        true
    };
    case "tab": {
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _k = _arg param [0, "players"];
        if !(_k in (_t get "tabs")) then { _k = "players"; };
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreTab", _k];
        private _hidden = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreHidden", false];
        { private _on = _x isEqualTo _k && {!_hidden}; { _x ctrlShow _on; } forEach _y; } forEach (_t get "tabs");
        { _y ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _k)); } forEach (_t get "tabBtns");
        if (_k isEqualTo "server") then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusSrvAsked", -100]; ["server"] call comspec_atak_native_fnc_zeusCentre; };
        if (_k isEqualTo "labels") then { ["labelCfg", []] call comspec_atak_native_fnc_zeusCentre; };
        if (_k isEqualTo "players") then { ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre; };
    };
    case "hide";
    case "show": {
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _on = _act isEqualTo "show";
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreHidden", !_on];
        { _x ctrlShow _on; } forEach (_t get "all");
        if (_on) then { ["tab", [uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreTab", "players"]]] call comspec_atak_native_fnc_zeusCentre; };
    };
    case "tool": {
        // Replie le centre, ouvre la fenêtre, puis guette le nouvel affichage (dialogue ou fenêtre enfant) jusqu'à 2 s :
        // le centre revient quand elle se ferme ; sans fenêtre ouverte (rien de sélectionné, message seulement), il revient aussitôt.
        private _code = _arg param [0, {}];
        if ((count (call _ui)) isEqualTo 0) exitWith { call _code; };
        private _before = allDisplays select { !isNull _x };
        ["hide"] call comspec_atak_native_fnc_zeusCentre;
        call _code;
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreWait", [_before, diag_tickTime]];
        [{
            params ["", "_pfh"];
            private _w = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreWait", []];
            if (_w isEqualTo [] || {(count (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreUi", createHashMap])) isEqualTo 0}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
            _w params ["_before", "_t0"];
            private _new = allDisplays select { private _d = _x; !isNull _d && {(_before findIf { _x isEqualTo _d }) < 0} && {_d isNotEqualTo (findDisplay 312)} };
            if (_new isNotEqualTo []) exitWith {
                [_pfh] call CBA_fnc_removePerFrameHandler;
                missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreWait", []];
                { _x displayAddEventHandler ["Unload", { [{ ["show"] call comspec_atak_native_fnc_zeusCentre; }] call comspec_atak_native_fnc_nextFrame; }]; } forEach _new;
            };
            if (diag_tickTime - _t0 > 2) exitWith {
                [_pfh] call CBA_fnc_removePerFrameHandler;
                missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreWait", []];
                ["show"] call comspec_atak_native_fnc_zeusCentre;
            };
        }, 0.2] call CBA_fnc_addPerFrameHandler;
    };
    case "close";
    case "closed": {
        private _pfh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentrePfh", -1];
        if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePfh", -1]; };
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1]; };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", []];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreWait", []];
        { ctrlDelete _x; } forEach ((call _ui) getOrDefault ["all", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreUi", nil];
    };
    case "labels": {
        // ["labels"] bascule ; ["labels", [true]] : applique l'état en cours (ouverture du panneau).
        if (_arg isEqualTo []) then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLabels", !(missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true])]; };
        private _on = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true];
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        if (_on && {_eh < 0}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_atak_native_fnc_zeusCentre; }]];
        };
        if (!_on && {_eh >= 0}) then {
            removeMissionEventHandler ["Draw3D", _eh];
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreDraw", -1];
        };
        ["labelCfg", []] call comspec_atak_native_fnc_zeusCentre;
    };
    case "labelCfg": {
        // ["labelCfg", [clé]] bascule un champ ; ["labelCfg", ["mode", valeur]] ; ["labelCfg", []] redessine seulement.
        private _cfg = call _labelCfg;
        if (_arg isNotEqualTo []) then {
            private _k = _arg param [0, ""];
            if ((count _arg) < 2) then { _cfg set [_k, !(_cfg getOrDefault [_k, true])]; } else { _cfg set [_k, _arg select 1]; };
            profileNamespace setVariable ["COMSPEC_ATAK_ZeusLabelCfg", (keys _cfg) apply { [_x, _cfg get _x] }];
            [] call comspec_atak_native_fnc_saveProfile;
            ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre;
        };
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _lb = _t get "lb";
        private _on = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true];
        private _onC = _lb get "on";
        ["fit", [_onC, ["ÉTIQUETTES 3D : NON", "ÉTIQUETTES 3D : OUI"] select _on, ["3D : NON", "3D : OUI"] select _on, "Étiquettes au-dessus des joueurs, panneau ouvert seulement"]] call comspec_atak_native_fnc_zeusCentre;
        _onC ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 1], [0.18, 0.38, 0.21, 1]] select _on);
        private _mode = _cfg getOrDefault ["mode", "compact"];
        { (_lb get ("mode_" + _x)) ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _mode)); } forEach ["compact", "detailed"];
        {
            private _c = _lb get _x;
            if (!isNull _c) then {
                (_c getVariable ["lbl", ["", ""]]) params ["_lbl", "_tip"];
                private _v = _cfg getOrDefault [_x, true];
                ["fit", [_c, format ["%1 : %2", _lbl, ["non", "oui"] select _v], "", _tip]] call comspec_atak_native_fnc_zeusCentre;
                _c ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 1], [0.14, 0.26, 0.17, 1]] select _v);
            };
        } forEach ["name", "phone", "bat", "net", "athena", "acct", "ver", "tx", "alerts"];
    };
    case "draw": {
        // Sécurité : l'événement Unload ne s'est pas produit (changement de mission).
        if (isNull DISP) exitWith { ["closed"] call comspec_atak_native_fnc_zeusCentre; };
        if (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreHidden", false]) exitWith {};
        private _cam = positionCameraToWorld [0, 0, 0];
        private _up = (positionCameraToWorld [0, 1, 0]) vectorDiff _cam;
        {
            _x params ["_o", "_lines", "_top"];
            if (!isNull _o) then {
                private _p = ASLToAGL (getPosASLVisual _o);
                _p set [2, (_p select 2) + _top];
                private _dist = _p distance _cam;
                private _step = _dist * 0.03;
                private _n = count _lines;
                {
                    _x params ["_txt", "_col", "_size"];
                    private _pi = _p vectorAdd (_up vectorMultiply ((_n - 1 - _forEachIndex) * _step));
                    if (_txt isNotEqualTo "") then {
                        drawIcon3D ["#(argb,8,8,3)color(0,0,0,0.55)", [1, 1, 1, 1], _pi, (count _txt) * 0.5 * (_size / 0.028), 2.4 * (_size / 0.028), 0, "", 0];
                    };
                    drawIcon3D [["", "\a3\ui_f\data\map\markers\military\dot_CA.paa"] select (_forEachIndex isEqualTo (_n - 1) && {_txt isEqualTo ""}), _col, _pi, 0.45, 0.45, 0, _txt, 2, _size, "RobotoCondensedBold", "center"];
                } forEach _lines;
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreMarks", []]);
    };
    case "filter": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFilter", _arg param [0, "all"]];
        ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre;
    };
    case "search": {
        ["refresh", [true]] call comspec_atak_native_fnc_zeusCentre;
    };
    case "select": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreSel", _arg param [0, ""]];
        ["card"] call comspec_atak_native_fnc_zeusCentre;
    };
    case "refresh": {
        if (isNull DISP) exitWith { ["closed"] call comspec_atak_native_fnc_zeusCentre; };
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _now = diag_tickTime;
        private _rows = missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap];
        // Demande au serveur : toutes les 5 s, et jamais vue fermée (cette boucle n'existe alors plus). Une fiche qui a plus de
        // 45 s (le joueur renvoie la sienne au moins toutes les 30 s) : toutes les fiches sont redemandées, au plus toutes les 30 s.
        if (_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreAsked", -100]) >= 5) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreAsked", _now];
            private _stale = ((values _rows) findIf { _now - (_x select 1) > 45 }) >= 0 && {_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreFullAt", -100]) > 30};
            if (_stale) then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFullAt", _now]; };
            ["request", [_stale]] call comspec_atak_native_fnc_zeusState;
        };
        if ((uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreTab", "players"]) isEqualTo "server" && {_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrvAsked", -100]) >= 5}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusSrvAsked", _now];
            ["srvRequest"] call comspec_atak_native_fnc_zeusState;
            ["server"] call comspec_atak_native_fnc_zeusCentre;
        };
        private _refV = call _refVer;
        // Drones : qui pilote (COMSPEC_DroneLock : uid), à qui appartient-il. Caméras posées.
        private _pilot = createHashMap;
        private _owned = createHashMap;
        {
            private _nm = _x getVariable ["COMSPEC_DroneName", getText (configOf _x >> "displayName")];
            private _lk = _x getVariable ["COMSPEC_DroneLock", ""];
            if (_lk isEqualType "" && {_lk isNotEqualTo ""}) then { _pilot set [_lk, _nm]; };
            private _ow = _x getVariable ["COMSPEC_DroneOwner", ""];
            if (_ow isEqualType "" && {_ow isNotEqualTo ""}) then { _owned set [_ow, (_owned getOrDefault [_ow, 0]) + 1]; };
        } forEach allUnitsUAV;
        private _cams = createHashMap;
        {
            private _o = _x getVariable ["COMSPEC_ATAK_CamOwnerUid", ""];
            if (!isNull _x && {_o isEqualType ""}) then { _cams set [_o, (_cams getOrDefault [_o, 0]) + 1]; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]);
        private _model = [];
        {
            private _u = _x;
            if (_u isKindOf "HeadlessClient_F") then { continue; };
            private _uid = getPlayerUID _u;
            private _rec = _rows getOrDefault [_uid, []];
            private _row = _rec param [0, []];
            private _age = if (_rec isEqualTo []) then { -1 } else { round (_now - (_rec select 1)) };
            // Fiche reçue il y a moins de 90 s : fiable ; plus vieille : « relevé ancien » ; jamais reçue : « en attente ».
            private _fresh = _age >= 0 && {_age < 90};
            private _pub = _u getVariable ["COMSPEC_ATAK_Pub", []];
            if !(_pub isEqualType []) then { _pub = []; };
            private _spk = _u getVariable ["COMSPEC_ATAK_Spk", []];
            if !(_spk isEqualType []) then { _spk = []; };
            private _node = ["node", _u] call comspec_atak_native_fnc_meshRead;
            private _mpu = ["mpu5", _u] call comspec_atak_native_fnc_meshRead;
            private _gw = (vehicle _u) getVariable ["COMSPEC_ATAK_Gateway", []];
            if !(_gw isEqualType []) then { _gw = []; };
            private _dropped = _node isNotEqualTo [] && {(_node select 2) isNotEqualTo _u};
            private _isRelay = _mpu isNotEqualTo [] || {_dropped} || {_gw isNotEqualTo []};
            private _dev = _pub param [2, ""];
            if !(_dev isEqualType "") then { _dev = ""; };
            private _pin = if (_fresh) then { _row select 11 } else { 0 };
            private _panic = if (_fresh) then { _row select 12 } else { -1 };
            private _sos = _u getVariable ["COMSPEC_ATAK_SimSos", false];
            private _alerts = [];
            if (_panic >= 0) then { _alerts pushBack "PANIQUE"; };
            if (_dev isEqualTo "BROKEN") then { _alerts pushBack "TÉLÉPHONE DÉTRUIT"; };
            if (_pin > 0) then { _alerts pushBack "CODE BLOQUÉ"; };
            if (_sos isEqualTo true) then { _alerts pushBack "SIM EN SECOURS"; };
            private _logged = _fresh && {_row select 6};
            private _ver = if (_fresh) then { (_row select 7) call _verOf } else { [] };
            _model pushBack (createHashMapFromArray [
                ["u", _u], ["uid", _uid], ["name", name _u], ["cs", [_u, true] call comspec_atak_native_fnc_unitCallsign], ["row", _row], ["fresh", _fresh], ["age", _age],
                ["pub", _pub], ["dev", _dev], ["spk", _spk], ["node", _node], ["mpu", _mpu],
                ["gw", _gw], ["dropped", _dropped], ["relay", _isRelay], ["alerts", _alerts], ["logged", _logged], ["pin", _pin], ["panic", _panic],
                ["ver", _ver], ["verCmp", [_ver, _refV] call _verCmp],
                ["pilot", _pilot getOrDefault [_uid, ""]], ["drones", _owned getOrDefault [_uid, 0]], ["cams", _cams getOrDefault [_uid, 0]]
            ]);
        } forEach allPlayers;
        private _pred = createHashMapFromArray [
            ["all", { true }], ["link", { _x get "logged" }], ["nolink", { (_x get "fresh") && {!(_x get "logged")} }], ["relay", { _x get "relay" }],
            ["spk", { (_x get "spk") isNotEqualTo [] }], ["alert", { (_x get "alerts") isNotEqualTo [] || {(_x get "age") < 0} }]
        ];
        private _n = createHashMap;
        { private _p = _y; _n set [_x, { call _p } count _model]; } forEach _pred;
        private _flt = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreFilter", "all"];
        {
            private _k = _x;
            (_y getVariable ["lbl", ["", "", ""]]) params ["_lbl", "_short", "_tip"];
            ["fit", [_y, format ["%1 (%2)", _lbl, _n get _k], format ["%1 (%2)", _short, _n get _k], _tip]] call comspec_atak_native_fnc_zeusCentre;
            _y ctrlSetBackgroundColor ([[0.070, 0.082, 0.072, 1], [0.18, 0.36, 0.22, 1]] select (_k isEqualTo _flt));
        } forEach (_t get "filters");
        private _denied = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateDenied", false];
        private _pending = { (_x get "age") < 0 } count _model;
        private _sub = if (_denied) then { ("Relevé refusé par le serveur") call _trZ } else {
            format ["%1 · %2", format [("%1 joueur(s)") call _trZ, count _model], format [("%1 lié(s)") call _trZ, _n get "link"]] + ([format [" · %1", format [("%1 en attente") call _trZ, _pending]], ""] select (_pending isEqualTo 0))
        };
        (_t get "head") ctrlSetStructuredText parseText (format ["<t size='1.05' font='RobotoCondensedBold'>%1</t><br/><t size='0.78' color='#8a9a93'>%2</t>", ("CENTRE ZEUS") call _trZ, _sub]);
        // Joueur choisi dans Zeus : sa fiche s'ouvre ici (le clic sur l'unité vaut clic dans la liste).
        private _picked = ((curatorSelected param [0, []]) select { isPlayer _x }) param [0, objNull];
        if (!isNull _picked) then {
            private _pu = getPlayerUID _picked;
            if (_pu isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentrePicked", ""])) then { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreSel", _pu]; };
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePicked", _pu];
        } else { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentrePicked", ""]; };
        private _test = _pred get _flt;
        private _shown = _model select { call _test };
        private _q = toLower (ctrlText (_t get "search"));
        if (_q isNotEqualTo "") then {
            _shown = _shown select { ((toLower format ["%1 %2 %3", _x get "name", _x get "cs", groupId group (_x get "u")]) find _q) >= 0 };
        };
        // Alertes d'abord, puis hors liaison, puis par nom.
        _shown = [_shown, [], { format ["%1%2%3", ["1", "0"] select ((_x get "alerts") isNotEqualTo []), ["1", "0"] select (!(_x get "logged")), toLower (_x get "name")] }] call BIS_fnc_sortBy;
        // La liste n'est remplie à nouveau que si elle a changé : sa position de défilement et la sélection restent.
        private _lines = _shown apply {
            private _m = _x;
            private _tok = [];
            (_m get "pub") params [["_b", -1], ["_s", -1]];
            private _bat = if (_m get "fresh") then { (_m get "row") select 4 } else { _b };
            if (_bat isEqualType 0 && {_bat >= 0}) then { _tok pushBack format ["%1 %%", _bat]; };
            if (_s isEqualType 0 && {_s >= 0}) then { _tok pushBack format ["S%1", _s]; };
            if ((_m get "spk") isNotEqualTo []) then { _tok pushBack "HP"; };
            if (_m get "relay") then { _tok pushBack "REL"; };
            if ((_m get "pilot") isNotEqualTo "") then { _tok pushBack "UAV"; };
            if ((_m get "verCmp") isEqualTo -1) then { _tok pushBack "PAS À JOUR"; };
            if ((_m get "age") < 0) then { _tok pushBack "SANS RELEVÉ"; };
            if ((_m get "alerts") isNotEqualTo []) then { _tok pushBack ((_m get "alerts") select 0); };
            private _col = switch (true) do {
                case ((_m get "alerts") isNotEqualTo []): { [0.95, 0.35, 0.28, 1] };
                case (!(_m get "fresh")): { [0.6, 0.64, 0.62, 1] };
                case (!(_m get "logged") || {(_m get "verCmp") isEqualTo -1}): { [0.95, 0.67, 0.2, 1] };
                default { [0.79, 0.88, 0.82, 1] };
            };
            private _cs = _m get "cs";
            [format ["%1%2  ·  %3", [format ["%1 · ", _cs], ""] select (_cs isEqualTo "" || {_cs isEqualTo (_m get "name")}), _m get "name", (_tok apply { _x call _trZ }) joinString " · "], _m get "uid", _col]
        };
        private _list = _t get "list";
        if (_lines isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLines", []]) || {_arg isEqualTo [true]}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreLines", _lines];
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFill", true];
            lbClear _list;
            private _sel = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
            private _at = -1;
            {
                _x params ["_txt", "_id", "_col"];
                private _i = _list lbAdd _txt;
                _list lbSetData [_i, _id];
                _list lbSetColor [_i, _col];
                if (_id isEqualTo _sel) then { _at = _i; };
            } forEach _lines;
            if (_at >= 0) then { _list lbSetCurSel _at; };
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreFill", false];
            private _empty = _t get "empty";
            if (_lines isEqualTo []) then {
                private _why = switch (true) do {
                    case (_model isEqualTo []): { "Aucun joueur connecté." };
                    case (_q isNotEqualTo ""): { "Aucun joueur ne correspond à la recherche." };
                    default { "Aucun joueur dans ce filtre. TOUS les montre tous." };
                };
                _empty ctrlSetStructuredText parseText (format ["<t size='0.85' color='#8a9a93' align='center'>%1</t>", _why call _trZ]);
                _empty ctrlShow ((uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreTab", "players"]) isEqualTo "players" && {!(missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreHidden", false])});
            } else { _empty ctrlSetStructuredText parseText ""; _empty ctrlShow false; };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreModel", _model];
        ["card"] call comspec_atak_native_fnc_zeusCentre;
        // Étiquettes 3D : 40 au plus, les plus proches de la caméra ; un équipage = une étiquette. Lignes selon le mode et les champs.
        if (missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLabels", true]) then {
            private _cfg = call _labelCfg;
            private _detailed = (_cfg getOrDefault ["mode", "compact"]) isEqualTo "detailed";
            private _cam = [curatorCamera, player] select (isNull curatorCamera);
            private _groups = createHashMap;
            {
                private _o = vehicle (_x get "u");
                private _k = hashValue _o;
                private _l = _groups getOrDefault [_k, [_o, []]];
                (_l select 1) pushBack _x;
                _groups set [_k, _l];
            } forEach _shown;
            private _marks = (values _groups) apply { [(_x select 0) distance _cam, _x select 0, _x select 1] };
            _marks sort true;
            private _green = [0.45, 0.88, 0.52, 0.95];
            private _amber = [0.95, 0.67, 0.2, 0.95];
            private _red = [0.95, 0.3, 0.25, 1];
            private _grey = [0.72, 0.76, 0.74, 0.9];
            private _white = [0.92, 0.95, 0.93, 1];
            missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", (_marks select [0, 40]) apply {
                _x params ["_dist", "_o", "_ms"];
                private _bad = _ms select { (_x get "alerts") isNotEqualTo [] };
                private _first = _ms select 0;
                private _lines = [];
                private _worst = switch (true) do {
                    case (_bad isNotEqualTo []): { _red };
                    case ((_ms findIf { (_x get "age") < 0 }) >= 0): { _grey };
                    case ((_ms findIf { !(_x get "logged") || {(_x get "verCmp") isEqualTo -1} }) >= 0): { _amber };
                    default { _green };
                };
                if ((count _ms) > 1) then {
                    _lines pushBack [format [("%1 joueurs") call _trZ, count _ms], _worst, 0.030];
                    { _lines pushBack [((_x get "alerts") select 0) call _trZ, _red, 0.026]; } forEach (_bad select [0, 3]);
                } else {
                    private _m = _first;
                    (_m get "pub") params [["_b", -1], ["_s", -1]];
                    private _row = _m get "row";
                    private _fresh = _m get "fresh";
                    private _cs = _m get "cs";
                    private _title = [format ["%1 · %2", _cs, _m get "name"], _m get "name"] select (_cs isEqualTo "" || {_cs isEqualTo (_m get "name")});
                    if (_cfg getOrDefault ["name", true] || {!_detailed}) then { _lines pushBack [_title, _worst, 0.030]; };
                    if (_cfg getOrDefault ["alerts", true]) then { { _lines pushBack [_x call _trZ, _red, 0.026]; } forEach ((_m get "alerts") select [0, 2]); };
                    private _bat = if (_fresh) then { _row select 4 } else { _b };
                    private _devTxt = switch (true) do {
                        case ((_m get "dev") isEqualTo "BROKEN"): { "" };
                        case ((_m get "dev") isEqualTo "OFF"): { "Tél. éteint" };
                        case (_fresh && {!(_row select 1)}): { "Sans téléphone" };
                        case (_fresh): { ["Tél. rangé", "Tél. porté", "Tél. en main"] select ((_row select 2) max 0 min 2) };
                        default { "" };
                    };
                    if ((_m get "dev") isEqualTo "CRACKED" && {_devTxt isNotEqualTo ""}) then { _devTxt = _devTxt + ", écran fêlé"; };
                    private _batTxt = [format ["%1 %%", _bat], ""] select !(_bat isEqualType 0 && {_bat >= 0});
                    if (_detailed) then {
                        if (_cfg getOrDefault ["phone", true] || {_cfg getOrDefault ["bat", true]}) then {
                            private _parts = [];
                            if (_cfg getOrDefault ["phone", true] && {_devTxt isNotEqualTo ""}) then { _parts pushBack (_devTxt call _trZ); };
                            if (_cfg getOrDefault ["bat", true] && {_batTxt isNotEqualTo ""}) then { _parts pushBack _batTxt; };
                            if (_parts isNotEqualTo []) then { _lines pushBack [_parts joinString " · ", [_white, _amber] select ((_m get "dev") in ["OFF"] || {_bat isEqualType 0 && {_bat >= 0} && {_bat < 20}}), 0.026]; };
                        };
                        if (_cfg getOrDefault ["net", true]) then {
                            private _parts = [];
                            if (_s isEqualType 0 && {_s >= 0}) then { _parts pushBack format [("Signal %1") call _trZ, _s]; };
                            private _node = _m get "node";
                            if (_node isNotEqualTo []) then { _parts pushBack format [("nœud %1 · %2 m") call _trZ, _node select 0, _node select 1]; };
                            if (_m get "relay") then { _parts pushBack (("relais") call _trZ); };
                            if (_parts isNotEqualTo []) then { _lines pushBack [_parts joinString " · ", [_white, _amber] select (_s isEqualType 0 && {_s isEqualTo 0}), 0.026]; };
                        };
                        if (_cfg getOrDefault ["athena", true] || {_cfg getOrDefault ["acct", true]}) then {
                            private _st = switch (true) do {
                                case (!_fresh): { ["Athena : relevé en attente", "Athena : relevé ancien"] select ((_m get "age") >= 0) };
                                case (_m get "logged"): { "Athena : connecté" };
                                case ((_row select 5) in ["unlinked", "unset", "off"]): { "Athena : jamais lié" };
                                default { "Athena : hors liaison" };
                            };
                            private _txt = [_st call _trZ, ""] select !(_cfg getOrDefault ["athena", true]);
                            if (_cfg getOrDefault ["acct", true] && {_fresh} && {(_row param [13, ""]) isNotEqualTo ""}) then { _txt = ([_txt + " · ", ""] select (_txt isEqualTo "")) + (_row select 13); };
                            if (_txt isNotEqualTo "") then { _lines pushBack [_txt, [[_amber, _green] select (_m get "logged"), _grey] select !_fresh, 0.026]; };
                        };
                        if (_cfg getOrDefault ["ver", true] && {_fresh}) then {
                            private _v = (_m get "ver") joinString ".";
                            private _vt = switch (_m get "verCmp") do { case (-1): { "PAS À JOUR" }; case 0: { "à jour" }; case 1: { "plus récent" }; default { "version inconnue" }; };
                            _lines pushBack [format [("Mod %1 · %2") call _trZ, [_v, "?"] select (_v isEqualTo ""), _vt call _trZ], [_white, _amber] select ((_m get "verCmp") isEqualTo -1), 0.026];
                        };
                        if (_cfg getOrDefault ["tx", true]) then {
                            private _parts = [];
                            if ((_m get "spk") isNotEqualTo []) then { _parts pushBack (("HAUT-PARLEUR") call _trZ); };
                            if (_fresh && {_row select 10}) then { _parts pushBack (("RADIO EN ÉMISSION") call _trZ); };
                            if ((_m get "pilot") isNotEqualTo "") then { _parts pushBack (("pilote de drone") call _trZ); };
                            if (_parts isNotEqualTo []) then { _lines pushBack [_parts joinString " · ", _amber, 0.026]; };
                        };
                    } else {
                        // Compact : seulement ce qui cloche.
                        if ((_m get "age") < 0) then { _lines pushBack [("SANS RELEVÉ") call _trZ, _grey, 0.026]; };
                        if (_fresh && {!(_m get "logged")}) then { _lines pushBack [("HORS LIAISON") call _trZ, _amber, 0.026]; };
                        if ((_m get "dev") isEqualTo "OFF") then { _lines pushBack [("TÉL. ÉTEINT") call _trZ, _amber, 0.026]; };
                        if (_bat isEqualType 0 && {_bat >= 0} && {_bat < 20}) then { _lines pushBack [format [("BATTERIE %1 %%") call _trZ, _bat], _amber, 0.026]; };
                        if ((_m get "verCmp") isEqualTo -1) then { _lines pushBack [format [("MOD %1 PAS À JOUR") call _trZ, (_m get "ver") joinString "."], _amber, 0.026]; };
                        private _parts = [];
                        if ((_m get "spk") isNotEqualTo []) then { _parts pushBack (("HP") call _trZ); };
                        if (_fresh && {_row select 10}) then { _parts pushBack (("RADIO") call _trZ); };
                        if (_parts isNotEqualTo []) then { _lines pushBack [_parts joinString " · ", _amber, 0.026]; };
                    };
                };
                // Au loin, le point seul : le texte reste pour les alertes.
                if (_dist > 400 && {_bad isEqualTo []}) then { _lines = [["", _worst, 0.026]]; };
                private _top = [2.3, ((boundingBoxReal _o) select 1 select 2) + 1] select (_o isNotEqualTo (_first get "u"));
                [_o, _lines, _top]
            }];
        } else { missionNamespace setVariable ["COMSPEC_ATAK_ZeusCentreMarks", []]; };
    };
    case "card": {
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        private _model = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreModel", []];
        private _i = _model findIf { (_x get "uid") isEqualTo _uid };
        private _card = _t get "card";
        if (_i < 0) exitWith { _card ctrlSetStructuredText parseText (("<t size='0.85' color='#8a9a93'>Choisissez un joueur dans la liste, ou sélectionnez-le dans Zeus. Double-clic : caméra sur le joueur.</t>") call _trZ); };
        private _m = _model select _i;
        private _u = _m get "u";
        private _tr = _trZ;
        private _yes = { [("non") call _tr, ("oui") call _tr] select _this };
        private _row = _m get "row";
        private _fresh = _m get "fresh";
        (_m get "pub") params [["_bat", -1], ["_bars", -1]];
        private _L = [format ["<t size='1' font='RobotoCondensedBold'>%1</t> <t size='0.8' color='#8a9a93'>%2 · %3</t>", (_m get "name") call _esc, _m get "cs", groupId group _u]];
        private _sec = { _L pushBack format ["<t size='0.85' font='RobotoCondensedBold' color='#7fd08a'>%1</t>", _this call _tr]; };
        private _line = { params ["_k", "_v"]; _L pushBack format ["<t size='0.82' color='#8a9a93'>%1 : </t><t size='0.82'>%2</t>", _k call _tr, _v]; };
        if ((_m get "alerts") isNotEqualTo []) then { _L pushBack format ["<t size='0.9' color='#f25a48'>%1</t>", ((_m get "alerts") apply { _x call _tr }) joinString " · "]; };
        private _age = _m get "age";
        _L pushBack format ["<t size='0.78' color='#8a9a93'>%1</t>", switch (true) do {
            case (_age < 0): { ("Relevé jamais reçu : joueur sans le mod, ou réponse en route.") call _tr };
            case (_fresh): { format [("Relevé reçu %1") call _tr, _age call _ago] };
            default { format [("Relevé ancien (%1), redemandé") call _tr, _age call _ago] };
        }];
        "TÉLÉPHONE" call _sec;
        if (_fresh) then {
            ["Possédé", (_row select 1) call _yes] call _line;
            ["Écran", (["rangé", "porté", "en main"] select ((_row select 2) max 0 min 2)) call _tr] call _line;
            ["Batterie", format ["%1 %%", _row select 4]] call _line;
            ["Dégâts", format ["%1 %%", _row select 3]] call _line;
        } else {
            if (_bat isEqualType 0 && {_bat >= 0}) then { ["Batterie", format ["%1 %%", _bat]] call _line; };
        };
        if ((_m get "dev") isNotEqualTo "") then {
            ["État", ((createHashMapFromArray [["OK", "allumé"], ["CRACKED", "allumé, écran fêlé"], ["OFF", "éteint"], ["BROKEN", "détruit"]]) getOrDefault [_m get "dev", _m get "dev"]) call _tr] call _line;
        };
        if ((_u getVariable ["COMSPEC_ATAK_Airplane", false]) isEqualTo true) then { ["Mode", ("avion") call _tr] call _line; };
        private _fx = (_u getVariable ["COMSPEC_ATAK_ZeusFx", []]) apply { _x param [0, ""] };
        if (_fx isNotEqualTo []) then { ["Effets Zeus", _fx joinString ", "] call _line; };
        if (_fresh) then {
            "ATHENA" call _sec;
            ["Liaison", ((createHashMapFromArray [
                ["connected", "connecté"], ["degraded", "connecté, liaison dégradée"], ["channel", "connecté, sans canal"], ["connecting", "connexion en cours"], ["syncing", "synchronisation"],
                ["code", "code en attente"], ["unlinked", "compte non relié"], ["disconnected", "hors liaison"], ["off", "service coupé"], ["battleye", "bloqué par BattlEye"],
                ["maintenance", "maintenance"], ["error", "erreur"], ["unset", "non configuré"]
            ]) getOrDefault [_row select 5, _row select 5]) call _tr] call _line;
            if ((_row param [13, ""]) isNotEqualTo "") then { ["Compte", (_row select 13) call _esc] call _line; };
            private _refV = call _refVer;
            private _vt = switch (_m get "verCmp") do {
                case (-1): { format ["<t color='#f2ab33'>%1</t>", format [("pas à jour (serveur %1)") call _tr, _refV joinString "."]] };
                case 0: { format ["<t color='#7fd08a'>%1</t>", ("à jour") call _tr] };
                case 1: { ("plus récent que le serveur") call _tr };
                default { ("version inconnue") call _tr };
            };
            ["Mod", format ["%1 · %2", (["?", _row select 7] select ((_row select 7) isNotEqualTo "")) call _esc, _vt]] call _line;
            private _lk = _row select 8;
            private _lkExp = missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""];
            ["Link", format ["%1%2", (["?", _lk] select (_lk isNotEqualTo "")) call _esc, ["", format [" · <t color='#f2ab33'>%1</t>", format [("attendu %1") call _tr, _lkExp]]] select (_lk isNotEqualTo "" && {_lkExp isNotEqualTo ""} && {((_lk call _verOf) isNotEqualTo (_lkExp call _verOf))})]] call _line;
        };
        "RÉSEAU" call _sec;
        if (_bars isEqualType 0 && {_bars >= 0}) then { ["Signal", format ["%1", _bars]] call _line; };
        (_u getVariable ["COMSPEC_ATAK_NetUp", []]) params [["_kb", -1], ["_loss", -1], ["_via", ""]];
        if (_kb isEqualType 0 && {_kb >= 0}) then { ["Liaison montante", format ["%1 kbit/s, %2 %% de pertes", _kb, _loss]] call _line; };
        if (_via isEqualType "" && {_via isNotEqualTo ""}) then { ["Passerelle", _via call _esc] call _line; };
        private _node = _m get "node";
        if (_node isNotEqualTo []) then {
            ["Nœud du maillage", format ["%1, %2 m%3", _node select 0, _node select 1, [("") , format [", %1", ("écoute seule") call _tr]] select ((_node select 5) isEqualTo 0)]] call _line;
        };
        private _mpu = _m get "mpu";
        private _carry = [];
        if (_mpu isNotEqualTo []) then { _carry pushBack format [("%1 (%2 saut(s))") call _tr, (_mpu select 3) call _esc, _mpu select 1]; };
        if (_m get "dropped") then { _carry pushBack (("radio posée au sol") call _tr); };
        if ((_m get "gw") isNotEqualTo []) then { _carry pushBack format [("passerelle de véhicule : %1") call _tr, ((_m get "gw") param [2, ""]) call _esc]; };
        private _kits = { _x in ["COMSPEC_ATAK_SatAntennaKit", "COMSPEC_ATAK_MastAntennaKit"] } count (items _u);
        if (_kits > 0) then { _carry pushBack format [("%1 kit(s) d'antenne") call _tr, _kits]; };
        if (_carry isNotEqualTo []) then { ["Relais portés", _carry joinString " · "] call _line; };
        "ÉMISSION" call _sec;
        private _spk = _m get "spk";
        if (_spk isNotEqualTo []) then {
            private _title = _spk param [2, ""];
            if !(_title isEqualType "") then { _title = ""; };
            ["Haut-parleur", format ["%1 (%2 %%)", [("sans titre") call _tr, (_title select [0, 60]) call _esc] select (_title isNotEqualTo ""), _spk param [4, 0]]] call _line;
        } else { ["Haut-parleur", ("non") call _tr] call _line; };
        private _voice = ["voice", _u] call comspec_atak_native_fnc_meshRead;
        if (_voice isNotEqualTo []) then { ["Voix relayée", format [("%1 (%2 saut(s))") call _tr, (_voice select 6) call _esc, _voice select 5]] call _line; };
        if (_fresh && {_row select 10}) then { ["Radio", ("en émission au dernier relevé") call _tr] call _line; };
        private _use = [];
        if ((_m get "pilot") isNotEqualTo "") then { _use pushBack format [("pilote : %1") call _tr, (_m get "pilot") call _esc]; };
        if ((_m get "drones") > 0) then { _use pushBack format [("%1 drone(s) appairé(s)") call _tr, _m get "drones"]; };
        if ((_m get "cams") > 0) then { _use pushBack format [("%1 caméra(s) posée(s)") call _tr, _m get "cams"]; };
        if (_fresh && {(_row select 9) isNotEqualTo ""}) then {
            _use pushBack (((createHashMapFromArray [["drone", "regarde la vue d'un drone"], ["livecam", "regarde une caméra"], ["photo", "appareil photo ouvert"]]) getOrDefault [_row select 9, _row select 9]) call _tr);
        };
        if (_use isNotEqualTo []) then {
            "DRONE ET CAMÉRA" call _sec;
            { _L pushBack format ["<t size='0.82'>%1</t>", _x]; } forEach _use;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusCentreCardText", _L joinString "<br/>"];
        _card ctrlSetStructuredText parseText (_L joinString "<br/>");
        private _pos = ctrlPosition _card;
        _pos set [3, (ctrlTextHeight _card) max ((ctrlPosition (_t get "grp")) select 3)];
        _card ctrlSetPosition _pos;
        _card ctrlCommit 0;
    };
    case "server": {
        private _t = call _ui;
        if ((count _t) isEqualTo 0) exitWith {};
        private _txt = _t get "srvTxt";
        private _rec = missionNamespace getVariable ["COMSPEC_ATAK_ZeusSrv", []];
        private _L = [];
        private _plain = [];
        private _sec = { _L pushBack format ["<t size='0.85' font='RobotoCondensedBold' color='#7fd08a'>%1</t>", _this call _trZ]; _plain pushBack format ["== %1", _this call _trZ]; };
        private _line = {
            params ["_k", "_v", ["_col", ""]];
            _L pushBack format ["<t size='0.82' color='#8a9a93'>%1 : </t><t size='0.82'%3>%2</t>", _k call _trZ, _v, ["", format [" color='%1'", _col]] select (_col isNotEqualTo "")];
            _plain pushBack format ["%1 : %2", _k call _trZ, _v];
        };
        private _yn = { [("non") call _trZ, ("oui") call _trZ] select (_this isEqualTo true) };
        if (_rec isEqualTo []) then {
            _L pushBack format ["<t size='0.85' color='#8a9a93'>%1</t>", (["État du serveur demandé…", "Relevé refusé par le serveur."] select (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateDenied", false])) call _trZ];
        } else {
            _rec params ["_s", "_at"];
            private _age = round (diag_tickTime - _at);
            private _up = _s getOrDefault ["uptime", 0];
            _L pushBack format ["<t size='1' font='RobotoCondensedBold'>%1</t> <t size='0.78' color='#8a9a93'>%2 · %3</t>", (_s getOrDefault ["mission", ""]) call _esc, (_s getOrDefault ["world", ""]) call _esc, ([("hôte"), ("serveur dédié")] select ((_s getOrDefault ["dedicated", false]) isEqualTo true)) call _trZ];
            _plain pushBack format ["%1 · %2", _s getOrDefault ["mission", ""], _s getOrDefault ["world", ""]];
            _L pushBack format ["<t size='0.78' color='#8a9a93'>%1</t>", format [("État reçu %1") call _trZ, _age call _ago]];
            "SERVEUR" call _sec;
            ["Mission en cours depuis", format ["%1 h %2 min", floor (_up / 3600), floor ((_up % 3600) / 60)]] call _line;
            private _fps = _s getOrDefault ["fps", 0];
            ["FPS serveur", format ["%1 (min %2)", _fps, _s getOrDefault ["fpsMin", 0]], ["", "#f2ab33"] select (_fps < 20)] call _line;
            ["Joueurs", format ["%1 · %2", _s getOrDefault ["players", 0], format [("%1 client(s) headless") call _trZ, _s getOrDefault ["hc", 0]]]] call _line;
            ["IA", format ["%1 · %2 · %3", format [("%1 unité(s)") call _trZ, _s getOrDefault ["ai", 0]], format [("%1 groupe(s)") call _trZ, _s getOrDefault ["groups", 0]], format [("%1 véhicule(s)") call _trZ, _s getOrDefault ["vehicles", 0]]]] call _line;
            ["Morts au sol", str (_s getOrDefault ["dead", 0])] call _line;
            "VERSIONS CHARGÉES SUR LE SERVEUR" call _sec;
            { [_x select 0, (_x select 1) call _esc] call _line; } forEach (_s getOrDefault ["mods", []]);
            private _mine = (missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]) call _verOf;
            private _srvV = (_s getOrDefault ["atakVersion", ""]) call _verOf;
            if (_mine isNotEqualTo [] && {_srvV isNotEqualTo []} && {_mine isNotEqualTo _srvV}) then { ["Votre mod", format [("%1, différent du serveur") call _trZ, _mine joinString "."], "#f2ab33"] call _line; };
            "LIAISON ATHENA DU SERVEUR" call _sec;
            private _a = createHashMapFromArray (_s getOrDefault ["athena", []]);
            private _mode = _a getOrDefault ["mode", "absent"];
            ["Service", ((createHashMapFromArray [["on", "Athena"], ["off", "local seulement (réglage serveur)"], ["absent", "COMSPEC Overwatch absent"]]) get _mode) call _trZ] call _line;
            if (_mode isEqualTo "on") then {
                ["Session", (_a getOrDefault ["auth", "—"]) call _esc] call _line;
                private _lk = _a getOrDefault ["link", "—"];
                ["Liaison", _lk call _esc, ["#f2ab33", ""] select (_lk isEqualTo "linked")] call _line;
                ["Prêt (identité reçue)", (_a getOrDefault ["ready", false]) call _yn] call _line;
                private _okAge = _a getOrDefault ["okAge", -1];
                ["Dernière réponse d'Athena", [("jamais") call _trZ, _okAge call _ago] select (_okAge >= 0), ["#f2ab33", ""] select (_okAge >= 0 && {_okAge < 900})] call _line;
                ["Hub serveur actif", (_a getOrDefault ["hub", false]) call _yn] call _line;
                ["File d'envoi", str (_a getOrDefault ["queue", 0])] call _line;
                if ((_a getOrDefault ["uplinkOff", false]) isEqualTo true) then { ["Envois", ("coupés (liaison désactivée sur le serveur)") call _trZ, "#f25a48"] call _line; };
                if ((_a getOrDefault ["detail", ""]) isNotEqualTo "") then { ["Détail", (_a getOrDefault ["detail", ""]) call _esc] call _line; };
                if ((_a getOrDefault ["err", ""]) in ["", "0"]) then {} else { ["Dernière erreur DLL", (_a getOrDefault ["err", ""]) call _esc, "#f2ab33"] call _line; };
            };
            "PAQUET D'IDENTITÉ (SSE)" call _sec;
            private _id = _s getOrDefault ["identity", []];
            if (_id isEqualTo []) then {
                _L pushBack format ["<t size='0.82' color='#8a9a93'>%1</t>", ("Addon identité du SSE absent du serveur.") call _trZ];
            } else {
                private _m = createHashMapFromArray _id;
                ["DLL d'identité", (_m getOrDefault ["dll", false]) call _yn] call _line;
                private _pa = _m getOrDefault ["packAt", ""];
                ["Paquet reçu", [format [("non (%1 tentative(s))") call _trZ, _m getOrDefault ["tries", 0]], format [("oui, généré le %1") call _trZ, _pa call _esc]] select (_pa isNotEqualTo ""), ["#f2ab33", ""] select (_pa isNotEqualTo "")] call _line;
                ["Contenu", format ["%1 · %2 · %3 · %4", format [("%1 document(s)") call _trZ, _m getOrDefault ["docs", 0]], format [("%1 pays") call _trZ, _m getOrDefault ["countries", 0]], format [("%1 identité(s) préparée(s)") call _trZ, _m getOrDefault ["population", 0]], format [("%1 avis") call _trZ, _m getOrDefault ["watch", 0]]]] call _line;
            };
            "MAILLAGE ET RELAIS" call _sec;
            private _me = createHashMapFromArray (_s getOrDefault ["mesh", []]);
            ["Liaison réaliste", (_me getOrDefault ["on", false]) call _yn] call _line;
            if ((_me getOrDefault ["on", false]) isEqualTo true) then {
                ["Graphe du serveur", format [("n° %1") call _trZ, _me getOrDefault ["seq", -1]]] call _line;
                { [_x select 0, format ["%1 · %2 · %3", format [("%1 nœud(s)") call _trZ, _x select 1], format [("%1 lien(s)") call _trZ, _x select 2], format [("%1 trouvé(s)") call _trZ, _x select 3]]] call _line; } forEach (_me getOrDefault ["sides", []]);
            };
            "ORDRES AUX IA" call _sec;
            private _ord = _s getOrDefault ["orders", []];
            if (_ord isEqualTo []) then { _L pushBack format ["<t size='0.82' color='#8a9a93'>%1</t>", ("Aucun ordre gardé.") call _trZ]; } else { ["Par état", (_ord apply { format ["%1 %2", _x select 1, _x select 0] }) joinString " · "] call _line; };
            "CENTRE ZEUS" call _sec;
            ["Zeus à l'écoute", str (_s getOrDefault ["zeusWatch", 0])] call _line;
            ["Fiches connues", str (_s getOrDefault ["zeusRows", 0])] call _line;
            "DERNIÈRES ALERTES DU JOURNAL" call _sec;
            private _log = _s getOrDefault ["log", []];
            if (_log isEqualTo []) then { _L pushBack format ["<t size='0.82' color='#8a9a93'>%1</t>", ("Aucune erreur ni avertissement COMSPEC sur le serveur.") call _trZ]; };
            {
                _x params ["_ago2", "_lvl", "_mod", "_msg"];
                _L pushBack format ["<t size='0.75' color='%1'>%2 [%3] %4</t> <t size='0.75'>%5</t>", ["#f2ab33", "#f25a48"] select (_lvl isEqualTo "ERROR"), _ago2 call _ago, _lvl, _mod, _msg call _esc];
                _plain pushBack format ["%1 [%2][%3] %4", _ago2 call _ago, _lvl, _mod, _msg];
            } forEach _log;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusSrvText", _plain joinString (toString [13, 10])];
        _txt ctrlSetStructuredText parseText (_L joinString "<br/>");
        private _pos = ctrlPosition _txt;
        _pos set [3, (ctrlTextHeight _txt) max ((ctrlPosition (_t get "srvGrp")) select 3)];
        _txt ctrlSetPosition _pos;
        _txt ctrlCommit 0;
    };
    case "targets": {
        // Joueurs visés par une action : selon la cible choisie (joueur choisi, sélection Zeus, joueurs affichés).
        private _t = call _ui;
        private _mode = if ((count _t) isEqualTo 0) then { "sel" } else { private _c = _t get "tgt"; _c lbData (lbCurSel _c) };
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        switch (_mode) do {
            case "zeus": { ["players", curatorSelected param [0, []]] call comspec_atak_native_fnc_zeusMenu };
            case "list": {
                private _ids = (uiNamespace getVariable ["COMSPEC_ATAK_ZeusCentreLines", []]) apply { _x select 1 };
                allPlayers select { (getPlayerUID _x) in _ids }
            };
            default { allPlayers select { (getPlayerUID _x) isEqualTo _uid } };
        }
    };
    case "act": {
        private _units = ["targets"] call comspec_atak_native_fnc_zeusCentre;
        if (_units isEqualTo []) exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        ["apply", [_arg param [0, ""], _units]] call comspec_atak_native_fnc_zeusMenu;
    };
    case "pin": {
        private _units = ["targets"] call comspec_atak_native_fnc_zeusCentre;
        if (_units isEqualTo []) exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        { ["server", ["phonePin", [getPlayerUID _x]]] call comspec_atak_native_fnc_adminClient; } forEach _units;
        ["feedback", [format [("Demande envoyée au serveur : code retiré (%1 joueur(s)).") call _trZ, count _units]]] call comspec_atak_native_fnc_zeusMenu;
    };
    case "camera": {
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        private _u = (allPlayers select { (getPlayerUID _x) isEqualTo _uid }) param [0, objNull];
        if (isNull _u || {isNull curatorCamera}) exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        private _p = getPosASLVisual (vehicle _u);
        private _d = getDir (vehicle _u);
        private _cp = _p vectorAdd [-12 * sin _d, -12 * cos _d, 7];
        curatorCamera setPosASL _cp;
        curatorCamera setVectorDirAndUp [(_p vectorAdd [0, 0, 1]) vectorDiff _cp, [0, 0, 1]];
    };
    case "admin": {
        private _uid = missionNamespace getVariable ["COMSPEC_ATAK_ZeusCentreSel", ""];
        if (_uid isEqualTo "") exitWith { ["feedback", ["ATAK : aucun joueur visé."]] call comspec_atak_native_fnc_zeusMenu; };
        if (isNull ([] call comspec_atak_native_fnc_display)) then { [true] call comspec_atak_native_fnc_open; };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["admTab", "GAME"];
        _s set ["admOpen", "P" + _uid];
        ["ADMIN"] call comspec_atak_native_fnc_navigate;
    };
    case "tips": {
        // Infobulles de la liste des modules : par texte affiché, ZEN n'en propose pas et la config ne s'y affiche pas.
        private _d = DISP;
        if (isNull _d) exitWith {};
        private _tips = createHashMap;
        {
            _x params ["_k", "_tip"];
            private _cfg = configFile >> "CfgVehicles" >> _k;
            _tips set [if (isClass _cfg) then { getText (_cfg >> "displayName") } else { _k call comspec_atak_native_fnc_tr }, _tip call comspec_atak_native_fnc_tr];
        } forEach (["tipsTable"] call comspec_atak_native_fnc_zeusCentre);
        {
            private _tv = _x;
            if (ctrlType _tv isEqualTo 12) then {
                for "_c" from 0 to ((_tv tvCount []) - 1) do {
                    if (((_tv tvText [_c]) find "COMSPEC · ") isEqualTo 0) then {
                        for "_m" from 0 to ((_tv tvCount [_c]) - 1) do {
                            private _tip = _tips getOrDefault [_tv tvText [_c, _m], ""];
                            if (_tip isNotEqualTo "") then { _tv tvSetTooltip [[_c, _m], _tip]; };
                        };
                    };
                };
            };
        } forEach (allControls _d);
    };
    case "tipsTable": {
        [
            ["COMSPEC_ATAK_Module_ZeusControl", "Applique un effet au téléphone. À poser sur un joueur ou au sol (rayon)."],
            ["COMSPEC_ATAK_Module_ZeusJammer", "Brouille le réseau autour du module. À poser au sol."],
            ["COMSPEC_ATAK_Module_Relay", "Crée un relais du réseau. À poser au sol ou sur un objet."],
            ["COMSPEC_ATAK_Module_Screen", "Fait d'un objet un écran de briefing. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_TocScreen", "Fait d'un objet un écran du poste TOC. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_AiAtak", "Donne ou retire l'ATAK à des IA. À poser sur une IA ou un groupe."],
            ["COMSPEC_ATAK_Module_DatingProfile", "Rend une IA visible dans Tinder. À poser sur l'IA."],
            ["COMSPEC_ATAK_Module_DataNode", "Rend un objet extractible au câble. À poser sur l'objet."],
            ["COMSPEC_ATAK_Module_DataFile", "Ajoute un fichier à extraire. À poser sur l'objet déjà préparé."],
            ["COMSPEC_ATAK_Module_DataReset", "Remet les données de l'objet à zéro. À poser sur l'objet."],
            ["COMSPEC_ModuleScanRelays", "Liste les relais de la carte."],
            ["COMSPEC_ModuleResyncRelay", "Republie l'état d'un relais. À poser sur le relais."],
            ["COMSPEC_SSE_Module_GenerateData", "Crée la fiche SSE d'une unité. À poser sur l'unité."],
            ["COMSPEC_SSE_Module_GenerateSite", "Génère un site et ses preuves. À poser au sol."],
            ["COMSPEC_SSE_Module_InitTarget", "Rend un objet ou un corps fouillable. À poser dessus."],
            ["COMSPEC_SSE_Module_PlaceEvidence", "Pose une preuve. À poser au sol."],
            ["COMSPEC_SSE_Module_ApplyModel", "Applique un modèle enregistré. À poser sur la cible."],
            ["COMSPEC_SSE_Module_SaveModel", "Enregistre la cible comme modèle. À poser sur la cible."],
            ["COMSPEC_SSE_Module_SandboxSite", "Pose un site d'exercice. À poser au sol."],
            ["COMSPEC_SSE_Module_LinkEntities", "Relie deux entités SSE. Sélectionnez-les d'abord."],
            ["COMSPEC_SSE_Module_ZeusControl", "Ouvre le contrôle Zeus de l'entité. À poser dessus."],
            ["COMSPEC_SSE_Module_ViewData", "Affiche les données d'une entité. À poser dessus."],
            ["COMSPEC_SSE_Module_SpoilView", "Compare ce que savent les joueurs et la vérité. À poser sur l'entité."],
            ["COMSPEC_SSE_Module_SiteManager", "Liste et pilote les sites."],
            ["COMSPEC_SSE_Module_AfterAction", "Ouvre le compte rendu et le graphe."],
            ["COMSPEC_SSE_Module_ListModels", "Liste les modèles enregistrés."],
            ["COMSPEC_SSE_Module_ClearData", "Efface les données SSE de la cible. À poser dessus."],
            ["COMSPEC_SSE_Module_ResetSite", "Remet un site à zéro. À poser sur le site."],
            ["COMSPEC_SSE_Module_DebugInspector", "Détail technique de l'entité. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexMark", "Rend un appareil exploitable. À poser sur l'objet."],
            ["COMSPEC_SSE_Module_DomexAddIntel", "Ajoute un renseignement à un appareil. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexSetStage", "Règle le palier d'accès d'un appareil. À poser dessus."],
            ["COMSPEC_SSE_Module_DomexMapPoint", "Ajoute un point carte au renseignement. À poser sur l'objet."],
            ["COMSPEC_SSE_Module_PlaceSite", "Pose un site préparé. À poser au sol."],
            ["COMSPEC_SSE_Module_PopulateBuilding", "Remplit un bâtiment. À poser sur le bâtiment."],
            ["COMSPEC_SSE_Module_SeedEvidence", "Répartit des preuves. À poser au sol."],
            ["COMSPEC_SSE_Module_SiteNetwork", "Crée un réseau de sites. À poser au sol."],
            ["COMSPEC_SSE_Module_PlaceKit", "Pose une caisse de matériel. À poser au sol."],
            ["COMSPEC_SSE_Module_GenerateBrief", "Génère depuis un scénario. À poser au sol."],
            ["COMSPEC_SSE_Module_ScenarioDirector", "Applique un jeu de données ou un niveau. À poser au sol."],
            ["COMSPEC_SSE_Module_CaseReference", "Rattache la partie à un dossier. À poser au sol."],
            ["Capturer la carte", "Crée les tuiles de la carte autour du point."],
            ["Affecter à une organisation", "Sélectionnez des unités."],
            ["Générer un réseau", "Sélectionnez au moins deux unités."],
            ["Rattacher des objets", "Sélectionnez des objets."],
            ["Révéler une organisation", "La montre aux joueurs. Sélectionnez un membre."],
            ["Retirer d'une organisation", "Sélectionnez des unités."],
            ["Ouvrir le tableau des liens", "Vue Zeus, rien à sélectionner."],
            ["Altérer un scellé (exercice)", "Sélectionnez la pièce scellée."],
            ["Charger une campagne", "Charge ou crée la campagne."],
            ["Sauvegarder la campagne", "Sauvegarde maintenant."],
            ["Voir l'état de la campagne", "Affiche l'état et le journal."],
            ["Remettre la campagne à zéro", "Remet à zéro pour un exercice."],
            ["Clore la mission", "Sauvegarde ou vide à la fin."],
            ["Rendre extractible au câble", "Sélectionnez les objets."],
            ["Ouvrir le manuel", "Manuel du Zeus."],
            ["Poser un exercice", "Pose un exercice complet au point visé."],
            ["Ouvrir l'éditeur", "Éditeur de renseignement sur le point ou l'objet."],
            ["Donner des papiers", "À poser sur une personne."],
            ["Révoquer des papiers", "À poser sur une personne."],
            ["Voir les vrais papiers", "Réservé au Zeus. À poser sur une personne."],
            ["Poser un checkpoint IA", "À poser sur le point à contrôler."],
            ["Retirer un checkpoint IA", "À poser sur le checkpoint."],
            ["Déclencher un contrôle", "À poser sur la personne à contrôler."],
            ["Forcer un état IA", "À poser sur une IA."],
            ["Imposer un couvre-feu", "Règle le couvre-feu."],
            ["Donner une couverture", "À poser sur une personne."],
            ["Poser une zone interdite", "À poser au centre de la zone."],
            ["Désigner une HVT", "À poser sur une IA."],
            ["Lancer un BOLO", "À poser sur une personne, ou au sol."],
            ["Lever un BOLO", "Retire une alerte de la liste."],
            ["Définir l'identité", "À poser sur une personne."],
            ["Régler la biométrie", "À poser sur une personne."],
            ["Doter d'un terminal SEEK", "À poser sur un joueur."],
            ["Régler la présentation des documents", "Style des documents de la partie."],
            ["Poser une zone sans couverture", "À poser au centre de la zone."],
            ["Poser une zone d'interférence", "À poser au centre de la zone."],
            ["Poser une zone dégradée", "À poser au centre de la zone."],
            ["Poser un émetteur RF", "À poser au sol."],
            ["Relever la carte du théâtre", "Prépare la carte du site. Rien à sélectionner."],
            ["Relever routes et villes", "Prépare les routes et villes du site. Rien à sélectionner."],
            ["Poser une balise GPS", "À poser sur un véhicule."],
            ["Localiser un téléphone", "À poser sur une personne."],
            ["Ajouter une IA alliée à l'ATAK", "À poser sur une IA."],
            ["Afficher les IA ennemies", "Montre ou masque les IA ennemies sur l'ATAK."]
        ]
    };
};
