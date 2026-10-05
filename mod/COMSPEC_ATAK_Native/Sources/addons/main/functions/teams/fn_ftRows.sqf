/*
    Lignes de l'onglet ÉQUIPES de l'app Groupe (fn_pageGroup) : équipes de feu du groupe, fiche de création
    et de modification (nom, couleur, icône, description), mon rôle, placement des membres par les chefs.
    Renvoie les lignes de fn_formRender.
*/
private _cat = [] call comspec_atak_native_fnc_ftCatalog;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _g = group player;
private _players = (units _g) select { isPlayer _x };
private _isGL = (leader _g) isEqualTo player || {(count units _g) isEqualTo 1} || {!isPlayer leader _g && {(_players param [0, objNull]) isEqualTo player}};
private _me = [player] call comspec_atak_native_fnc_ftInfo;
private _isTL = (_me get "lead") && {(_me get "id") isNotEqualTo ""};
private _teams = [_g] call comspec_atak_native_fnc_ftTeams;
private _free = (_teams select ((count _teams) - 1)) select 1;
_teams deleteAt ((count _teams) - 1);
private _swatch = "#(argb,8,8,3)color(1,1,1,1)";
private _colorItems = (_cat get "colors") apply { [_x select 1, _x select 0, _swatch, _x select 3] };
private _iconItems = (_cat get "icons") apply { [_x select 1, _x select 0, _x select 2, [0.9, 0.94, 0.91, 1]] };
private _roleShort = { params ["_u"]; private _r = _u getVariable ["COMSPEC_FTRole", ""]; (((_cat get "roles") select { (_x select 0) isEqualTo _r }) param [0, ["", "", ""]]) select 2 };
private _rows = [];

// Moi
private _mine = if ((_me get "id") isEqualTo "") then { "<t color='#8a9a93'>sans équipe</t>" } else { format ["<t color='%1' font='RobotoCondensedBold'>● %2</t>", _me get "hex", [_me get "name"] call _esc] };
_rows pushBack ["text", format ["<t size='0.9'>Vous : %1 · %2</t>", _mine, ["<t color='#8a9a93'>aucun rôle</t>", _me get "roleLabel"] select ((_me get "role") isNotEqualTo "")]];
_rows pushBack ["section", format ["Équipes de feu (%1)", count _teams], "Couleur, icône et rôles visibles par tout le camp : Groupe, BFT, carte et Inter-team"];

