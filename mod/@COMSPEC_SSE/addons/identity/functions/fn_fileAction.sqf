/*
    Actions de l'app « Fichiers » (fn_filePage).
      "tab" query | journal        "type" QW | QA | QB | QV      "show" i   rouvre la réponse i du journal
      "run" ("deep" : lien profond, critères déjà posés)   animation du terminal (fn_scanAnim, durée selon le signal
            réseau), consultation (comspec_sse_fnc_fileQuery), réponse affichée à la fin de l'animation. La consultation
            se termine toujours (réponse, aucun enregistrement ou erreur : 8 s au plus sans réponse du serveur).
      "cancel"                     abandonne la consultation en cours (la page redevient utilisable tout de suite)
      "retry" i                    relance la consultation i du journal (mêmes critères)
      "copy" numéro                copie le numéro (presse-papiers ; en multijoueur par la DLL de l'ATAK)
      "wanted"                     app Recherchés (lien profond)
    Messages de l'app (saisie manquante, numéro copié, annulation) : dans l'app elle-même (ligne « msg »), jamais en
    alerte de l'interface d'Arma.
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
    if (((toUpper (_f getOrDefault ["bioKey", ""])) select [0, 4]) isEqualTo "REC-") then { _f set ["rec", _f get "bioKey"]; _f set ["bioKey", ""]; };
    _st set ["form", _f];
    _f
};
switch (_act) do {
    case "tab": { call _keep; _st set ["tab", _arg]; _st set ["msg", ""]; call _render; };
    case "type": { call _keep; _st set ["type", _arg]; _st set ["msg", ""]; call _render; };
    case "show": { _st set ["view", _arg]; _st set ["tab", "query"]; _st set ["msg", ""]; _st set ["type", ((_st getOrDefault ["history", []]) param [_arg, createHashMap]) getOrDefault ["type", "QA"]]; call _render; };
    case "wanted": { ["WANTED"] call comspec_sse_fnc_openApp; };
    case "cancel": {
        private _tag = _st getOrDefault ["pending", ""];
        if (_tag isEqualTo "") exitWith {};
        ["cancel", [_tag]] call comspec_sse_fnc_fileClient;
        ["stop", ["files"]] call comspec_sse_fnc_scanAnim;
        _st set ["pending", ""];
        _st set ["ready", -1];
        _st set ["msg", "Requête annulée."];
        call _render;
    };
    case "retry": {
        private _r = (_st getOrDefault ["history", []]) param [_arg, createHashMap];
        if ((count _r) > 0) then {
            _st set ["type", _r getOrDefault ["type", "QA"]];
            _st set ["form", +(_r getOrDefault ["query", createHashMap])];
            _st set ["source", _r getOrDefault ["source", "phone"]];
        };
        _st set ["tab", "query"];
        ["run", "deep"] call comspec_sse_fnc_fileAction;
    };
    case "copy": {
        private _t = _arg;
        if !(_t isEqualType "") then { _t = str _t; };
        copyToClipboard _t;
        // En multijoueur copyToClipboard est sans effet : la DLL de l'ATAK écrit le presse-papiers Windows (fn_planAction).
        if (isMultiplayer && {!isNil "comspec_atak_native_fnc_extensionCall"} && {((["ClipBegin"] call comspec_atak_native_fnc_extensionCall) select [0, 2]) isEqualTo "OK"}) then {
            ["ClipAppend", [_t]] call comspec_atak_native_fnc_extensionCall;
            ["ClipCommit"] call comspec_atak_native_fnc_extensionCall;
        };
        _st set ["msg", format [("Numéro copié : %1" call comspec_sse_fnc_tr), _t]];
        call _render;
    };
    case "run": {
        // Animation restée d'une mission précédente (uiNamespace survit à la fin de mission, pas son minuteur) : oubliée,
        // sinon « busy » bloquait toute nouvelle consultation.
        private _scans = uiNamespace getVariable ["comspec_sse_scans", createHashMap];
        private _sc = _scans getOrDefault ["files", createHashMap];
        if ((count _sc) > 0 && {time < (_sc getOrDefault ["t0", 0]) || {(time - (_sc getOrDefault ["t0", 0])) > ((_sc getOrDefault ["total", 0]) + 5)}}) then { _scans deleteAt "files"; };
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
            _st set ["msg", ["Saisissez le numéro du document.", "Saisissez au moins le nom.", "Saisissez l'identifiant SEEK II.", "Saisissez la plaque."] select ((["QA", "QW", "QB", "QV"] find _type) max 0)];
            call _render;
        };
        _st set ["msg", ""];
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
