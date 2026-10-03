/* Ration Express : catalogue des rations et boissons (ACE Field Rations), panier, suivi de livraison. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cart = _s getOrDefault ["foodCart", createHashMap];
private _menu = ["menu"] call comspec_atak_native_fnc_foodAction;
private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
private _step = _o getOrDefault ["step", ""];
private _rows = [["hero", "\z\comspec_atak_native\addons\main\data\app_food.paa", "<t size='1.3' font='RobotoCondensedBold' color='#ff7a59'>Ration Express</t><br/><t color='#8a9a93'>Livré par drone-cargo, partout sur le théâtre.<br/>6 articles maximum, une commande toutes les 10 min.</t>"]];
if (_step in ["PREP", "FLIGHT"]) then {
    private _left = round ((_o get "eta") - time) max 0;
    _rows append [
        ["section", format ["Commande %1", _o get "ref"], ["En préparation en cuisine", "Drone en vol vers vous"] select (_step isEqualTo "FLIGHT")],
        ["text", format ["<t size='1.2'>%1</t>  <t color='#8a9a93'>%2 %3 %4</t><br/>Arrivée dans <t color='#ff7a59'>%5 min %6 s</t><br/><t size='0.8' color='#8a9a93'>%7 article(s) · restez à l'extérieur, une fumée violette marquera le colis.</t>",
            ["CUISINE", "DRONE"] select (_step isEqualTo "FLIGHT"), "● Acceptée", ["○ En vol", "● En vol"] select (_step isEqualTo "FLIGHT"), "○ Livrée",
            floor (_left / 60), _left mod 60, count (_o get "items")]]
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
        _rows pushBack ["person", _it select 2, format ["<t font='RobotoCondensedBold'>%1 ×</t> %2", _q, _it select 1], [["−", compile format ["['remove', '%1'] call comspec_atak_native_fnc_foodAction;", _k]]]];
    } forEach _cart;
    _rows pushBack ["buttons", [["COMMANDER", { ["order"] call comspec_atak_native_fnc_foodAction; }, true]]];
};
_rows pushBack ["section", "Au menu", format ["%1 article(s)", count _menu]];
{
    _x params ["_cls", "_name", "_pic"];
    _rows pushBack ["person", _pic, format ["%1%2", _name, ["", format ["  <t color='#ff7a59'>×%1</t>", _cart getOrDefault [_cls, 0]]] select (_cls in _cart)], [["+", compile format ["['add', '%1'] call comspec_atak_native_fnc_foodAction;", _cls], true]]];
} forEach _menu;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
