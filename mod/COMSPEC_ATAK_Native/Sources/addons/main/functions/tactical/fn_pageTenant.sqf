/*
    App Communauté (fn_tenantAction) : CONSIGNES, ORDRES, TIPS, KIT. Lecture pour tous ; un admin ou un Zeus note
    aussi pour la partie (revérifié par le serveur). Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _tab = _s getOrDefault ["tenantTab", "CONSIGNE"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_tenant.paa";
private _admin = [player] call comspec_atak_native_fnc_adminAllowed;
["briefLoad"] call comspec_atak_native_fnc_tenantAction;
private _notes = ["notes"] call comspec_atak_native_fnc_tenantAction;
private _missing = ["missing"] call comspec_atak_native_fnc_tenantAction;
private _seg = {
    params ["_t", "_k"];
    [_t, compile format ["(uiNamespace getVariable 'COMSPEC_ATAK_State') set ['tenantTab', %1]; ['TENANT'] call comspec_atak_native_fnc_pageRender;", str _k], _tab isEqualTo _k]
};
private _kitLabel = if (_missing isEqualTo []) then { "KIT" } else { format ["KIT (%1)", count _missing] };
private _rows = [["segment", "", [["CONSIGNES", "CONSIGNE"] call _seg, ["ORDRES", "ORDRE"] call _seg, ["TIPS", "TIP"] call _seg, [_kitLabel, "KIT"] call _seg]]];
private _noteRows = {
    params ["_kind"];
    private _mine = _notes select { (_x select 1) isEqualTo _kind };
    {
        _x params ["_id", "", "_title", "_text"];
        private _body = format ["<t font='COMSPEC_BarlowBold'>%1</t><br/>%2", [_title] call _esc, [_text] call _esc];
        // Note du site (identifiant « A… ») : se modifie sur Athena, pas de suppression ici.
        if (_admin && {(_id select [0, 1]) isNotEqualTo "A"}) then {
            _rows pushBack ["person", "", _body, [["SUPPRIMER", compile format ["['del', %1] call comspec_atak_native_fnc_tenantAction;", str _id], false, true, "Retire cette note pour toute la partie"]]];
        } else {
            _rows pushBack ["text", _body];
        };
    } forEach _mine;
    _mine
};
private _editor = {
    params ["_kind"];
    if (!_admin) exitWith {};
    _rows pushBack ["section", "Noter pour la partie", "Tous les téléphones la reçoivent. Perdue en fin de partie : la version durable se règle sur le site."];
    _rows pushBack ["edit", "tenantTitle", "Titre", ""];
    _rows pushBack ["memo", "tenantText", "Texte", "", 3];
    _rows pushBack ["buttons", [["PUBLIER", compile format ["['add', [%1, ['tenantTitle'] call comspec_atak_native_fnc_formValue, ['tenantText'] call comspec_atak_native_fnc_formValue]] call comspec_atak_native_fnc_tenantAction;", str _kind], true]]];
};
switch (_tab) do {
    case "ORDRE": {
        _rows pushBack ["section", "Ordres de la communauté", ""];
        private _mine = ["ordre"] call _noteRows;
        private _done = ["ACK", "EXEC", "FAILED", "DONE", "COMPLETED", "CANCELLED", "CANCELED"];
        private _orders = (values ([] call comspec_atak_native_fnc_tasksAll)) select { !((toUpper (_x getOrDefault ["status", ""])) in _done) };
        if (_mine isEqualTo [] && {_orders isEqualTo []}) then { _rows pushBack ["empty", _icon, "Aucun ordre", "Les ordres de mission arrivent ici et dans Tâches."]; };
        if (_orders isNotEqualTo []) then {
            _rows pushBack ["section", format ["Ordres de mission : %1", count _orders], "À traiter dans l'app Tâches"];
            {
                _rows pushBack ["text", [format ["%1 · %2", _x getOrDefault ["typeLabel", _x getOrDefault ["type", "Ordre"]], _x getOrDefault ["target", ""]]] call _esc];
            } forEach (_orders select [0, 8]);
            _rows pushBack ["buttons", [["OUVRIR TÂCHES", { ["TASK"] call comspec_atak_native_fnc_navigate; }, true]]];
        };
        ["ordre"] call _editor;
    };
    case "TIP": {
        _rows pushBack ["section", "Tips de la communauté", ""];
        if ((["tip"] call _noteRows) isEqualTo []) then { _rows pushBack ["empty", _icon, "Aucun tip", ""]; };
        ["tip"] call _editor;
    };
    case "KIT": {
        private _kit = ["kit"] call comspec_atak_native_fnc_tenantAction;
        if (_kit isEqualTo []) then {
            _rows pushBack ["empty", _icon, "Aucun kit obligatoire", "La communauté sur Athena, la mission ou l'admin du serveur peut en définir un."];
        } else {
            if (_missing isEqualTo []) then {
                _rows pushBack ["banner", "Kit complet", [0.13, 0.42, 0.22, 1]];
            } else {
                _rows pushBack ["banner", format ["%1 objet(s) manquant(s)", count _missing], [0.55, 0.12, 0.08, 1]];
                { _x params ["_name", "_n"]; _rows pushBack ["info", _name, format ["manque %1", _n]]; } forEach _missing;
            };
            _rows pushBack ["section", "Objets obligatoires", "Comparés à ce que vous portez : équipé, sur vous, dans le sac"];
            {
                _x params ["_cls", "_n", "_lbl"];
                private _name = if (_lbl isNotEqualTo "") then { _lbl } else { (_cls apply { ["label", _x] call comspec_atak_native_fnc_tenantAction }) joinString " / " };
                _rows pushBack ["info", _name, format ["x %1", _n]];
            } forEach _kit;
        };
        _rows pushBack ["buttons", [["VÉRIFIER", { ["TENANT"] call comspec_atak_native_fnc_pageRender; }, true]]];
    };
    default {
        _rows pushBack ["section", "Consignes de la communauté", "Depuis Athena et pour cette partie"];
        private _guide = ["guide"] call comspec_atak_native_fnc_tenantAction;
        if (_guide isNotEqualTo "") then {
            _rows pushBack ["text", ((([_guide] call _esc) splitString toString [10]) joinString "<br/>")];
        };
        private _mine = ["consigne"] call _noteRows;
        if (_guide isEqualTo "" && {_mine isEqualTo []}) then {
            _rows pushBack ["empty", _icon, "Aucune consigne", "Connectez-vous à Athena pour recevoir le guide de votre communauté."];
        };
        ["consigne"] call _editor;
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
