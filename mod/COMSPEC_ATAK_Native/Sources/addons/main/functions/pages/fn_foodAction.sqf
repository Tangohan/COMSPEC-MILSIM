/*
    UberEats : commande de rations livrée par drone-cargo (caisse sous parachute près du joueur).
    Params : [action, argument]
      "menu"  : catalogue [[classe, nom, image]...] (rations et boissons ACE chargées)
      "add" / "remove" : panier (classe)   "order" : passer la commande   "cancel" : annuler avant le départ
    Une commande à la fois, puis 10 min d'attente. La commande part par le réseau simulé (fn_netSend).
*/
params [["_act", "menu"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cart = _s getOrDefault ["foodCart", createHashMap];
_s set ["foodCart", _cart];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FOOD") then { ["FOOD"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Le catalogue renvoie une valeur : traité hors du switch (la fonction finit par true, ce qui vidait la page).
if (_act isEqualTo "menu") exitWith {
    call {
        private _cached = uiNamespace getVariable ["COMSPEC_ATAK_FoodMenu", []];
        if ((count _cached) > 0) exitWith { _cached };
        private _menu = ("(getNumber (_x >> 'scope')) >= 2 && {((configName _x) select [0, 8]) in ['ACE_MRE_', 'ACE_Wate', 'ACE_Cant', 'ACE_Can_', 'ACE_Juic', 'ACE_Sunr', 'ACE_Bana', 'ACE_Huma', 'ACE_Spir']} && {((configName _x) find 'Empty') < 0}" configClasses (configFile >> "CfgWeapons")) apply {
            [configName _x, getText (_x >> "displayName"), getText (_x >> "picture")]
        };
        _menu = _menu select { (_x select 1) isNotEqualTo "" };
        _menu sort true;
        uiNamespace setVariable ["COMSPEC_ATAK_FoodMenu", _menu];
        _menu
    }
};
switch (_act) do {
    case "add": {
        private _n = 0; { _n = _n + _y; } forEach _cart;
        if (_n >= 6) exitWith { ["WARNING", "Panier plein : 6 articles au maximum par livraison", 3, 20] call comspec_atak_native_fnc_notify; };
        _cart set [_arg, (_cart getOrDefault [_arg, 0]) + 1];
        call _rerender;
    };
    case "remove": {
        private _q = (_cart getOrDefault [_arg, 0]) - 1;
        if (_q <= 0) then { _cart deleteAt _arg; } else { _cart set [_arg, _q]; };
        call _rerender;
    };
    case "cancel": {
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
        if ((_o getOrDefault ["step", ""]) isNotEqualTo "PREP") exitWith { ["WARNING", "Trop tard : le drone est déjà parti", 3, 20] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
        ["INFO", "Commande annulée", 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "order": {
        if ((count _cart) isEqualTo 0) exitWith {};
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
        if ((_o getOrDefault ["step", ""]) in ["PREP", "FLIGHT"]) exitWith { ["WARNING", "Une commande est déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        private _next = missionNamespace getVariable ["COMSPEC_ATAK_FoodNextAt", 0];
        if (time < _next) exitWith { ["WARNING", format ["Prochaine commande possible dans %1 min", ceil ((_next - time) / 60)], 3, 20] call comspec_atak_native_fnc_notify; };
        if ((vehicle player) isNotEqualTo player) exitWith { ["WARNING", "Descendez du véhicule : le drone livre au sol", 3, 20] call comspec_atak_native_fnc_notify; };
        private _items = []; { for "_i" from 1 to _y do { _items pushBack _x; }; } forEach _cart;
        _s set ["foodCart", createHashMap];
        // La commande part par le réseau simulé : sans signal, elle attend.
        [{
            params ["_items"];
            private _prep = 30 + random 30;
            private _fly = 60 + random 90;
            missionNamespace setVariable ["COMSPEC_ATAK_FoodOrder", createHashMapFromArray [["step", "PREP"], ["items", _items], ["eta", time + _prep + _fly], ["launch", time + _prep], ["ref", format ["RX-%1", 1000 + floor random 9000]]]];
            missionNamespace setVariable ["COMSPEC_ATAK_FoodNextAt", time + 600];
            ["SUCCESS", format ["Commande acceptée : livraison dans %1 min environ", ceil ((_prep + _fly) / 60)], 4, 30] call comspec_atak_native_fnc_notify;
            [{ ["launch"] call comspec_atak_native_fnc_foodAction; }, [], _prep] call CBA_fnc_waitAndExecute;
            [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FOOD") then { ["FOOD"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        }, [_items], "Commande UberEats", 2] call comspec_atak_native_fnc_netSend;
        call _rerender;
    };
    case "launch": {
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
        if ((_o getOrDefault ["step", ""]) isNotEqualTo "PREP") exitWith {};
        _o set ["step", "FLIGHT"];
        ["INFO", "UberEats : le drone est parti, restez dans la zone", 4, 30] call comspec_atak_native_fnc_notify;
        [{ ["drop"] call comspec_atak_native_fnc_foodAction; }, [], ((_o get "eta") - time) max 5] call CBA_fnc_waitAndExecute;
        call _rerender;
    };
    case "drop": {
        private _o = missionNamespace getVariable ["COMSPEC_ATAK_FoodOrder", createHashMap];
        if ((_o getOrDefault ["step", ""]) isNotEqualTo "FLIGHT") exitWith {};
        // Point de largage : à côté du joueur, sur un sol dégagé.
        private _pos = [getPosATL player, 15, 40, 3, 0, 0.4, 0, [], [getPosATL player, getPosATL player]] call BIS_fnc_findSafePos;
        if ((count _pos) < 2) then { _pos = player getRelPos [20, random 360]; };
        _pos = [_pos select 0, _pos select 1, 0];
        private _box = createVehicle ["Box_NATO_Support_F", [_pos select 0, _pos select 1, 120], [], 0, "CAN_COLLIDE"];
        clearItemCargoGlobal _box; clearMagazineCargoGlobal _box; clearWeaponCargoGlobal _box; clearBackpackCargoGlobal _box;
        { _box addItemCargoGlobal [_x, 1]; } forEach (_o get "items");
        private _chute = createVehicle ["B_Parachute_02_F", [_pos select 0, _pos select 1, 120], [], 0, "CAN_COLLIDE"];
        _box attachTo [_chute, [0, 0, -1.2]];
        [_box, _chute] spawn {
            params ["_box", "_chute"];
            waitUntil { sleep 0.5; isNull _chute || {((getPosATL _box) select 2) < 3} };
            detach _box;
            private _p = getPosATL _box;
            _box setPosATL [_p select 0, _p select 1, 0];
            if (!isNull _chute) then { deleteVehicle _chute; };
            createVehicle ["SmokeShellPurple", getPosATL _box, [], 0, "CAN_COLLIDE"];
            // Caisse vide ou oubliée : retirée au bout de 20 min.
            sleep 1200;
            if (!isNull _box) then { deleteVehicle _box; };
        };
        _o set ["step", "DONE"];
        _o set ["pos", _pos];
        private _m = createMarkerLocal [format ["COMSPEC_FOOD_%1", _o get "ref"], _pos];
        _m setMarkerTypeLocal "mil_pickup"; _m setMarkerColorLocal "ColorPink"; _m setMarkerTextLocal format ["UberEats %1", _o get "ref"];
        [{ deleteMarkerLocal _this; }, _m, 900] call CBA_fnc_waitAndExecute;
        ["SUCCESS", format ["UberEats : livré à %1 m (fumée violette), bon appétit !", round (player distance2D _pos)], 6, 40] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        call _rerender;
    };
};
true
