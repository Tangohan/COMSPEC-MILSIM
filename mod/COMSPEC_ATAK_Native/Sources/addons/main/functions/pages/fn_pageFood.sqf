/* UberEats : catalogue des rations et boissons (ACE Field Rations), panier, suivi de livraison. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cart = _s getOrDefault ["foodCart", createHashMap];
private _menu = ["menu"] call comspec_atak_native_fnc_foodAction;
private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
private _step = _o getOrDefault ["step", ""];
private _rows = [["hero", "\z\comspec_atak_native\addons\main\data\app_food.paa", "<t size='1.3' font='RobotoCondensedBold' color='#ff7a59'>UberEats</t><br/><t color='#8a9a93'>Livré par drone-cargo, partout sur le théâtre.<br/>6 articles maximum, une commande toutes les 10 min.</t>"]];
if (_step in ["PREP", "FLIGHT"]) then {
    private _left = round ((_o get "eta") - time) max 0;
    _rows append [
        ["section", format ["Commande %1", _o get "ref"], ["En préparation en cuisine", "Drone en vol vers vous"] select (_step isEqualTo "FLIGHT")],
        ["text", format ["<t size='0.85'><t color='#ff7a59'>ACCEPTÉE</t>  ·  <t color='%1'>EN CUISINE</t>  ·  <t color='%2'>DRONE EN VOL</t>  ·  <t color='#5c6b65'>LIVRÉE</t></t><br/><t size='1.4' font='RobotoCondensedBold'>%3 min %4 s</t>  <t color='#8a9a93'>avant l'arrivée</t><br/><t size='0.8' color='#8a9a93'>%5 article(s) · restez à l'extérieur, une fumée violette marquera le colis.</t>",
            ["#5c6b65", "#ff7a59"] select (_step isEqualTo "PREP"), ["#5c6b65", "#ff7a59"] select (_step isEqualTo "FLIGHT"),
            floor (_left / 60), [str (_left mod 60), "0" + str (_left mod 60)] select ((_left mod 60) < 10), count (_o get "items")]]
    ];
    if (_step isEqualTo "PREP") then { _rows pushBack ["buttons", [["ANNULER LA COMMANDE", { ["cancel"] call comspec_atak_native_fnc_foodAction; }]]]; };
};
if (_step isEqualTo "DONE") then {
    _rows pushBack ["text", format ["<t color='#5cc76b'>Commande %1 livrée</t> à %2 m de vous.", _o get "ref", round (player distance2D (_o getOrDefault ["pos", getPosATL player]))]];
};
if ((count _menu) isEqualTo 0) exitWith {
    _rows pushBack ["text", "<t color='#f2ab33'>Aucune ration disponible : le mod ACE (Field Rations) n'est pas chargé sur ce serveur.</t>"];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _n = 0; { _n = _n + _y; } forEach _cart;
if (_n > 0) then {
    _rows pushBack ["section", format ["Panier (%1/6)", _n], ""];
    {
        private _k = _x; private _q = _y;
        private _it = (_menu select { (_x select 0) isEqualTo _k }) param [0, [_k, _k, ""]];
        _rows pushBack ["person", _it select 2, format ["<t font='RobotoCondensedBold'>%1 ×</t> %2", _q, _it select 1], [["-", compile format ["['remove', '%1'] call comspec_atak_native_fnc_foodAction;", _k]]]];
    } forEach _cart;
    _rows pushBack ["buttons", [["COMMANDER", { ["order"] call comspec_atak_native_fnc_foodAction; }, true]]];
};
// Rayons : repas, boissons, encas (d'après la classe ACE).
private _kind = {
    params ["_c"];
    _c = toLower _c;
    if ((_c find "ace_mre_") isEqualTo 0 || {(_c find "humanitarian") >= 0}) exitWith { "MEAL" };
    if ((_c find "water") >= 0 || {(_c find "canteen") >= 0} || {(_c find "ace_can_") isEqualTo 0} || {(_c find "juice") >= 0}) exitWith { "DRINK" };
    "SNACK"
};
private _cat = _s getOrDefault ["foodCat", ""];
private _catBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['foodCat', '%1']; ['FOOD'] call comspec_atak_native_fnc_pageRender;", _k], _cat isEqualTo _k] };
private _shown = _menu select { _cat isEqualTo "" || {([_x select 0] call _kind) isEqualTo _cat} };
_rows pushBack ["section", "Au menu", format ["%1 article(s)", count _shown]];
_rows pushBack ["segment", "", [["TOUT", ""] call _catBtn, ["REPAS", "MEAL"] call _catBtn, ["BOISSONS", "DRINK"] call _catBtn, ["ENCAS", "SNACK"] call _catBtn]];
{
    _x params ["_cls", "_name", "_pic"];
    _rows pushBack ["person", _pic, format ["%1%2", _name, ["", format ["  <t color='#ff7a59'>×%1</t>", _cart getOrDefault [_cls, 0]]] select (_cls in _cart)], [["+", compile format ["['add', '%1'] call comspec_atak_native_fnc_foodAction;", _cls], true]]];
} forEach _shown;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
