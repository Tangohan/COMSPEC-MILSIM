/*
    Actions de l'app « Fichiers » (fn_filePage).
      "tab" query | journal        "type" QW | QA | QB | QV      "show" i   rouvre la réponse i du journal
      "run" ("deep" : lien profond, critères déjà posés)   animation du terminal (fn_scanAnim, durée selon le signal
            réseau), consultation (comspec_sse_fnc_fileQuery), réponse affichée à la fin de l'animation
      "wanted"                     app Recherchés (lien profond)
*/
params [["_act", ""], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
uiNamespace setVariable ["comspec_sse_fileApp", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS") then { ["FICHIERS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
// Saisie en cours gardée avant chaque redessin (changement de type, lancement).
private _keep = {
    private _f = _st getOrDefault ["form", createHashMap];
    { _x params ["_k", "_fk"]; private _v = [_k, "§"] call comspec_atak_native_fnc_formValue; if (_v isNotEqualTo "§") then { _f set [_fk, trim _v]; }; }
        forEach [["fq_last", "last"], ["fq_first", "first"], ["fq_dob", "dob"], ["fq_number", "number"], ["fq_country", "country"], ["fq_bio", "bioKey"], ["fq_plate", "plate"]];
    // Identifiant SEEK II tapé : REC-… est un numéro d'enregistrement, le reste une clé biométrique.
    if (((_f getOrDefault ["bioKey", ""]) select [0, 4]) isEqualTo "REC-") then { _f set ["rec", _f get "bioKey"]; _f set ["bioKey", ""]; };
    _st set ["form", _f];
    _f
};
switch (_act) do {
    case "tab": { call _keep; _st set ["tab", _arg]; call _render; };
    case "type": { call _keep; _st set ["type", _arg]; call _render; };
    case "show": { _st set ["view", _arg]; _st set ["tab", "query"]; _st set ["type", ((_st getOrDefault ["history", []]) param [_arg, createHashMap]) getOrDefault ["type", "QA"]]; call _render; };
    case "wanted": { ["WANTED"] call comspec_sse_fnc_openApp; };
    case "run": {
        if (["busy", ["files"]] call comspec_sse_fnc_scanAnim) exitWith {};
        private _f = if (_arg isEqualTo "deep") then { _st getOrDefault ["form", createHashMap] } else { call _keep };
        private _type = _st getOrDefault ["type", "QA"];
        private _need = switch (_type) do {
            case "QW": { (_f getOrDefault ["last", ""]) isNotEqualTo "" };
            case "QB": { (_f getOrDefault ["bioKey", ""]) isNotEqualTo "" || {(_f getOrDefault ["rec", ""]) isNotEqualTo ""} };
            case "QV": { (_f getOrDefault ["plate", ""]) isNotEqualTo "" };
            default { (_f getOrDefault ["number", ""]) isNotEqualTo "" };
        };
        if (!_need) exitWith {
            [["Saisissez le numéro du document.", "Saisissez au moins le nom.", "Saisissez l'identifiant SEEK II.", "Saisissez la plaque."] select ((["QA", "QW", "QB", "QV"] find _type) max 0), 2.5] call comspec_atak_native_fnc_toast;
        };
        private _q = +_f;
        if (_type isNotEqualTo "QA") then { _q deleteAt "docType"; };
        private _tag = format ["app%1", round (diag_tickTime * 1000)];
        _st set ["pending", _tag];
        _st set ["ready", -1];
        // Base interrogée par le réseau : plus long quand le signal est faible.
        private _bars = if (isNil "comspec_atak_native_fnc_linkQuality") then { 4 } else { ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 4] };
        private _net = 0.3 * (4 - ((_bars max 0) min 4));
        ["start", ["files", [["Connexion au fichier…", 0.4 + _net], [format [("Interrogation %1…" call comspec_sse_fnc_tr), _type], 0.9 + _net], ["Réception de la réponse…", 0.4]],
            "comspec_sse_fileScanCtrl", {
                private _st = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
                private _r = _st getOrDefault ["ready", -1];
                if (_r >= 0) then { _st set ["view", _r]; _st set ["pending", ""]; };
                [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS") then { ["FICHIERS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
            }, [], objNull, {}]] call comspec_sse_fnc_scanAnim;
        [_type, _q, _st getOrDefault ["source", "phone"], _tag] call comspec_sse_fnc_fileQuery;
        _st set ["source", "phone"];
        call _render;
    };
};
true
