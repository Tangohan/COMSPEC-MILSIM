/*
    Page « IA de la zone » de l'éditeur SSE (client du Zeus) : détecter les IA, voir leur état, les configurer une à une ou en
    groupe, ouvrir et compléter leurs papiers, garder pour les prochaines parties. Tout est vérifié et appliqué sur le serveur
    (fn_idServer « aiCfg », fn_idAiConfig) ; rien par image.
    Aperçu : ce que verra le joueur, tiré du profil ou du réglage.
    Garder : « Garder pour les prochaines parties » envoie sur Athena (SubmitIdCheck, ai_keep), par communauté et par carte ; le serveur
    remet en place au démarrage (paquet). Retrouver une IA : nom de variable Eden, sinon même classe à moins de 20 m. Sans
    nom de variable, une IA posée au Zeus n'existe plus à la partie suivante : on le dit dans la note de la page.
*/
params [["_op", ""], ["_arg", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_editorAi", createHashMap];
uiNamespace setVariable ["comspec_sse_editorAi", _st];
private _tr = { _this call comspec_sse_fnc_tr };
private _send = { params ["_sub", ["_x1", []]]; ["send", ["aiCfg", [player, _sub, _x1]]] call comspec_sse_fnc_idClient; };
// Papiers à porter : anciens alias (fn_idRegistry « kit »), puis chaque clé du registre (CfgComspecDocuments, extensions actives
// comprises : « Carte de presse (Documents d'exemple) »). Même valeur que l'attribut Eden COMSPEC_ID_Carry.
private _papers = [["auto", "Selon son camp"], ["none", "Aucun"], ["card", "Carte d'identité"], ["passport", "Passeport"], ["license", "Permis de conduire"], ["card_license", "Carte et permis"], ["card_passport", "Carte et passeport"], ["military", "Carte militaire"]];
private _extNames = createHashMap;
{ _extNames set [toLower (_x select 0), _x select 1]; } forEach (["extensions"] call comspec_sse_fnc_idDocTypes);
{
    private _ext = toLower ((["get", _x] call comspec_sse_fnc_idDocTypes) getOrDefault ["extension", ""]);
    private _label = ["label", _x] call comspec_sse_fnc_idDocTypes;
    _papers pushBack [_x, if (_ext isEqualTo "") then { format ["%1 [%2]", _label, _x] } else { format ["%1 (%2)", _label, _extNames getOrDefault [_ext, _ext]] }];
} forEach (["list", [""]] call comspec_sse_fnc_idDocTypes);
private _kinds = [["civil", "Vrais papiers"], ["cover", "Couverture"], ["forged", "Faux papiers"]];
private _behav = [["auto", "Selon le contexte"], ["coopere", "Coopère"], ["nerveux", "Nerveux"], ["ment", "Ment avec aplomb"], ["refuse", "Refuse"], ["fuit", "S'enfuit"], ["agressif", "Devient agressif"], ["rendu", "Se rend"]];
private _sides = [["auto", "Ne pas changer"], ["civ", "Civils"], ["west", "BLUFOR"], ["east", "OPFOR"], ["guer", "Indépendants"]];
// Types de documents servis (registre, extensions actives) : liste « Ajouter un papier » et libellés des papiers d'une IA.
private _docTypes = (["list", [""]] call comspec_sse_fnc_idDocTypes) apply { [_x, ["label", _x] call comspec_sse_fnc_idDocTypes] };
private _fill = {
    params ["_idc", "_rows"];
    private _c = _d displayCtrl _idc;
    lbClear _c;
    { private _i = _c lbAdd ((_x select 1) call _tr); _c lbSetData [_i, _x select 0]; } forEach _rows;
    _c lbSetCurSel 0;
};
private _data = { params ["_idc"]; private _c = _d displayCtrl _idc; private _i = lbCurSel _c; if (_i < 0) exitWith { "" }; _c lbData _i };
private _selNet = { private _l = _d displayCtrl 94510; private _r = lnbCurSelRow _l; if (_r < 0) exitWith { "" }; _l lnbData [_r, 0] };
private _note = { (_d displayCtrl 94548) ctrlSetText (_this call _tr); };
private _spec = {
    createHashMapFromArray [["profile", [94532] call _data], ["papers", [94534] call _data], ["kind", [94536] call _data], ["behaviour", [94538] call _data], ["side", [94540] call _data]]
};
private _ctxPos = {
    private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
    private _p = _ctx param [0, []];
    if (_p isNotEqualTo []) exitWith { _p };
    private _cam = curatorCamera;
    if (!isNull _cam) exitWith { screenToWorld [0.5, 0.5] };
    getPosATL player
};
private _apply = {
    params ["_units"];
    if (_units isEqualTo []) exitWith { "Détectez puis choisissez d'abord une IA." call _note; };
    private _s = call _spec;
    ["apply", [_units, _s]] call _send;
    if (_st getOrDefault ["keep", false]) then { ["keepAi", [_units, _s]] call comspec_sse_fnc_editorAi; };
};

switch (_op) do {
    case "load": {
        [94502, [["25", "25 m"], ["50", "50 m"], ["100", "100 m"], ["250", "250 m"], ["500", "500 m"]]] call _fill;
        (_d displayCtrl 94502) lbSetCurSel 1;
        [94534, _papers] call _fill;
        [94536, _kinds] call _fill;
        [94538, _behav] call _fill;
        [94540, _sides] call _fill;
        [94516, _docTypes] call _fill;
        [94532, [["", "Générer une identité"]]] call _fill;
        (_d displayCtrl 94510) lnbSetColumnsPos [0, 0.36, 0.48, 0.68, 0.84];
        "Une IA sans nom de variable (posée au Zeus) n'existe plus à la partie suivante : seule une IA de la mission, retrouvée par son nom de variable ou sa classe à moins de 20 m, garde sa configuration." call _note;
        (_d displayCtrl 94544) ctrlSetText ((["GARDER POUR LES PROCHAINES PARTIES : NON", "GARDER POUR LES PROCHAINES PARTIES : OUI"] select (_st getOrDefault ["keep", false])) call _tr);
        private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
        private _o = _ctx param [1, objNull];
        // Module posé sur une IA : tout son groupe est listé. Sinon, sélection du Zeus, puis les IA autour.
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (!isNull _o && {_o isKindOf "CAManBase"}) then { _sel = units group _o; };
        // Liste donnée par l'appelant (fn_aiConfigOpen) : elle prime, une seule fois.
        private _given = uiNamespace getVariable ["comspec_sse_editorAiUnits", []];
        uiNamespace setVariable ["comspec_sse_editorAiUnits", []];
        if (_given isNotEqualTo []) then { _sel = _given; };
        if (_sel isNotEqualTo []) then { ["scan", [[], 0, _sel]] call _send; } else { ["scan", [call _ctxPos, 50, []]] call _send; };
    };
    case "scan": { ["scan", [call _ctxPos, parseNumber ([94502] call _data), []]] call _send; };
    case "scanZeus": {
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (_sel isEqualTo []) exitWith { "Aucune IA sélectionnée dans le Zeus : fermez cette fenêtre, sélectionnez-les, puis rouvrez-la." call _note; };
        ["scan", [[], 0, _sel]] call _send;
    };
    case "aiReply": {
        if (isNull _d) exitWith {};
        _arg params [["_kind", ""], ["_p1", []], ["_p2", []], ["_p3", []]];
        switch (_kind) do {
            case "rows": {
                private _keepProfile = [94532] call _data;
                private _l = _d displayCtrl 94510;
                lnbClear _l;
                {
                    _x params ["_net", "_name", "_side", "_prof", "_docs", "_beh", "_grp"];
                    private _r = _l lnbAddRow [_name, _side, _prof, _docs, _grp];
                    _l lnbSetData [[_r, 0], _net];
                } forEach _p1;
                (_d displayCtrl 94505) ctrlSetText format ["%1 %2", count _p1, "IA" call _tr];
                if (_p1 isEqualTo []) then { "Aucune IA ici : élargissez le rayon, ou sélectionnez-les dans le Zeus." call _note; } else { _l lnbSetCurSelRow 0; };
                _st set ["profiles", _p2];
                private _rows = [["", "Générer une identité"]];
                { _rows pushBack [_x select 0, format ["%1 · %2%3", _x select 1, _x select 2, ["", format [" (%1)", _x select 3]] select ((_x select 3) isNotEqualTo "")]]; } forEach _p2;
                [94532, _rows] call _fill;
                private _c = _d displayCtrl 94532;
                for "_i" from 0 to ((lbSize _c) - 1) do { if ((_c lbData _i) isEqualTo _keepProfile) exitWith { _c lbSetCurSel _i; }; };
                if (_p2 isEqualTo []) then { "Aucun profil Athena reçu sur ce serveur : créez-en dans le Bureau SSE (Profils IA), ou générez." call _note; };
                private _kc = _d displayCtrl 94519;
                lbClear _kc;
                { private _i = _kc lbAdd (_x select 1); _kc lbSetData [_i, str (_x select 0)]; } forEach _p3;
                if (_p3 isEqualTo []) then { _kc lbAdd ("Rien de gardé" call _tr); };
                _kc lbSetCurSel 0;
            };
            case "applied": {
                ["scan"] call comspec_sse_fnc_editorAi;
                format [("Appliqué à %1 IA." call _tr), _p1] call _note;
                [format [("Appliqué à %1 IA." call _tr), _p1], "SUCCESS"] call comspec_sse_fnc_toast;
            };
            case "docs": {
                _st set ["docs", _p1];
                private _c = _d displayCtrl 94512;
                lbClear _c;
                private _labels = createHashMapFromArray (_docTypes apply { [_x select 0, _x select 1] });
                {
                    _x params ["_id", "_type", "_num", "_last", "_first", "_kind"];
                    private _i = _c lbAdd format ["%1 · n° %2 · %3 %4 · %5", (_labels getOrDefault [_type, _type]) call _tr, _num, _first, toUpper _last, (["Vrai", "Faux"] select (_kind isEqualTo "forged")) call _tr];
                    _c lbSetData [_i, _id];
                } forEach _p1;
                if (_p1 isEqualTo []) then { _c lbAdd ("Pas encore de papier : décidés à la première demande." call _tr); };
                _c lbSetCurSel 0;
                ["preview"] call comspec_sse_fnc_editorAi;
            };
        };
    };
    case "select": {
        private _u = objectFromNetId (call _selNet);
        if (isNull _u) exitWith {};
        ["docs", [_u]] call _send;
    };
    case "preview": {
        if (isNull _d) exitWith {};
        private _pid = [94532] call _data;
        private _p = (_st getOrDefault ["profiles", []]) select { (_x select 0) isEqualTo _pid };
        private _docs = _st getOrDefault ["docs", []];
        private _labels = createHashMapFromArray (_docTypes apply { [_x select 0, _x select 1] });
        private _who = if (_p isNotEqualTo []) then { (_p select 0) select 3 } else { "" };
        if (_who isEqualTo "" && {_docs isNotEqualTo []}) then { _who = format ["%1 %2", (_docs select 0) select 4, toUpper ((_docs select 0) select 3)]; };
        if (_who isEqualTo "") then { _who = "Identité générée à la première demande" call _tr; };
        private _carry = (_papers select (((_papers apply { _x select 0 }) find ([94534] call _data)) max 0)) select 1;
        private _kindTxt = (_kinds select (((_kinds apply { _x select 0 }) find ([94536] call _data)) max 0)) select 1;
        private _bh = (_behav select (((_behav apply { _x select 0 }) find ([94538] call _data)) max 0)) select 1;
        private _docLine = if (_pid isNotEqualTo "") then { "Papiers du profil" call _tr } else { format ["%1 · %2", _carry call _tr, _kindTxt call _tr] };
        (_d displayCtrl 94543) ctrlSetStructuredText parseText format [
            "<t font='PuristaBold' size='1.25' color='#1b1c19'>%1</t><br/><br/><t color='#1b1c19'>%2</t><br/><t color='#1b1c19'>%3 : %4</t><br/><br/><t size='0.85' color='#4a4c45'>%5</t>",
            _who, _docLine, "Au contrôle" call _tr, _bh call _tr, "Il répond au-dessus de sa tête. Un contrôle N3 le reconnaît sur Athena si ses papiers viennent du profil." call _tr
        ];
    };
    case "applyOne": { private _u = objectFromNetId (call _selNet); [([[_u], []] select (isNull _u))] call _apply; };
    case "applyGroup": { private _u = objectFromNetId (call _selNet); [([units group _u, []] select (isNull _u))] call _apply; };
    case "applyAll": {
        private _l = _d displayCtrl 94510;
        private _all = [];
        for "_i" from 0 to (((lnbSize _l) select 0) - 1) do { _all pushBack (objectFromNetId (_l lnbData [_i, 0])); };
        [_all select { !isNull _x }] call _apply;
    };
    case "keep": {
        _st set ["keep", !(_st getOrDefault ["keep", false])];
        (_d displayCtrl 94544) ctrlSetText ((["GARDER POUR LES PROCHAINES PARTIES : NON", "GARDER POUR LES PROCHAINES PARTIES : OUI"] select (_st get "keep")) call _tr);
        // Le contenu de l'éditeur en cours suit le même choix quand il est publié.
        private _it = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["item", createHashMap];
        if ((count _it) > 0) then { _it set ["keep", _st get "keep"]; };
    };
    case "docOpen": {
        private _id = [94512] call _data;
        if (_id isEqualTo "") exitWith {};
        ["docOpen", [_id]] call _send;
    };
    case "docMark";
    case "docMarkFalse": {
        private _u = objectFromNetId (call _selNet);
        private _id = [94512] call _data;
        if (isNull _u || {_id isEqualTo ""}) exitWith {};
        ["docMark", [_u, _id, ["civil", "forged"] select (_op isEqualTo "docMarkFalse")]] call _send;
    };
    case "docAdd": {
        private _u = objectFromNetId (call _selNet);
        if (isNull _u) exitWith {};
        ["docAdd", [_u, [94516] call _data]] call _send;
    };
    case "reload": { ["reload"] call _send; };
    case "forget": {
        private _c = _d displayCtrl 94519;
        private _i = lbCurSel _c;
        if (_i < 0 || {(_c lbData _i) isEqualTo ""}) exitWith {};
        ["unkeep", parseNumber (_c lbData _i)] spawn comspec_sse_fnc_editorAi;
    };
    // --- Athena, depuis le poste du Zeus (contexte planifié). Rechargé ensuite par le serveur, sans dépendre de ce poste.
    case "keepAi": {
        if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_editorAi; };
        _arg params [["_units", []], ["_s", createHashMap]];
        {
            private _var = vehicleVarName _x;
            ["post", createHashMapFromArray [["kind", "ai_keep"], ["place", createHashMapFromArray [
                ["kind", "ai"], ["profile_id", _s getOrDefault ["profile", ""]], ["var_name", _var], ["class_name", typeOf _x], ["x", (getPosATL _x) select 0], ["y", (getPosATL _x) select 1],
                ["label", format ["%1 · %2", [_var, typeOf _x] select (_var isEqualTo ""), mapGridPosition _x]], ["config", _s]
            ]]]] call comspec_sse_fnc_editorAi;
        } forEach _units;
        ["scan"] call comspec_sse_fnc_editorAi;
    };
    case "keepItem": {
        if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_editorAi; };
        _arg params [["_it", createHashMap], ["_where", []]];
        private _pairs = [];
        { if (_x in _it) then { _pairs pushBack [_x, _it get _x]; }; } forEach ["id", "type", "title", "body", "marking", "paper", "nature", "delivery"];
        ["post", createHashMapFromArray [["kind", "ai_keep"], ["place", createHashMapFromArray [
            ["kind", "item"], ["x", _where select 0], ["y", _where select 1], ["label", _it get "title"], ["config", createHashMapFromArray [["item", _pairs]]]
        ]]]] call comspec_sse_fnc_editorAi;
    };
    case "unkeep": {
        if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_editorAi; };
        private _raw = ["post", createHashMapFromArray [["kind", "ai_unkeep"], ["id", _arg]]] call comspec_sse_fnc_editorAi;
        if ((_raw select [0, 3]) isEqualTo "OK|") then { ["forget", [_arg]] call _send; };
        ["scan"] call comspec_sse_fnc_editorAi;
    };
    case "post": {
        _arg set ["map", missionNamespace getVariable ["COMSPEC_MapId", 1]];
        _arg set ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]];
        private _json = [_arg] call comspec_sse_fnc_toJsonApprox;
        private _parts = [];
        for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
        private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
        private _ok = (_raw select [0, 3]) isEqualTo "OK|";
        [["Enregistrement sur Athena impossible (liaison, ou habilitation Confidentiel) : rien n'est gardé.", "Enregistré sur Athena."] select _ok, ["WARNING", "SUCCESS"] select _ok] call comspec_sse_fnc_toast;
        _raw
    };
};
true
