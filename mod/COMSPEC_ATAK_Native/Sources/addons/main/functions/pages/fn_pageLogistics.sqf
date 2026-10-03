/*
    App Logistique : onglets NOUVELLE (demande de ravitaillement), MES DEMANDES (suivi), REÇUES (demandes du camp).
    Demande : besoin, quantité, mode de livraison (ramassage, largage parachute, véhicule), point (ma position ou carte), priorité.
    Suivi : DEMANDÉE -> VALIDÉE -> EN ROUTE -> LIVRÉE, ou REFUSÉE. Tout porteur de téléphone du camp peut traiter les demandes reçues.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Logi", createHashMap];
private _tab = _s getOrDefault ["logiTab", "NEW"];
private _reqs = missionNamespace getVariable ["COMSPEC_ATAK_LogiReqs", createHashMap];
private _mine = getPlayerUID player;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _icon = "\z\comspec_atak_native\addons\main\data\app_logistics.paa";
private _all = [values _reqs, [], { _x getOrDefault ["ts", 0] }, "DESCEND"] call BIS_fnc_sortBy;
private _myReqs = _all select { (_x get "uid") isEqualTo _mine };
private _open = { (_x get "status") in ["DEMANDEE", "VALIDEE", "EN_ROUTE"] };
private _inbox = _all select { (_x get "uid") isNotEqualTo _mine };
private _nIn = { (_x get "status") isEqualTo "DEMANDEE" } count _inbox;
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['logiTab', '%1']; [{ ['LOGI'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [
    ["NOUVELLE", "NEW"] call _tabBtn,
    [["MES DEMANDES", format ["MES DEMANDES (%1)", { call _open } count _myReqs]] select (({ call _open } count _myReqs) > 0), "MINE"] call _tabBtn,
    [["REÇUES", format ["REÇUES (%1)", _nIn]] select (_nIn > 0), "INBOX"] call _tabBtn
]], ["gap"]];
// Libellés et couleurs communs.
private _stLab = createHashMapFromArray [["DEMANDEE", ["DEMANDÉE", "#e8b84a"]], ["VALIDEE", ["VALIDÉE", "#7fb6e6"]], ["EN_ROUTE", ["EN ROUTE", "#f2ab33"]], ["LIVREE", ["LIVRÉE", "#5cc76b"]], ["REFUSEE", ["REFUSÉE", "#e5483a"]], ["ANNULEE", ["ANNULÉE", "#8a9a93"]]];
private _prioLab = createHashMapFromArray [["URGENT", ["URGENT", "#e5483a"]], ["PRIORITY", ["PRIORITAIRE", "#f2ab33"]], ["ROUTINE", ["ROUTINE", "#8a9a93"]]];
private _modeLab = createHashMapFromArray [["PICKUP", "Ramassage"], ["AIRDROP", "Largage parachute"], ["VEHICLE", "Livraison véhicule"]];
private _progress = {
    params ["_st"];
    if (_st in ["REFUSEE", "ANNULEE"]) exitWith { format ["<t color='%1'>● %2</t>", (_stLab get _st) select 1, (_stLab get _st) select 0] };
    private _i = ["DEMANDEE", "VALIDEE", "EN_ROUTE", "LIVREE"] find _st;
    private _out = [];
    { _out pushBack ([format ["○ %1", _x], format ["<t color='#5cc76b'>● %1</t>", _x]] select (_forEachIndex <= _i)); } forEach ["Demandée", "Validée", "En route", "Livrée"];
    _out joinString "  "
};
private _card = {
    params ["_r", "_showWho"];
    private _p = _prioLab getOrDefault [_r get "prio", ["ROUTINE", "#8a9a93"]];
    format ["<t font='RobotoCondensedBold'>%1 x%2</t>  <t size='0.75' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5%6 · %7 · %8</t><br/><t size='0.75'>%9</t>%10",
        [_r get "label"] call _esc, _r get "qty", _p select 1, _p select 0,
        ["", format ["%1 · ", [_r get "cs"] call _esc]] select _showWho, _r get "id", _modeLab getOrDefault [_r get "mode", ""], _r get "grid",
        [_r get "status"] call _progress,
        ["", format ["<br/><t size='0.75' color='#8a9a93'>%1</t>", [_r get "note"] call _esc]] select ((_r getOrDefault ["note", ""]) isNotEqualTo "")]
};

switch (_tab) do {
    case "MINE": {
        _rows pushBack ["section", "Mes demandes", "Suivi en direct, notification à chaque changement"];
        {
            private _id = _x get "id";
            private _st = _x get "status";
            _rows pushBack ["text", [_x, false] call _card];
            private _b = [["CARTE", compile format ["['locate', '%1'] call comspec_atak_native_fnc_logisticsAction;", _id]]];
            if (_st in ["DEMANDEE", "VALIDEE"]) then { _b pushBack ["ANNULER", compile format ["['cancel', '%1'] call comspec_atak_native_fnc_logisticsAction;", _id]]; };
            if ((_x getOrDefault ["by", ""]) isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.75' color='#8a9a93'>Traité par %1 à %2</t>", [_x get "by"] call _esc, _x getOrDefault ["hourUpd", ""]]]; };
            _rows pushBack ["buttons", _b];
        } forEach (_myReqs select [0, 15]);
        if ((count _myReqs) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune demande envoyée.</t>"]; };
    };
    case "INBOX": {
        _rows pushBack ["section", "Demandes reçues", "Demandes du camp, les ouvertes en premier"];
        private _sorted = (_inbox select _open) + (_inbox select { !(call _open) });
        {
            private _id = _x get "id";
            private _st = _x get "status";
            _rows pushBack ["text", [_x, true] call _card];
            private _cmd = { params ["_label", "_a", ["_primary", false]]; [_label, compile format ["['%1', '%2'] call comspec_atak_native_fnc_logisticsAction;", _a, _id], _primary] };
            private _b = [["CARTE", "locate"] call _cmd];
            switch (_st) do {
                case "DEMANDEE": { _b append [["VALIDER", "validate", true] call _cmd, ["REFUSER", "refuse"] call _cmd]; };
                case "VALIDEE": {
                    if ((_x get "mode") isEqualTo "AIRDROP") then { _b pushBack (["LANCER LE LARGAGE", "airdrop", true] call _cmd); } else { _b pushBack (["EN ROUTE", "enroute", true] call _cmd); };
                };
                case "EN_ROUTE": { if ((_x get "mode") isNotEqualTo "AIRDROP") then { _b pushBack (["LIVRÉE", "deliver", true] call _cmd); }; };
            };
            _rows pushBack ["buttons", _b];
        } forEach (_sorted select [0, 20]);
        if ((count _inbox) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune demande reçue depuis votre arrivée.</t>"]; };
    };
    default {
        private _cur = { params ["_k", "_d"]; _f getOrDefault [_k, _d] };
        private _seg = {
            params ["_label", "_key", "_def", "_opts", ["_help", ""]];
            private _v = [_key, _def] call _cur;
            ["segment", _label, _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_logisticsAction;", _key, _x select 1], (_x select 1) isEqualTo _v] }, _help]
        };
        private _cat = ["cat", "AMMO"] call _cur;
        _rows append [
            ["hero", _icon, "<t size='1.2' font='RobotoCondensedBold'>Demande de ravitaillement</t><br/><t color='#8a9a93'>Envoyée à tous les porteurs de téléphone du camp.</t>"],
            ["Besoin", "cat", "AMMO", [["MUNITIONS", "AMMO"], ["SANTÉ", "MED"], ["VIVRES", "FOOD"]]] call _seg,
            ["", "cat", "AMMO", [["BATTERIES", "BATT"], ["VÉHICULE", "VEH"], ["AUTRE", "CUSTOM"]]] call _seg
        ];
        if (_cat isEqualTo "AMMO") then {
            _rows pushBack (["Arme", "weap", "PRIM", [["PRINCIPALE", "PRIM"], ["POING", "HAND"], ["LANCEUR", "LAUNCH"]]] call _seg);
        };
        if (_cat isEqualTo "CUSTOM") then {
            _rows pushBack ["edit", "logiCustom", "Matériel (classes séparées par des virgules, ex. ToolKit x2)", ["logiCustom", ""] call _cur];
        };
        (["items"] call comspec_atak_native_fnc_logisticsAction) params ["_items", "_label"];
        private _what = if (_label isEqualTo "") then { "<t color='#f2ab33'>Aucune arme dans cet emplacement</t>" } else {
            if ((count _items) isEqualTo 0) then { format ["%1 <t color='#f2ab33'>(rien de connu à charger)</t>", [_label] call _esc] } else { [_label] call _esc };
        };
        _rows pushBack ["info", "Contenu", _what];
        private _ptMap = (["point", "ME"] call _cur) isEqualTo "MAP";
        private _pt = if (_ptMap) then { ["pickPos", getPosATL player] call _cur } else { getPosATL player };
        _rows append [
            ["Quantité", "qty", "2", [["1", "1"], ["2", "2"], ["4", "4"], ["6", "6"], ["10", "10"]], createHashMapFromArray [["AMMO", "chargeurs"], ["MED", "lots de soins"], ["FOOD", "par article"]] getOrDefault [_cat, "unités"]] call _seg,
            ["Livraison", "mode", "PICKUP", [["RAMASSAGE", "PICKUP"], ["LARGAGE", "AIRDROP"], ["VÉHICULE", "VEHICLE"]]] call _seg,
            ["segment", "Point de livraison", [
                ["MA POSITION", { ['pickHere'] call comspec_atak_native_fnc_logisticsAction; }, !_ptMap],
                ["SUR LA CARTE", { ['pickMap'] call comspec_atak_native_fnc_logisticsAction; }, _ptMap]
            ]],
            ["info", "Grille", format ["%1  <t color='#8a9a93'>(%2 m)</t>", [_pt, 8] call comspec_atak_native_fnc_gridRef, round (player distance2D _pt)]],
            ["Priorité", "prio", "PRIORITY", [["URGENT", "URGENT"], ["PRIORITAIRE", "PRIORITY"], ["ROUTINE", "ROUTINE"]]] call _seg,
            ["edit", "logiNote", "Remarques (200 caractères)", ["logiNote", ""] call _cur],
            ["buttons", [["ENVOYER LA DEMANDE", { ['send'] call comspec_atak_native_fnc_logisticsAction; }, true]]],
            ["text", format ["<t size='0.75' color='#8a9a93'>%1</t>", ["Sans liaison Overwatch, la demande reste sur le réseau du camp.", "Liaison Overwatch : la demande part aussi au poste (rapport tactique sur le portail)."] select ([] call comspec_atak_native_fnc_bridge)]]
        ];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
