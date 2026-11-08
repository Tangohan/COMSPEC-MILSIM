/*
    Objet obligatoire pour participer au réseau : réglage serveur comspec_atak_native_net_required_items (vide par défaut : rien ne change).
    Syntaxe de « Kit obligatoire » (fn_tenantAction "kit") : classes séparées par des virgules, classeA|classeB au choix, classe*2 pour un nombre.
    Sans l'objet : le joueur n'est plus un nœud (ni maillage, ni relais porté, ni données), le téléphone lui-même reste utilisable.
    Instances reconnues par la classe de base : classe, ses parents dans la config (ACRE_PRC117F_ID_1 hérite d'ACRE_PRC117F), acre_baseClass (ACRE2),
    tf_parent (TFAR : non vérifié sur une installation, TFAR n'étant pas sur la machine d'étude ; l'héritage de config suffit s'il est absent). Aucune fonction d'ACRE2 ni de TFAR appelée : marche sans eux.
    Une entrée dont aucune classe n'existe dans ce modset est ignorée et notée au RPT : une faute de frappe ne coupe pas le réseau de tout le serveur.
    Le serveur décide : il lit l'inventaire de chaque joueur toutes les 2 s et publie COMSPEC_ATAK_NetDenied sur le joueur (réécrit si un client l'efface) ;
    fn_meshGraph écarte le joueur quoi qu'il publie. Le client lit aussi son propre inventaire : la coupure et le retour sont immédiats de son côté.
      "groups"            -> [[[classes en minuscules], nombre]...]
      "missing" [unité]   -> groupes manquants d'après son inventaire : [[classes]...]
      "denied"            -> groupes manquants du joueur local (verdict du serveur et inventaire local, cache 1 s)
      "blocked" [unité]   -> vrai si l'unité (joueur) est exclue du réseau ; faux tout de suite si le réglage est vide
      "label" [classes]   -> noms affichés, séparés par « / »
      "labels" [groupes]  -> noms des groupes, séparés par des virgules
      "rows"              -> lignes de formulaire : un objet requis par ligne, présent ou manquant
      "tick" (client, 2 s) : changement d'état : notification, liaison recalculée, page redessinée
      "serverTick" (serveur, 2 s)
*/
params [["_act", ""], ["_arg", []]];
private _cfgs = ["CfgWeapons", "CfgMagazines", "CfgVehicles", "CfgGlasses"];
private _text = missionNamespace getVariable ["comspec_atak_native_net_required_items", ""];
if !(_text isEqualType "") then { _text = ""; };
switch (_act) do {
    case "groups": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_NetReqGroups", ["", []]];
        if ((_c select 0) isEqualTo _text) exitWith { _c select 1 };
        private _out = [];
        {
            private _p = (trim _x) splitString "*";
            private _cls = (((_p param [0, ""]) splitString "|") apply { toLower trim _x }) select { _x isNotEqualTo "" };
            private _known = _cls select { private _k = _x; (_cfgs findIf { isClass (configFile >> _x >> _k) }) >= 0 };
            if (_known isEqualTo []) then {
                if (_cls isNotEqualTo []) then { diag_log format ["[COMSPEC ATAK NATIVE][WARN][NET] Objet obligatoire ignoré, classe inconnue dans ce modset : %1", _cls]; };
            } else {
                _out pushBack [_known, (parseNumber (_p param [1, "1"])) max 1];
            };
        } forEach ((_text splitString ",;") select [0, 20]);
        uiNamespace setVariable ["COMSPEC_ATAK_NetReqGroups", [_text, _out]];
        _out
    };
    case "chain": {
        // Noms sous lesquels un objet compte : sa classe, ses parents, sa classe de base ACRE2 ou TFAR. Gardé en mémoire par classe.
        private _cls = _arg param [0, ""];
        private _memo = uiNamespace getVariable "COMSPEC_ATAK_NetReqChain";
        if (isNil "_memo") then { _memo = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_NetReqChain", _memo]; };
        private _hit = _memo getOrDefault [_cls, []];
        if (_hit isNotEqualTo []) exitWith { _hit };
        private _names = [toLower _cls];
        private _cfg = configNull;
        { if (isClass (configFile >> _x >> _cls)) exitWith { _cfg = configFile >> _x >> _cls; }; } forEach _cfgs;
        if (isClass _cfg) then {
            { private _b = getText (_cfg >> _x); if (_b isNotEqualTo "") then { _names pushBackUnique toLower _b; }; } forEach ["acre_baseClass", "tf_parent"];
            private _p = inheritsFrom _cfg;
            private _guard = 0;
            while { isClass _p && {_guard < 16} } do {
                _names pushBackUnique toLower (configName _p);
                _p = inheritsFrom _p;
                _guard = _guard + 1;
            };
        };
        _memo set [_cls, _names];
        _names
    };
    case "missing": {
        private _u = _arg param [0, player];
        private _groups = ["groups"] call comspec_atak_native_fnc_netRequire;
        if (_groups isEqualTo [] || {isNull _u}) exitWith { [] };
        private _have = createHashMap;
        {
            if (_x isNotEqualTo "") then {
                { _have set [_x, (_have getOrDefault [_x, 0]) + 1]; } forEach (["chain", [_x]] call comspec_atak_native_fnc_netRequire);
            };
        } forEach ((assignedItems _u) + (items _u) + (magazines _u) + (weapons _u) + (primaryWeaponItems _u) + (secondaryWeaponItems _u) + (handgunItems _u)
            + [uniform _u, vest _u, backpack _u, headgear _u, goggles _u, hmd _u]);
        (_groups select {
            _x params ["_cls", "_n"];
            private _sum = 0;
            { _sum = _sum + (_have getOrDefault [_x, 0]); } forEach _cls;
            _sum < _n
        }) apply { _x select 0 }
    };
    case "flag": {
        // Verdict publié par le serveur, lu avec type et taille vérifiés.
        private _f = (_arg param [0, objNull]) getVariable ["COMSPEC_ATAK_NetDenied", []];
        if (!(_f isEqualType []) || {(count _f) > 20} || {(_f findIf { !(_x isEqualType []) }) >= 0}) then { [] } else { _f }
    };
    case "denied": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_NetReqMe", [-100, []]];
        if (diag_tickTime - (_c select 0) < 1) exitWith { _c select 1 };
        private _d = ["missing", [player]] call comspec_atak_native_fnc_netRequire;
        if (_d isEqualTo []) then { _d = ["flag", [player]] call comspec_atak_native_fnc_netRequire; };
        uiNamespace setVariable ["COMSPEC_ATAK_NetReqMe", [diag_tickTime, _d]];
        _d
    };
    case "blocked": {
        private _u = _arg param [0, objNull];
        if (_text isEqualTo "" || {isNull _u} || {!isPlayer _u}) exitWith { false };
        if ((["flag", [_u]] call comspec_atak_native_fnc_netRequire) isNotEqualTo []) exitWith { true };
        (["missing", [_u]] call comspec_atak_native_fnc_netRequire) isNotEqualTo []
    };
    case "label": {
        (_arg param [0, []] apply {
            private _k = _x;
            private _name = "";
            { private _n = getText (configFile >> _x >> _k >> "displayName"); if (_n isNotEqualTo "") exitWith { _name = _n; }; } forEach _cfgs;
            [_name, _k] select (_name isEqualTo "")
        }) joinString " / "
    };
    case "labels": {
        ((_arg param [0, []]) apply { ["label", [_x]] call comspec_atak_native_fnc_netRequire }) joinString ", "
    };
    case "rows": {
        private _miss = ["missing", [player]] call comspec_atak_native_fnc_netRequire;
        (["groups"] call comspec_atak_native_fnc_netRequire) apply {
            _x params ["_cls", "_n"];
            private _lack = _cls in _miss;
            ["check", ["ok", "ko"] select _lack, (["label", [_cls]] call comspec_atak_native_fnc_netRequire) + (["", format [" × %1", _n]] select (_n > 1)), ["sur vous", "manquant"] select _lack]
        }
    };
    case "tick": {
        private _d = ["denied"] call comspec_atak_native_fnc_netRequire;
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_NetReqWas", []];
        if (_d isEqualTo _was) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_NetReqWas", _d];
        uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", nil];
        uiNamespace setVariable ["COMSPEC_ATAK_Mesh", nil];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        if (_d isNotEqualTo []) then {
            ["WARNING", format ["Réseau coupé, objet requis manquant : %1", ["labels", [_d]] call comspec_atak_native_fnc_netRequire], 6, 30] call comspec_atak_native_fnc_notify;
        } else {
            ["SUCCESS", "Objet requis équipé : le réseau repart", 4, 30] call comspec_atak_native_fnc_notify;
        };
        private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if (_page in ["WAVERELAY", "NETWORK"]) then {
            [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call comspec_atak_native_fnc_nextFrame;
        };
    };
    case "serverTick": {
        if (!isServer) exitWith {};
        private _on = (["groups"] call comspec_atak_native_fnc_netRequire) isNotEqualTo [];
        // Réglage vide et personne d'exclu : rien à faire.
        if (!_on && {!(missionNamespace getVariable ["COMSPEC_ATAK_NetReqAny", false])}) exitWith {};
        private _any = false;
        {
            private _v = if (_on) then { ["missing", [_x]] call comspec_atak_native_fnc_netRequire } else { [] };
            if (_v isNotEqualTo []) then { _any = true; };
            if ((_x getVariable ["COMSPEC_ATAK_NetDenied", []]) isNotEqualTo _v) then { _x setVariable ["COMSPEC_ATAK_NetDenied", _v, true]; };
        } forEach (allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} });
        missionNamespace setVariable ["COMSPEC_ATAK_NetReqAny", _any];
    };
};
