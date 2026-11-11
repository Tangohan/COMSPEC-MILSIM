/*
    Diffusion (BROADCAST) et cartes fixes des écrans TOC (demande de TTRD, 4.8.0 : « il faudrait aussi un mode
    BROADCAST », avec une mire de barres de couleur).
    1. Affichage BROADCAST d'un écran 16:9 (fn_tocScreen, vue "BROADCAST") :
         - sans source : la mire. Barres de couleur à 75 % (gris, jaune, cyan, vert, magenta, rouge, bleu), rangée de
           créneaux dessous (bleu, noir, magenta, noir, cyan, noir, gris), bande du bas (-I, blanc, +Q, noir, trois
           bandes PLUGE, noir), dessinées ici contrôle par contrôle (aucune image copiée), titre de l'écran, heures
           ZULU et locale en direct, témoin « EN DIRECT » fixe au choix. Aucun son.
         - avec une source (un autre écran de la mission, par son identifiant stable COMSPEC_ATAK_TocUid) : l'écran
           montre ce que montre la source, au même rythme : une source, autant de sorties qu'on veut (toute une salle,
           un groupe nommé). Source perdue, éteinte ou en veille : la mire (option « Mire ») ou la carte AUCUN SIGNAL.
    2. Carte « AUCUN SIGNAL » (tous les affichages qui lisent le réseau du camp) : retour de TTRD, « certains écrans
       clignotent en l'absence de liaison et sans joueur ». Tant que le serveur n'a publié aucun flux pour le camp de
       l'écran, une carte fixe remplace le contenu à moitié vide ; un filet glisse lentement en bas, rien ne clignote.
       Bascule avec retenue : contenu après deux images de suite avec un flux, carte après trois sans.
    Réglage d'un écran : variable publique COMSPEC_ATAK_TocBc = [source (identifiant d'écran, "" : mire), titre,
    témoin EN DIRECT, mire si la source est perdue, groupe de diffusion, affichage d'avant la diffusion], posée par le
    serveur (fn_tocScreen « view », clé "bc") et gardée avec l'écran (fn_tocPersist).
    Modes :
      ["cfg", écran]                     réglage complet (valeurs par défaut comprises)
      ["sanitize", réglage]              réglage nettoyé (types, longueurs)
      ["source", écran]                  écran source en état de diffuser, objNull sinon
      ["signal", [écran, display, affichage]]   true : contenu ; false : carte AUCUN SIGNAL (retenue comprise)
      ["nosig", [écran, display]]        dessine la carte AUCUN SIGNAL
      ["draw", [écran, display]]         dessine l'affichage BROADCAST (source recopiée, mire ou AUCUN SIGNAL)
      ["hide", display]                  masque les cartes (un autre affichage reprend le display)
      ["targets", [portée "hall" | "group" | "list", valeur, source]]   écrans 16:9 visés
      ["start", [source, portée, valeur, options]]   la source est diffusée sur les écrans visés
      ["stop", [portée, valeur]]         fin de la diffusion : chaque écran reprend son affichage d'avant
      ["set", [écran, clé "src" | "title" | "tally" | "mire" | "group", valeur]]
      ["menu", écran] / ["do", [écran, clé]]   entrées du menu de l'écran (ACE, molette) : [[libellé, clé]...]
      ["label", écran]                   « mire », « recopie de nom » ou "" (tableau des écrans)
      ["bars"]                           table de la mire : [[rangée, y, h, [[x, l, [r, v, b]]...]]...] en fractions
*/
params [["_mode", "cfg"], ["_a", objNull]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocBroadcast;
private _ts = comspec_atak_native_fnc_tocScreen;
private _cfgOf = {
    params ["_o"];
    private _c = _o getVariable ["COMSPEC_ATAK_TocBc", []];
    if !(_c isEqualType []) then { _c = []; };
    _c params [["_src", ""], ["_title", ""], ["_tally", true], ["_mire", true], ["_grp", ""], ["_prev", ""]];
    if !(_src isEqualType "") then { _src = ""; };
    if !(_title isEqualType "") then { _title = ""; };
    if !(_grp isEqualType "") then { _grp = ""; };
    if !(_prev isEqualType "") then { _prev = ""; };
    [_src select [0, 40], _title select [0, 40], _tally isEqualTo true, _mire isEqualTo true, (toUpper _grp) select [0, 24], _prev select [0, 16]]
};
private _screens = { (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(count (_x getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} } };
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
private _isStd = { params ["_o"]; ((["format", _o] call _ts) param [3, ""]) isEqualTo "STD" };
// ---- TABLE _smpte (JSON) : mire. Rangées [nom, y, h, cases [x, l, [r, v, b]]] en fractions de l'image.
private _smpte = [
["bars", 0, 0.67, [[0, 0.142857, [0.75, 0.75, 0.75]], [0.142857, 0.142857, [0.75, 0.75, 0]], [0.285714, 0.142857, [0, 0.75, 0.75]], [0.428571, 0.142857, [0, 0.75, 0]], [0.571429, 0.142857, [0.75, 0, 0.75]], [0.714286, 0.142857, [0.75, 0, 0]], [0.857143, 0.142857, [0, 0, 0.75]]]],
["castellations", 0.67, 0.08, [[0, 0.142857, [0, 0, 0.75]], [0.142857, 0.142857, [0.075, 0.075, 0.075]], [0.285714, 0.142857, [0.75, 0, 0.75]], [0.428571, 0.142857, [0.075, 0.075, 0.075]], [0.571429, 0.142857, [0, 0.75, 0.75]], [0.714286, 0.142857, [0.075, 0.075, 0.075]], [0.857143, 0.142857, [0.75, 0.75, 0.75]]]],
["pluge", 0.75, 0.25, [[0, 0.178571, [0, 0.13, 0.3]], [0.178571, 0.178571, [1, 1, 1]], [0.357143, 0.178571, [0.2, 0, 0.42]], [0.535714, 0.178572, [0.075, 0.075, 0.075]], [0.714286, 0.047619, [0.035, 0.035, 0.035]], [0.761905, 0.047619, [0.075, 0.075, 0.075]], [0.809524, 0.047619, [0.115, 0.115, 0.115]], [0.857143, 0.142857, [0.075, 0.075, 0.075]]]]
];
// ---- FIN
// Contrôles des cartes, créés une fois par display : groupe plein cadre, réserve de cases de couleur, textes.
private _uiOf = {
    params ["_d"];
    private _ui = _d getVariable ["COMSPEC_ATAK_BcUi", createHashMap];
    if ((count _ui) > 0 && {!isNull (_ui getOrDefault ["g", controlNull])}) exitWith { _ui };
    private _g = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1];
    _g ctrlSetPosition [0, 0, 1, 1];
    _g ctrlCommit 0;
    private _boxes = [];
    for "_i" from 1 to 28 do { private _c = _d ctrlCreate ["RscText", -1, _g]; _c ctrlSetPosition [0, 0, 0, 0]; _c ctrlCommit 0; _boxes pushBack _c; };
    private _txt = [];
    for "_i" from 1 to 5 do { private _c = _d ctrlCreate ["COMSPEC_RscTocLabelCenter", -1, _g]; _c ctrlSetPosition [0, 0, 0, 0]; _c ctrlCommit 0; _txt pushBack _c; };
    _ui = createHashMapFromArray [["g", _g], ["boxes", _boxes], ["txt", _txt]];
    _d setVariable ["COMSPEC_ATAK_BcUi", _ui];
    _ui
};
// Les autres calques du display (carte, tableaux, texte à LED) sont masqués sous une carte.
private _others = {
    params ["_d", "_show"];
    {
        if (!isNull _x) then { _x ctrlShow _show; };
    } forEach [(_d getVariable ["COMSPEC_ATAK_TocUi", createHashMap]) getOrDefault ["grp", controlNull], _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull],
        (_d getVariable ["COMSPEC_ATAK_TxtUi", createHashMap]) getOrDefault ["g", controlNull]];
};
private _tcol = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
// Texte d'une carte : réécrit seulement s'il change (une image redessinée à l'identique ne coûte rien).
private _say = {
    params ["_c", "_r", "_s", "_px", "_col", ["_font", "RobotoCondensedBold"], ["_bg", [0, 0, 0, 0]]];
    private _key = [_s, _px, _font];
    if ((_c getVariable ["COMSPEC_ATAK_BcTxt", []]) isNotEqualTo _key) then {
        _c setVariable ["COMSPEC_ATAK_BcTxt", _key];
        _c ctrlSetFont _font;
        _c ctrlSetFontHeight _px;
        _c ctrlSetText _s; // i18n:brut (déjà traduit)
    };
    _c ctrlSetTextColor _col;
    _c ctrlSetBackgroundColor _bg;
    _c ctrlSetPosition _r;
    _c ctrlCommit 0;
    _c ctrlShow true;
};
private _clock = {
    format ["%1 Z  ·  %2 LOCAL", ["zulu"] call comspec_atak_native_fnc_tocRoom, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString]
};
// Témoin « EN DIRECT » au-dessus d'une image recopiée : pastille rouge fixe et titre, en haut à droite. Recréé quand
// l'affichage a ajouté des contrôles (il doit rester au-dessus).
private _tallyOn = {
    params ["_d", "_show", ["_label", ""]];
    private _t = _d getVariable ["COMSPEC_ATAK_BcTally", []];
    if (!_show) exitWith { { if (!isNull _x) then { _x ctrlShow false; }; } forEach _t; };
    if ((count _t) < 2 || {isNull (_t select 0)} || {(_d getVariable ["COMSPEC_ATAK_BcTallyN", -1]) isNotEqualTo (count (allControls _d))}) then {
        { if (!isNull _x) then { ctrlDelete _x; }; } forEach _t;
        private _bg = _d ctrlCreate ["COMSPEC_RscTocLabelCenter", -1];
        private _dot = _d ctrlCreate ["RscText", -1];
        _t = [_bg, _dot];
        _d setVariable ["COMSPEC_ATAK_BcTally", _t];
        _d setVariable ["COMSPEC_ATAK_BcTallyN", count (allControls _d)];
    };
    _t params ["_bg", "_dot"];
    [_bg, [0.69, 0.092, 0.295, 0.05], "     " + _label, 28 / 900, ["text"] call _tcol, "RobotoCondensedBold", ["bg0", 0.82] call _tcol] call _say;
    _dot ctrlSetBackgroundColor [0.9, 0.1, 0.1, 1];
    _dot ctrlSetPosition [0.7, 0.107, 0.0113, 0.02];
    _dot ctrlCommit 0;
    _dot ctrlShow true;
};

