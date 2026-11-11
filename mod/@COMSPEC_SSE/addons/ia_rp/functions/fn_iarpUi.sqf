/*
    Terminal « Parler à… » (client) : dialog COMSPEC_SSE_IarpTerminal (dialogs\iarp_terminal.hpp).
      open [ia | objNull, onglet]   ouvre sur une IA vivante à 5 m (à pied) ; objNull + "log" : journal seul (Zeus).
                                    Le téléphone ATAK ouvert est rangé (la fenêtre ne se pose pas dessus).
      onLoad / onUnload             brouillon gardé par IA (Échap ferme sans rien perdre)
      head                          en-tête : avatar (initiales), nom (identité unique une fois apprise, sinon « Inconnu » :
                                    même règle que les bulles, fn_iarpName), état et distance
      tab [talk | guide | log]      DISCUSSION (fil), GUIDE (ce qu'on peut dire, exemples cliquables), JOURNAL
      refresh                       fil en bulles : moi à droite, l'IA à gauche, actions et récits au centre, « … écrit » ;
                                    texte aligné à gauche dans sa bulle (jamais rogné), didascalie « (Il hoche la tête.) » sur
                                    sa propre ligne, en gris
      hint                          ligne d'aide sous la saisie : astuce GUIDE après deux messages incompris de suite
      theme [index] · example [texte]   guide : thème choisi ; un exemple cliqué va dans le champ de saisie
      actions · actionPick [index] · action [id]   menu « Faire une action » (lexique « _actions ») : geste envoyé comme un
                                    /me compris par l'IA, ligne « Vous levez lentement les mains. » dans le fil
      key [contrôle, touche, maj, ctrl, alt] -> vrai si traitée : Entrée envoie (Maj+Entrée : nouvelle ligne),
                                    flèches haut / bas : messages déjà envoyés
      keyUp                         compteur de caractères, indicateur d'écriture (une fois par 2,5 s au plus)
      prefix [me | do | try | todo] commande RP en tête du message (compatibilité)     send   envoie (0,9 s entre deux)
      refreshLog · copy · filter (cette IA / toutes) · tick (distance, humeur toutes les 2,5 s)
    Commandes tapées à la main (guide, thème « rp ») : /me (geste, compris par l'IA), /do (fait constaté, compris comme un
    geste), /try (jet de réussite partagé : le serveur tire RÉUSSI ou ÉCHEC, le même pour tous), /todo (parole et geste :
    « Vos papiers. *tend la main », la parole est comprise).
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_iarpDisplay", displayNull];
private _drafts = missionNamespace getVariable ["comspec_sse_iarpDrafts", createHashMap];
missionNamespace setVariable ["comspec_sse_iarpDrafts", _drafts];
private _sent = missionNamespace getVariable ["comspec_sse_iarpSent", []];
missionNamespace setVariable ["comspec_sse_iarpSent", _sent];
private _targetObj = { objectFromNetId (uiNamespace getVariable ["comspec_sse_iarpTarget", ""]) };
private _maxLen = round (missionNamespace getVariable ["comspec_sse_iarp_maxLen", 600]);
private _fr = language isEqualTo "French";
private _barlow = isClass (configFile >> "CfgFontFamilies" >> "COMSPEC_Barlow");
private _fReg = ["RobotoCondensed", "COMSPEC_Barlow"] select _barlow;
private _fBold = ["RobotoCondensedBold", "COMSPEC_BarlowBold"] select _barlow;
private _fLight = ["RobotoCondensedLight", "COMSPEC_BarlowLight"] select _barlow;
private _esc = {
    private _s = [_this, "&", "&amp;"] call CBA_fnc_replace;
    _s = [_s, "<", "&lt;"] call CBA_fnc_replace;
    [_s, ">", "&gt;"] call CBA_fnc_replace
};
private _hex = {
    params ["_c"];
    private _h = "0123456789ABCDEF";
    "#" + (((_c select [0, 3]) apply { private _v = round (((_x max 0) min 1) * 255); (_h select [floor (_v / 16), 1]) + (_h select [_v mod 16, 1]) }) joinString "")
};
private _rgbOf = {
    params ["_kind"];
    private _c = switch (_kind) do {
        case "me"; case "act": { missionNamespace getVariable ["comspec_sse_iarp_colMe", [0.8, 0.62, 1]] };
        case "do": { missionNamespace getVariable ["comspec_sse_iarp_colDo", [0.55, 0.8, 1]] };
        case "todo": { missionNamespace getVariable ["comspec_sse_iarp_colTodo", [1, 0.75, 0.35]] };
        case "try": { missionNamespace getVariable ["comspec_sse_iarp_colTry", [0.5, 0.95, 0.55]] };
        case "tryOk": { [0.5, 0.95, 0.55] };
        case "tryKo": { [1, 0.45, 0.4] };
        case "ai": { missionNamespace getVariable ["comspec_sse_iarp_colAi", [0.62, 0.9, 1]] };
        case "sys": { [0.62, 0.68, 0.64] };
        default { missionNamespace getVariable ["comspec_sse_iarp_colSay", [0.97, 0.98, 0.95]] };
    };
    [_c param [0, 1], _c param [1, 1], _c param [2, 1], 1]
};
private _colOf = { [_this call _rgbOf] call _hex };
// Humeur : libellé et couleur (en-tête, avatar).
private _moodCol = {
    params ["_k"];
    switch (_k) do {
        case "calme": { [0.45, 0.8, 0.5] };
        case "attentif"; case "coopere": { [0.45, 0.78, 0.74] };
        case "tendu"; case "refuse"; case "ment": { [0.95, 0.72, 0.28] };
        case "nerveux": { [1, 0.55, 0.25] };
        case "fuit"; case "agressif"; case "renforts": { [0.9, 0.32, 0.27] };
        default { [0.55, 0.66, 0.85] };
    }
};
// Commande RP en tête du texte : ["me", "lève les mains"] ; sans commande : ["", texte].
private _split = {
    params ["_t"];
    private _l = toLower _t;
    private _r = ["", _t];
    {
        private _p = "/" + _x + " ";
        if ((_l select [0, count _p]) isEqualTo _p) exitWith { _r = [_x, trim (_t select [count _p])]; };
    } forEach ["todo", "try", "me", "do"];
    _r
};
private _grid = {
    private _wabs = (safezoneW / safezoneH) min 1.2;
    [_wabs / 40, _wabs / 1.2 / 25]
};
private _actionsList = {
    private _l = [];
    { if ((_x param [0, ""]) isEqualTo "_actions") then { _l append (_x param [2, []]); }; } forEach (["lexicon"] call comspec_sse_fnc_iarpData);
    _l
};

switch (_op) do {
    case "open": {
        _a params [["_t", objNull, [objNull]], ["_tab", "talk", [""]]];
        if !(missionNamespace getVariable ["comspec_sse_iarp_enabled", true]) exitWith { false };
        if (_tab isNotEqualTo "log") then {
            if (isNull _t || {!alive _t} || {isPlayer _t} || {!(_t isKindOf "CAManBase")}) exitWith { _t = objNull; };
            if ((player distance _t) > 5 || {!isNull objectParent player}) exitWith { ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call comspec_sse_fnc_iarpClient; _t = objNull; };
        };
        if (_tab isNotEqualTo "log" && {isNull _t}) exitWith { false };
        // Téléphone ATAK ouvert : rangé (sinon il dépasse sous la fenêtre et prend la souris).
        if (!isNil "comspec_atak_native_fnc_display" && {!isNil "comspec_atak_native_fnc_close"} && {!isNull ([] call comspec_atak_native_fnc_display)}) then { [] call comspec_atak_native_fnc_close; };
        if (!isNull _d) then { closeDialog 0; };
        uiNamespace setVariable ["comspec_sse_iarpTarget", [netId _t, ""] select (isNull _t)];
        uiNamespace setVariable ["comspec_sse_iarpTab", _tab];
        createDialog "COMSPEC_SSE_IarpTerminal";
        true
    };
    case "onLoad": {
        _a params [["_disp", displayNull]];
        uiNamespace setVariable ["comspec_sse_iarpDisplay", _disp];
        _d = _disp;
        private _t = call _targetObj;
        { (_d displayCtrl _x) ctrlSetFont _fBold; } forEach [94601, 94641];
        (_d displayCtrl 94651) ctrlShow false;
        private _e = _d displayCtrl 94620;
        _e ctrlSetText (_drafts getOrDefault [uiNamespace getVariable ["comspec_sse_iarpTarget", ""], ""]);
        uiNamespace setVariable ["comspec_sse_iarpRecall", -1];
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        uiNamespace setVariable ["comspec_sse_iarpAiTyping", [false, 0]];
        uiNamespace setVariable ["comspec_sse_iarpBubbleCtrls", []];
        uiNamespace setVariable ["comspec_sse_iarpGuideCtrls", []];
        uiNamespace setVariable ["comspec_sse_iarpMoodAt", time];
        // Nom (identité SSE assurée par le serveur) et humeur.
        if (!isNull _t) then { ["send", ["hello", [player, _t]]] call comspec_sse_fnc_iarpClient; };
        // Thèmes du guide (tirés du lexique).
        private _lb = _d displayCtrl 94660;
        lbClear _lb;
        {
            _x params ["_th"];
            private _key = format ["STR_COMSPEC_IARP_G_%1_T", _th];
            _lb lbAdd ([_th, localize _key] select (isLocalized _key));
        } forEach (["guide"] call comspec_sse_fnc_iarpData);
        // Menu « Faire une action ».
        private _al = _d displayCtrl 94651;
        lbClear _al;
        { _al lbAdd ([_x select 2, _x select 1] select _fr); _al lbSetData [(lbSize _al) - 1, _x select 0]; } forEach (call _actionsList);
        _al lbAdd localize "STR_COMSPEC_IARP_ActionFree";
        _al lbSetData [(lbSize _al) - 1, "free"];
        ["head"] call comspec_sse_fnc_iarpUi;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
        ["hint"] call comspec_sse_fnc_iarpUi;
        ["tab", [uiNamespace getVariable ["comspec_sse_iarpTab", "talk"]]] call comspec_sse_fnc_iarpUi;
        ["tick"] call comspec_sse_fnc_iarpUi;
        private _h = [{ ["tick"] call comspec_sse_fnc_iarpUi; }, 0.5] call CBA_fnc_addPerFrameHandler;
        uiNamespace setVariable ["comspec_sse_iarpTick", _h];
        ctrlSetFocus _e;
    };
    case "onUnload": {
        private _net = uiNamespace getVariable ["comspec_sse_iarpTarget", ""];
        if (!isNull _d && {_net isNotEqualTo ""}) then { _drafts set [_net, ctrlText (_d displayCtrl 94620)]; };
        [uiNamespace getVariable ["comspec_sse_iarpTick", -1]] call CBA_fnc_removePerFrameHandler;
        if ((uiNamespace getVariable ["comspec_sse_iarpTypingAt", -10]) > (time - 8)) then { ["send", ["typing", [player, false]]] call comspec_sse_fnc_iarpClient; };
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        uiNamespace setVariable ["comspec_sse_iarpDisplay", displayNull];
        uiNamespace setVariable ["comspec_sse_iarpTarget", ""];
    };
    case "head": {
        if (isNull _d) exitWith {};
        private _t = call _targetObj;
        if (isNull _t) exitWith {
            (_d displayCtrl 94601) ctrlSetText localize "STR_COMSPEC_IARP_LogOnly";
            (_d displayCtrl 94641) ctrlSetText "";
            (_d displayCtrl 94602) ctrlSetStructuredText parseText "";
        };
        private _who = (missionNamespace getVariable ["comspec_sse_iarpWho", createHashMap]) getOrDefault [netId _t, ["", [], 0]];
        // Même règle que les bulles (fn_iarpName) : le nom une fois appris (dit, lu sur ses papiers), sinon « Inconnu » ;
        // le Zeus le voit toujours. Jamais un nom que le joueur ne connaît pas.
        private _name = ["name", [_t]] call comspec_sse_fnc_iarpClient;
        private _known = (["known", [_t, player]] call comspec_sse_fnc_iarpName) && {(_t getVariable ["comspec_sse_idName", ""]) isNotEqualTo ""};
        (_d displayCtrl 94601) ctrlSetText _name;
        // Initiales : prénom et nom (« Nikos KARRAS » -> NK) ; inconnu : « ? ».
        private _ws = (_name splitString " ") select { _x isNotEqualTo "" };
        private _ini = if (!_known) then { "?" } else {
            toUpper (((_ws select [0, 2]) apply { _x select [0, 1] }) joinString "")
        };
        (_d displayCtrl 94641) ctrlSetText _ini;
        (_who param [1, []]) params [["_mk", "calme"], ["_ten", 0]];
        private _mc = [_mk] call _moodCol;
        (_d displayCtrl 94640) ctrlSetBackgroundColor [(_mc select 0) * 0.35, (_mc select 1) * 0.35, (_mc select 2) * 0.35, 1];
        (_d displayCtrl 94641) ctrlSetTextColor (_mc + [1]);
        private _key = format ["STR_COMSPEC_IARP_Mood_%1", _mk];
        private _lab = [_mk, localize _key] select (isLocalized _key);
        private _dist = player distance _t;
        private _ok = alive _t && {_dist <= 6} && {isNull objectParent player};
        (_d displayCtrl 94602) ctrlSetStructuredText parseText format ["<t font='%1' color='%2'>%3</t><t color='#7F8A84'>   ·   %4   ·   %5</t>",
            _fBold, [_mc] call _hex, toUpper (_lab call _esc), format [localize "STR_COMSPEC_IARP_Fear", _ten],
            format [localize (["STR_COMSPEC_IARP_StatusFar", "STR_COMSPEC_IARP_StatusNear"] select _ok), round _dist]];
    };
    case "hint": {
        if (isNull _d) exitWith {};
        private _n = (missionNamespace getVariable ["comspec_sse_iarpMiss", createHashMap]) getOrDefault [uiNamespace getVariable ["comspec_sse_iarpTarget", ""], 0];
        private _c = _d displayCtrl 94627;
        _c ctrlSetText localize (["STR_COMSPEC_IARP_Hint", "STR_COMSPEC_IARP_HintMiss"] select (_n >= 2));
        _c ctrlSetTextColor ([[0.62, 0.68, 0.64, 1], [0.95, 0.72, 0.28, 1]] select (_n >= 2));
    };
    case "tick": {
        if (isNull _d) exitWith {};
        private _t = call _targetObj;
        if (isNull _t) exitWith {};
        private _ok = alive _t && {(player distance _t) <= 6} && {isNull objectParent player};
        (_d displayCtrl 94625) ctrlEnable _ok;
        if ((time - (uiNamespace getVariable ["comspec_sse_iarpMoodAt", 0])) > 2.5) then {
            uiNamespace setVariable ["comspec_sse_iarpMoodAt", time];
            ["send", ["mood", [player, _t]]] call comspec_sse_fnc_iarpClient;
        };
        // « … écrit » : retiré seul après 8 s sans réponse.
        (uiNamespace getVariable ["comspec_sse_iarpAiTyping", [false, 0]]) params ["_on", "_at"];
        if (_on && {(time - _at) > 8}) then { uiNamespace setVariable ["comspec_sse_iarpAiTyping", [false, time]]; ["refresh"] call comspec_sse_fnc_iarpUi; };
        ["head"] call comspec_sse_fnc_iarpUi;
    };
    case "tab": {
        _a params [["_tab", "talk"]];
        if (isNull _d) exitWith {};
        private _noAi = isNull (call _targetObj);
        if (_noAi) then { _tab = "log"; };
        uiNamespace setVariable ["comspec_sse_iarpTab", _tab];
        private _talk = _tab isEqualTo "talk";
        private _guide = _tab isEqualTo "guide";
        private _log = _tab isEqualTo "log";
        { (_d displayCtrl _x) ctrlShow _talk; } forEach [94610];
        { (_d displayCtrl _x) ctrlShow _guide; } forEach [94660, 94661];
        { (_d displayCtrl _x) ctrlShow (_talk || _guide); } forEach [94612, 94620, 94625, 94626, 94627, 94650];
        { (_d displayCtrl _x) ctrlShow _log; } forEach [94630, 94632, 94633, 94634];
        (_d displayCtrl 94612) ctrlShow _talk;
        (_d displayCtrl 94651) ctrlShow false;
        { (_d displayCtrl _x) ctrlEnable !_noAi; } forEach [94603, 94606];
        // Onglet actif : fond vert, comme le téléphone.
        {
            _x params ["_idc", "_on"];
            (_d displayCtrl _idc) ctrlSetBackgroundColor ([[0.16, 0.19, 0.2, 1], [0.15, 0.3, 0.19, 1]] select _on);
        } forEach [[94603, _talk], [94606, _guide], [94604, _log]];
        if (_log) exitWith {
            (_d displayCtrl 94634) ctrlShow !_noAi;
            (_d displayCtrl 94634) ctrlSetText localize (["STR_COMSPEC_IARP_FilterAll", "STR_COMSPEC_IARP_FilterOne"] select (uiNamespace getVariable ["comspec_sse_iarpLogAll", false]));
            ["send", ["log", [player]]] call comspec_sse_fnc_iarpClient;
            ["refreshLog"] call comspec_sse_fnc_iarpUi;
        };
        if (_guide) then {
            private _lb = _d displayCtrl 94660;
            if ((lbCurSel _lb) < 0 && {(lbSize _lb) > 0}) then { _lb lbSetCurSel 0; } else { ["theme", [lbCurSel _lb]] call comspec_sse_fnc_iarpUi; };
        } else { ["refresh"] call comspec_sse_fnc_iarpUi; };
        ctrlSetFocus (_d displayCtrl 94620);
    };
    case "refresh": {
        if (isNull _d) exitWith {};
        private _g = _d displayCtrl 94610;
        { ctrlDelete _x; } forEach (uiNamespace getVariable ["comspec_sse_iarpBubbleCtrls", []]);
        private _made = [];
        (call _grid) params ["_gw", "_gh"];
        private _gp = ctrlPosition _g;
        private _W = (_gp select 2) - 0.012;
        private _maxW = _W * 0.74;
        private _px = 0.45 * _gw;
        private _py = 0.2 * _gh;
        private _probe = _d displayCtrl 94699;
        _probe ctrlSetFont _fReg;
        private _widthOf = { params ["_s", ["_k", 1], ["_f", _fReg]]; _probe ctrlSetFont _f; _probe ctrlSetText _s; (ctrlTextWidth _probe) * _k };
        // Didascalie (« (Il ne bouge pas.) ») : ligne à part, police fine, grise ; la parole garde sa ligne.
        private _stageOf = {
            params ["_s"];
            private _out = [];
            private _rest = _s;
            private _guard = 0;
            while { _rest isNotEqualTo "" && {_guard < 12} } do {
                _guard = _guard + 1;
                private _a1 = _rest find "(";
                private _b1 = if (_a1 < 0) then { -1 } else { (_rest select [_a1]) find ")" };
                if (_a1 < 0 || {_b1 < 0}) exitWith { _out pushBack [false, trim _rest]; _rest = ""; };
                if (_a1 > 0) then { _out pushBack [false, trim (_rest select [0, _a1])]; };
                _out pushBack [true, _rest select [_a1, _b1 + 1]];
                _rest = trim (_rest select [_a1 + _b1 + 1]);
            };
            _out select { (_x select 1) isNotEqualTo "" }
        };
        // Largeur d'une bulle : texte mesuré dans sa police, plus une marge d'au moins un caractère (0,9 case) ; le texte
        // est aligné à gauche dans sa bulle, son premier caractère ne peut pas sortir du cadre.
        private _padW = 0.9 * _gw;
        private _net = uiNamespace getVariable ["comspec_sse_iarpTarget", ""];
        private _l = ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [_net, []]) select [((count ((missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [_net, []])) - 40) max 0];
        private _y = 0.3 * _gh;
        // Bulles de l'IA : le nom montré dans l'en-tête, maintenant (appris depuis : le fil entier passe d'« Inconnu » à
        // son nom) ; en-tête et bulles disent toujours la même chose.
        private _aiNow = if (isNull (call _targetObj)) then { "" } else { ["shown", call _targetObj] call comspec_sse_fnc_iarpName };
        private _add = {
            params ["_side", "_body", "_plainW", "_bg", ["_stripe", []]];
            // _side : left (IA), right (moi), center (action, récit, système)
            private _inner = if (_side isEqualTo "center") then { _W * 0.86 } else { ((_plainW * 1.06 + _padW) min (_maxW - 2 * _px)) max (4 * _gw) };
            private _bx = switch (_side) do { case "right": { _W - _inner - 2 * _px }; case "center": { (_W - _inner) / 2 - _px }; default { 0 } };
            // Fond et liseré d'abord (le texte, créé après, passe par-dessus), hauteur posée une fois le texte mesuré.
            private _b = controlNull;
            private _s = controlNull;
            if (_bg isNotEqualTo []) then {
                _b = _d ctrlCreate ["COMSPEC_SSE_IarpBubbleBg", -1, _g];
                _b ctrlSetBackgroundColor _bg;
                _made pushBack _b;
                if (_stripe isNotEqualTo []) then {
                    _s = _d ctrlCreate ["COMSPEC_SSE_IarpBubbleBg", -1, _g];
                    _s ctrlSetBackgroundColor _stripe;
                    _made pushBack _s;
                };
            };
            private _st = _d ctrlCreate ["COMSPEC_SSE_IarpBubbleText", -1, _g];
            _st ctrlSetStructuredText parseText _body;
            _st ctrlSetPosition [_bx + _px, _y + _py, _inner, _gh];
            _st ctrlCommit 0;
            private _h = (ctrlTextHeight _st) max (0.9 * _gh);
            _st ctrlSetPosition [_bx + _px, _y + _py, _inner, _h];
            _st ctrlCommit 0;
            if (!isNull _b) then { _b ctrlSetPosition [_bx, _y, _inner + 2 * _px, _h + 2 * _py]; _b ctrlCommit 0; };
            if (!isNull _s) then { _s ctrlSetPosition [[_bx, _bx + _inner + 2 * _px - 0.18 * _gw] select (_side isEqualTo "right"), _y, 0.18 * _gw, _h + 2 * _py]; _s ctrlCommit 0; };
            _made pushBack _st;
            _y = _y + _h + 2 * _py + 0.38 * _gh;
        };
        {
            _x params ["_kind", "_who", "_text", "_hm"];
            private _tm = format ["<t size='0.72' color='#7F8A84'>%1</t>", _hm];
            switch (_kind) do {
                case "ai": {
                    if (_aiNow isNotEqualTo "") then { _who = _aiNow; };
                    private _hdr = format ["<t font='%1' size='0.78' color='%2'>%3</t>  %4", _fBold, ["ai"] call _colOf, toUpper (_who call _esc), _tm];
                    private _segs = [_text] call _stageOf;
                    private _bubW = ([toUpper _who, 0.78, _fBold] call _widthOf) + (["  00:00", 0.72] call _widthOf);
                    { _bubW = _bubW max ([_x select 1] call _widthOf); } forEach _segs;
                    private _body = (_segs apply {
                        if (_x select 0) then { format ["<t font='%1' color='#9EAFA6'>%2</t>", _fLight, (_x select 1) call _esc] }
                        else { format ["<t font='%1' color='#E6EDE6'>%2</t>", _fReg, (_x select 1) call _esc] }
                    }) joinString "<br/>";
                    ["left", format ["%1<br/>%2", _hdr, _body], _bubW, [0.1, 0.15, 0.15, 0.98], ["ai"] call _rgbOf] call _add;
                };
                case "say": {
                    // Ma bulle, à droite du fil ; son texte aligné à gauche (un texte aligné à droite plus large que la bulle
                    // perdait sa première lettre : « OI », « uel est ton prénom ? »).
                    private _meT = toUpper (localize "STR_COMSPEC_IARP_Me");
                    private _hdr = format ["<t font='%1' size='0.78' color='#A9D9B0'>%2</t>  %3", _fBold, _meT call _esc, _tm];
                    private _bubW = ([_text] call _widthOf) max (([_meT, 0.78, _fBold] call _widthOf) + (["  00:00", 0.72] call _widthOf));
                    ["right", format ["%1<br/><t font='%2' color='%3'>%4</t>", _hdr, _fReg, ["say"] call _colOf, _text call _esc], _bubW, [0.13, 0.25, 0.16, 0.98], [0.45, 0.8, 0.5, 1]] call _add;
                };
                case "sys": {
                    ["center", format ["<t align='center' font='%1' size='0.82' color='#9EAFA6'>%2</t>", _fLight, _text call _esc], 0, []] call _add;
                };
                default {
                    // Actions et récits : au centre, police fine, couleur de la commande, sans bulle.
                    private _line = switch (_kind) do {
                        case "me": { format [localize "STR_COMSPEC_IARP_MeLine", _text] };
                        case "do": { format ["(%1)", _text] };
                        case "todo": { format ["%1 *", _text] };
                        default { _text };
                    };
                    ["center", format ["<t align='center' font='%1' color='%2'>%3</t>  %4", _fLight, [_kind] call _colOf, _line call _esc, _tm], 0, []] call _add;
                };
            };
        } forEach _l;
        if (_l isEqualTo []) then {
            ["center", format ["<t align='center' color='#9EAFA6'>%1</t>", localize "STR_COMSPEC_IARP_EmptyHistory"], 0, []] call _add;
        };
        // L'IA écrit (réponse en préparation).
        (uiNamespace getVariable ["comspec_sse_iarpAiTyping", [false, 0]]) params ["_on", "_at"];
        if (_on && {(time - _at) < 8}) then {
            private _t = call _targetObj;
            ["center", format ["<t align='left' font='%1' size='0.85' color='#7F8A84'>%2</t>", _fLight, format [localize "STR_COMSPEC_IARP_Typing", (["name", [_t]] call comspec_sse_fnc_iarpClient) call _esc]], 0, []] call _add;
        };
        uiNamespace setVariable ["comspec_sse_iarpBubbleCtrls", _made];
        _g ctrlSetScrollValues [1, -1];
    };
    case "theme": {
        _a params [["_i", 0]];
        if (isNull _d || {_i < 0}) exitWith {};
        private _g = _d displayCtrl 94661;
        { ctrlDelete _x; } forEach (uiNamespace getVariable ["comspec_sse_iarpGuideCtrls", []]);
        private _made = [];
        private _themes = ["guide"] call comspec_sse_fnc_iarpData;
        if (_i >= (count _themes)) exitWith {};
        (_themes select _i) params ["_th", "_entries"];
        (call _grid) params ["_gw", "_gh"];
        private _W = ((ctrlPosition _g) select 2) - 0.012;
        private _tk = format ["STR_COMSPEC_IARP_G_%1_T", _th];
        private _dk = format ["STR_COMSPEC_IARP_G_%1_D", _th];
        private _txt = _d ctrlCreate ["COMSPEC_SSE_IarpBubbleText", -1, _g];
        _txt ctrlSetStructuredText parseText format ["<t font='%1' size='1.15' color='#E6EDE6'>%2</t><br/><t size='0.84' color='#B9C4BD'>%3</t><br/><t size='0.74' color='#7F8A84'>%4</t>",
            _fBold, ([_th, localize _tk] select (isLocalized _tk)) call _esc, ["", localize _dk] select (isLocalized _dk), localize "STR_COMSPEC_IARP_GuideClick"];
        _txt ctrlSetPosition [0.2 * _gw, 0.2 * _gh, _W - 0.4 * _gw, _gh];
        _txt ctrlCommit 0;
        private _h = ctrlTextHeight _txt;
        _txt ctrlSetPosition [0.2 * _gw, 0.2 * _gh, _W - 0.4 * _gw, _h];
        _txt ctrlCommit 0;
        _made pushBack _txt;
        private _y = 0.2 * _gh + _h + 0.4 * _gh;
        {
            _x params ["", ["_ex", []]];
            {
                private _s = [_x param [1, _x select 0], _x select 0] select _fr;
                private _b = _d ctrlCreate ["COMSPEC_SSE_IarpExample", -1, _g];
                _b ctrlSetText ("  " + _s);
                _b ctrlSetTooltip localize "STR_COMSPEC_IARP_GuideClick";
                _b ctrlSetPosition [0.2 * _gw, _y, _W - 0.4 * _gw, 1.05 * _gh];
                _b ctrlCommit 0;
                _b setVariable ["comspec_sse_iarpEx", _s];
                _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; ["example", [_c getVariable ["comspec_sse_iarpEx", ""]]] call comspec_sse_fnc_iarpUi; }];
                _made pushBack _b;
                _y = _y + 1.25 * _gh;
            } forEach _ex;
        } forEach _entries;
        uiNamespace setVariable ["comspec_sse_iarpGuideCtrls", _made];
        _g ctrlSetScrollValues [0, -1];
    };
    case "example": {
        _a params [["_s", ""]];
        if (isNull _d || {_s isEqualTo ""}) exitWith {};
        private _e = _d displayCtrl 94620;
        _e ctrlSetText _s;
        ctrlSetFocus _e;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
    };
    case "actions": {
        if (isNull _d) exitWith {};
        private _al = _d displayCtrl 94651;
        private _show = !(ctrlShown _al);
        _al ctrlShow _show;
        if (_show) then { _al lbSetCurSel -1; ctrlSetFocus _al; } else { ctrlSetFocus (_d displayCtrl 94620); };
    };
    case "actionPick": {
        _a params [["_i", -1]];
        if (isNull _d || {_i < 0}) exitWith {};
        private _al = _d displayCtrl 94651;
        if !(ctrlShown _al) exitWith {};
        private _id = _al lbData _i;
        _al ctrlShow false;
        ["action", [_id]] call comspec_sse_fnc_iarpUi;
    };
    case "action": {
        _a params [["_id", ""]];
        if (isNull _d) exitWith {};
        private _e = _d displayCtrl 94620;
        if (_id isEqualTo "free") exitWith { _e ctrlSetText "/me "; ctrlSetFocus _e; ["keyUp"] call comspec_sse_fnc_iarpUi; };
        private _row = (call _actionsList) select { (_x select 0) isEqualTo _id };
        if (_row isEqualTo []) exitWith {};
        (_row select 0) params ["", "", "", "_meFr", "_meEn", "_shFr", "_shEn"];
        private _t = call _targetObj;
        if (isNull _t || {!(ctrlEnabled (_d displayCtrl 94625))}) exitWith { ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call comspec_sse_fnc_iarpClient; };
        if ((time - (uiNamespace getVariable ["comspec_sse_iarpSentAt", -10])) < 0.9) exitWith {};
        uiNamespace setVariable ["comspec_sse_iarpSentAt", time];
        // Effet réel sur mon arme : baissée (elle ne compte plus comme braquée) ou levée.
        if (_id isEqualTo "lower" && {(currentWeapon player) isNotEqualTo ""} && {!(weaponLowered player)}) then { player action ["WeaponOnBack", player]; };
        if (_id isEqualTo "aim" && {(currentWeapon player) isNotEqualTo ""} && {weaponLowered player}) then { player action ["WeaponInHand", player]; };
        private _hist = missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap];
        private _l = _hist getOrDefault [netId _t, []];
        _l pushBack ["act", "", [_shEn, _shFr] select _fr, [daytime, "HH:MM"] call BIS_fnc_timeToString];
        if ((count _l) > 80) then { _l deleteAt 0; };
        _hist set [netId _t, _l];
        missionNamespace setVariable ["comspec_sse_iarpHist", _hist];
        missionNamespace setVariable ["comspec_sse_iarpLastAction", [_id, time]];
        ["send", ["say", [player, _t, [_meEn, _meFr] select _fr, "me", profileNameSteam]]] call comspec_sse_fnc_iarpClient;
        ["refresh"] call comspec_sse_fnc_iarpUi;
        ctrlSetFocus _e;
    };
    case "filter": {
        uiNamespace setVariable ["comspec_sse_iarpLogAll", !(uiNamespace getVariable ["comspec_sse_iarpLogAll", false])];
        if (!isNull _d) then { (_d displayCtrl 94634) ctrlSetText localize (["STR_COMSPEC_IARP_FilterAll", "STR_COMSPEC_IARP_FilterOne"] select (uiNamespace getVariable ["comspec_sse_iarpLogAll", false])); };
        ["refreshLog"] call comspec_sse_fnc_iarpUi;
    };
    case "refreshLog": {
        if (isNull _d) exitWith {};
        private _rows = missionNamespace getVariable ["comspec_sse_iarpLogRows", []];
        private _net = uiNamespace getVariable ["comspec_sse_iarpTarget", ""];
        // Cette IA seulement (par défaut quand on lui parle) ; « Toutes » : tout le journal reçu.
        if (_net isNotEqualTo "" && {!(uiNamespace getVariable ["comspec_sse_iarpLogAll", false])}) then { _rows = _rows select { (_x param [7, ""]) isEqualTo _net }; };
        private _out = (_rows select [((count _rows) - 200) max 0]) apply {
            _x params ["", "_gt", "_rt", "_dir", "_rp", "_steam", "_ai", "_net2", "_grid", "_dist", "_cmd", "_text", "_intent", "_res"];
            private _aiN = [_ai, localize "STR_COMSPEC_IARP_Anonymous"] select (_ai isEqualTo "");
            private _rpN = _rp + (["", format [" (%1)", _steam]] select (_steam isNotEqualTo ""));
            private _who = switch (_dir) do {
                case "P": { format ["<t font='%1' color='#E6EDE6'>%2</t><t color='#7F8A84'>  →  </t><t color='%3'>%4</t>", _fBold, _rpN call _esc, ["ai"] call _colOf, _aiN call _esc] };
                case "IA>P": { format ["<t font='%1' color='%2'>%3</t><t color='#7F8A84'>  →  </t><t color='#E6EDE6'>%4</t>", _fBold, ["ai"] call _colOf, _aiN call _esc, _rpN call _esc] };
                case "PEUR": { format ["<t font='%1' color='#F2B847'>%2 · %3</t>", _fBold, localize "STR_COMSPEC_IARP_LogFear", _aiN call _esc] };
                default { format ["<t font='%1' color='#FFB84D'>%2</t><t color='#7F8A84'>  →  </t><t color='%3'>%4</t>", _fBold, _dir, ["ai"] call _colOf, _aiN call _esc] };
            };
            private _col = [["say"] call _colOf, ["ai"] call _colOf] select ((_dir select [0, 2]) isEqualTo "IA");
            if (_dir isEqualTo "PEUR") then { _col = "#F2D49B"; };
            format ["<t size='0.72' color='#7F8A84'>%1 · %2 · %3 · %4 m</t><br/>%5<br/><t color='%6'>%7%8</t><br/><t size='0.72' color='#7F8A84'>%9 · %10</t>",
                _gt, _rt, _grid, _dist, _who, _col, ["", "/" + _cmd + " "] select (_cmd isNotEqualTo ""), _text call _esc, _intent call _esc, _res call _esc]
        };
        if (_out isEqualTo []) then { _out = [format ["<t color='#9EAFA6'>%1</t>", localize "STR_COMSPEC_IARP_EmptyLog"]]; };
        private _g = _d displayCtrl 94630;
        private _st = _d displayCtrl 94631;
        _st ctrlSetStructuredText parseText (_out joinString "<br/><br/>");
        _st ctrlSetPosition [0, 0, ((ctrlPosition _g) select 2) - 0.012, (ctrlTextHeight _st) max ((ctrlPosition _g) select 3)];
        _st ctrlCommit 0;
        _g ctrlSetScrollValues [1, -1];
    };
    case "copy": {
        private _rows = missionNamespace getVariable ["comspec_sse_iarpLogRows", []];
        private _nl = toString [13, 10];
        private _txt = (_rows apply {
            _x params ["_id", "_gt", "_rt", "_dir", "_rp", "_steam", "_ai", "_net", "_grid", "_dist", "_cmd", "_text", "_intent", "_res"];
            format ["#%1 | %2 | %3 | %4 | %5 (%6) | %7 (%8) | %9 | %10 m | %11 | %12 | %13 | %14", _id, _gt, _rt, _dir, _rp, _steam, _ai, _net, _grid, _dist, _cmd, _text, _intent, _res]
        }) joinString _nl;
        copyToClipboard _txt;
        ["notice", ["STR_COMSPEC_IARP_N_Copied", count _rows]] call comspec_sse_fnc_iarpClient;
    };
    case "prefix": {
        _a params [["_cmd", "me"]];
        if (isNull _d) exitWith {};
        private _e = _d displayCtrl 94620;
        ([ctrlText _e] call _split) params ["_old", "_body"];
        _e ctrlSetText ([format ["/%1 %2", _cmd, _body], _body] select (_old isEqualTo _cmd));
        ctrlSetFocus _e;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
    };
    case "keyUp": {
        if (isNull _d) exitWith {};
        private _e = _d displayCtrl 94620;
        private _txt = ctrlText _e;
        private _n = count (toArray _txt);
        if (_n > _maxLen) then { _e ctrlSetText (toString ((toArray _txt) select [0, _maxLen])); _n = _maxLen; };
        (_d displayCtrl 94626) ctrlSetText format ["%1 / %2", _n, _maxLen];
        if (_n > 0 && {missionNamespace getVariable ["comspec_sse_iarp_typing", true]} && {(time - (uiNamespace getVariable ["comspec_sse_iarpTypingAt", -10])) > 2.5}) then {
            uiNamespace setVariable ["comspec_sse_iarpTypingAt", time];
            ["send", ["typing", [player, true]]] call comspec_sse_fnc_iarpClient;
        };
    };
    case "key": {
        _a params ["", ["_dik", 0], ["_shift", false], ["_ctrlKey", false], ["_alt", false]];
        if (isNull _d) exitWith { false };
        private _e = _d displayCtrl 94620;
        if (_dik in [28, 156] && {!_shift}) exitWith { ["send"] call comspec_sse_fnc_iarpUi; true };
        if (_dik in [200, 208] && {_sent isNotEqualTo []}) exitWith {
            private _i = uiNamespace getVariable ["comspec_sse_iarpRecall", -1];
            private _cur = ctrlText _e;
            // Rappel seulement sur une saisie vide ou un message déjà rappelé : sinon la flèche déplace le curseur.
            if (_cur isNotEqualTo "" && {_i < 0 || {_cur isNotEqualTo (_sent param [_i, ""])}}) exitWith { false };
            if (_i < 0) then { _i = count _sent; };
            _i = if (_dik isEqualTo 200) then { (_i - 1) max 0 } else { _i + 1 };
            if (_i >= (count _sent)) then { uiNamespace setVariable ["comspec_sse_iarpRecall", -1]; _e ctrlSetText ""; } else {
                uiNamespace setVariable ["comspec_sse_iarpRecall", _i];
                _e ctrlSetText (_sent select _i);
            };
            ["keyUp"] call comspec_sse_fnc_iarpUi;
            true
        };
        false
    };
    case "send": {
        if (isNull _d) exitWith {};
        private _t = call _targetObj;
        private _e = _d displayCtrl 94620;
        private _raw = trim (ctrlText _e);
        if (_raw isEqualTo "" || {isNull _t}) exitWith {};
        if !(ctrlEnabled (_d displayCtrl 94625)) exitWith { ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call comspec_sse_fnc_iarpClient; };
        ([_raw] call _split) params ["_cmd", "_body"];
        if (_body isEqualTo "") exitWith {};
        // Deux Entrée trop rapprochées : la seconde attend (le serveur l'aurait refusée, laissant une question sans réponse).
        if ((time - (uiNamespace getVariable ["comspec_sse_iarpSentAt", -10])) < 0.9) exitWith {};
        uiNamespace setVariable ["comspec_sse_iarpSentAt", time];
        if ((_sent param [(count _sent) - 1, ""]) isNotEqualTo _raw) then { _sent pushBack _raw; };
        if ((count _sent) > 30) then { _sent deleteAt 0; };
        uiNamespace setVariable ["comspec_sse_iarpRecall", -1];
        _e ctrlSetText "";
        _drafts deleteAt (netId _t);
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        (_d displayCtrl 94651) ctrlShow false;
        // Ma ligne tout de suite dans le fil (le résultat d'un /try arrive du serveur).
        if (_cmd isNotEqualTo "try") then {
            private _hist = missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap];
            private _l = _hist getOrDefault [netId _t, []];
            _l pushBack [["say", _cmd] select (_cmd isNotEqualTo ""), localize "STR_COMSPEC_IARP_Me", _body, [daytime, "HH:MM"] call BIS_fnc_timeToString];
            if ((count _l) > 80) then { _l deleteAt 0; };
            _hist set [netId _t, _l];
            missionNamespace setVariable ["comspec_sse_iarpHist", _hist];
        };
        ["send", ["say", [player, _t, _body, _cmd, profileNameSteam]]] call comspec_sse_fnc_iarpClient;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
        if ((uiNamespace getVariable ["comspec_sse_iarpTab", "talk"]) isEqualTo "guide") then { ["tab", ["talk"]] call comspec_sse_fnc_iarpUi; } else { ["refresh"] call comspec_sse_fnc_iarpUi; };
        ctrlSetFocus _e;
    };
};
