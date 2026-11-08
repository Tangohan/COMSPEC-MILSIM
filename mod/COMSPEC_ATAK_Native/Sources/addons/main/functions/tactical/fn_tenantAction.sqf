/*
    App Communauté : consignes, ordres, tips et kit obligatoire.
    Sources :
      Consignes d'Athena : le « guide » de la communauté, déjà reçu avec ses réglages (COMSPEC_TenantExperience,
      GetExperience). Athena n'a pas encore d'API de consignes ni de kit : voir docs/technical/ICD-ATHENA-ATAK.md.
      Notes de la partie : variable publique COMSPEC_ATAK_TenantNotes : [[id, genre (consigne | ordre | tip), titre, texte]...],
      posée par la mission, ou écrite dans l'app par un admin ou un Zeus (revérifié par fn_adminServer). Perdues en fin de partie.
      Kit obligatoire : variable publique de mission COMSPEC_ATAK_RequiredKit, sinon réglage comspec_atak_native_kit_required.
      Texte « classe, classe*2, classeA|classeB » ; ou tableau de [[classes acceptées], nombre, libellé].
    Comparaison à l'ouverture de l'app et toutes les 30 s, jamais par image.
    Params : [action, argument]
      "notes" notes valides          "guide" texte du guide      "kit" besoins [[classes, nombre, libellé]...]
      "missing" [[libellé, manque]...]    "inventory" table classe -> nombre    "tick"
      "add" [genre, titre, texte]    "del" id    (client : demande au serveur)
      "srvAdd" [genre, titre, texte, auteur]    "srvDel" id    (serveur, appelé par fn_adminServer)
*/
params [["_act", ""], ["_arg", []]];
private _str = { params ["_v", "_n"]; if (_v isEqualType "") then { _v select [0, _n] } else { "" } };
private _read = {
    private _v = missionNamespace getVariable ["COMSPEC_ATAK_TenantNotes", []];
    if !(_v isEqualType []) exitWith { [] };
    (_v select [0, 40]) select { _x isEqualTypeArray ["", "", "", ""] && {(_x select 1) in ["consigne", "ordre", "tip"]} }
};
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "TENANT") then { ["TENANT"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
switch (_act) do {
    case "notes": { call _read };
    case "guide": {
        private _m = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
        if !(_m isEqualType createHashMap) exitWith { "" };
        [_m getOrDefault ["guide", ""], 4000] call _str
    };
    case "kit": {
        // Bornes vérifiées : une liste trop longue ou mal formée ne coûte rien et ne plante pas.
        private _src = missionNamespace getVariable ["COMSPEC_ATAK_RequiredKit", []];
        if (_src isEqualType [] && {_src isNotEqualTo []}) exitWith {
            ((_src select [0, 40]) select { _x isEqualType [] && {(_x param [0, []]) isEqualType []} }) apply {
                _x params [["_cls", []], ["_n", 1], ["_lbl", ""]];
                [(_cls select { _x isEqualType "" }) apply { toLower _x }, [1, _n max 1] select (_n isEqualType 0), [_lbl, ""] select !(_lbl isEqualType "")]
            }
        };
        private _txt = missionNamespace getVariable ["comspec_atak_native_kit_required", ""];
        if !(_txt isEqualType "") exitWith { [] };
        ((_txt splitString ",;" ) select [0, 40]) apply {
            private _p = (trim _x) splitString "*";
            [((_p param [0, ""]) splitString "|") apply { toLower trim _x }, (parseNumber (_p param [1, "1"])) max 1, ""]
        }
    };
    case "inventory": {
        private _all = (assignedItems player) + (items player) + (magazines player) + (weapons player) + (primaryWeaponItems player) + (secondaryWeaponItems player) + (handgunItems player)
            + [uniform player, vest player, backpack player, headgear player, goggles player, hmd player];
        private _h = createHashMap;
        { if (_x isNotEqualTo "") then { private _k = toLower _x; _h set [_k, (_h getOrDefault [_k, 0]) + 1]; }; } forEach _all;
        _h
    };
    case "label": {
        private _cls = _arg;
        private _cfg = (["CfgWeapons", "CfgMagazines", "CfgVehicles", "CfgGlasses"] apply { configFile >> _x >> _cls }) select { isClass _x };
        if (_cfg isEqualTo []) exitWith { _cls };
        private _n = getText ((_cfg select 0) >> "displayName");
        [_n, _cls] select (_n isEqualTo "")
    };
    case "missing": {
        private _kit = ["kit"] call comspec_atak_native_fnc_tenantAction;
        if (_kit isEqualTo []) exitWith { [] };
        private _inv = ["inventory"] call comspec_atak_native_fnc_tenantAction;
        private _out = [];
        {
            _x params ["_cls", "_n", "_lbl"];
            private _have = 0;
            {
                private _want = _x;
                _have = _have + (_inv getOrDefault [_want, 0]);
                // Classe parente acceptée : une variante du même objet compte (CfgWeapons seulement).
                if (_have < _n && {isClass (configFile >> "CfgWeapons" >> _want)}) then {
                    { if (_x isNotEqualTo _want && {_x isKindOf [_want, configFile >> "CfgWeapons"]}) then { _have = _have + _y; }; } forEach _inv;
                };
            } forEach _cls;
            if (_have < _n && {_cls isNotEqualTo []}) then {
                private _name = if (_lbl isNotEqualTo "") then { _lbl } else { (_cls apply { ["label", _x] call comspec_atak_native_fnc_tenantAction }) joinString " / " };
                _out pushBack [_name, _n - _have];
            };
        } forEach _kit;
        _out
    };
    // Toutes les 30 s : pas de calcul sans kit défini ; alerte seulement quand la liste des manques change.
    case "tick": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith {};
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _miss = ["missing"] call comspec_atak_native_fnc_tenantAction;
        if (_miss isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_KitMissSig", []])) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_KitMissSig", _miss];
        if (_miss isNotEqualTo []) then {
            ["WARNING", format ["Kit obligatoire : %1 objet(s) manquant(s). Voir l'app Communauté.", count _miss], 6, 30, "TENANT"] call comspec_atak_native_fnc_notify;
        };
        call _render;
    };
    case "add": {
        _arg params [["_kind", "consigne"], ["_title", ""], ["_text", ""]];
        if ((trim _title) isEqualTo "" && {(trim _text) isEqualTo ""}) exitWith { ["Écrivez un titre ou un texte", 3] call comspec_atak_native_fnc_toast; false };
        ["server", ["tenantNote", ["add", [_kind, _title, _text]]]] call comspec_atak_native_fnc_adminClient;
        [_render, [], 1] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "del": {
        ["server", ["tenantNote", ["del", _arg]]] call comspec_atak_native_fnc_adminClient;
        [_render, [], 1] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "srvAdd": {
        if (!isServer) exitWith { false };
        _arg params [["_kind", "consigne"], ["_title", ""], ["_text", ""], ["_by", ""]];
        if !(_kind in ["consigne", "ordre", "tip"]) exitWith { false };
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_TenantNoteSeq", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_TenantNoteSeq", _n];
        private _l = call _read;
        _l pushBack [format ["N%1", _n], _kind, trim ([_title, 60] call _str), format ["%1%2", trim ([_text, 600] call _str), ["", format [" · %1", _by]] select (_by isNotEqualTo "")]];
        missionNamespace setVariable ["COMSPEC_ATAK_TenantNotes", _l select [((count _l) - 40) max 0], true];
        true
    };
    case "srvDel": {
        if (!isServer) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TenantNotes", (call _read) select { (_x select 0) isNotEqualTo _arg }, true];
        true
    };
    default { false };
};
