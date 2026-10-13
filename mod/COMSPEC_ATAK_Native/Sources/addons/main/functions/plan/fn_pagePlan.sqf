/*
    App Plan : carte de briefing statique (nord en haut, fixe), à part de la carte qui suit le joueur.
    - Haut : onglets ZONES / LIGNES / POINTS, couche visée (plan PARTAGÉ du camp ou BROUILLON local), PLANS.
    - Sous les onglets : types APP-6A de l'onglet (ENY, INFIL, OBJ, AO, PL, axe, attaque, SBF, CLEARED n...).
    - Carte : plan partagé, brouillon (atténué), aperçu d'un plan enregistré (transparent), tracé en cours.
    - Bas : consigne, couleur, épaisseur, tirets, flèche, crayon / segments, texte, TERMINER, ANNULER, DÉFAIRE, SUPPR.
    Vue PLANS : enregistrer sous un nom, aperçu, charger, publier, exporter / importer (chaîne COMSPECPLAN1).
    Porté (sans souris) : la carte seule, en lecture. Données : fn_planAction ; carte : fn_planMap ; rendu : fn_planDraw.
    Params (app de module, fn_pageRender) : [page, rectangle]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _inter = _l get "interactive";
private _ui = ["ui"] call comspec_atak_native_fnc_planAction;
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _kinds = _cat get "kinds";
private _can = ["can"] call comspec_atak_native_fnc_planAction;
private _ctrls = createHashMap;
uiNamespace setVariable ["COMSPEC_ATAK_PlanCtrls", _ctrls];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_planAction;", str _a, str _v] };
private _btn = {
    params ["_rect", "_label", "_code", ["_on", false], ["_tip", ""]];
    private _b = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight ((_fs * 0.86) max COMSPEC_ATAK_FontMin);
    if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    // Bouton cliqué : son groupe (zone de contenu) passe devant la carte et prend ses clics (aucun sommet posé).
    _b ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
    _b
};
private _rowH = (_font * 1.3) min (_bh * 0.075);
private _gap = _pad / 3;

// Vue PLANS : bibliothèque, publication, export / import.
if (_inter && {(_ui get "view") isEqualTo "LIB"}) exitWith {
    [[_pad, _pad / 2, (_bw - _pad * 2) * 0.3, _rowH], "◀ CARTE", ["view", "MAP"] call _act] call _btn;
    private _sh = ["shared"] call comspec_atak_native_fnc_planAction;
    private _dr = ["draft"] call comspec_atak_native_fnc_planAction;
    private _sharedName = (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [2, ""];
    private _lib = profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []];
    private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
    private _mode = ["tout le monde", "chefs de groupe, Zeus et admins", "Zeus et admins"] select ((missionNamespace getVariable ["comspec_atak_native_plan_edit", 1]) max 0 min 2);
    private _rows = [
        ["section", "Plan partagé du camp", format ["%1 élément(s)%2 · modifiable par : %3%4", count _sh, ["", format [" · « %1 »", [_sharedName] call _esc]] select (_sharedName isNotEqualTo ""), _mode, ["", " (vous : lecture seule)"] select !_can]],
        ["text", format ["<t size='0.85' color='#8a9a93'>%1</t>", ["state"] call comspec_atak_native_fnc_planWeb]],
        ["buttons", [["EXPORTER", ["export", "S"] call _act], ["ENREGISTRER", { ["save", "S"] call comspec_atak_native_fnc_planAction; }]]],
        ["section", "Brouillon local", format ["%1 élément(s), gardé dans votre profil : préparez le plan à l'avance, puis ajoutez-le quand le groupe se réunit", count _dr]],
        ["buttons", [["AJOUTER AU PARTAGÉ", { ["publish", ["D", false]] call comspec_atak_native_fnc_planAction; }, true, _can], ["REMPLACER LE PARTAGÉ", { ["publish", ["D", true]] call comspec_atak_native_fnc_planAction; }, false, _can]]],
        ["buttons", [["EXPORTER", ["export", "D"] call _act], ["ENREGISTRER", { ["save", "D"] call comspec_atak_native_fnc_planAction; }]]],
        ["edit", "planName", "Nom pour ENREGISTRER", _ui getOrDefault ["lastName", ""]],
        ["section", "Plans enregistrés", format ["%1 plan(s) dans votre profil (30 au plus)", count _lib]]
    ];
    {
        _x params ["_n", "_w", "_its", ["_when", ""]];
        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t> <t size='0.8' color='#8a9a93'>%2 · %3 élément(s) · %4</t>", [_n] call _esc, _w, count _its, _when]];
        private _i = _forEachIndex;
        _rows pushBack ["buttons", [
            ["APERÇU", compile format ["['preview', %1] call comspec_atak_native_fnc_planAction;", _i]],
            ["CHARGER", compile format ["['load', %1] call comspec_atak_native_fnc_planAction;", _i]],
            ["PUBLIER", compile format ["['publish', [%1, false]] call comspec_atak_native_fnc_planAction;", _i], true, _can],
            ["EXPORTER", compile format ["['export', %1] call comspec_atak_native_fnc_planAction;", _i]],
            ["SUPPR.", compile format ["['libDel', %1] call comspec_atak_native_fnc_planAction;", _i]]
        ]];
    } forEach _lib;
    _rows append [
        ["section", "Importer", "Collez une chaîne COMSPECPLAN1 (laissez vide pour lire le presse-papiers). Elle arrive dans le brouillon."],
        ["memo", "planImport", "Plan à importer", "", 3],
        ["buttons", [["IMPORTER DANS LE BROUILLON", { ["import"] call comspec_atak_native_fnc_planAction; }, true]]]
    ];
    private _exp = uiNamespace getVariable ["COMSPEC_ATAK_PlanExport", ""];
    if (_exp isNotEqualTo "") then { _rows pushBack ["memo", "planExport", "Dernier export (aussi copié dans le presse-papiers)", _exp select [0, 4000], 3]; };
    _rows append [
        ["section", "Affichage", ""],
        ["switch", "Plan sur la carte principale", profileNamespace getVariable ["COMSPEC_ATAK_PlanOnMap2", true], { ["layer"] call comspec_atak_native_fnc_planAction; }, "Plan partagé et brouillon sur l'app Carte, sous les unités"],
        ["switch", "Fond assombri (façon image ISR)", profileNamespace getVariable ["COMSPEC_ATAK_PlanDim", true], {
            profileNamespace setVariable ["COMSPEC_ATAK_PlanDim", !(profileNamespace getVariable ["COMSPEC_ATAK_PlanDim", true])]; saveProfileNamespace; ["rerender"] call comspec_atak_native_fnc_planAction;
        }, "Les couleurs du plan ressortent mieux"]
    ];
    [_rows, [0, _pad + _rowH, _bw, _bh - _pad - _rowH]] call comspec_atak_native_fnc_formRender;
    true
};

// Haut : onglets, couche visée, plans.
private _y = _pad / 2;
private _topH = 0;
private _botH = 0;
if (_inter) then {
    private _tabs = (_cat get "cats") apply { [_x select 0, _x select 1] };
    private _n = (count _tabs) + 2;
    private _tw = (_bw - _pad * 2 - _gap * (_n - 1)) / _n;
    {
        _x params ["_c", "_t"];
        [[_pad + _forEachIndex * (_tw + _gap), _y, _tw, _rowH], _t, ["tab", _c] call _act, (_ui get "cat") isEqualTo _c] call _btn;
    } forEach _tabs;
    private _tgt = (_ui get "target") isEqualTo "S";
    private _tb = [[_pad + 3 * (_tw + _gap), _y, _tw, _rowH], [["BROUILLON", "PARTAGÉ"] select _tgt, "PARTAGÉ (LECT.)"] select (_tgt && !_can), ["target"] call _act, false,
        "Couche où vont les nouveaux éléments : plan PARTAGÉ du camp (selon le réglage serveur) ou BROUILLON local (préparé à l'avance)"] call _btn;
    _tb ctrlSetBackgroundColor ([[0.55, 0.40, 0.10, 0.95], [0.12, 0.32, 0.55, 0.95]] select _tgt);
    [[_pad + 4 * (_tw + _gap), _y, _tw, _rowH], "PLANS", ["view", "LIB"] call _act, false, "Plans enregistrés, publication, export / import"] call _btn;
    _y = _y + _rowH + _gap;
    // Types de l'onglet : deux rangées au plus.
    private _keys = ((_cat get "cats") select { (_x select 0) isEqualTo (_ui get "cat") }) param [0, ["", "", []]] select 2;
    private _cols = [count _keys, ceil ((count _keys) / 2)] select ((count _keys) > 6);
    private _kw = (_bw - _pad * 2 - _gap * ((_cols max 1) - 1)) / (_cols max 1);
    private _kh = _rowH * 0.92;
    {
        private _d = _kinds get _x;
        private _c = (_cat get "colors") getOrDefault [_d select 4, [1, 1, 1, 1]];
        private _on = (_ui get "tool") isEqualTo _x;
        private _b = [[_pad + (_forEachIndex mod _cols) * (_kw + _gap), _y + (floor (_forEachIndex / _cols)) * (_kh + _gap), _kw, _kh], _d select 1, ["tool", _x] call _act, _on, _d select 2] call _btn;
        if (!_on) then { _b ctrlSetTextColor _c; };
    } forEach _keys;
    _y = _y + (ceil ((count _keys) / (_cols max 1))) * (_kh + _gap);
    _topH = _y;
    _botH = _rowH * 3 + _gap * 3 + _pad / 2;
};

// Carte (absolue sur le display, hors zone défilante) : vue gardée d'un rendu à l'autre.
private _mapH = _bh - _topH - _botH;
private _map = ["COMSPEC_RscMap", [_bx, _by + _topH, _bw, _mapH], "", false] call comspec_atak_native_fnc_pageCtrl;
_ctrls set ["map", _map];
_map ctrlAddEventHandler ["Draw", { ["draw", _this] call comspec_atak_native_fnc_planMap; }];
if (_inter) then {
    _map ctrlAddEventHandler ["MouseButtonDown", { ["down", _this] call comspec_atak_native_fnc_planMap; }];
    _map ctrlAddEventHandler ["MouseButtonUp", { ["up", _this] call comspec_atak_native_fnc_planMap; }];
    _map ctrlAddEventHandler ["MouseMoving", { ["move", _this] call comspec_atak_native_fnc_planMap; }];
    _map ctrlAddEventHandler ["MouseButtonDblClick", { ["dbl", _this] call comspec_atak_native_fnc_planMap; }];
} else {
    _map ctrlEnable false;
};
private _view = uiNamespace getVariable ["COMSPEC_ATAK_PlanView", []];
if ((count _view) isEqualTo 2) then { _map ctrlMapAnimAdd [0, _view select 0, _view select 1]; } else {
    // Première ouverture : sur le plan partagé s'il existe, sinon sur moi.
    private _sh = ["shared"] call comspec_atak_native_fnc_planAction;
    private _c = if ((count _sh) > 0) then { ((_sh select 0) select 2) select 0 } else { getPosATL player };
    _map ctrlMapAnimAdd [0, 0.03, [_c select 0, _c select 1, 0]];
};
ctrlMapAnimCommit _map;
if (!_inter) exitWith { true };
// Page redessinée (choix d'un outil) : le focus resté dans la zone de contenu la mettait devant la carte, qui ne
// recevait plus les clics. Il revient au bouton invisible hors écran (fn_overlayFront, sauf saisie de texte).
[{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame;

// Bas : consigne (et aperçu), options du tracé, texte et actions.
private _yb = _topH + _mapH + _gap;
private _pv = uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []];
private _hintW = _bw - _pad * 2;
if ((count _pv) > 1) then {
    private _pw = (_bw - _pad * 2) * 0.17;
    [[_bw - _pad - _pw * 2 - _gap, _yb, _pw, _rowH * 0.9], "AJOUTER", { ["previewAdd"] call comspec_atak_native_fnc_planAction; }, true, format ["Ajouter l'aperçu « %1 » à la couche visée", _pv select 0]] call _btn;
    [[_bw - _pad - _pw, _yb, _pw, _rowH * 0.9], "FERMER", { ["previewOff"] call comspec_atak_native_fnc_planAction; }, false, "Fermer l'aperçu"] call _btn;
    _hintW = _hintW - _pw * 2 - _gap * 2;
};
private _hint = ["COMSPEC_RscStructuredText", [_pad, _yb, _hintW, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_ctrls set ["hint", _hint];
if ((count _pv) > 1) then {
    _hint ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#e0c060'>APERÇU : %1 (%2 éléments)</t>", (_pv select 0) regexReplace ["[<>&]", ""], count (_pv select 1)]) call comspec_atak_native_fnc_tr);
} else {
    _hint ctrlSetStructuredText parseText (((["hintText"] call comspec_atak_native_fnc_planAction)) call comspec_atak_native_fnc_tr);
};
_yb = _yb + _rowH + _gap;

// Options : couleurs, épaisseur, tirets, flèche, crayon.
private _geom = (_kinds getOrDefault [_ui get "tool", []]) param [3, ""];
private _opts = [["FIN", 1], ["MOY.", 2], ["ÉPAIS", 3]];
private _sw = _rowH * 0.95 / _ratio;
private _cx = _pad;
{
    private _rgba = (_cat get "colors") get _x;
    private _on = (_ui get "color") isEqualTo _x;
    private _c = ["COMSPEC_RscButton", [_cx, _yb, _sw, _rowH * 0.95], ["", "●"] select _on] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlSetBackgroundColor _rgba;
    _c ctrlSetTextColor ([[1, 1, 1, 1], [0, 0, 0, 1]] select (_x in ["WHITE", "YELLOW", "GREEN", "CYAN"]));
    _c ctrlAddEventHandler ["ButtonClick", ["color", _x] call _act];
    _c ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
    _cx = _cx + _sw + _gap / 2;
} forEach (_cat get "colorOrder");
_cx = _cx + _gap;
private _rest = [];
{ _rest pushBack [_x select 0, ["width", _x select 1] call _act, (_ui get "width") isEqualTo (_x select 1), "Épaisseur du trait"]; } forEach _opts;
if (_geom in ["LINE", "AREA", "DIR", ""]) then {
    _rest pushBack ["TIRETS", ["dash"] call _act, _ui get "dash", "Trait en tirets"];
};
if (_geom in ["LINE", ""]) then {
    _rest pushBack ["FLÈCHE", ["arrow"] call _act, _ui get "arrow", "Pointe de flèche au bout, dans le sens du dernier segment"];
    _rest pushBack [["SEGMENTS", "CRAYON"] select (_ui get "pen"), ["pen"] call _act, _ui get "pen", "CRAYON : maintenir le clic et dessiner (lissé) · SEGMENTS : clic point par point"];
};
private _rw = ((_bw - _pad - _cx - _gap * ((count _rest) - 1)) / ((count _rest) max 1)) max 0;
{
    _x params ["_t", "_code", "_on", "_tip"];
    [[_cx + _forEachIndex * (_rw + _gap), _yb, _rw, _rowH * 0.95], _t, _code, _on, _tip] call _btn;
} forEach _rest;
_yb = _yb + _rowH + _gap;

// Texte des prochains éléments et actions.
private _ew = (_bw - _pad * 2) * 0.3;
private _ed = ["COMSPEC_RscEdit", [_pad, _yb, _ew, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_ed ctrlSetText (((_ui get "text")) call comspec_atak_native_fnc_tr);
_ed ctrlSetFontHeight (_fs * 0.9);
_ed ctrlSetTooltip (("Texte des prochains éléments (vide : nom du type, ex. ENY, INFIL, PL). CLEARED / UNCLEARED sont numérotés tout seuls.") call comspec_atak_native_fnc_tr);
_ed ctrlAddEventHandler ["KeyUp", { params ["_c"]; ["text", ctrlText _c] call comspec_atak_native_fnc_planAction; }];
_ed ctrlAddEventHandler ["KillFocus", { params ["_c"]; ["text", ctrlText _c] call comspec_atak_native_fnc_planAction; }];
private _acts = [
    ["fin", "TERMINER", { ["finish"] call comspec_atak_native_fnc_planAction; }, "Termine le trait ou ferme la zone (aussi : clic droit, double clic)"],
    ["cancel", "ANNULER", { ["cancel"] call comspec_atak_native_fnc_planAction; }, "Abandonne le tracé en cours, puis l'outil"],
    ["undo", "DÉFAIRE", { ["undo"] call comspec_atak_native_fnc_planAction; }, "Dernier point du tracé, sinon dernier élément (partagé : le dernier posé par vous)"],
    ["toggle", "NET. ⇄", { ["toggleClear"] call comspec_atak_native_fnc_planAction; }, "Bâtiment sélectionné : CLEARED <-> UNCLEARED (même numéro)"],
    ["del", "SUPPR.", { ["delete"] call comspec_atak_native_fnc_planAction; }, "Efface l'élément sélectionné"],
    ["clear", ["TOUT EFF.", "CONFIRMER ?"] select ((diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PlanClearArm", -10])) <= 4), { ["clearTarget"] call comspec_atak_native_fnc_planAction; }, "Efface le brouillon et, si vous avez le droit, le plan partagé (deux appuis)"]
];
private _aw = (_bw - _pad * 2 - _ew - _gap * (count _acts)) / (count _acts);
{
    _x params ["_k", "_t", "_code", "_tip"];
    private _b = [[_pad + _ew + _gap + _forEachIndex * (_aw + _gap), _yb, _aw, _rowH], _t, _code, false, _tip] call _btn;
    if (_k in ["del", "toggle"]) then { _b ctrlEnable ((_ui get "sel") isNotEqualTo ""); };
    if (_k isEqualTo "clear") then { _b ctrlSetBackgroundColor [0.45, 0.12, 0.10, 0.95]; };
    _ctrls set [_k, _b];
} forEach _acts;
true
