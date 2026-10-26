/*
    Actions de l'app Numéros (fn_pageNumbers). Brouillon du formulaire : uiNamespace COMSPEC_ATAK_NumDraft
    ("num" / "ct" = index édité, -1 nouveau, absent = liste ; champs gardés à chaque redessin).
      "tab" onglet | "edit" i | "cancel" | "save" | "del" i (deux appuis) | "primary" i | "flip" clé | "gen" | "plan" code
      "ctEdit" i | "ctCancel" | "ctSave" | "ctDel" i | "ctFrom" [nom, numéro] (annuaire -> carnet) | "sms" nom
      "open" | "reload" | "write"
*/
params [["_a", ""], ["_v", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_NumDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_NumDraft", _draft];
private _cfg = ["get"] call comspec_atak_native_fnc_localCfg;
private _nums = (_cfg get "phone") get "numbers";
private _ct = _cfg get "contacts";
private _redraw = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NUMBERS") then { ["NUMBERS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Champs saisis gardés avant un redessin (changement d'interrupteur, numéro généré).
private _keep = {
    private _f = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    { if (!isNull (_f getOrDefault [_x, controlNull])) then { _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["numNumber", "numType", "numLabel", "ctName", "ctNumber", "ctType", "ctNote"];
};
private _arm = {
    params ["_k"];
    if ((_s getOrDefault ["numDelArm", ""]) isEqualTo _k) exitWith { _s set ["numDelArm", ""]; true };
    _s set ["numDelArm", _k];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["numDelArm", ""]) isEqualTo _this) then { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["numDelArm", ""]; if ((_st getOrDefault ["activePage", ""]) isEqualTo "NUMBERS") then { ["NUMBERS"] call comspec_atak_native_fnc_pageRender; }; }; }, _k, 4] call CBA_fnc_waitAndExecute;
    false
};
switch (_a) do {
    case "tab": { _s set ["numTab", _v]; { _draft deleteAt _x; } forEach (keys _draft); };
    case "edit": { { _draft deleteAt _x; } forEach (keys _draft); _draft set ["num", _v]; };
    case "cancel": { { _draft deleteAt _x; } forEach (keys _draft); };
    case "flip": {
        call _keep;
        private _i = _draft getOrDefault ["num", -1];
        private _n = [createHashMap, _nums select _i] select (_i >= 0 && {_i < count _nums});
        private _cur = _draft getOrDefault [_v, _n getOrDefault [["shared", "primary"] select (_v isEqualTo "numPrimary"), [true, (count _nums) isEqualTo 0] select (_v isEqualTo "numPrimary")]];
        _draft set [_v, !_cur];
    };
    case "gen": {
        call _keep;
        _draft set ["numNumber", ["generate", [_draft getOrDefault ["numType", "mobile"], (_cfg get "phone") get "plan"]] call comspec_atak_native_fnc_localCfg];
    };
    case "plan": { (_cfg get "phone") set ["plan", _v]; ["save"] call comspec_atak_native_fnc_localCfg; };
    case "save": {
        call _keep;
        private _num = trim (_draft getOrDefault ["numNumber", ""]);
        if (_num isEqualTo "") exitWith { ["WARNING", "Saisissez un numéro (ou GÉNÉRER)", 3, 30] call comspec_atak_native_fnc_notify; };
        private _i = _draft getOrDefault ["num", -1];
        private _n = if (_i >= 0 && {_i < count _nums}) then { _nums select _i } else { private _h = createHashMap; _nums pushBack _h; _h };
        _n set ["number", _num];
        _n set ["type", _draft getOrDefault ["numType", _n getOrDefault ["type", "mobile"]]];
        _n set ["label", trim (_draft getOrDefault ["numLabel", ""])];
        _n set ["shared", _draft getOrDefault ["numShared", _n getOrDefault ["shared", true]]];
        private _prim = _draft getOrDefault ["numPrimary", _n getOrDefault ["primary", (count _nums) isEqualTo 1]];
        if (_prim) then { { _x set ["primary", false]; } forEach _nums; };
        _n set ["primary", _prim];
        { _draft deleteAt _x; } forEach (keys _draft);
        ["save"] call comspec_atak_native_fnc_localCfg;
        ["INFO", format ["Numéro enregistré : %1", _num], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "del": {
        if !([format ["num%1", _v]] call _arm) exitWith {};
        if (_v >= 0 && {_v < count _nums}) then { _nums deleteAt _v; ["save"] call comspec_atak_native_fnc_localCfg; };
    };
    case "primary": {
        { _x set ["primary", _forEachIndex isEqualTo _v]; } forEach _nums;
        ["save"] call comspec_atak_native_fnc_localCfg;
    };
    case "ctEdit": { { _draft deleteAt _x; } forEach (keys _draft); _draft set ["ct", _v]; };
    case "ctCancel": { { _draft deleteAt _x; } forEach (keys _draft); };
    case "ctFrom": {
        _v params [["_name", ""], ["_num", ""]];
        { _draft deleteAt _x; } forEach (keys _draft);
        _draft set ["ct", -1];
        _draft set ["ctName", _name];
        _draft set ["ctNumber", _num];
        _s set ["numTab", "BOOK"];
    };
    case "ctSave": {
        call _keep;
        private _name = trim (_draft getOrDefault ["ctName", ""]);
        private _num = trim (_draft getOrDefault ["ctNumber", ""]);
        if (_name isEqualTo "" && {_num isEqualTo ""}) exitWith { ["WARNING", "Saisissez au moins un nom ou un numéro", 3, 30] call comspec_atak_native_fnc_notify; };
        private _i = _draft getOrDefault ["ct", -1];
        private _c = if (_i >= 0 && {_i < count _ct}) then { _ct select _i } else { private _h = createHashMap; _ct pushBack _h; _h };
        _c set ["name", _name];
        _c set ["number", _num];
        _c set ["type", _draft getOrDefault ["ctType", "mobile"]];
        _c set ["note", trim (_draft getOrDefault ["ctNote", ""])];
        { _draft deleteAt _x; } forEach (keys _draft);
        ["save"] call comspec_atak_native_fnc_localCfg;
    };
    case "ctDel": {
        if !([format ["ct%1", _v]] call _arm) exitWith {};
        if (_v >= 0 && {_v < count _ct}) then { _ct deleteAt _v; ["save"] call comspec_atak_native_fnc_localCfg; };
    };
    case "sms": {
        _s set ["chatPeer", _v];
        _s set ["chatView", "CONV"];
        _s set ["chatPick", false];
        [{ ["CHAT"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "open": { ["open"] call comspec_atak_native_fnc_localCfg; };
    case "reload": { ["reload"] call comspec_atak_native_fnc_localCfg; };
    case "write": {
        private _ok = ["save"] call comspec_atak_native_fnc_localCfg;
        ["INFO", ["Enregistré dans le profil Arma (DLL native à mettre à jour pour le fichier)", "config.json enregistré"] select _ok, 3, 20] call comspec_atak_native_fnc_notify;
    };
};
if (_a isNotEqualTo "sms") then { call _redraw; };
true
