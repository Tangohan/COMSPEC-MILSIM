/*
    App Plan : carte de briefing statique (nord en haut, fixe), à part de la carte qui suit le joueur.
    Vue CARTE : la carte est créée ici une fois ; barre du haut (OUTILS, couches CAMP / BROUILLON / SALLE, ANNULER,
    RÉTABLIR, EXPORTER, PLANS, AIDE), palette d'outils, barre du bas et menus sont dessinés par fn_planUi, qui les
    refait seuls à chaque clic : la carte garde sa vue (plus de saut en haut à gauche après SUPPR. ou CONFIRMER).
    Vue PLANS : enregistrer sous un nom, aperçu, charger, publier, exporter / importer (chaîne COMSPECPLAN1), salle.
    Porté (sans souris) : la carte seule, en lecture. Données : fn_planAction ; carte : fn_planMap ; rendu : fn_planDraw ;
    aide et tutoriel : fn_planHelp.
    Params (app de module, fn_pageRender) : [page, rectangle]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _inter = _l get "interactive";
private _ui = ["ui"] call comspec_atak_native_fnc_planAction;
private _can = ["can"] call comspec_atak_native_fnc_planAction;
private _ctrls = createHashMap;
uiNamespace setVariable ["COMSPEC_ATAK_PlanCtrls", _ctrls];
uiNamespace setVariable ["COMSPEC_ATAK_PlanChrome", []];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_planAction;", str _a, str _v] };
private _btn = {
    params ["_rect", "_label", "_code", ["_on", false], ["_tip", ""]];
    private _b = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight ((_fs * 0.86) max COMSPEC_ATAK_FontMin);
    if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b
};
private _rowH = (_font * 1.3) min (_bh * 0.075);
private _gap = _pad / 3;

// Vue PLANS : bibliothèque, salle, publication, export / import.
if (_inter && {(_ui get "view") isEqualTo "LIB"}) exitWith {
    [[_pad, _pad / 2, (_bw - _pad * 2) * 0.3, _rowH], "◀ CARTE", ["view", "MAP"] call _act] call _btn;
    private _sh = ["shared"] call comspec_atak_native_fnc_planAction;
    private _dr = ["draft"] call comspec_atak_native_fnc_planAction;
    private _room = ["roomId"] call comspec_atak_native_fnc_planAction;
    private _sharedName = (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [2, ""];
    private _lib = profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []];
    private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
    private _mode = ["tout le monde", "chefs de groupe, Zeus et admins", "Zeus et admins"] select ((missionNamespace getVariable ["comspec_atak_native_plan_edit", 1]) max 0 min 2);
    private _rows = [
        ["section", "Plan du camp", format ["%1 élément(s)%2 · modifiable par : %3%4", count _sh, ["", format [" · « %1 »", [_sharedName] call _esc]] select (_sharedName isNotEqualTo ""), _mode, ["", " (vous : lecture seule)"] select !_can]],
        ["text", format ["<t size='0.85' color='#8a9a93'>%1</t>", ["state"] call comspec_atak_native_fnc_planWeb]],
        ["buttons", [["EXPORTER", ["export", "S"] call _act], ["ENREGISTRER", { ["save", "S"] call comspec_atak_native_fnc_planAction; }]]],
        ["section", "Brouillon local", format ["%1 élément(s), gardé dans votre profil : préparez le plan à l'avance, puis ajoutez-le quand le groupe se réunit", count _dr]],
        ["buttons", [["AJOUTER AU CAMP", { ["publish", ["D", false]] call comspec_atak_native_fnc_planAction; }, true, _can], ["REMPLACER LE CAMP", { ["publish", ["D", true]] call comspec_atak_native_fnc_planAction; }, false, _can]]],
        ["buttons", [["EXPORTER", ["export", "D"] call _act], ["ENREGISTRER", { ["save", "D"] call comspec_atak_native_fnc_planAction; }]]]
    ];
    if (_room isNotEqualTo "") then {
        _rows append [
            ["section", format ["Salle %1", _room], format ["%1 élément(s) · plan collaboratif : tous ceux qui ont le code dessinent dessus", count (["room"] call comspec_atak_native_fnc_planAction)]],
            ["buttons", [["PUBLIER SUR LE CAMP", { ["publish", ["R", false]] call comspec_atak_native_fnc_planAction; }, true, _can], ["EXPORTER", ["export", "R"] call _act], ["ENREGISTRER", { ["save", "R"] call comspec_atak_native_fnc_planAction; }]]]
        ];
    };
    _rows append [
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
        ["section", "Importer", "Collez un code de plan COMSPECPLAN1 (laissez vide pour lire le presse-papiers). Il arrive dans le brouillon."],
        ["memo", "planImport", "Plan à importer", "", 3],
        ["buttons", [["IMPORTER DANS LE BROUILLON", { ["import"] call comspec_atak_native_fnc_planAction; }, true]]]
    ];
    private _exp = uiNamespace getVariable ["COMSPEC_ATAK_PlanExport", ""];
    if (_exp isNotEqualTo "") then { _rows pushBack ["memo", "planExport", "Dernier export (aussi copié dans le presse-papiers)", _exp select [0, 4000], 3]; };
    _rows append [
        ["section", "Affichage", ""],
        ["switch", "Plan sur la carte principale", profileNamespace getVariable ["COMSPEC_ATAK_PlanOnMap2", true], { ["layer"] call comspec_atak_native_fnc_planAction; }, "Plan du camp, salle et brouillon sur l'app Carte, sous les unités"],
        ["switch", "Fond assombri (façon image ISR)", profileNamespace getVariable ["COMSPEC_ATAK_PlanDim", true], {
            profileNamespace setVariable ["COMSPEC_ATAK_PlanDim", !(profileNamespace getVariable ["COMSPEC_ATAK_PlanDim", true])]; saveProfileNamespace; ["rerender"] call comspec_atak_native_fnc_planAction;
        }, "Les couleurs du plan ressortent mieux"],
        ["buttons", [["REVOIR LE TUTORIEL", { ["view", "MAP"] call comspec_atak_native_fnc_planAction; [{ ["tuto", 0] call comspec_atak_native_fnc_planAction; }, [], 0.2] call CBA_fnc_waitAndExecute; }]]]
    ];
    [_rows, [0, _pad + _rowH, _bw, _bh - _pad - _rowH]] call comspec_atak_native_fnc_formRender;
    true
};

// Carte (absolue sur le display, hors zone défilante) entre la barre du haut et celle du bas.
private _topH = [0, _pad / 2 + _rowH + _gap] select _inter;
private _botH = [0, _rowH * 2 + _gap * 3] select _inter;
private _mapRect = [_bx, _by + _topH, _bw, _bh - _topH - _botH];
uiNamespace setVariable ["COMSPEC_ATAK_PlanLayout", createHashMapFromArray [["body", [_bx, _by, _bw, _bh]], ["map", _mapRect], ["top", _topH], ["bot", _botH], ["rowH", _rowH], ["gap", _gap], ["pad", _pad]]];
private _map = ["COMSPEC_RscMap", _mapRect, "", false] call comspec_atak_native_fnc_pageCtrl;
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
// Vue gardée d'un rendu à l'autre. Une carte toute neuve affiche d'abord le coin haut gauche du monde : fn_planMap
// n'enregistre la vue qu'une fois la carte calée sur ce but (sinon le rendu suivant repartait de ce coin).
private _view = uiNamespace getVariable ["COMSPEC_ATAK_PlanView", []];
if ((count _view) isNotEqualTo 2) then {
    // Première ouverture : sur le plan partagé (salle, puis camp) s'il existe, sinon sur moi.
    private _sh = (["room"] call comspec_atak_native_fnc_planAction) + (["shared"] call comspec_atak_native_fnc_planAction);
    private _c = if ((count _sh) > 0) then { ((_sh select 0) select 2) select 0 } else { getPosATL player };
    _view = [0.03, [_c select 0, _c select 1]];
};
_map setVariable ["comspec_planAim", _view];
_map setVariable ["comspec_planBorn", diag_tickTime];
_map ctrlMapAnimAdd [0, _view select 0, [(_view select 1) select 0, (_view select 1) select 1, 0]];
ctrlMapAnimCommit _map;
if (!_inter) exitWith { true };

// Clavier de l'app (un gestionnaire par écran du téléphone, actif seulement sur cette page) : fn_planUi "key".
private _disp = [] call comspec_atak_native_fnc_display;
if (!isNull _disp && {(_disp getVariable ["COMSPEC_PlanKeyEh", -1]) < 0}) then {
    _disp setVariable ["COMSPEC_PlanKeyEh", _disp displayAddEventHandler ["KeyDown", { ["key", _this] call comspec_atak_native_fnc_planUi }]];
};
["build"] call comspec_atak_native_fnc_planUi;
// Premier lancement : tutoriel pas à pas (rejouable depuis l'aide ou la vue PLANS).
if ((profileNamespace getVariable ["COMSPEC_ATAK_PlanTuto", 0]) < 1 && {(_ui get "tuto") < 0} && {!(uiNamespace getVariable ["COMSPEC_ATAK_PlanTutoShown", false])}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_PlanTutoShown", true];
    [{ ["tuto", 0] call comspec_atak_native_fnc_planAction; }, [], 0.4] call CBA_fnc_waitAndExecute;
};
true