switch (_mode) do {
    case "cfg": { [_a] call _cfgOf };
    case "bars": { +_smpte };
    case "sanitize": {
        private _tmp = "Land_HelipadEmpty_F";
        private _c = if (_a isEqualType []) then { _a } else { [] };
        _c params [["_src", ""], ["_title", ""], ["_tally", true], ["_mire", true], ["_grp", ""], ["_prev", ""]];
        if !(_src isEqualType "") then { _src = ""; };
        if !(_title isEqualType "") then { _title = ""; };
        if !(_grp isEqualType "") then { _grp = ""; };
        if !(_prev isEqualType "") then { _prev = ""; };
        [_src select [0, 40], _title select [0, 40], _tally isEqualTo true, _mire isEqualTo true, (toUpper _grp) select [0, 24], _prev select [0, 16]]
    };
    case "source": {
        // Source en état de diffuser : écran désigné, vivant, allumé, 16:9, qui ne recopie pas lui-même une source.
        private _id = ([_a] call _cfgOf) select 0;
        if (_id isEqualTo "") exitWith { objNull };
        private _l = (call _screens) select { (_x getVariable ["COMSPEC_ATAK_TocUid", ""]) isEqualTo _id && {_x isNotEqualTo _a} };
        private _s = _l param [0, objNull];
        if (isNull _s || {_s getVariable ["COMSPEC_ATAK_TocOff", false]} || {((_s getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "BROADCAST"} || {!([_s] call _isStd)}) exitWith { objNull };
        _s
    };
    case "label": {
        if (((_a getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isNotEqualTo "BROADCAST") exitWith { "" };
        private _id = ([_a] call _cfgOf) select 0;
        if (_id isEqualTo "") exitWith { ("mire") call comspec_atak_native_fnc_tr };
        private _s = ((call _screens) select { (_x getVariable ["COMSPEC_ATAK_TocUid", ""]) isEqualTo _id }) param [0, objNull];
        format [("recopie de %1") call comspec_atak_native_fnc_tr, [(_s getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "?"], ("source perdue") call comspec_atak_native_fnc_tr] select (isNull _s)]
    };
    case "signal": {
        _a params [["_o", objNull], ["_d", displayNull], ["_view", "SPLIT"]];
        if (isNull _o || {isNull _d}) exitWith { true };
        // Affichages qui lisent le réseau du camp publié par le serveur (fn_tocFeed). Les autres (horloge, texte,
        // registres du poste, satellite, mosaïque, mire) ont leur propre état vide.
        if !(_view in ["SPLIT", "MAP", "TOC", "SQUAD", "FEED", "NET", "COLB", "CAM"]) exitWith { true };
        private _cs = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
        private _side = [_cs, str side group player] select (_cs isEqualTo "");
        private _ft = (missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []]) param [0, -1];
        private _ok = _ft isEqualType 0 && {_ft >= 0};
        // Retenue : [contenu ?, images de suite dans l'autre état]. Contenu après 2 images avec flux, carte après 3 sans.
        (_d getVariable ["COMSPEC_ATAK_BcSig", [false, 0]]) params ["_on", "_n"];
        if (_ok isEqualTo _on) then { _n = 0; } else {
            _n = _n + 1;
            if (_n >= ([2, 3] select _on)) then { _on = _ok; _n = 0; };
        };
        _d setVariable ["COMSPEC_ATAK_BcSig", [_on, _n]];
        _on
    };
    case "hide": {
        private _d = _a;
        if (isNull _d) exitWith { false };
        private _g = (_d getVariable ["COMSPEC_ATAK_BcUi", createHashMap]) getOrDefault ["g", controlNull];
        if (!isNull _g) then { _g ctrlShow false; };
        [_d, false] call _tallyOn;
        true
    };
    case "nosig": {
        _a params [["_o", objNull], ["_d", displayNull], ["_why", ""]];
        if (isNull _d) exitWith { false };
        private _ui = [_d] call _uiOf;
        [_d, false] call _others;
        [_d, false] call _tallyOn;
        (_ui get "g") ctrlShow true;
        private _boxes = _ui get "boxes";
        private _txt = _ui get "txt";
        // Fond, cadre fin, filet qui glisse lentement en bas (une position par image, jamais d'extinction).
        (_boxes select 0) ctrlSetBackgroundColor (["bg0"] call _tcol);
        (_boxes select 0) ctrlSetPosition [0, 0, 1, 1]; (_boxes select 0) ctrlCommit 0; (_boxes select 0) ctrlShow true;
        {
            private _c = _boxes select (_forEachIndex + 1);
            _c ctrlSetBackgroundColor (["lineStrong"] call _tcol);
            _c ctrlSetPosition _x; _c ctrlCommit 0; _c ctrlShow true;
        } forEach [[0.04, 0.07, 0.92, 0.003], [0.04, 0.927, 0.92, 0.003], [0.04, 0.07, 0.0017, 0.86], [0.9583, 0.07, 0.0017, 0.86]];
        private _slide = _boxes select 5;
        private _k = ((diag_tickTime / 24) mod 1);
        _slide ctrlSetBackgroundColor (["accent", 0.7] call _tcol);
        _slide ctrlSetPosition [0.06 + 0.76 * _k, 0.86, 0.12, 0.006]; _slide ctrlCommit 0; _slide ctrlShow true;
        for "_i" from 6 to ((count _boxes) - 1) do { (_boxes select _i) ctrlShow false; };
        private _name = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"];
        // Colonne d'un écran incurvé (texture carrée) : textes plus petits pour tenir dans la largeur.
        private _narrow = ((["format", _o] call _ts) param [3, "STD"]) isEqualTo "COL";
        private _kf = [1, 0.5] select _narrow;
        [_txt select 0, [0.05, 0.33, 0.9, 0.16], ("AUCUN SIGNAL") call comspec_atak_native_fnc_tr, _kf * 120 / 900, ["text2"] call _tcol] call _say;
        [_txt select 1, [0.05, 0.5, 0.9, 0.06], format ["NO SIGNAL · %1", _name], _kf * 40 / 900, ["muted"] call _tcol, "RobotoCondensed"] call _say;
        [_txt select 2, [0.05, 0.6, 0.9, 0.05], ([_why, "Le serveur n'a encore publié aucun flux pour ce camp"] select (_why isEqualTo "")) call comspec_atak_native_fnc_tr, _kf * 30 / 900, ["muted"] call _tcol, "RobotoCondensed"] call _say;
        [_txt select 3, [0.05, 0.76, 0.9, 0.06], call _clock, _kf * 36 / 900, ["text2"] call _tcol, "EtelkaMonospacePro"] call _say;
        (_txt select 4) ctrlShow false;
        true
    };
    case "draw": {
        _a params [["_o", objNull], ["_d", displayNull]];
        if (isNull _o || {isNull _d}) exitWith { false };
        ([_o] call _cfgOf) params ["_srcId", "_title", "_tally", "_mire"];
        private _name = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"];
        private _src = ["source", _o] call _fn;
        // Retenue sur la source : prise après 2 images de suite, rendue après 3 sans elle (une source qui redémarre
        // ne fait pas sauter la sortie entre image et mire).
        (_d getVariable ["COMSPEC_ATAK_BcSrc", [false, 0]]) params ["_on", "_n"];
        private _ok = !isNull _src;
        if (_ok isEqualTo _on) then { _n = 0; } else {
            _n = _n + 1;
            if (_n >= ([2, 3] select _on)) then { _on = _ok; _n = 0; };
        };
        _d setVariable ["COMSPEC_ATAK_BcSrc", [_on, _n]];
        // Source tenue : son image, redessinée ici avec son réglage (même données, même rythme sur toutes les sorties).
        if (_on && {_ok}) exitWith {
            private _g = (_d getVariable ["COMSPEC_ATAK_BcUi", createHashMap]) getOrDefault ["g", controlNull];
            if (!isNull _g) then { _g ctrlShow false; };
            ["draw", [_src, _d]] call _ts;
            [_d, _tally, format ["%1 · %2", ("EN DIRECT") call comspec_atak_native_fnc_tr, [_title, (_src getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"]] select (_title isEqualTo "")]] call _tallyOn;
            true
        };
        // Source tenue mais absente à cette image : la dernière image reste (rien n'est touché).
        if (_on) exitWith { true };
        // Source demandée et perdue, mire refusée : carte AUCUN SIGNAL.
        if (_srcId isNotEqualTo "" && {!_mire}) exitWith { ["nosig", [_o, _d, "Source de la diffusion perdue ou en veille"]] call _fn };
        // ---- Mire ----
        private _ui = [_d] call _uiOf;
        [_d, false] call _others;
        [_d, false] call _tallyOn;
        (_ui get "g") ctrlShow true;
        private _boxes = _ui get "boxes";
        private _txt = _ui get "txt";
        private _bi = 0;
        {
            _x params ["", "_ry", "_rh", "_cells"];
            {
                _x params ["_cx", "_cw", "_rgb"];
                private _c = _boxes select _bi;
                _bi = _bi + 1;
                _c ctrlSetBackgroundColor (_rgb + [1]);
                // Un rien de recouvrement : aucun liseré entre deux cases à l'arrondi des pixels.
                _c ctrlSetPosition [_cx, _ry, _cw + 0.001, _rh + 0.001];
                _c ctrlCommit 0;
                _c ctrlShow true;
            } forEach _cells;
        } forEach _smpte;
        // Cartouche du titre (fond noir), heures, témoin.
        private _cart = _boxes select _bi;
        _cart ctrlSetBackgroundColor [0.02, 0.02, 0.02, 1];
        _cart ctrlSetPosition [0.25, 0.26, 0.5, 0.2]; _cart ctrlCommit 0; _cart ctrlShow true;
        _bi = _bi + 1;
        private _dot = _boxes select _bi;
        _dot ctrlShow _tally;
        if (_tally) then { _dot ctrlSetBackgroundColor [0.9, 0.1, 0.1, 1]; _dot ctrlSetPosition [0.405, 0.418, 0.0113, 0.02]; _dot ctrlCommit 0; };
        _bi = _bi + 1;
        for "_i" from _bi to ((count _boxes) - 1) do { (_boxes select _i) ctrlShow false; };
        [_txt select 0, [0.25, 0.27, 0.5, 0.075], [_title, _name] select (_title isEqualTo ""), 56 / 900, [1, 1, 1, 1]] call _say;
        [_txt select 1, [0.25, 0.345, 0.5, 0.05], call _clock, 32 / 900, [0.85, 0.85, 0.85, 1], "EtelkaMonospacePro"] call _say;
        private _state = switch (true) do {
            case (_srcId isNotEqualTo ""): { "SOURCE EN ATTENTE" };
            case (_tally): { "EN DIRECT" };
            default { "MIRE" };
        };
        [_txt select 2, [0.25, 0.4, 0.5, 0.05], (_state) call comspec_atak_native_fnc_tr, 30 / 900, [0.85, 0.85, 0.85, 1]] call _say;
        (_txt select 3) ctrlShow false;
        (_txt select 4) ctrlShow false;
        true
    };
    case "targets": {
        _a params [["_scope", "hall"], ["_val", ""], ["_src", objNull]];
        private _all = (call _screens) select { _x isNotEqualTo _src && {[_x] call _isStd} };
        switch (_scope) do {
            case "hall": { _all select { _val isNotEqualTo "" && {(([_x] call _hostOf) getVariable ["COMSPEC_ATAK_TocHall", ""]) isEqualTo _val} } };
            case "group": { _all select { _val isNotEqualTo "" && {(([_x] call _cfgOf) select 4) isEqualTo (toUpper _val)} } };
            default { _all select { _x in _val } };
        }
    };
    case "set": {
        _a params [["_o", objNull], ["_k", ""], ["_v", ""]];
        if (isNull _o) exitWith { false };
        private _c = [_o] call _cfgOf;
        private _i = ["src", "title", "tally", "mire", "group", "prev"] find _k;
        if (_i < 0) exitWith { false };
        _c set [_i, _v];
        ["view", [_o, "bc", _c]] call _ts;
        true
    };
    case "start": {
        _a params [["_src", objNull], ["_scope", "hall"], ["_val", ""], ["_opt", []]];
        _opt params [["_title", ""], ["_tally", true], ["_mire", true]];
        if (isNull _src) exitWith { 0 };
        private _uid = _src getVariable ["COMSPEC_ATAK_TocUid", ""];
        if (_uid isEqualTo "") exitWith { 0 };
        private _to = ["targets", [_scope, _val, _src]] call _fn;
        {
            private _c = [_x] call _cfgOf;
            private _cur = (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, "SPLIT"];
            // Affichage d'avant gardé une fois (une diffusion relancée ne l'écrase pas par « BROADCAST »).
            private _prev = [_cur, _c select 5] select (_cur isEqualTo "BROADCAST");
            ["view", [_x, "bc", [_uid, _title, _tally, _mire, _c select 4, _prev]]] call _ts;
            ["view", [_x, "mode", "BROADCAST"]] call _ts;
        } forEach _to;
        count _to
    };
    case "stop": {
        _a params [["_scope", "hall"], ["_val", ""]];
        private _to = (["targets", [_scope, _val, objNull]] call _fn) select { ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "BROADCAST" };
        {
            private _c = [_x] call _cfgOf;
            private _prev = [_c select 5, "SPLIT"] select ((_c select 5) in ["", "BROADCAST"]);
            ["view", [_x, "bc", ["", _c select 1, _c select 2, _c select 3, _c select 4, ""]]] call _ts;
            ["view", [_x, "mode", _prev]] call _ts;
        } forEach _to;
        count _to
    };
    case "menu": {
        private _o = _a;
        if (isNull _o || {!([_o] call _isStd)}) exitWith { [] };
        ([_o] call _cfgOf) params ["", "", "_tally", "_mire"];
        private _hall = ([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocHall", ""];
        private _yn = { ([("non") call comspec_atak_native_fnc_tr, ("oui") call comspec_atak_native_fnc_tr] select _this) };
        private _out = [[("Afficher la mire (barres de couleur)") call comspec_atak_native_fnc_tr, "bars"]];
        if (_hall isNotEqualTo "") then {
            _out pushBack [("Diffuser cet écran dans toute la salle") call comspec_atak_native_fnc_tr, "hallOn"];
            _out pushBack [("Arrêter la diffusion de la salle") call comspec_atak_native_fnc_tr, "hallOff"];
        };
        _out pushBack [format [("Témoin EN DIRECT : %1") call comspec_atak_native_fnc_tr, _tally call _yn], "tally"];
        _out pushBack [format [("Mire si la source est perdue : %1") call comspec_atak_native_fnc_tr, _mire call _yn], "mire"];
        _out
    };
    case "do": {
        _a params [["_o", objNull], ["_k", ""]];
        if (isNull _o) exitWith { false };
        ([_o] call _cfgOf) params ["", "", "_tally", "_mire"];
        private _hall = ([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocHall", ""];
        switch (_k) do {
            case "bars": {
                ["set", [_o, "src", ""]] call _fn;
                ["ensure", [_o, "BROADCAST"]] call _ts;
            };
            case "hallOn": { ["start", [_o, "hall", _hall, ["", _tally, _mire]]] call _fn; };
            case "hallOff": { ["stop", ["hall", _hall]] call _fn; };
            case "tally": { ["set", [_o, "tally", !_tally]] call _fn; };
            case "mire": { ["set", [_o, "mire", !_mire]] call _fn; };
        };
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        true
    };
    default { false };
};
