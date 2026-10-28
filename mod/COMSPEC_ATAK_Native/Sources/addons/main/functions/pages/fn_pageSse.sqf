/*
    App SSE (exploitation de site), console d'analyste : fond ardoise, panneaux denses, données d'abord.
      Bandeau     : dossier actif, carroyage, heure, état du terminal SEEK et du verrou sur un sujet ;
      Indicateurs : interrogations, concordances confirmées / possibles, entités distinctes ;
      Entité      : sujet visé (homme à moins de 6 m, vivant ou non), attributs, photo du visage, fiche et biométrie ;
      Graphe      : liens dossier → sujet en vue → personnes déjà interrogées par le groupe ;
      Dossier     : référence transmise à toute l'équipe ;   Production : fiches rapides FRS pré-remplies ;
      Terminal    : accès direct aux pages du terminal SEEK ; Chronologie : interrogations du groupe.
    Téléphone en paysage : deux colonnes. Les actions restent celles de fn_sseAction.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _wide = _l getOrDefault ["landscape", false];
private _d = [] call comspec_atak_native_fnc_display;

// Palette de la console (alignée sur le bureau SSE d'Athena).
private _cBg = [0.027, 0.035, 0.043, 1];
private _cPanel = [0.071, 0.09, 0.114, 1];
private _cElev = [0.102, 0.129, 0.165, 1];
private _cLine = [0.17, 0.21, 0.26, 1];
private _cBlue = [0.29, 0.64, 1, 1];
private _cRed = [0.9, 0.28, 0.23, 1];
private _cAmber = [0.95, 0.67, 0.2, 1];
private _cGreen = [0.2, 0.83, 0.6, 1];
private _cMute = [0.36, 0.42, 0.48, 1];
private _hDim = "#8b98a7";
private _hMute = "#5c6b7a";
private _hBlue = "#4aa3ff";
private _hRed = "#e5483a";
private _hAmber = "#f2ab33";
private _hGreen = "#34d399";
private _mono = "EtelkaMonospacePro";
private _monoB = "EtelkaMonospaceProBold";
private _hairH = pixelH * 2;
private _hairW = pixelH * 2 * _ratio;
private _gap = _pad * 0.6;
private _btnH = _font * 1.45;

private _bgAll = ["COMSPEC_RscText", [0, 0, _bw, _bh]] call comspec_atak_native_fnc_pageCtrl;
_bgAll ctrlSetBackgroundColor _cBg;
private _grp = ["COMSPEC_RscControlsGroup", [0, 0, _bw, _bh]] call comspec_atak_native_fnc_pageCtrl;
private _fields = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];

private _mk = {
    params ["_class", "_pos", ["_text", ""]];
    private _c = _d ctrlCreate [_class, -1, _grp];
    _c ctrlSetPosition _pos;
    if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
    _c ctrlCommit 0;
    _c
};
private _box = {
    params ["_pos", "_col"];
    private _c = ["COMSPEC_RscText", _pos] call _mk;
    _c ctrlSetBackgroundColor _col;
    _c ctrlEnable false;
    _c
};
// Texte structuré ; hauteur ajustée au contenu (au moins _minH) quand _fit.
private _st = {
    params ["_pos", "_txt", ["_fit", true], ["_minH", 0]];
    private _c = ["COMSPEC_RscStructuredText", _pos] call _mk;
    _c ctrlSetStructuredText parseText ((_txt) call comspec_atak_native_fnc_tr);
    _c ctrlEnable false;
    if (_fit) then {
        _pos params ["_sx", "_sy", "_sw"];
        _c ctrlSetPosition [_sx, _sy, _sw, (ctrlTextHeight _c) max _minH];
        _c ctrlCommit 0;
    };
    _c
};
private _hOf = { (ctrlPosition _this) select 3 };
// Bouton : style "" (neutre), "main" (action principale, bleu), "warn" ; clic différé d'une image comme fn_formRender
// (l'action redessine souvent la page, ce qui supprimait le bouton pendant son propre gestionnaire).
private _btn = {
    params ["_pos", "_label", "_code", ["_style", ""], ["_en", true], ["_tip", ""]];
    private _b = ["COMSPEC_RscButton", _pos, _label] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.82) max COMSPEC_ATAK_FontMin);
    switch (true) do {
        case (!_en): { _b ctrlSetBackgroundColor [0.06, 0.075, 0.095, 1]; _b ctrlSetTextColor [0.36, 0.42, 0.48, 1]; };
        case (_style isEqualTo "main"): { _b ctrlSetBackgroundColor [0.16, 0.42, 0.78, 1]; _b ctrlSetTextColor [1, 1, 1, 1]; };
        default { _b ctrlSetBackgroundColor _cElev; _b ctrlSetTextColor [0.9, 0.93, 0.95, 1]; };
    };
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b ctrlEnable _en;
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_click", {}]]] call CBA_fnc_execNextFrame;
    }];
    _b
};
// Rangée de boutons de même largeur : [[libellé, code, style, actif, aide]...] ; renvoie la hauteur.
private _btnRow = {
    params ["_rx0", "_ry0", "_rw0", "_list"];
    private _n = (count _list) max 1;
    private _g = _pad * 0.35;
    private _bwid = (_rw0 - (_n - 1) * _g) / _n;
    { _x params ["_lab", "_code", ["_style", ""], ["_en", true], ["_tip", ""]]; [[_rx0 + _forEachIndex * (_bwid + _g), _ry0, _bwid, _btnH], _lab, _code, _style, _en, _tip] call _btn; } forEach _list;
    _btnH
};
// Panneau : liseré de couleur, en-tête (titre et repère à droite), filet, puis le corps ; renvoie la hauteur totale.
// Le corps est appelé avec [x, y, largeur] et renvoie la hauteur qu'il occupe.
private _panel = {
    params ["_px", "_py", "_pw", "_title", "_meta", "_tone", "_body"];
    private _bg = [[_px, _py, _pw, _font], _cPanel] call _box;
    [[_px, _py, _pw, _hairH], _tone] call _box;
    private _ip = _pad * 0.7;
    // Hauteur de l'en-tête mesurée sur le titre : un titre long passe à la ligne sans être coupé par le filet.
    private _tw = if (_meta isEqualTo "") then { _pw - 2 * _ip } else { _pw * 0.7 - _ip };
    private _tc = [[_px + _ip, _py + _hairH + _fs * 0.15, _tw, _fs], format ["<t font='%1' size='0.68' color='%2'>%3</t>", _monoB, _hDim, toUpper _title]] call _st;
    private _hh = ((_tc call _hOf) + _hairH + _fs * 0.3) max (_fs * 1.5);
    if (_meta isNotEqualTo "") then {
        [[_px + _pw * 0.5, _py + _hairH + _fs * 0.15, _pw * 0.5 - _ip, _fs], format ["<t font='%1' size='0.64' align='right' color='%2'>%3</t>", _mono, _hMute, toUpper _meta]] call _st;
    };
    [[_px, _py + _hh, _pw, pixelH], _cLine] call _box;
    private _h = [_px + _ip, _py + _hh + _ip, _pw - 2 * _ip] call _body;
    private _total = _hh + _ip + _h + _ip;
    _bg ctrlSetPosition [_px, _py, _pw, _total];
    _bg ctrlCommit 0;
    _total
};

// ─── Bandeau ───────────────────────────────────────────────────────────────────────────────────────────
private _okMod = !isNil "comspec_overwatch_connect_fnc_sseOpenTerminal";
private _case = "";
private _t = objNull;
private _item = false;
if (_okMod) then {
    _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
    _t = [] call comspec_atak_native_fnc_sseAction;
    _item = [] call comspec_overwatch_connect_fnc_sseHasTerminalItem;
};
private _chip = { params ["_on", "_yes", "_no", ["_onHex", _hGreen]]; format ["<t font='%1' size='0.62' color='%2'>■ %3</t>", _monoB, [_hMute, _onHex] select _on, [_no, _yes] select _on] };
// Bandeau : fond et filet posés d'abord (derrière), puis recalés sur la hauteur réelle du texte.
private _hdH = _font * 2.15;
private _hdBg = [[0, 0, _bw, _hdH], _cPanel] call _box;
private _hdLine = [[0, _hdH - _hairH, _bw, _hairH], _cBlue] call _box;
private _hdA = [[_pad, _font * 0.15, _bw * 0.6 - _pad, _font], format ["<t font='PuristaBold' size='1.05'>SSE</t><t font='PuristaSemibold' size='0.8' color='%1'>  //  EXPLOITATION DE SITE</t><br/><t font='%2' size='0.62' color='%1'>OPS %3 · GR %4 · %5</t>",
    _hDim, _mono, [format ["<t color='%1'>%2</t>", _hGreen, toUpper _case], "NON CLASSÉ"] select (_case isEqualTo ""), [player, 8] call comspec_atak_native_fnc_gridRef, [dayTime, "HH:MM"] call BIS_fnc_timeToString]] call _st;
private _hdB = [[_bw * 0.6, _font * 0.2, _bw * 0.4 - _pad, _font], format ["<t align='right'>%1<br/>%2</t>",
    [_item, "SEEK PRÊT", "SEEK ABSENT", _hGreen] call _chip, [!isNull _t, "CIBLE ACQUISE", "AUCUNE CIBLE", _hBlue] call _chip]] call _st;
_hdH = (((_hdA call _hOf) max (_hdB call _hOf)) + _font * 0.4) max _hdH;
_hdBg ctrlSetPosition [0, 0, _bw, _hdH];
_hdBg ctrlCommit 0;
_hdLine ctrlSetPosition [0, _hdH - _hairH, _bw, _hairH];
_hdLine ctrlCommit 0;
private _y = _hdH + _gap;

if (!_okMod) exitWith {
    [_pad, _y, _bw - 2 * _pad, "Module indisponible", "SEEK", _cRed, {
        params ["_ox", "_oy", "_ow"];
        ([[_ox, _oy, _ow, _fs], format ["<t size='0.85' color='%1'>Le module SSE d'Overwatch n'est pas chargé. Relancez build_mod.bat puis le jeu avec @COMSPEC_ATAK_Native.</t>", _hDim]] call _st) call _hOf
    }] call _panel;
    true
};

// ─── Données ───────────────────────────────────────────────────────────────────────────────────────────
private _hist = (group player) getVariable ["COMSPEC_SeekQueryHistory", []];
if (!(_hist isEqualType []) || {(count _hist) isEqualTo 0}) then { _hist = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []]; };
if (!(_hist isEqualType [])) then { _hist = []; };
// Une ligne par sujet (fn_seekHistoryPush regroupe) : le nombre d'interrogations est en [10].
private _nQueries = 0;
{ _nQueries = _nQueries + ((_x param [10, 1]) max 1); } forEach _hist;
private _rank = createHashMapFromArray [["confirmed", 2], ["possible", 1]];
private _resHex = { switch (_this) do { case "confirmed": { _hRed }; case "possible": { _hAmber }; default { _hDim }; } };
private _resCol = { switch (_this) do { case "confirmed": { _cRed }; case "possible": { _cAmber }; default { _cMute }; } };
// Entités interrogées : nom → [interrogations, pire verdict, statut, confiance, heure, alias] (historique du plus récent au plus ancien).
private _ents = createHashMap;
private _order = [];
{
    _x params [["_tick", 0], ["_clock", ""], ["_who", "?"], ["_alias", ""], ["_status", ""], ["_conf", 0], ["_ref", ""], ["_grid", ""], ["_res", "none"]];
    if !(_who in _ents) then { _ents set [_who, [0, "none", _status, _conf, _clock, _alias]]; _order pushBack _who; };
    private _e = _ents get _who;
    _e set [0, (_e select 0) + ((_x param [10, 1]) max 1)];
    if ((_rank getOrDefault [_res, 0]) > (_rank getOrDefault [_e select 1, 0])) then { _e set [1, _res]; _e set [2, _status]; _e set [3, _conf]; };
} forEach _hist;
private _nConf = { (_x param [8, "none"]) isEqualTo "confirmed" } count _hist;
private _nPoss = { (_x param [8, "none"]) isEqualTo "possible" } count _hist;
private _whoT = "";
if (!isNull _t) then {
    _whoT = trim format ["%1 %2", _t getVariable ["COMSPEC_SSE_FirstName", ""], _t getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_whoT isEqualTo "") then { _whoT = name _t; };
};
private _dist = [0, round (player distance _t)] select !isNull _t;
private _faceOk = !isNull _t && {_dist <= 4} && {!isNil "comspec_overwatch_connect_fnc_sseCaptureFacePhoto"};

// ─── Indicateurs ───────────────────────────────────────────────────────────────────────────────────────
private _kpis = [
    [_nQueries, "INTERROGATIONS", "#e8edf2", _cBlue],
    [_nConf, "CONFIRMÉES", [_hMute, _hRed] select (_nConf > 0), [_cMute, _cRed] select (_nConf > 0)],
    [_nPoss, "POSSIBLES", [_hMute, _hAmber] select (_nPoss > 0), [_cMute, _cAmber] select (_nPoss > 0)],
    [count _order, "ENTITÉS", "#e8edf2", _cMute]
];
private _kg = _pad * 0.35;
// Quatre tuiles sur une ligne ; écran étroit (téléphone en portrait, mode PETIT) : deux lignes de deux, sinon les
// libellés se coupaient au milieu des mots (« QUER IES », « CONF IRME D »).
private _kCols = [4, 2] select (((_bw - 2 * _pad - 3 * _kg) / 4) < _font * 4.6);
private _kw = (_bw - 2 * _pad - (_kCols - 1) * _kg) / _kCols;
// Tuiles mesurées sur leur texte (chiffre + libellé) : le libellé n'est plus coupé en bas.
private _kh = _font * 2.05;
private _kTiles = [];
private _kTxtH = 0;
{
    _x params ["_n", "_lab", "_hex", "_col"];
    private _kx = _pad + (_forEachIndex mod _kCols) * (_kw + _kg);
    private _bx = [[_kx, _y, _kw, _kh], _cPanel] call _box;
    private _hl = [[_kx, _y, _kw, _hairH], _col] call _box;
    private _kt = [[_kx, _y + _font * 0.2, _kw, _font], format ["<t align='center' font='PuristaBold' size='1.45' color='%1'>%2</t><br/><t align='center' font='%3' size='0.55' color='%4'>%5</t>", _hex, _n, _mono, _hDim, _lab]] call _st;
    _kTxtH = _kTxtH max (_kt call _hOf);
    _kTiles pushBack [_bx, _hl, _kt, floor (_forEachIndex / _kCols)];
} forEach _kpis;
_kh = (_kTxtH + _font * 0.45) max _kh;
{
    _x params ["_bx", "_hl", "_kt", "_row"];
    private _dy = _row * (_kh + _kg);
    { private _p = ctrlPosition _x; _p set [1, (_p select 1) + _dy]; _x ctrlSetPosition _p; _x ctrlCommit 0; } forEach [_hl, _kt];
    private _p = ctrlPosition _bx; _p set [1, (_p select 1) + _dy]; _p set [3, _kh]; _bx ctrlSetPosition _p; _bx ctrlCommit 0;
} forEach _kTiles;
_y = _y + (ceil ((count _kpis) / _kCols)) * (_kh + _kg) - _kg + _gap;

// ─── Panneaux ──────────────────────────────────────────────────────────────────────────────────────────
// Entité active : le sujet devant le joueur.
private _pEntity = {
    params ["_ox", "_oy", "_ow"];
    private _ah = _font * 2.5;
    private _aw = _ah * _ratio;
    private _tone = [_cBlue, _cMute] select (isNull _t);
    [[_ox, _oy, _aw, _ah], _cElev] call _box;
    [[_ox, _oy, _hairW * 1.5, _ah], _tone] call _box;
    private _ini = "?";
    if (!isNull _t) then { _ini = toUpper ((((_whoT splitString " ") select [0, 2]) apply { _x select [0, 1] }) joinString ""); };
    [[_ox, _oy + _ah * 0.22, _aw, _ah], format ["<t align='center' font='PuristaBold' size='1.4' color='%1'>%2</t>", [_hBlue, _hMute] select (isNull _t), _ini], false] call _st;
    private _tx = _ox + _aw + _pad * 0.6;
    private _tw = _ow - _aw - _pad * 0.6;
    if (isNull _t) exitWith {
        private _c = [[_tx, _oy, _tw, _ah], format ["<t font='%1' size='0.62' color='%2'>ENTITÉ · PERSONNE</t><br/><t font='COMSPEC_BarlowBold' color='%3'>Aucune entité en vue</t><br/><t size='0.78' color='%2'>Regardez une personne à moins de 6 m. Le terminal s'ouvrira sans sujet.</t>", _mono, _hDim, _hMute]] call _st;
        (_c call _hOf) max _ah
    };
    [[_tx, _oy, _tw, _ah], format ["<t font='%1' size='0.62' color='%2'>ENTITÉ · PERSONNE</t><br/><t font='COMSPEC_BarlowBold' size='1.15'>%3</t><br/><t font='%1' size='0.62' color='%2'>%4 · %5 M · GR %6</t>",
        _mono, _hDim, _whoT, ["<t color='#e5483a'>DÉCÉDÉ</t>", "<t color='#34d399'>VIVANT</t>"] select (alive _t), _dist, [_t, 8] call comspec_atak_native_fnc_gridRef]] call _st;
    private _yy = _oy + _ah + _pad * 0.5;
    // Attributs, deux par ligne.
    private _meta = _t getVariable ["comspec_sse_facePhotoMeta", []];
    private _face = format ["<t color='%1'>aucun cliché</t>", _hMute];
    if ((_meta isEqualType []) && {(count _meta) >= 3}) then {
        _face = format ["<t color='%1'>%2</t> <t color='%3'>Q%4</t>", _hGreen, _meta select 2, _hDim, _meta select 1];
    };
    private _known = _ents getOrDefault [_whoT, []];
    private _prior = if ((count _known) > 0) then {
        format ["<t color='%1'>%2%3</t>", (_known select 1) call _resHex, _known select 2, ["", format [" %1 %2", _known select 3, "%"]] select ((_known select 1) isNotEqualTo "none")]
    } else { format ["<t color='%1'>jamais interrogé</t>", _hMute] };
    private _attrs = [
        ["VISAGE", _face],
        ["CAPTURE", [format ["<t color='%1'>tête découverte</t>", _hMute], format ["<t color='%1'>sac sur la tête</t>", _hAmber]] select (_t getVariable ["COMSPEC_SSE_HeadBagged", false])],
        ["ANTÉCÉDENTS", _prior],
        ["DOSSIER", [format ["<t color='%1'>%2</t>", _hGreen, toUpper _case], format ["<t color='%1'>non classé</t>", _hMute]] select (_case isEqualTo "")]
    ];
    private _cg = _pad * 0.35;
    private _cw = (_ow - _cg) / 2;
    private _ch = _fs * 2.3;
    // Cases posées puis recalées sur le texte le plus haut (valeur sur deux lignes : plus de débordement).
    private _cells = [];
    private _cTxt = [];
    {
        _x params ["_lab", "_val"];
        private _cx = _ox + (_forEachIndex mod 2) * (_cw + _cg);
        _cells pushBack ([[_cx, _yy, _cw, _ch], _cElev] call _box);
        private _ct = [[_cx + _pad * 0.4, _yy + _fs * 0.15, _cw - _pad * 0.5, _fs], format ["<t font='%1' size='0.55' color='%2'>%3</t><br/><t size='0.82'>%4</t>", _mono, _hMute, _lab, _val]] call _st;
        _cTxt pushBack _ct;
        _ch = _ch max ((_ct call _hOf) + _fs * 0.3);
    } forEach _attrs;
    {
        private _row = floor (_forEachIndex / 2);
        private _p = ctrlPosition _x;
        _x ctrlSetPosition [_p select 0, _yy + _row * (_ch + _cg), _p select 2, _ch];
        _x ctrlCommit 0;
        private _q = ctrlPosition (_cTxt select _forEachIndex);
        (_cTxt select _forEachIndex) ctrlSetPosition [_q select 0, _yy + _row * (_ch + _cg) + _fs * 0.15, _q select 2, _q select 3];
        (_cTxt select _forEachIndex) ctrlCommit 0;
    } forEach _cells;
    _yy = _yy + 2 * (_ch + _cg) + _pad * 0.2;
    // Actions de nos autres opérateurs sur ce même sujet (journal d'équipe du terminal SEEK, fn_sseTeamLog).
    private _team = if (isNil "comspec_overwatch_connect_fnc_sseTeamLog") then { [] } else { ["get", _t] call comspec_overwatch_connect_fnc_sseTeamLog };
    if ((count _team) > 0) then {
        private _lines = (_team select [0, 4]) apply {
            _x params [["_hh", ""], ["_cs", ""], ["_uid", ""], ["_what", ""], ["_det", ""]];
            format ["<t font='%1' size='0.62' color='%2'>%3</t>  <t color='%4'>%5</t> · %6<t size='0.75' color='%2'>%7</t>", _mono, _hMute, _hh,
                [_hBlue, _hDim] select (_uid isEqualTo getPlayerUID player), [[_cs, "<", "&lt;"] call CBA_fnc_replace, "vous"] select (_uid isEqualTo getPlayerUID player), _what, ["", format [" · %1", _det]] select (_det isNotEqualTo "")]
        };
        _yy = _yy + (([[_ox, _yy, _ow, _fs], format ["<t font='%1' size='0.55' color='%2'>ÉQUIPE SUR CE SUJET</t><br/><t size='0.8'>%3</t>", _mono, _hMute, _lines joinString "<br/>"]] call _st) call _hOf) + _pad * 0.3;
    };
    if (_dist > 4) then {
        _yy = _yy + (([[_ox, _yy, _ow, _fs], format ["<t size='0.78' color='%1'>▲ Approchez-vous à 4 m au plus pour photographier le visage.</t>", _hAmber]] call _st) call _hOf) + _pad * 0.2;
    };
    _yy = _yy + ([_ox, _yy, _ow, [
        ["PHOTO DU VISAGE", { ['face'] call comspec_atak_native_fnc_sseAction; }, "main", _faceOk, "Cliché de face sur la tête du sujet, joint à sa fiche SEEK. Le téléphone se range le temps du cliché. Pour choisir le profil ou les trois quarts, ou voir le viseur, utilisez la page PHOTO du terminal SEEK."],
        ["FICHE SUJET", { ['open', 1] call comspec_atak_native_fnc_sseAction; }, "", _item, "Ouvre le terminal SEEK sur la fiche du sujet"],
        ["BIOMÉTRIE", { ['open', 3] call comspec_atak_native_fnc_sseAction; }, "", _item, "Ouvre le terminal SEEK sur les relevés biométriques"]
    ]] call _btnRow);
    _yy - _oy
};

// Graphe de liens : dossier → entités (sujet en vue, puis personnes interrogées), arêtes à angle droit.
private _pGraph = {
    params ["_ox", "_oy", "_ow"];
    private _nh = _fs * 1.9;
    private _rw = _ow * 0.7;
    [[_ox, _oy, _rw, _nh], _cElev] call _box;
    [[_ox, _oy, _hairW * 1.5, _nh], [_cMute, _cGreen] select (_case isNotEqualTo "")] call _box;
    [[_ox + _pad * 0.5, _oy + _nh * 0.18, _rw - _pad * 0.6, _nh], format ["<t font='%1' size='0.55' color='%2'>DOSSIER  </t><t font='%3' size='0.75' color='%4'>%5</t>", _mono, _hMute, _monoB, [_hMute, _hGreen] select (_case isNotEqualTo ""), [toUpper _case, "NON CLASSÉ"] select (_case isEqualTo "")], false] call _st;
    private _nodes = [];
    if (!isNull _t) then {
        private _k = _ents getOrDefault [_whoT, [0, "none", "", 0, "", ""]];
        _nodes pushBack [_whoT, "EN VUE", _hBlue, _cBlue, _k select 0, _k select 1, _k select 3];
    };
    {
        if (_x isNotEqualTo _whoT) then {
            private _e = _ents get _x;
            _nodes pushBack [[_x, format ["%1 « %2 »", _x, _e select 5]] select ((_e select 5) isNotEqualTo ""), _e select 4, (_e select 1) call _resHex, (_e select 1) call _resCol, _e select 0, _e select 1, _e select 3];
        };
    } forEach _order;
    private _more = ((count _nodes) - 6) max 0;
    _nodes = _nodes select [0, 6];
    if ((count _nodes) isEqualTo 0) exitWith {
        _nh + _pad * 0.4 + (([[_ox, _oy + _nh + _pad * 0.4, _ow, _fs], format ["<t size='0.78' color='%1'>Aucune entité liée. Visez un sujet ou interrogez la base depuis le terminal SEEK.</t>", _hMute]] call _st) call _hOf)
    };
    private _step = _nh + _pad * 0.4;
    private _tx = _ox + _ow * 0.05;
    private _cx = _ox + _ow * 0.12;
    private _y0 = _oy + _nh;
    private _yLast = _y0 + _pad * 0.4 + ((count _nodes) - 1) * _step + _nh / 2;
    [[_tx, _y0, _hairW, _yLast - _y0], _cLine] call _box;
    {
        _x params ["_lab", "_tag", "_hex", "_col", "_n", "_res", "_conf"];
        private _ny = _y0 + _pad * 0.4 + _forEachIndex * _step;
        [[_tx, _ny + _nh / 2, _cx - _tx, _hairH], _cLine] call _box;
        [[_cx, _ny, _ox + _ow - _cx, _nh], _cElev] call _box;
        [[_cx, _ny, _hairW * 1.5, _nh], _col] call _box;
        private _sq = _nh * 0.3;
        [[_cx - _sq * _ratio * 0.5, _ny + (_nh - _sq) / 2, _sq * _ratio, _sq], _col] call _box;
        private _right = if (_n > 0) then {
            format ["<t color='%1'>%2 REQ</t>%3", _hDim, _n, ["", format ["  <t color='%1'>%2 %3</t>", _hex, _conf, "%"]] select (_res isNotEqualTo "none")]
        } else { format ["<t color='%1'>NOUVEAU</t>", _hMute] };
        [[_cx + _pad * 0.5, _ny + _nh * 0.2, (_ox + _ow - _cx) * 0.6, _nh], format ["<t size='0.82' font='COMSPEC_BarlowBold'>%1</t> <t font='%2' size='0.5' color='%3'>%4</t>", _lab, _mono, _hex, _tag], false] call _st;
        [[_cx + (_ox + _ow - _cx) * 0.55, _ny + _nh * 0.28, (_ox + _ow - _cx) * 0.45 - _pad * 0.4, _nh], format ["<t align='right' font='%1' size='0.58'>%2</t>", _mono, _right], false] call _st;
    } forEach _nodes;
    private _h = _yLast + _nh / 2 - _oy;
    if (_more > 0) then { _h = _h + _pad * 0.3 + (([[_cx, _oy + _h + _pad * 0.3, _ox + _ow - _cx, _fs], format ["<t font='%1' size='0.58' color='%2'>+ %3 AUTRES ENTITÉS DANS LA CHRONOLOGIE</t>", _mono, _hMute, _more]] call _st) call _hOf); };
    _h
};

// Dossier de l'opération : référence partagée par toute l'équipe.
private _pCase = {
    params ["_ox", "_oy", "_ow"];
    private _yy = _oy;
    _yy = _yy + (([[_ox, _yy, _ow, _fs], format ["<t size='0.78' color='%1'>Le poste de commandement vous donne une référence (ex. OBJ-ALPHA-01). Toutes les fiches SSE de l'équipe (sujets, photos, constats) y seront rattachées sur Athena.</t>", _hDim]] call _st) call _hOf) + _pad * 0.4;
    // Saisie gardée telle quelle (pas de traduction du contenu).
    private _e = ["COMSPEC_RscEdit", [_ox, _yy, _ow, _btnH]] call _mk;
    _e ctrlSetText (["", profileNamespace getVariable ["COMSPEC_SseLastCaseCode", ""]] select (_case isEqualTo "")); // i18n:brut
    _e ctrlSetFont _mono;
    _e ctrlSetFontHeight (_fs * 0.9);
    _e ctrlSetBackgroundColor _cBg;
    _fields set ["sseCase", _e];
    _yy = _yy + _btnH + _pad * 0.35;
    _yy = _yy + ([_ox, _yy, _ow, [
        ["UTILISER CE DOSSIER", { ['case'] call comspec_atak_native_fnc_sseAction; }, "main", true, "Transmet la référence à toute votre équipe"],
        ["NE PLUS CLASSER", { ['clear'] call comspec_atak_native_fnc_sseAction; }, "", _case isNotEqualTo ""]
    ]] call _btnRow);
    _yy - _oy
};

// Production : fiches rapides FRS pré-remplies (dossier, lieu, carroyage, sujet visé).
private _pFrs = {
    params ["_ox", "_oy", "_ow"];
    private _h = [_ox, _oy, _ow, [["CRÉER UNE FICHE FRM", { ['frs', 'FRM'] call comspec_atak_native_fnc_sseAction; }, "main", true, "Fiche de renseignement multi-sources, ouverte dans l'app FRS"]]] call _btnRow;
    _h + _pad * 0.35 + ([_ox, _oy + _h + _pad * 0.35, _ow, [
        ["CONTACT (FRC)", { ['frs', 'FRC'] call comspec_atak_native_fnc_sseAction; }],
        ["OBSERVATION (FRO)", { ['frs', 'FRO'] call comspec_atak_native_fnc_sseAction; }],
        ["TECHNIQUE (FRT)", { ['frs', 'FRT'] call comspec_atak_native_fnc_sseAction; }]
    ]] call _btnRow)
};

// Terminal SEEK : modules numérotés comme les pages du terminal.
private _pSeek = {
    params ["_ox", "_oy", "_ow"];
    private _yy = _oy;
    if (!_item) then {
        _yy = _yy + (([[_ox, _yy, _ow, _fs], format ["<t size='0.78' color='%1'>▲ Terminal SEEK absent : récupérez l'appareil dans votre équipement.</t>", _hAmber]] call _st) call _hOf) + _pad * 0.3;
    };
    _yy = _yy + ([_ox, _yy, _ow, [["OUVRIR LE TERMINAL SEEK", { ['open', 0] call comspec_atak_native_fnc_sseAction; }, "main", _item, "Range le téléphone et ouvre le terminal sur son accueil"]]] call _btnRow) + _pad * 0.35;
    private _mods = [["01 SUJET", 1], ["03 BIOMÉTRIE", 3], ["05 PHOTO", 5], ["04 CONSTAT", 4], ["06 DOSSIER", 6], ["07 TERRAIN", 7]];
    {
        _yy = _yy + ([_ox, _yy, _ow, _x apply { [_x select 0, compile format ["['open', %1] call comspec_atak_native_fnc_sseAction;", _x select 1], "", _item] }] call _btnRow) + _pad * 0.35;
    } forEach [_mods select [0, 3], _mods select [3, 3]];
    _yy - _oy - _pad * 0.35
};

// Chronologie : interrogations de l'élément (partagées par le groupe), la plus récente en haut.
private _pTimeline = {
    params ["_ox", "_oy", "_ow"];
    if ((count _hist) isEqualTo 0) exitWith {
        ([[_ox, _oy, _ow, _fs], format ["<t size='0.78' color='%1'>Aucune interrogation pour l'instant. Prenez un relevé biométrique puis interrogez la base depuis le terminal.</t>", _hMute]] call _st) call _hOf
    };
    private _tcw = _ow * 0.17;
    private _rx = _ox + _tcw;
    private _ex = _rx + _pad * 0.7;
    private _rail = [[_rx, _oy, _hairW, _fs], _cLine] call _box;
    private _yy = _oy;
    private _lastMid = _oy;
    {
        _x params [["_tick", 0], ["_clock", ""], ["_who", "?"], ["_alias", ""], ["_status", ""], ["_conf", 0], ["_ref", ""], ["_grid", ""], ["_res", "none"], ["_key", ""], ["_n", 1]];
        private _c = [[_ex, _yy, _ox + _ow - _ex, _fs], format ["<t size='0.82' font='COMSPEC_BarlowBold'>%1</t>%2  <t size='0.78' color='%3'>%4%5</t><br/><t font='%6' size='0.55' color='%7'>GR %8%9%10</t>",
            _who, ["", format [" <t color='%1'>« %2 »</t>", _hDim, _alias]] select (_alias isNotEqualTo ""), _res call _resHex, _status, ["", format [" %1 %2", _conf, "%"]] select (_res isNotEqualTo "none"),
            _mono, _hMute, [_grid, "—"] select (_grid isEqualTo ""), ["", format [" · %1", _ref]] select (_ref isNotEqualTo ""), ["", format [" · ×%1", _n]] select (_n > 1)], true, _fs * 1.6] call _st;
        [[_ox, _yy + _fs * 0.1, _tcw - _pad * 0.3, _fs], format ["<t font='%1' size='0.6' color='%2'>%3</t>", _mono, _hDim, _clock], false] call _st;
        private _sq = _fs * 0.45;
        _lastMid = _yy + _fs * 0.5;
        [[_rx - _sq * _ratio * 0.5 + _hairW * 0.5, _lastMid - _sq / 2, _sq * _ratio, _sq], _res call _resCol] call _box;
        _yy = _yy + (_c call _hOf) + _pad * 0.35;
    } forEach (_hist select [0, 12]);
    _rail ctrlSetPosition [_rx, _oy + _fs * 0.5, _hairW, (_lastMid - _oy - _fs * 0.5) max 0];
    _rail ctrlCommit 0;
    _yy - _oy - _pad * 0.35
};

private _panels = [
    ["Entité active", ["aucun verrou", "verrou à 6 m"] select (!isNull _t), [_cMute, _cBlue] select (!isNull _t), _pEntity, 0],
    ["Graphe de liens", format ["%1 nœuds", (count _order) + ([0, 1] select (!isNull _t && {!(_whoT in _ents)})) + 1], _cBlue, _pGraph, 0],
    ["Dossier de l'opération", ["non classé", "actif"] select (_case isNotEqualTo ""), [_cMute, _cGreen] select (_case isNotEqualTo ""), _pCase, 1],
    ["Production · fiches rapides", "FRS", _cMute, _pFrs, 1],
    ["Terminal SEEK", ["hors dotation", "prêt"] select _item, [_cAmber, _cGreen] select _item, _pSeek, 1],
    ["Chronologie · interrogations", format ["%1 dans le groupe", _nQueries], _cMute, _pTimeline, [0, 1] select _wide]
];
private _cols = if (_wide) then {
    private _cw = (_bw - 2 * _pad - _gap) / 2;
    [[_pad, _cw, _y], [_pad + _cw + _gap, _cw, _y]]
} else { [[_pad, _bw - 2 * _pad, _y]] };
{
    _x params ["_title", "_meta", "_tone", "_body", "_col"];
    private _c = _cols select ([0, _col] select _wide);
    _c params ["_cx", "_cw", "_cy"];
    private _h = [_cx, _cy, _cw, _title, _meta, _tone, _body] call _panel;
    _c set [2, _cy + _h + _gap];
} forEach _panels;
// Espace en bas pour que le dernier panneau ne colle pas au bord en fin de défilement.
private _end = selectMax (_cols apply { _x select 2 });
[[0, _end, _bw, _pad], [0, 0, 0, 0]] call _box;
uiNamespace setVariable ["COMSPEC_ATAK_Form", _fields];
true