{
    _x params ["_t", "_m"];
    private _tid = _t get "id";
    private _lead = (_m select { (_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE" }) param [0, objNull];
    private _canEdit = _isGL || {_isTL && {(_me get "id") isEqualTo _tid}};
    private _inIt = (_me get "id") isEqualTo _tid;
    private _names = (_m apply { private _rs = [_x] call _roleShort; format ["%1%2", ["", format ["<t color='#7fb6e6'>%1</t> ", _rs]] select (_rs isNotEqualTo ""), [name _x] call _esc] }) joinString " · ";
    private _btns = [];
    if (_inIt) then {
        _btns pushBack ["QUITTER", { ["leave"] call comspec_atak_native_fnc_ftAction; }];
    } else {
        _btns pushBack [["REJOINDRE", "FERMÉE"] select ((_t get "locked") && {!_isGL}), compile format ["['join', '%1'] call comspec_atak_native_fnc_ftAction;", _tid], true, !(_t get "locked") || {_isGL}];
    };
    if (_canEdit) then { _btns pushBack ["MODIFIER", compile format ["['editOpen', '%1'] call comspec_atak_native_fnc_ftAction;", _tid]]; };
    _rows pushBack ["person", _t get "icon", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.8' color='#8a9a93'>%3 · %4 membre(s)%5%6</t>%7<br/><t size='0.8'>%8</t>",
        _t get "hex", [_t get "name"] call _esc, _t get "colorLabel", count _m,
        ["", format [" · chef %1", [name _lead] call _esc]] select !isNull _lead,
        ["", " · <t color='#f2ab33'>fermée</t>"] select (_t get "locked"),
        ["", format ["<br/><t size='0.8' color='#b8c4bd'>%1</t>", [_t get "desc"] call _esc]] select ((_t get "desc") isNotEqualTo ""),
        [_names, "<t color='#8a9a93'>Personne pour l'instant</t>"] select ((count _m) isEqualTo 0)], _btns, _t get "rgba"];
    if (_canEdit && {(_s getOrDefault ["ftEdit", ""]) isEqualTo _tid}) then {
        _rows append [
            ["edit", "ftEName", "Nom", _t get "name"],
            ["combo", "ftEColor", "Couleur", _colorItems, _t get "color"],
            ["combo", "ftEIcon", "Icône", _iconItems, _t get "iconKey"],
            ["memo", "ftEDesc", "Description (mission, secteur, consignes)", _t get "desc", 2],
            ["buttons", [
                ["ENREGISTRER", compile format ["['edit', '%1'] call comspec_atak_native_fnc_ftAction;", _tid], true],
                [["FERMER", "OUVRIR"] select (_t get "locked"), compile format ["['lock', '%1'] call comspec_atak_native_fnc_ftAction;", _tid]],
                ["DISSOUDRE", compile format ["['delete', '%1'] call comspec_atak_native_fnc_ftAction;", _tid]],
                ["ANNULER", { ["editClose"] call comspec_atak_native_fnc_ftAction; }]
            ]]
        ];
    };
} forEach _teams;
if ((count _teams) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune équipe de feu dans ce groupe. Créez la première : Alpha, Bravo…</t>"]; };
if ((count _free) > 0) then {
    _rows pushBack ["person", "\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", format ["<t font='RobotoCondensedBold'>Sans équipe</t>  <t size='0.8' color='#8a9a93'>%1</t><br/><t size='0.8'>%2</t>",
        count _free, (_free apply { [name _x] call _esc }) joinString " · "], [], [0.55, 0.6, 0.58, 1]];
};

// Création
if (_s getOrDefault ["ftCreate", false]) then {
    private _used = (_teams apply { (_x select 0) get "color" });
    private _defColor = (((_cat get "colors") select { !((_x select 0) in _used) }) param [0, (_cat get "colors") select 0]) select 0;
    private _names = ["Alpha", "Bravo", "Charlie", "Delta", "Echo", "Foxtrot", "Golf", "Hotel"] select { private _n = _x; (_teams findIf { ((_x select 0) get "name") isEqualTo _n }) < 0 };
    _rows append [
        ["section", "Nouvelle équipe", "Vous en devenez le chef d'équipe"],
        ["edit", "ftName", "Nom", _names param [0, ""]],
        ["combo", "ftColor", "Couleur", _colorItems, _defColor],
        ["combo", "ftIcon", "Icône", _iconItems, "INF"],
        ["memo", "ftDesc", "Description (mission, secteur, consignes)", "", 2],
        ["buttons", [["CRÉER", { ["create"] call comspec_atak_native_fnc_ftAction; }, true], ["ANNULER", { ["createClose"] call comspec_atak_native_fnc_ftAction; }]]]
    ];
} else {
    _rows pushBack ["buttons", [["CRÉER UNE ÉQUIPE", { ["createOpen"] call comspec_atak_native_fnc_ftAction; }, true, (count _teams) < 8]]];
};

// Mon rôle
private _roleItems = (_cat get "roles") apply { [_x select 1, _x select 0, _x select 3, [0.9, 0.94, 0.91, 1]] };
_rows append [
    ["section", "Mon rôle", "Compté dans les temps par rôle (app Temps d'écran et Athena)"],
    ["combo", "ftMyRole", "Rôle", [["Aucun rôle", "", "", []]] + _roleItems, _me get "role"],
    ["buttons", [["APPLIQUER MON RÔLE", { ["myRole"] call comspec_atak_native_fnc_ftAction; }]]]
];

// Placement des membres (chef de groupe, chefs d'équipe)
if (_isGL || {_isTL}) then {
    private _sel = _s getOrDefault ["ftMember", ""];
    private _memberItems = (units _g) apply {
        private _i = [_x] call comspec_atak_native_fnc_ftInfo;
        [format ["%1%2", name _x, ["", format ["  (%1)", _i get "name"]] select ((_i get "id") isNotEqualTo "")], netId _x, ["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", _i get "icon"] select ((_i get "icon") isNotEqualTo ""), _i get "rgba"]
    };
    if (_sel isEqualTo "" || {(_memberItems findIf { (_x select 1) isEqualTo _sel }) < 0}) then { _sel = (_memberItems param [0, ["", ""]]) select 1; };
    private _targets = [["Équipe inchangée", "-", "", []], ["Sans équipe", "", "", []]] + (_teams apply { private _t = _x select 0; [_t get "name", _t get "id", _swatch, _t get "rgba"] });
    if (!_isGL) then { _targets = _targets select { (_x select 1) in ["-", "", _me get "id"] }; };
    _rows append [
        ["section", "Placer un membre", ["Chef d'équipe : vous placez dans votre équipe et nommez les rôles", "Chef de groupe : toutes les équipes et tous les rôles"] select _isGL],
        ["combo", "ftMember", "Membre", _memberItems, _sel],
        ["combo", "ftTarget", "Équipe", _targets, "-"],
        ["combo", "ftRole", "Rôle", [["Rôle inchangé", "-", "", []], ["Aucun rôle", "", "", []]] + _roleItems, "-"],
        ["buttons", [["APPLIQUER", { ["manage"] call comspec_atak_native_fnc_ftAction; }, true]]]
    ];
};
_rows
