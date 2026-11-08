/*
    Écran simplifié posé sur la carte du téléphone (appelé par fn_pageMap quand l'état « quickView » est posé) :
      "PILOT" mode pilote (fn_pilotMode) : grande carte, appareils alliés, ma position, FIRES en un geste, missions reçues ;
      "FIRES" FIRES court (fn_firesQuick) : le panneau cible, munition et envoi est ouvert d'office.
    Même contrôle carte et mêmes pistes que l'app Carte : rien que le téléphone n'aurait déjà. Couleurs du thème (sombre, jour,
    JVN), textes au plancher de 14 px. Boutons seulement téléphone en main ; porté : la carte et les textes.
    Params : ["render"] (fn_pageMap) ; ["tick"] (fn_statusUpdate, une fois par seconde) : bandeau réécrit s'il change,
    page redessinée si une mission ou un accusé a changé. Aucun coût par image hors du dessin de fn_firesQuick.
*/
params [["_act", "render"]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _view = _s getOrDefault ["quickView", ""];
private _sigOf = {
    private _in = (["missions"] call comspec_atak_native_fnc_firesQuick) apply { [_x get "ref", _x getOrDefault ["ack", ""]] };
    private _mine = ["mine"] call comspec_atak_native_fnc_firesQuick;
    private _acks = (missionNamespace getVariable ["COMSPEC_ATAK_AirSent", []]) select { (_x get "ref") in _mine } apply { [_x get "ref", values (_x get "dest")] };
    [_in, _acks]
};
private _hdr = {
    private _v = vehicle player;
    private _p = uiNamespace getVariable ["COMSPEC_ATAK_SelfGpsPos", getPosASL player];
    format ["<t font='EtelkaMonospacePro' color='%1'>%2°  %3 m  %4 km/h  %5</t>", COMSPEC_ATAK_Th get "hexMapText", [(round getDir _v) mod 360, 3] call CBA_fnc_formatNumber,
        round (((getPosASL _v) select 2) - (getTerrainHeightASL _p)) max 0, round abs speed _v, [_p, 8] call comspec_atak_native_fnc_gridRef]
};
if (_act isEqualTo "tick") exitWith {
    if (_view isEqualTo "") exitWith { false };
    ["tick"] call comspec_atak_native_fnc_firesQuick;
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_QvStrip", controlNull];
    if (!isNull _c) then {
        private _html = call _hdr;
        if ((_c getVariable ["txt", ""]) isNotEqualTo _html) then { _c setVariable ["txt", _html]; _c ctrlSetStructuredText parseText _html; }; // i18n:brut
    };
    if ((uiNamespace getVariable ["COMSPEC_ATAK_QvSig", []]) isNotEqualTo (call _sigOf)) then {
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
    };
    true
};

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_mw", "_mh"];
private _pad = (_l get "pad") * 1.6;
private _fs = (_l get "fontSmall") max COMSPEC_ATAK_FontMin;
private _tap = _l get "tap";
private _mini = _l get "mini";
private _interactive = _l get "interactive";
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _th = COMSPEC_ATAK_Th;
private _q = uiNamespace getVariable ["COMSPEC_ATAK_FiresQ", createHashMap];
private _armed = ["armed"] call comspec_atak_native_fnc_firesQuick;
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
private _btn = {
    params ["_pos", "_label", "_code", ["_main", false], ["_on", true], ["_tip", ""]];
    private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _main, _pos, _label] call _mk;
    _b ctrlSetFontHeight _fs;
    if (!_main) then { _b ctrlSetBackgroundColor (_th get "mapPanel"); _b ctrlSetTextColor ([_th get "mapDim", _th get "mapText"] select _on); };
    _b ctrlEnable _on;
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip (_tip call comspec_atak_native_fnc_tr); };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b
};
private _txt = {
    params ["_pos", "_html"];
    private _c = ["COMSPEC_RscStructuredText", _pos] call _mk;
    _c ctrlSetFontHeight _fs;
    _c ctrlSetStructuredText parseText _html; // i18n:brut (libellés déjà traduits)
    _c ctrlEnable false;
    _c
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _esc = { [[_this, "&", "&amp;"] call CBA_fnc_replace, "<", "&lt;"] call CBA_fnc_replace };
private _panel = { params ["_pos"]; private _c = ["COMSPEC_RscText", _pos] call _mk; _c ctrlSetBackgroundColor (_th get "mapPanel"); _c ctrlEnable false; _c };

// Bandeau du haut : cap, altitude, vitesse et grille.
private _stripH = _fs * 1.45;
[[_bx, _by, _mw, _stripH]] call _panel;
private _strip = [[_bx + _pad, _by + _fs * 0.08, _mw - 2 * _pad, _stripH], call _hdr] call _txt;
uiNamespace setVariable ["COMSPEC_ATAK_QvStrip", _strip];

// Colonne de droite (en main) : FIRES, zoom, centrer, sortie.
private _colW = ((_mw * 0.17) max (_fs * 5.2 / _ratio)) min (_mw * 0.32);
private _cx = _bx + _mw - _colW - _pad / _ratio * 0.5;
if (_interactive) then {
    private _y = _by + _stripH + _pad;
    private _col = [];
    if ((_view isEqualTo "PILOT") && {["can"] call comspec_atak_native_fnc_firesQuick}) then {
        _col pushBack ["FIRES", { ["panel"] call comspec_atak_native_fnc_firesQuick; }, !(_q getOrDefault ["open", false])];
    };
    _col append [
        ["+", { [0.5] call comspec_atak_native_fnc_mapZoom; }, false],
        ["–", { [2] call comspec_atak_native_fnc_mapZoom; }, false],
        ["MOI", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", true]; [player] call comspec_atak_native_fnc_mapCenter; }, false],
        ["CARTE", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", false]; [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, false]
    ];
    {
        _x params ["_lb", "_code", "_main"];
        [[_cx, _y, _colW, _tap], _lb, _code, _main] call _btn;
        _y = _y + _tap + _pad * 0.5;
    } forEach _col;
};

// Bas : panneau FIRES, puis missions au-dessus.
private _w = _mw - 2 * _pad / _ratio * 0.5 - ([0, _colW + _pad / _ratio * 0.5] select _interactive);
private _x0 = _bx + _pad / _ratio * 0.5;
private _yB = _by + _mh - _pad;
if (_interactive && {_armed}) then {
    private _per = [5, 3] select (_mini && {!(_l get "landscape")});
    private _h = _fs * 1.5 + _tap * (1 + ceil (5 / _per)) + _pad * 1.5;
    private _y = _yB - _h;
    [[_x0, _y, _w, _h]] call _panel;
    private _tg = _q getOrDefault ["target", []];
    private _n = count (["recipients"] call comspec_atak_native_fnc_firesQuick);
    private _dest = format [("%1 pilote(s) ou JTAC en ligne") call _tr, _n];
    private _head = if ((count _tg) >= 2) then {
        private _d = player distance2D _tg;
        format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t color='%3'>%4 · %5° · %6</t>", _th get "hexMapWarn", [_tg, 8] call comspec_atak_native_fnc_gridRef, _th get "hexMapText",
            [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000), [(round (player getDir _tg)) mod 360, 3] call CBA_fnc_formatNumber, _dest]
    } else {
        format ["<t color='%1'>%2</t> <t color='%3'>· %4</t>", _th get "hexMapWarn", ("Touchez la cible sur la carte") call _tr, _th get "hexMapDim", _dest]
    };
    [[_x0 + _pad * 0.3, _y + _pad * 0.2, _w - _pad * 0.6, _fs * 1.4], _head] call _txt;
    private _list = ["ammoList"] call comspec_atak_native_fnc_firesQuick;
    private _now = ["ammoNow"] call comspec_atak_native_fnc_firesQuick;
    private _cw = (_w - _pad * 0.3 * (_per + 1)) / _per;
    private _ya = _y + _fs * 1.5 + _pad * 0.3;
    {
        _x params ["_k", "_lb"];
        private _b = [[_x0 + _pad * 0.3 + (_forEachIndex mod _per) * (_cw + _pad * 0.3), _ya + (floor (_forEachIndex / _per)) * _tap, _cw, _tap], _lb, {
            params ["_c"];
            ["ammo", _c getVariable ["ammoKey", ""]] call comspec_atak_native_fnc_firesQuick;
        }, _k isEqualTo _now] call _btn;
        _b setVariable ["ammoKey", _k];
    } forEach _list;
    private _yb = _ya + _tap * (ceil (5 / _per)) + _pad * 0.3;
    private _bw3 = (_w - _pad * 1.2) / 3;
    [[_x0 + _pad * 0.3, _yb, _bw3 * 1.4, _tap], "ENVOYER", { ["send"] call comspec_atak_native_fnc_firesQuick; }, true, (count _tg) >= 2, "Ping et marqueur sur la carte, demande aux pilotes et JTAC du camp"] call _btn;
    [[_x0 + _pad * 0.6 + _bw3 * 1.4, _yb, _bw3 * 0.9, _tap], "9-LINE", { ["full"] call comspec_atak_native_fnc_firesQuick; }, false, true, "Ouvre le 9-LINE complet avec cette cible"] call _btn;
    if (_view isEqualTo "PILOT") then {
        [[_x0 + _pad * 0.9 + _bw3 * 2.3, _yb, _bw3 * 0.7, _tap], "FERMER", { ["panel"] call comspec_atak_native_fnc_firesQuick; }] call _btn;
    };
    _yB = _y - _pad * 0.4;
};

// Missions reçues : accepter et clôturer sans menu. Demandes envoyées : accusés et fin de demande.
private _rowH = _tap + _pad * 0.3;
private _rows = [];
private _max = [3, 1] select _mini;
{
    private _b = _x;
    private _ref = _b get "ref";
    private _ack = _b getOrDefault ["ack", ""];
    private _lines = _b getOrDefault ["lines", []];
    private _is5 = (_b getOrDefault ["fmt", "9"]) isEqualTo "5";
    private _tp = ["tpos", _b] call comspec_atak_native_fnc_firesQuick;
    private _where = if ((count _tp) >= 2) then {
        private _d = player distance2D _tp;
        format ["%1° %2", [(round (player getDir _tp)) mod 360, 3] call CBA_fnc_formatNumber, [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000)]
    } else { _lines param [[5, 2] select _is5, "-"] };
    private _html = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t color='%3'>%4 · %5</t>", [_th get "hexMapAlert", _th get "hexMapOk"] select (_ack isEqualTo "WILCO"), (_b getOrDefault ["from", ""]) call _esc, _th get "hexMapText", (_lines param [[4, 3] select _is5, "-"]) call _esc, _where call _esc];
    private _acts = switch (_ack) do {
        case "WILCO": { [["CLÔTURER", "DONE", true]] };
        case "UNABLE": { [["RETIRER", "DEL", false]] };
        default { [["ACCEPTER", "WILCO", true], ["IMPOSSIBLE", "UNABLE", false]] };
    };
    _rows pushBack [_html, _ref, _acts, _tp];
    if (_interactive && {!(_b getOrDefault ["read", false])}) then { ["ack", [_ref, "READ"]] call comspec_atak_native_fnc_airBrief; };
} forEach ((["missions"] call comspec_atak_native_fnc_firesQuick) select [0, _max]);
{
    private _i = (["mineInfo", _x] call comspec_atak_native_fnc_firesQuick);
    if ((count _rows) >= _max || {(count _i) < 3}) then { continue };
    private _ref = _x;
    private _e = (missionNamespace getVariable ["COMSPEC_ATAK_AirSent", []]) select { (_x get "ref") isEqualTo _ref } param [0, createHashMap];
    private _dest = _e getOrDefault ["dest", createHashMap];
    private _parts = (keys _dest) apply { format ["%1 %2", _x, (["label", _dest get _x] call comspec_atak_native_fnc_airBrief) call _tr] };
    _rows pushBack [format ["<t font='COMSPEC_BarlowBold' color='%1'>FIRES %2</t> <t color='%3'>%4</t>", _th get "hexMapWarn", _i select 2, _th get "hexMapText", (_parts joinString ", ") call _esc], _ref, [["CLORE", "CANCEL", false]], []];
} forEach (["mine"] call comspec_atak_native_fnc_firesQuick);
{
    _x params ["_html", "_ref", "_acts", "_tp"];
    private _y = _yB - _rowH * (_forEachIndex + 1);
    [[_x0, _y, _w, _rowH - _pad * 0.2]] call _panel;
    private _abw = [0, (_fs * 5.2 / _ratio) min (_w * 0.26)] select _interactive;
    private _ax = _x0 + _w - (count _acts) * (_abw + pixelW * 3);
    [[_x0 + _pad * 0.3, _y + (_rowH - _fs * 1.5) / 2, (_ax - _x0) - _pad * 0.4, _fs * 1.5], _html] call _txt;
    if (_interactive) then {
        {
            _x params ["_lb", "_code", "_main"];
            private _b = [[_ax + _forEachIndex * (_abw + pixelW * 3), _y + pixelH, _abw, _tap], _lb, {
                params ["_c"];
                (_c getVariable ["qa", []]) params ["_ref", "_code"];
                switch (_code) do {
                    case "DEL": { ["del", _ref] call comspec_atak_native_fnc_airBrief; };
                    case "CANCEL": { ["cancel", _ref] call comspec_atak_native_fnc_firesQuick; };
                    default { ["ack", [_ref, _code]] call comspec_atak_native_fnc_airBrief; };
                };
            }, _main] call _btn;
            _b setVariable ["qa", [_ref, _code]];
        } forEach _acts;
    };
} forEach _rows;

uiNamespace setVariable ["COMSPEC_ATAK_QvSig", call _sigOf];
// Pas de fiche flottante ni de réglette d'effectif sur cet écran ; la carte reste à sa place.
uiNamespace setVariable ["COMSPEC_ATAK_UnitCardGeom", []];
uiNamespace setVariable ["COMSPEC_ATAK_SquadRect", []];
uiNamespace setVariable ["COMSPEC_ATAK_MapHudRects", [[_mw, _mh], [[0, 0, _mw, _stripH]]]];
uiNamespace setVariable ["COMSPEC_ATAK_MapOverlay", createHashMap];
([] call comspec_atak_native_fnc_mapCtrl) ctrlEnable true;
if (_interactive) then { ["render"] call comspec_atak_native_fnc_mapCtxMenu; };
["tick"] call comspec_atak_native_fnc_firesQuick;
if (_view isEqualTo "PILOT" && {!(_s getOrDefault ["qvCentered", false])}) then {
    _s set ["qvCentered", true];
    _s set ["mapFollow", true];
    [player, 0.12] call comspec_atak_native_fnc_mapCenter;
};
if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, uiNamespace getVariable ["COMSPEC_ATAK_MapScale", 0.08]] call comspec_atak_native_fnc_mapCenter;
};
[] call comspec_atak_native_fnc_mapUnfocus;
true
