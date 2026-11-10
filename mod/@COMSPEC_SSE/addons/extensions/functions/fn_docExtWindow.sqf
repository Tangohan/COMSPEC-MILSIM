/*
    Fenêtre « SSE ROLEPLAY · Extensions » (dialogs/extensions.hpp) : extensions de documents détectées
    (["extensions"] call comspec_sse_fnc_idDocTypes), description, modèles, aperçu, bouton Activer / Désactiver.
      ["open"]            ouvre (ou relit si déjà ouverte)        ["load", display]   onLoad de la fenêtre
      ["select"]          ligne choisie -> volet de droite         ["toggle"]          demande « set » au serveur
      ["refresh"]         relit la liste (liste coupée reçue du serveur)   ["unload"]  onUnload
    Activer / Désactiver : Zeus, administrateur ou hôte seulement (grisé sinon) ; dans Eden, consultation seulement.
    Aucune alerte Arma : tout se dit sur la ligne d'état de la fenêtre.
*/
params [["_op", "open", [""]], ["_a", displayNull, [displayNull]]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_docExtDisplay", displayNull];
private _tr = { _this call comspec_sse_fnc_tr };
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _rows = { if (isNil "comspec_sse_fnc_idDocTypes") then { [] } else { ["extensions"] call comspec_sse_fnc_idDocTypes } };
private _canEdit = {
    if (is3DEN) exitWith { false };
    isServer || {!isNull (getAssignedCuratorLogic player)} || {[player] call comspec_sse_fnc_isAuthority} || {serverCommandAvailable "#kick"}
};
private _setState = { params ["_rich"]; if (!isNull _d) then { (_d displayCtrl 93924) ctrlSetStructuredText parseText _rich; }; };
private _rowOf = {
    params ["_cls"];
    ((call _rows) select { (toLower (_x select 0)) isEqualTo (toLower _cls) }) param [0, []]
};
private _current = {
    private _l = _d displayCtrl 93910;
    private _i = lbCurSel _l;
    if (_i < 0) exitWith { [] };
    [_l lbData _i] call _rowOf
};

// Volet de droite pour la ligne choisie.
private _select = {
    if (isNull _d) exitWith {};
    private _row = call _current;
    private _toggle = _d displayCtrl 93930;
    private _enabledSetting = missionNamespace getVariable ["comspec_sse_docExt_enabled", true];
    if (_row isEqualTo []) exitWith {
        (_d displayCtrl 93920) ctrlSetText "";
        (_d displayCtrl 93921) ctrlSetStructuredText parseText "";
        (_d displayCtrl 93922) ctrlSetStructuredText parseText "";
        (_d displayCtrl 93923) ctrlSetText "";
        _toggle ctrlSetText (("Activer" call _tr));
        _toggle ctrlEnable false;
        [[("Aucune extension de documents détectée : chargez un addon qui déclare CfgComspecDocumentExtensions (exemple : @COMSPEC_SSE_ExampleDocuments)." call _tr)] call _esc] call _setState;
    };
    _row params ["_cls", "_name", "_author", "_version", "_desc", "_docs", "_preview", "_active", "_builtin"];
    if (_name isEqualTo "") then { _name = _cls; };
    (_d displayCtrl 93920) ctrlSetText _name;
    private _meta = [_author, ["", format [" · v%1", _version]] select (_version isNotEqualTo ""), ["", format [" · %1", "intégrée au SSE" call _tr]] select _builtin] joinString "";
    (_d displayCtrl 93921) ctrlSetStructuredText parseText format ["<t color='#9eafa4' size='0.9'>%1</t><br/>%2", [_meta] call _esc, [_desc call _tr] call _esc];
    private _lines = _docs apply { format ["<t color='#73cc80'>•</t> %1 <t color='#9eafa4' size='0.85'>(%2)</t>", [["label", _x] call comspec_sse_fnc_idDocTypes] call _esc, [_x] call _esc] };
    if (_lines isEqualTo []) then { _lines = [[("Aucun modèle déclaré" call _tr)] call _esc]; };
    (_d displayCtrl 93922) ctrlSetStructuredText parseText (_lines joinString "<br/>");
    // Aperçu : image « preview » de l'extension, sinon le recto du premier modèle (repli alt).
    private _pic = _preview;
    if (_pic isEqualTo "" || {!fileExists _pic}) then {
        _pic = if (_docs isEqualTo []) then { "" } else { ["texture", [_docs select 0, "recto", "alt", ""]] call comspec_sse_fnc_idDocTypes };
    };
    (_d displayCtrl 93923) ctrlSetText _pic;
    _toggle ctrlSetText ((["Activer", "Désactiver"] select _active) call _tr);
    private _can = call _canEdit;
    _toggle ctrlEnable (_can && _enabledSetting);
    private _stateTxt = switch (true) do {
        case (!_enabledSetting): { format [("Extensions de documents coupées par le réglage SSE ROLEPLAY › Extensions : seuls les papiers intégrés sont servis. %1 ne peut pas être activée ici." call _tr), _name] };
        case (is3DEN): { ("Dans Eden : consultation seulement. L'activation se fait en jeu (Zeus, menu Échap) et vaut pour le serveur." call _tr) };
        case (!_can): { format [("%1 : %2. Seul un Zeus ou un administrateur peut activer ou désactiver une extension." call _tr), _name, (["désactivée", "active"] select _active) call _tr] };
        case (_active): { format [("%1 : active. Ses papiers sont proposés aux Zeus, à l'éditeur SSE et aux IA." call _tr), _name] };
        default { format [("%1 : désactivée. Ses papiers ne sont plus proposés ; ceux déjà délivrés restent lisibles." call _tr), _name] };
    };
    [[_stateTxt] call _esc] call _setState;
};

// Liste de gauche (sélection gardée), puis volet de droite.
private _fill = {
    if (isNull _d) exitWith {};
    private _l = _d displayCtrl 93910;
    private _keep = if ((lbCurSel _l) >= 0) then { _l lbData (lbCurSel _l) } else { "" };
    private _all = call _rows;
    lbClear _l;
    private _sel = -1;
    {
        _x params ["_cls", "_name", "_author", "_version", "", "_docs", "", "_active"];
        private _i = _l lbAdd format ["%1 · %2%3 · %4", [_name, _cls] select (_name isEqualTo ""), _author, ["", format [" v%1", _version]] select (_version isNotEqualTo ""), format [("%1 modèle(s)" call _tr), count _docs]];
        _l lbSetData [_i, _cls];
        _l lbSetTextRight [_i, (["Désactivé", "Actif"] select _active) call _tr];
        _l lbSetColor [_i, [[0.62, 0.68, 0.64, 1], [0.9, 0.93, 0.9, 1]] select _active];
        if (_cls isEqualTo _keep) then { _sel = _i; };
    } forEach _all;
    (_d displayCtrl 93902) ctrlSetText format [("%1 extension(s) de documents détectée(s) · %2 active(s)" call _tr), count _all, count (_all select { _x select 7 })];
    if (_all isNotEqualTo []) then { _l lbSetCurSel (_sel max 0); };
    call _select;
};

switch (_op) do {
    case "open": {
        if (!isNull _d) exitWith { call _fill; true };
        createDialog "COMSPEC_SSE_DocExtensions"
    };
    case "load": {
        _d = _a;
        uiNamespace setVariable ["comspec_sse_docExtDisplay", _d];
        call _fill;
        true
    };
    case "unload": { uiNamespace setVariable ["comspec_sse_docExtDisplay", displayNull]; true };
    case "select": { call _select; true };
    case "refresh": { if (isNull _d) exitWith { false }; call _fill; true };
    case "toggle": {
        if (isNull _d) exitWith { false };
        private _row = call _current;
        if (_row isEqualTo []) exitWith { false };
        _row params ["_cls", "_name", "", "", "", "", "", "_active"];
        if (_name isEqualTo "") then { _name = _cls; };
        if !(call _canEdit) exitWith { call _select; false };
        // Ligne d'état avant l'envoi : sur un serveur hébergé, le serveur relit la fenêtre dans la foulée.
        [[format [("Demande envoyée au serveur : %1 → %2. La liste se met à jour dès sa réponse." call _tr), _name, (["active", "désactivée"] select _active) call _tr]] call _esc] call _setState;
        (_d displayCtrl 93930) ctrlEnable false;
        ["set", [_cls, !_active]] remoteExecCall ["comspec_sse_fnc_docExtServer", 2];
        true
    };
    default { false };
};
