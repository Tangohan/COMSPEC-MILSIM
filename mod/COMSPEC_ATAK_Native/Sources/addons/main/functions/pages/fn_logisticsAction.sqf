/*
    App Logistique : demandes de ravitaillement suivies de bout en bout.
    Params : [action, argument, valeur]
      "set" clé valeur      : choix du formulaire (besoin, arme, quantité, livraison, point, priorité)
      "pickMap" / "picked"  : point de livraison pointé sur la carte (clé logiPick de COMSPEC_ATAK_State)
      "pickHere"            : point de livraison à ma position
      "send"                : envoyer la demande au camp (réseau simulé fn_netSend, puis Athena si Overwatch)
      "validate" / "refuse" / "enroute" / "deliver" / "cancel" id : changement d'état
      "airdrop" id          : largage parachuté de la caisse au point demandé
      "locate" id           : centrer la carte sur le point de livraison
      "recv" [op, camp, données] : réception de l'évènement comspec_atak_native_logi (XEH_postInitClient)
    États : DEMANDEE -> VALIDEE -> EN_ROUTE -> LIVREE, ou REFUSEE / ANNULEE.
    Les demandes du camp sont gardées localement dans missionNamespace COMSPEC_ATAK_LogiReqs (id -> HashMap).
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Logi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Logi", _f];
private _reqs = missionNamespace getVariable ["COMSPEC_ATAK_LogiReqs", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_LogiReqs", _reqs];
private _now = [time, serverTime] select isMultiplayer;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "LOGI") then { ["LOGI"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Les champs texte sont relus avant chaque nouveau rendu.
private _save = {
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    { if (_x in _form) then { _f set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["logiNote", "logiCustom"];
};
private _me = { private _c = [player, true] call comspec_atak_native_fnc_unitCallsign; if (_c isEqualTo "") then { name player } else { _c } };
// Changement d'état diffusé au camp par le réseau simulé.
private _push = {
    params ["_id", "_st", ["_pos", []]];
    [{
        params ["_side", "_id", "_st", "_by", "_pos"];
        ["comspec_atak_native_logi", ["STATUS", _side, [_id, _st, _by, _pos]]] call CBA_fnc_globalEvent;
    }, [str side group player, _id, _st, call _me, _pos], format ["Logistique %1", _id], 1] call comspec_atak_native_fnc_netSend;
};
private _get = { params ["_id"]; _reqs getOrDefault [_id, createHashMap] };
private _ret = true;

switch (_act) do {
    case "set": { call _save; _f set [_arg, _val]; call _rerender; };
    case "pickHere": { call _save; _f set ["point", "ME"]; _f deleteAt "pickPos"; call _rerender; };
    case "pickMap": {
        call _save;
        _s set ["logiPick", "1"];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["INFO", "Touchez le point de livraison sur la carte", 4, 40] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        // Appelé par la carte (fn_mapMouseButtonDown) avec la position touchée.
        _f set ["point", "MAP"];
        _f set ["pickPos", [_arg select 0, _arg select 1, 0]];
        _s set ["logiTab", "NEW"];
        ["INFO", format ["Point de livraison : %1", [_arg, 8] call comspec_atak_native_fnc_gridRef], 3, 30] call comspec_atak_native_fnc_notify;
        [{ ["LOGI"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "items": {
        // Contenu de la demande d'après le formulaire : [[classe, nombre]...], libellé, classe de caisse.
        private _cat = _f getOrDefault ["cat", "AMMO"];
        private _qty = parseNumber (_f getOrDefault ["qty", "2"]) max 1;
        private _items = [];
        private _label = "";
        private _box = "Box_NATO_Support_F";
        private _cw = { params ["_c"]; isClass (configFile >> "CfgWeapons" >> _c) || {isClass (configFile >> "CfgMagazines" >> _c)} };
        switch (_cat) do {
            case "AMMO": {
                private _wk = _f getOrDefault ["weap", "PRIM"];
                private _w = switch (_wk) do { case "HAND": { handgunWeapon player }; case "LAUNCH": { secondaryWeapon player }; default { primaryWeapon player }; };
                if (_w isEqualTo "") exitWith { _label = ""; };
                private _mag = switch (_wk) do { case "HAND": { (handgunMagazine player) param [0, ""] }; case "LAUNCH": { (secondaryWeaponMagazine player) param [0, ""] }; default { (primaryWeaponMagazine player) param [0, ""] }; };
                if (_mag isEqualTo "") then { _mag = (compatibleMagazines _w) param [0, ""]; };
                if (_mag isEqualTo "") exitWith { _label = ""; };
                _items = [[_mag, _qty]];
                _label = format ["Munitions · %1", getText (configFile >> "CfgMagazines" >> _mag >> "displayName")];
                _box = "Box_NATO_Ammo_F";
            };
            case "MED": {
                private _set = if (isClass (configFile >> "CfgPatches" >> "ace_medical_treatment")) then {
                    [["ACE_fieldDressing", 4], ["ACE_elasticBandage", 4], ["ACE_packingBandage", 4], ["ACE_tourniquet", 1], ["ACE_morphine", 1], ["ACE_epinephrine", 1], ["ACE_salineIV_500", 1]]
                } else { [["FirstAidKit", 2]] };
                _items = (_set select { [_x select 0] call _cw }) apply { [_x select 0, (_x select 1) * _qty] };
                _label = "Santé · lot de soins";
                if (isClass (configFile >> "CfgVehicles" >> "ACE_medicalSupplyCrate")) then { _box = "ACE_medicalSupplyCrate"; };
            };
            case "FOOD": {
                _items = (["ACE_WaterBottle", "ACE_MRE_BeefStew", "ACE_MRE_ChickenTikkaMasala"] select { [_x] call _cw }) apply { [_x, _qty] };
                _label = "Vivres · eau et rations";
            };
            case "BATT": {
                private _b = ((missionNamespace getVariable ["comspec_atak_native_battery_items", "ACE_UAVBattery"]) splitString ", ") select { [_x] call _cw };
                if ((count _b) > 0) then { _items = [[_b select 0, _qty]]; };
                _label = "Batteries de téléphone";
            };
            case "VEH": {
                if ([("ToolKit")] call _cw) then { _items = [["ToolKit", _qty]]; };
                _label = "Véhicule · carburant et réparation";
                _box = "Box_NATO_AmmoVeh_F";
            };
            default {
                // Matériel libre : « classe x2, classe » ; seules les classes connues remplissent la caisse.
                private _txt = _f getOrDefault ["logiCustom", ""];
                {
                    private _parts = (trim _x) splitString " ";
                    if ((count _parts) > 0) then {
                        private _c = _parts select 0;
                        private _n = 1;
                        if ((count _parts) > 1) then { _n = (parseNumber (((_parts select 1) splitString "xX") param [0, "1"])) max 1; };
                        if ([_c] call _cw || {isClass (configFile >> "CfgVehicles" >> _c)}) then { _items pushBack [_c, _n * _qty]; };
                    };
                } forEach (_txt splitString ",;");
                _label = format ["Autre · %1", [_txt select [0, 40], "matériel divers"] select (_txt isEqualTo "")];
            };
        };
        _ret = [_items, _label, _box];
    };
    case "send": {
        call _save;
        private _last = missionNamespace getVariable ["COMSPEC_ATAK_LogiLastAt", -999];
        if (time - _last < 20) exitWith { ["WARNING", "Patientez avant une nouvelle demande", 3, 20] call comspec_atak_native_fnc_notify; };
        (["items"] call comspec_atak_native_fnc_logisticsAction) params ["_items", "_label", "_box"];
        if (_label isEqualTo "") exitWith { ["WARNING", "Aucune arme à ravitailler dans cet emplacement", 3, 20] call comspec_atak_native_fnc_notify; };
        private _cat = _f getOrDefault ["cat", "AMMO"];
        if ((count _items) isEqualTo 0 && {_cat isNotEqualTo "CUSTOM"}) exitWith { ["WARNING", "Ce matériel n'existe pas sur ce serveur (mod absent)", 3, 20] call comspec_atak_native_fnc_notify; };
        private _pos = if ((_f getOrDefault ["point", "ME"]) isEqualTo "MAP") then { _f getOrDefault ["pickPos", getPosATL player] } else { getPosATL player };
        _pos = [_pos select 0, _pos select 1, 0];
        missionNamespace setVariable ["COMSPEC_ATAK_LogiLastAt", time];
        private _uid = getPlayerUID player;
        private _id = format ["LG%1-%2", 100 + floor random 900, _uid select [((count _uid) - 3) max 0]];
        private _req = createHashMapFromArray [
            ["id", _id], ["uid", _uid], ["cs", call _me], ["side", str side group player], ["cat", _cat], ["label", _label],
            ["items", _items], ["box", _box], ["qty", _f getOrDefault ["qty", "2"]], ["mode", _f getOrDefault ["mode", "PICKUP"]],
            ["pos", _pos], ["grid", [_pos, 8] call comspec_atak_native_fnc_gridRef], ["prio", _f getOrDefault ["prio", "PRIORITY"]],
            ["note", (_f getOrDefault ["logiNote", ""]) select [0, 200]], ["status", "DEMANDEE"], ["by", ""], ["ts", _now],
            ["hour", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ];
        private _pairs = (keys _req) apply { [_x, _req get _x] };
        _f set ["logiNote", ""];
        _s set ["logiTab", "MINE"];
        [{
            params ["_pairs", "_side"];
            ["comspec_atak_native_logi", ["NEW", _side, _pairs]] call CBA_fnc_globalEvent;
            // Liaison Overwatch : la demande part aussi au poste (rapport tactique, visible sur le portail Athena).
            if ([] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_submitTacticalReport"}) then {
                private _r = createHashMapFromArray _pairs;
                private _prio = createHashMapFromArray [["URGENT", "IMMEDIATE"], ["PRIORITY", "PRIORITY"], ["ROUTINE", "ROUTINE"]] getOrDefault [_r get "prio", "ROUTINE"];
                private _mode = createHashMapFromArray [["PICKUP", "ramassage"], ["AIRDROP", "largage parachute"], ["VEHICLE", "livraison véhicule"]] getOrDefault [_r get "mode", "ramassage"];
                private _sd = createHashMapFromArray [
                    ["kind", "resupply_request"], ["request_id", _r get "id"], ["category", _r get "cat"], ["quantity", _r get "qty"],
                    ["delivery", _r get "mode"], ["grid_ref", _r get "grid"], ["items", ((_r get "items") apply { format ["%1 x%2", _x select 0, _x select 1] }) joinString ", "]
                ];
                [_prio, format ["LOGREQ %1 · %2 x%3", _r get "id", _r get "label", _r get "qty"],
                    format ["Demande de ravitaillement %1 : %2 x%3, %4 en %5. %6", _r get "cs", _r get "label", _r get "qty", _mode, _r get "grid", _r get "note"], _sd, _r get "pos"] spawn {
                    params ["_prio", "_sum", "_det", "_sd", "_pos"];
                    ["OTHER", _prio, _sum, _det, _sd, _pos] call comspec_overwatch_connect_fnc_submitTacticalReport;
                };
            };
            ["SUCCESS", "Demande logistique transmise au camp", 4, 40] call comspec_atak_native_fnc_notify;
        }, [_pairs, str side group player], "Demande logistique", 2] call comspec_atak_native_fnc_netSend;
        call _rerender;
    };
    case "validate": { [_arg, "VALIDEE"] call _push; };
    case "refuse": { [_arg, "REFUSEE"] call _push; };
    case "enroute": { [_arg, "EN_ROUTE"] call _push; };
    case "deliver": { [_arg, "LIVREE"] call _push; };
    case "cancel": {
        if (((([_arg] call _get) getOrDefault ["status", ""])) in ["DEMANDEE", "VALIDEE"]) then { [_arg, "ANNULEE"] call _push; };
    };
    case "locate": {
        private _p = ([_arg] call _get) getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "airdrop": {
        private _r = [_arg] call _get;
        if ((_r getOrDefault ["status", ""]) isNotEqualTo "VALIDEE") exitWith { ["WARNING", "Validez d'abord la demande", 3, 20] call comspec_atak_native_fnc_notify; };
        if (_r getOrDefault ["dropping", false]) exitWith {};
        _r set ["dropping", true];
        [_arg, "EN_ROUTE"] call _push;
        private _eta = 40 + random 50;
        ["INFO", format ["Largage %1 : avion en route, arrivée dans %2 s environ", _arg, round _eta], 5, 40] call comspec_atak_native_fnc_notify;
        [{ ["drop", _this] call comspec_atak_native_fnc_logisticsAction; }, _arg, _eta] call CBA_fnc_waitAndExecute;
    };
    case "drop": {
        // Machine du logisticien qui a lancé le largage : caisse sous parachute remplie selon la demande.
        private _r = [_arg] call _get;
        if ((count _r) isEqualTo 0) exitWith {};
        private _p = _r get "pos";
        private _pos = [_p, 0, 30, 3, 0, 0.4, 0, [], [_p, _p]] call BIS_fnc_findSafePos;
        if ((count _pos) < 2) then { _pos = _p; };
        _pos = [_pos select 0, _pos select 1, 0];
        private _cls = _r getOrDefault ["box", "Box_NATO_Support_F"];
        if !(isClass (configFile >> "CfgVehicles" >> _cls)) then { _cls = "Box_NATO_Support_F"; };
        private _box = createVehicle [_cls, [_pos select 0, _pos select 1, 150], [], 0, "CAN_COLLIDE"];
        clearItemCargoGlobal _box; clearMagazineCargoGlobal _box; clearWeaponCargoGlobal _box; clearBackpackCargoGlobal _box;
        {
            _x params ["_c", "_n"];
            switch (true) do {
                case (isClass (configFile >> "CfgMagazines" >> _c)): { _box addMagazineCargoGlobal [_c, _n]; };
                case (isClass (configFile >> "CfgVehicles" >> _c)): { _box addBackpackCargoGlobal [_c, _n]; };
                case ((getNumber (configFile >> "CfgWeapons" >> _c >> "type")) in [1, 2, 4]): { _box addWeaponCargoGlobal [_c, _n]; };
                default { _box addItemCargoGlobal [_c, _n]; };
            };
        } forEach (_r getOrDefault ["items", []]);
        private _chute = createVehicle ["B_Parachute_02_F", [_pos select 0, _pos select 1, 150], [], 0, "CAN_COLLIDE"];
        _box attachTo [_chute, [0, 0, -1.2]];
        [_box, _chute, _arg, (_r getOrDefault ["cat", ""]) isEqualTo "VEH"] spawn {
            params ["_box", "_chute", "_id", "_veh"];
            waitUntil { sleep 0.5; isNull _chute || {((getPosATL _box) select 2) < 3} };
            detach _box;
            private _p = getPosATL _box;
            _box setPosATL [_p select 0, _p select 1, 0];
            if (!isNull _chute) then { deleteVehicle _chute; };
            createVehicle ["SmokeShellGreen", getPosATL _box, [], 0, "CAN_COLLIDE"];
            if (sunOrMoon < 0.5) then { createVehicle ["Chemlight_green", getPosATL _box, [], 0, "CAN_COLLIDE"]; };
            // Besoin véhicule : un jerrican à côté de la caisse (ACE Refuel s'il est chargé).
            if (_veh) then {
                private _can = createVehicle ["Land_CanisterFuel_F", _box getRelPos [2, 90], [], 0, "CAN_COLLIDE"];
                if (!isNil "ace_refuel_fnc_makeJerryCan") then { [_can, 20] call ace_refuel_fnc_makeJerryCan; };
            };
            [{ ["landed", _this] call comspec_atak_native_fnc_logisticsAction; }, [_id, getPosATL _box]] call CBA_fnc_execNextFrame;
            // Caisse oubliée : retirée au bout de 30 min.
            sleep 1800;
            if (!isNull _box) then { deleteVehicle _box; };
        };
    };
    case "landed": {
        _arg params ["_id", "_pos"];
        [_id, "LIVREE", _pos] call _push;
    };
    case "recv": {
        _arg params [["_op", ""], ["_side", ""], ["_p", []]];
        if (_side isNotEqualTo str side group player) exitWith {};
        private _mine = getPlayerUID player;
        private _phone = [player] call comspec_atak_native_fnc_hasDevice;
        switch (_op) do {
            case "NEW": {
                private _r = createHashMapFromArray _p;
                _r set ["recvAt", time];
                _reqs set [_r get "id", _r];
                if ((_r get "uid") isNotEqualTo _mine && {_phone}) then {
                    ["INFO", format ["Demande logistique · %1 · %2 x%3 · %4", _r get "cs", _r get "label", _r get "qty", _r get "grid"], 6, [40, 70] select ((_r get "prio") isEqualTo "URGENT")] call comspec_atak_native_fnc_notify;
                    if ((_r get "prio") isEqualTo "URGENT") then { [] call comspec_atak_native_fnc_vibrate; };
                };
            };
            case "STATUS": {
                _p params ["_id", "_st", "_by", ["_pos", []]];
                private _r = _reqs getOrDefault [_id, createHashMap];
                if ((count _r) isEqualTo 0) exitWith {};
                _r set ["status", _st];
                _r set ["by", _by];
                _r set ["hourUpd", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
                if ((count _pos) >= 2) then { _r set ["dropPos", _pos]; };
                private _lab = createHashMapFromArray [["VALIDEE", "VALIDÉE"], ["REFUSEE", "REFUSÉE"], ["EN_ROUTE", "EN ROUTE"], ["LIVREE", "LIVRÉE"], ["ANNULEE", "ANNULÉE"]] getOrDefault [_st, _st];
                if ((_r get "uid") isEqualTo _mine && {_st isNotEqualTo "ANNULEE"}) then {
                    [["INFO", "SUCCESS"] select (_st in ["VALIDEE", "LIVREE"]), format ["Votre demande %1 : %2 (%3)", _id, _lab, _by], 6, 50] call comspec_atak_native_fnc_notify;
                    [] call comspec_atak_native_fnc_vibrate;
                };
                // Largage posé : repère local pour tout le camp équipé.
                if (_st isEqualTo "LIVREE" && {(_r get "mode") isEqualTo "AIRDROP"} && {_phone} && {(count _pos) >= 2}) then {
                    private _m = createMarkerLocal [format ["COMSPEC_LOGI_%1", _id], _pos];
                    _m setMarkerTypeLocal "mil_pickup"; _m setMarkerColorLocal "ColorGreen"; _m setMarkerTextLocal format ["Largage %1", _id];
                    [{ deleteMarkerLocal _this; }, _m, 900] call CBA_fnc_waitAndExecute;
                };
            };
        };
        // Historique borné : 40 demandes au plus.
        if ((count _reqs) > 40) then {
            private _old = (keys _reqs) apply { [(_reqs get _x) getOrDefault ["ts", 0], _x] };
            _old sort true;
            { _reqs deleteAt (_x select 1); } forEach (_old select [0, (count _reqs) - 40]);
        };
        call _rerender;
    };
};
_ret
