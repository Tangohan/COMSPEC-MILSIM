/*
    Affichages « tableau » des écrans TOC (fn_tocScreen), dans la même texture UI 1600 x 900 que la carte du poste :
      SAT      vue satellite en direct (fn_tocSat) : image r2t dans la texture, suivi des alliés par-dessus (symbole,
               cadre, indicatif ; ancien / perdu / hors ligne en gris), bandeau « SAT 2 · … · contrôlé par … »
      CLOCK    horloge : heure ZULU et heure locale de la mission en grand, tableau des chronos du poste (fn_tocRoom)
      LOG      main courante du poste          EXCHECK  liste d'exécution de l'opération
      KILLS    pertes et résultats par élément REQ      demandes reçues des éléments
    Les contrôles vivent dans un groupe plein écran posé au-dessus de la carte (créé après elle) ; la carte n'est pas
    dessinée tant qu'un tableau est affiché. FLASH du poste (fn_tocRoom) : bandeau rouge en tête de tous les affichages.
    Modes :
      ["draw", [écran, display, affichage]]   dessin complet (fn_tocScreen, toutes les 1 à 2 s)
      ["overlay", [écran, display]]           SAT : cadres de suivi seuls, 5 fois par seconde (boucle de fn_tocBoard)
      ["hide", display]                       masque le tableau (retour à la carte)
      ["flash", camp]                         FLASH en cours : [texte, auteur, heure] ou []
*/
params [["_mode", "draw"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocBoard;
private _texW = 1600;
private _texH = 900;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _setTxt = {
    params ["_c", "_s"];
    if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _s) then {
        _c setVariable ["COMSPEC_ATAK_Txt", _s];
        _c ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
    };
};
// Image satellite : cadre 16:9 dans la zone sous l'en-tête (bandes noires sur les côtés).
private _top = 0.08;
private _picH = 1 - _top;
private _picW = (_picH * _texH * 16 / 9) / _texW;
private _picX = (1 - _picW) / 2;
switch (_mode) do {
    case "flash": {
        private _f = (["state", _a] call comspec_atak_native_fnc_tocRoom) select 6;
        if ((count _f) >= 4 && {serverTime - (_f select 3) < 180}) then { [_f select 0, _f select 1, _f select 2] } else { [] }
    };
    case "hide": {
        private _g = _a getVariable ["COMSPEC_ATAK_TocBoard", controlNull];
        if (!isNull _g) then { _g ctrlShow false; };
    };
    case "overlay": {
        _a params ["_o", "_d"];
        private _ui = _d getVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap];
        if ((count _ui) isEqualTo 0) exitWith { false };
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _side = [_c param [1, ""], str side group player] select ((_c param [1, ""]) isEqualTo "");
        private _n = ((_c param [8, 0]) max 0) min 1;
        private _rgb = (createHashMapFromArray [["WEST", [0.35, 0.68, 1]], ["EAST", [1, 0.38, 0.32]], ["GUER", [0.4, 0.9, 0.45]], ["CIV", [0.8, 0.55, 1]]]) getOrDefault [_side, [0.35, 0.68, 1]];
        private _boxes = _ui get "boxes";
        private _used = 0;
        private _rendered = (["rt", [_side, _n]] call comspec_atak_native_fnc_tocSat) isNotEqualTo "";
        if (_rendered) then {
            {
                _x params ["", "_cs", "_fr", "_kind", "_pos", "_size"];
                if (_used >= (count _boxes)) exitWith {};
                private _pr = ["project", [_side, _n, _pos]] call comspec_atak_native_fnc_tocSat;
                if ((count _pr) < 3) then { continue };
                _pr params ["_u", "_v", "_span"];
                if (_u < 0 || {_u > 1} || {_v < 0} || {_v > 1}) then { continue };
                // Cadre : taille de l'unité vue d'en haut, 22 px au moins.
                private _px = ((_size / _span) * _picW * _texW) max 22;
                private _gx = _picX + _u * _picW;
                private _gy = _top + _v * _picH;
                private _w = _px / _texW; private _h = _px / _texH;
                private _col = switch (_fr) do { case 0: { _rgb + [1] }; case 1: { (_rgb apply { _x * 0.75 }) + [0.85] }; default { [0.7, 0.7, 0.7, 0.8] }; };
                (_boxes select _used) params ["_l1", "_l2", "_l3", "_l4", "_ic", "_lb"];
                private _t = 2 / _texW; private _tv = 2 / _texH;
                { _x ctrlSetBackgroundColor _col; } forEach [_l1, _l2, _l3, _l4];
                _l1 ctrlSetPosition [_gx - _w / 2, _gy - _h / 2, _w, _tv];
                _l2 ctrlSetPosition [_gx - _w / 2, _gy + _h / 2 - _tv, _w, _tv];
                _l3 ctrlSetPosition [_gx - _w / 2, _gy - _h / 2, _t, _h];
                _l4 ctrlSetPosition [_gx + _w / 2 - _t, _gy - _h / 2, _t, _h];
                _ic ctrlSetText (format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]]); // i18n:brut (image)
                _ic ctrlSetTextColor _col;
                _ic ctrlSetPosition [_gx + _w / 2 + 4 / _texW, _gy - _h / 2 - 30 / _texH, 28 / _texW, 28 / _texH];
                private _txt = _cs + (["", " · ancien", " · perdu", " · hors ligne"] select ((_fr min 3) max 0));
                _lb ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
                _lb ctrlSetTextColor ([[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2));
                _lb ctrlSetPosition [_gx + _w / 2 + 34 / _texW, _gy - _h / 2 - 30 / _texH, ((count _txt) * 12 + 14) / _texW, 28 / _texH];
                { _x ctrlCommit 0; _x ctrlShow true; } forEach [_l1, _l2, _l3, _l4, _ic, _lb];
                _used = _used + 1;
            } forEach (["targets", _side] call comspec_atak_native_fnc_tocSat);
        };
        for "_i" from _used to ((count _boxes) - 1) do { { _x ctrlShow false; } forEach (_boxes select _i); };
        _rendered
    };
    case "draw": {
        _a params ["_o", "_d", "_view"];
        private _cfg = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        _cfg params [["_name", "TOC"], ["_cside", ""]];
        private _side = [_cside, str side group player] select (_cside isEqualTo "");
        private _rgb = (createHashMapFromArray [["WEST", [0.28, 0.62, 1]], ["EAST", [0.95, 0.3, 0.25]], ["GUER", [0.36, 0.85, 0.42]], ["CIV", [0.75, 0.5, 0.95]]]) getOrDefault [_side, [0.28, 0.62, 1]];
        private _g = _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull];
        private _ui = _d getVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap];
        if (isNull _g) then {
            _g = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1];
            _g ctrlSetPosition [0, 0, 1, 1];
            _g ctrlCommit 0;
            _d setVariable ["COMSPEC_ATAK_TocBoard", _g];
            private _mk = {
                params ["_cls", ["_pos", [0, 0, 0, 0]]];
                private _c = _d ctrlCreate [_cls, -1, _g];
                _c ctrlSetPosition _pos;
                _c ctrlCommit 0;
                _c
            };
            (["RscText", [0, 0, 1, 1]] call _mk) ctrlSetBackgroundColor [0.03, 0.04, 0.035, 1];
            private _pic = ["RscPicture", [_picX, _top, _picW, _picH]] call _mk;
            private _hdr = ["RscText", [0, 0, 1, 0.075]] call _mk;
            _hdr ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
            private _accent = ["RscText", [0, 0.072, 1, 0.004]] call _mk;
            private _title = ["COMSPEC_RscStructuredText", [0.012, 0.012, 0.6, 0.06]] call _mk;
            private _clock = ["COMSPEC_RscStructuredText", [0.515, 0.016, 0.475, 0.056]] call _mk;
            private _strip = ["COMSPEC_RscStructuredText", [_picX, _top, _picW, 0.05]] call _mk;
            _strip ctrlSetBackgroundColor [0, 0, 0, 0.6];
            private _boxes = [];
            for "_i" from 1 to 40 do {
                private _b = [];
                for "_j" from 1 to 4 do { _b pushBack (["RscText"] call _mk); };
                _b pushBack (["RscPicture"] call _mk);
                private _t = ["RscText"] call _mk;
                _t ctrlSetFont "RobotoCondensedBold"; _t ctrlSetFontHeight 0.026; _t ctrlSetBackgroundColor [0, 0, 0, 0.55];
                _b pushBack _t;
                { _x ctrlShow false; } forEach _b;
                _boxes pushBack _b;
            };
            private _main = ["COMSPEC_RscStructuredText", [0.02, 0.1, 0.96, 0.88]] call _mk;
            private _main2 = ["COMSPEC_RscStructuredText", [0.02, 0.1, 0.96, 0.88]] call _mk;
            private _wait = ["COMSPEC_RscStructuredText", [_picX, 0.45, _picW, 0.12]] call _mk;
            private _flash = ["COMSPEC_RscStructuredText", [0, 0.076, 1, 0.06]] call _mk;
            _flash ctrlSetBackgroundColor [0.62, 0.08, 0.06, 0.95];
            _ui = createHashMapFromArray [["pic", _pic], ["accent", _accent], ["title", _title], ["clock", _clock], ["strip", _strip], ["boxes", _boxes],
                ["main", _main], ["main2", _main2], ["wait", _wait], ["flash", _flash]];
            _d setVariable ["COMSPEC_ATAK_TocBoardUi", _ui];
        };
        _g ctrlShow true;
        (_ui get "accent") ctrlSetBackgroundColor (_rgb + [1]);
        private _room = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        private _zulu = ["zulu"] call comspec_atak_native_fnc_tocRoom;
        private _local = [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString;
        private _titles = createHashMapFromArray [["SAT", "SATELLITE"], ["CLOCK", "HORLOGE"], ["LOG", "MAIN COURANTE"], ["EXCHECK", "EXÉCUTION"], ["KILLS", "PERTES ET RÉSULTATS"], ["REQ", "DEMANDES"]];
        [_ui get "title", format ["<t size='1.7' font='COMSPEC_BarlowBold'>%1 · %2</t>", _titles getOrDefault [_view, _view], [_name select [0, 20]] call _esc]] call _setTxt;
        [_ui get "clock", format ["<t align='right' size='1.25'>%1 <t color='#8a9a93'>ZULU</t>   %2 <t color='#8a9a93'>LOCAL</t></t>", _zulu, _local]] call _setTxt;
        private _isSat = _view isEqualTo "SAT";
        { (_ui get _x) ctrlShow _isSat; } forEach ["pic", "strip"];
        { (_ui get _x) ctrlShow !_isSat; } forEach ["main", "main2"];
        (_ui get "wait") ctrlShow false;
        if (!_isSat) then { { { _x ctrlShow false; } forEach _x; } forEach (_ui get "boxes"); };
        // FLASH du poste : bandeau rouge sous l'en-tête, 3 minutes.
        private _fl = ["flash", _side] call _fn;
        (_ui get "flash") ctrlShow ((count _fl) > 0);
        if ((count _fl) > 0) then {
            [_ui get "flash", format ["<t size='1.45' font='COMSPEC_BarlowBold'>  FLASH · %1</t> <t size='1.1' color='#ffd0c8'>· %2 · %3Z</t>", [_fl select 0] call _esc, [_fl select 1] call _esc, _fl select 2]] call _setTxt;
        };
        private _y0 = [0.1, 0.15] select ((count _fl) > 0);
        { (_ui get _x) ctrlSetPosition [0.02, _y0, 0.96, 0.98 - _y0]; (_ui get _x) ctrlCommit 0; (_ui get _x) ctrlSetBackgroundColor [0, 0, 0, 0]; } forEach ["main", "main2"];
        switch (_view) do {
            case "SAT": {
                private _n = ((_cfg param [8, 0]) max 0) min 1;
                private _tex = ["tex", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _pic = _ui get "pic";
                if ((_pic getVariable ["COMSPEC_ATAK_Tex", ""]) isNotEqualTo _tex) then {
                    _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
                    _pic ctrlSetText ([_tex, "#(argb,8,8,3)color(0,0,0,1)"] select (_tex isEqualTo "")); // i18n:brut (texture)
                };
                // Un écran du mur ne pilote rien : la main est au PC ou au téléphone du poste.
                [_ui get "strip", format ["<t size='1.15'>  <t color='#e0b040'>Lecture seule</t> · %1</t>", [["label", [_side, _n]] call comspec_atak_native_fnc_tocSat] call _esc]] call _setTxt;
                (_ui get "wait") ctrlShow (_tex isEqualTo "");
                if (_tex isEqualTo "") then {
                    [_ui get "wait", "<t align='center' size='1.6' color='#cfd8d3'>Image satellite en attente</t><br/><t align='center' size='1.1' color='#8a9a93'>Approchez-vous de l'écran : l'image n'est rendue qu'aux joueurs proches qui le regardent</t>"] call _setTxt;
                };
                ["overlay", [_o, _d]] call _fn;
                // Cadres de suivi 5 fois par seconde tant que l'image est rendue (la carte de fond, elle, reste à 1 s).
                private _live = uiNamespace getVariable ["COMSPEC_ATAK_TocBoardLive", []];
                if (_tex isNotEqualTo "" && {(_live findIf { (_x select 0) isEqualTo _o }) < 0}) then {
                    _live pushBack [_o, _d];
                    uiNamespace setVariable ["COMSPEC_ATAK_TocBoardLive", _live];
                    if (isNil { missionNamespace getVariable "COMSPEC_ATAK_TocBoardPfh" }) then {
                        missionNamespace setVariable ["COMSPEC_ATAK_TocBoardPfh", [{
                            private _l = (uiNamespace getVariable ["COMSPEC_ATAK_TocBoardLive", []]) select {
                                _x params ["_o", "_d"];
                                !isNull _o && {!isNull _d} && {((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "SAT"}
                                    && {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""}
                            };
                            uiNamespace setVariable ["COMSPEC_ATAK_TocBoardLive", _l];
                            if ((count _l) isEqualTo 0) exitWith {
                                [missionNamespace getVariable "COMSPEC_ATAK_TocBoardPfh"] call CBA_fnc_removePerFrameHandler;
                                missionNamespace setVariable ["COMSPEC_ATAK_TocBoardPfh", nil];
                            };
                            // Image rendue sur ce poste seulement : sinon rien à redessiner.
                            { if (["overlay", _x] call comspec_atak_native_fnc_tocBoard) then { displayUpdate (_x select 1); }; } forEach _l;
                        }, 0.2] call CBA_fnc_addPerFrameHandler];
                    };
                };
            };
            case "CLOCK": {
                private _s = format ["<t align='center'><t size='7' font='COMSPEC_BarlowBold'>%1</t><t size='2.4' color='#8a9a93'> ZULU</t><br/><t size='4.2' font='COMSPEC_BarlowBold' color='#cfd8d3'>%2</t><t size='1.8' color='#8a9a93'> LOCAL</t></t><br/><br/>", _zulu, _local];
                private _tm = _room select 1;
                if ((count _tm) isEqualTo 0) then {
                    _s = _s + "<t align='center' size='1.3' color='#8a9a93'>Aucun chrono : créez-en un au PC du poste (onglet CHRONOS)</t>";
                } else {
                    {
                        (["timerText", _x] call comspec_atak_native_fnc_tocRoom) params ["_txt", "", "", "_hex"];
                        _s = _s + format ["<t size='2.2'><t font='COMSPEC_BarlowBold'>%1</t><t align='right' font='COMSPEC_BarlowBold' color='%2'>%3</t></t><br/>", [_x select 1] call _esc, _hex, _txt];
                    } forEach (_tm select [0, 9]);
                };
                (_ui get "main2") ctrlShow false;
                [_ui get "main", _s] call _setTxt;
            };
            // Faces 4:1 (texture 2048 x 512) : texte plein cadre, sur fond opaque par-dessus l'en-tête.
            case "STRIP";
            case "PLATE": {
                private _m = _ui get "main";
                (_ui get "main2") ctrlShow false;
                (_ui get "flash") ctrlShow false;
                _m ctrlSetBackgroundColor [0.02, 0.025, 0.022, 1];
                _m ctrlSetPosition [0, 0, 1, 1];
                _m ctrlCommit 0;
                private _s = if (_view isEqualTo "STRIP") then {
                    // Bandeau : ZULU et LOCAL, puis le premier chrono du poste s'il y en a un.
                    private _t = (_room select 1) param [0, []];
                    private _tt = "";
                    if ((count _t) > 0) then {
                        (["timerText", _t] call comspec_atak_native_fnc_tocRoom) params ["_x1", "", "", "_hx"];
                        _tt = format ["   <t size='4.5' color='#8a9a93'>%1</t> <t size='6' font='COMSPEC_BarlowBold' color='%2'>%3</t>", [_t select 1] call _esc, _hx, _x1];
                    };
                    format ["<t align='center'><t size='9' font='COMSPEC_BarlowBold' color='#ff5a4a'>%1</t><t size='4.5' color='#8a9a93'> Z</t>   <t size='7' font='COMSPEC_BarlowBold' color='#e0b040'>%2</t><t size='4.5' color='#8a9a93'> LOCAL</t>%3</t>",
                        _zulu, _local, _tt]
                } else {
                    // Plaque : nom du groupe lié (écran d'escouade choisi), sinon le nom de l'écran ; état ATAK du groupe dessous.
                    private _g = _cfg param [9, ""];
                    private _txt = toUpper ([_g, _name] select (_g isEqualTo ""));
                    private _sub = "";
                    if (_g isNotEqualTo "") then {
                        private _nodes = ((missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []]) param [1, []]) select { (_x param [7, ""]) isEqualTo _g };
                        _sub = format ["<br/><t size='4.5' color='%1'>%2</t>", ["#6f7773", "#5cc76b"] select ((count _nodes) > 0),
                            [format ["%1 nœud(s) ATAK", count _nodes], "hors réseau"] select ((count _nodes) isEqualTo 0)];
                    };
                    format ["<t align='center'><t size='9' font='COMSPEC_BarlowBold'>%1</t>%2</t>", [_txt select [0, 18]] call _esc, _sub]
                };
                [_m, _s] call _setTxt;
            };
            default {
                (["boardText", [_view, _room, _side]] call _fn) params [["_l", ""], ["_r", ""]];
                [_ui get "main", _l] call _setTxt;
                (_ui get "main2") ctrlShow (_r isNotEqualTo "");
                if (_r isNotEqualTo "") then {
                    (_ui get "main") ctrlSetPosition [0.02, _y0, 0.47, 0.98 - _y0]; (_ui get "main") ctrlCommit 0;
                    (_ui get "main2") ctrlSetPosition [0.51, _y0, 0.47, 0.98 - _y0]; (_ui get "main2") ctrlCommit 0;
                    [_ui get "main2", _r] call _setTxt;
                };
            };
        };
        true
    };
    case "boardText": {
        // Textes des tableaux LOG, EXCHECK, KILLS, REQ (aussi lus par le PC du poste) : [colonne gauche, colonne droite].
        _a params ["_view", "_room", ["_side", ""]];
        private _muted = "#8a9a93";
        switch (_view) do {
            case "LOG": {
                private _l = (_room select 3) select [0 max ((count (_room select 3)) - 36)];
                reverse _l;
                private _rows = _l apply {
                    _x params ["_t", "_k", "_w", "_txt"];
                    private _c = (createHashMapFromArray [["FLASH", "#ff5a4a"], ["DEMANDE", "#e0b040"], ["EXCHECK", "#7fd0b0"], ["POSTE", "#7fb8e0"], ["CHRONO", "#b0a0e0"], ["CR", "#e0b040"]]) getOrDefault [_k, "#cfd8d3"];
                    format ["<t size='1.05'><t color='%1'>%2Z</t> <t color='%3' font='COMSPEC_BarlowBold'>%4</t> %5 <t color='%1'>· %6</t></t>", _muted, (_t select [0, 8]), _c, _k, [_txt select [0, 90]] call _esc, [_w select [0, 28]] call _esc]
                };
                if ((count _rows) isEqualTo 0) then { _rows = [format ["<t size='1.2' color='%1'>Main courante vide : comptes rendus, prises de poste, FLASH et chronos s'y inscrivent seuls</t>", _muted]]; };
                [(_rows select [0, 18]) joinString "<br/>", (_rows select [18, 18]) joinString "<br/>"]
            };
            case "EXCHECK": {
                private _rows = (_room select 4) apply {
                    _x params ["", "_n", "_ev", "_cw", "_el", "_at", "_done"];
                    private _ok = _done isNotEqualTo "";
                    format ["<t size='1.35'><t color='%1'>%2</t> <t font='COMSPEC_BarlowBold'>%3</t> %4 <t color='#e0b040'>%5</t> <t color='%6'>· %7</t><t align='right' color='%1'>%8 %9</t></t>",
                        ["#cfd8d3", "#5cc76b"] select _ok, [format ["%1.", _n], "✔"] select _ok, [_cw] call _esc, [_ev] call _esc, [_el] call _esc, _muted, ["prévu", "passé"] select _ok,
                        [[_at] call _esc, ""] select _ok, [["", _done] select _ok] call _esc]
                };
                if ((count _rows) isEqualTo 0) then { _rows = [format ["<t size='1.2' color='%1'>Aucune ligne : préparez la liste au PC du poste (onglet EXCHECK) ou dans le module Écran TOC</t>", _muted]]; };
                [(_rows select [0, 22]) joinString "<br/>", ""]
            };
            case "KILLS": {
                private _rows = [format ["<t size='1.3' font='COMSPEC_BarlowBold'>ÉLÉMENT<t align='right'>AMIS BLESSÉS · TUÉS   ENNEMIS NEUTRALISÉS · BLESSÉS</t></t>"]];
                {
                    _x params ["_el", "_wia", "_kia", "_ekia", "_ewia"];
                    _rows pushBack format ["<t size='1.35'><t font='COMSPEC_BarlowBold'>%1</t><t align='right'><t color='#e0b040'>%2</t> · <t color='#ff5a4a'>%3</t>          <t color='#5cc76b'>%4</t> · %5</t></t>", [_el] call _esc, _wia, _kia, _ekia, _ewia];
                } forEach (_room select 8);
                if ((count (_room select 8)) isEqualTo 0) then { _rows pushBack format ["<t size='1.2' color='%1'>Rien de rapporté : seuls comptent les pertes amies connues du réseau ATAK et les ennemis déclarés par compte rendu</t>", _muted]; };
                [(_rows select [0, 22]) joinString "<br/>", ""]
            };
            case "REQ": {
                private _l = +(_room select 5);
                reverse _l;
                private _rows = _l apply {
                    _x params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", "_ans"];
                    private _c = (createHashMapFromArray [["RECUE", "#e0b040"], ["PRISE", "#7fb8e0"], ["REFUSEE", "#ff5a4a"], ["TERMINEE", "#5cc76b"]]) getOrDefault [_stt, "#cfd8d3"];
                    private _lbl = (createHashMapFromArray [["RECUE", "REÇUE"], ["PRISE", "PRISE EN COMPTE"], ["REFUSEE", "REFUSÉE"], ["TERMINEE", "TERMINÉE"]]) getOrDefault [_stt, _stt];
                    format ["<t size='1.25'><t color='%1'>%2Z</t> <t font='COMSPEC_BarlowBold'>%3</t> %4%5<t align='right' color='%6' font='COMSPEC_BarlowBold'>%7</t></t><br/><t size='1' color='%1'>   %8%9</t>",
                        _muted, _t, [_k] call _esc, [_by] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), _c, _lbl, [_txt select [0, 90]] call _esc, ["", " · réponse : " + ([_ans] call _esc)] select (_ans isNotEqualTo "")]
                };
                if ((count _rows) isEqualTo 0) then { _rows = [format ["<t size='1.2' color='%1'>Aucune demande reçue des éléments</t>", _muted]]; };
                [(_rows select [0, 9]) joinString "<br/>", (_rows select [9, 9]) joinString "<br/>"]
            };
            default { ["", ""] };
        }
    };
    default { false };
};
