/*
    Atelier IA (client) : personnalité et dialogue d'une IA ou d'un lot d'IA, même fenêtre en Eden et en Zeus
    (dialog COMSPEC_SSE_IarpWorkshop, dialogs\iarp_workshop.hpp).
      open ["eden", groupe de l'attribut]   Eden : attribut « Profil IA RP » d'une unité ou du module « Atelier IA »
      open ["zeus", [unités]]               Zeus : module « Atelier IA » (unité visée + sélection du curateur)
      onLoad · onUnload · loaded [ia, texte] (profil lu sur le serveur) · help [champ] · slider [idc, valeur]
      preset (charger le profil de bibliothèque) · generate (tirage local, champs verrouillés gardés) · test
      apply · applyGen (Zeus : un tirage par IA sélectionnée) · say (Zeus : faire dire une réplique) · import · export
    Rien n'est envoyé à Athena : import / export par copier-coller du texte du profil. Champs : fn_iarpProfile.
    Zone de test : interprète et générateur locaux, sans lancer la mission (faits tirés de l'histoire de couverture).
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_iarpWsDisplay", displayNull];
private _nl = toString [10];
private _sliders = [["nerve", 94724], ["talk", 94725], ["aggro", 94726], ["honesty", 94727], ["bribe", 94728], ["fear", 94729], ["drunk", 94730]];
private _locks = [["name", 94760], ["role", 94761], ["lang", 94762], ["nerve", 94763], ["talk", 94764], ["aggro", 94765], ["honesty", 94766],
    ["bribe", 94767], ["fear", 94768], ["drunk", 94769], ["react", 94770], ["know", 94771], ["unlock", 94772], ["story", 94773],
    ["phrases", 94774], ["sensitive", 94775], ["secrets", 94776]];
private _combos = [
    [94721, "role", ["auto", "civil", "insurge", "hvt", "agent", "policier"]],
    [94722, "lang", ["common", "local"]],
    [94723, "country", ["auto", "ALT", "TNA", "LVN", "MLD"]],
    [94736, "unlock", ["trust", "bribe", "keyword", "never"]]
];
private _reacts = [[94731, "search"], [94732, "threat"], [94733, "bribe"], [94734, "order"]];
private _reactVals = ["auto", "coopere", "refuse", "fuit", "agressif", "rendu"];
private _ctl = { _d displayCtrl _this };
private _lines = { params ["_c"]; ((ctrlText (_c call _ctl)) splitString (_nl + ";")) apply { trim _x } select { _x isNotEqualTo "" } };

// Fenêtre -> profil (HashMap normalisé).
private _read = {
    private _p = createHashMap;
    _p set ["name", trim (ctrlText (94720 call _ctl))];
    { _x params ["_idc", "_k", "_vals"]; private _i = lbCurSel (_idc call _ctl); _p set [_k, _vals select (_i max 0)]; } forEach _combos;
    { _x params ["_k", "_idc"]; _p set [_k, round (sliderPosition (_idc call _ctl))]; } forEach _sliders;
    _p set ["story", ctrlText (94740 call _ctl)];
    _p set ["phrases", [94741] call _lines];
    _p set ["sensitive", [94742] call _lines];
    _p set ["secrets", [94743] call _lines];
    _p set ["know", trim (ctrlText (94735 call _ctl))];
    _p set ["unlockWord", trim (ctrlText (94737 call _ctl))];
    private _r = createHashMap;
    { _x params ["_idc", "_k"]; _r set [_k, _reactVals select ((lbCurSel (_idc call _ctl)) max 0)]; } forEach _reacts;
    _p set ["react", _r];
    _p set ["locks", (_locks select { cbChecked ((_x select 1) call _ctl) }) apply { _x select 0 }];
    _p set ["preset", uiNamespace getVariable ["comspec_sse_iarpWsPreset", ""]];
    ["normalize", _p] call comspec_sse_fnc_iarpProfile
};
// Profil -> fenêtre.
private _write = {
    params ["_p"];
    _p = ["normalize", _p] call comspec_sse_fnc_iarpProfile;
    (94720 call _ctl) ctrlSetText (_p get "name");
    { _x params ["_idc", "_k", "_vals"]; (_idc call _ctl) lbSetCurSel ((_vals find (_p get _k)) max 0); } forEach _combos;
    { _x params ["_k", "_idc"]; (_idc call _ctl) sliderSetPosition (_p get _k); ["slider", [_idc, _p get _k]] call comspec_sse_fnc_iarpWorkshop; } forEach _sliders;
    (94740 call _ctl) ctrlSetText (_p get "story");
    (94741 call _ctl) ctrlSetText ((_p get "phrases") joinString _nl);
    (94742 call _ctl) ctrlSetText ((_p get "sensitive") joinString "; ");
    (94743 call _ctl) ctrlSetText ((_p get "secrets") joinString _nl);
    (94735 call _ctl) ctrlSetText (_p get "know");
    (94737 call _ctl) ctrlSetText (_p get "unlockWord");
    { _x params ["_idc", "_k"]; (_idc call _ctl) lbSetCurSel ((_reactVals find ((_p get "react") getOrDefault [_k, "auto"])) max 0); } forEach _reacts;
    { _x params ["_k", "_idc"]; (_idc call _ctl) cbSetChecked (_k in (_p get "locks")); } forEach _locks;
    uiNamespace setVariable ["comspec_sse_iarpWsPreset", _p get "preset"];
};
private _status = { (94702 call _ctl) ctrlSetText _this; };

switch (_op) do {
    case "open": {
        _a params [["_mode", "zeus"], ["_x1", []]];
        uiNamespace setVariable ["comspec_sse_iarpWsMode", _mode];
        if (_mode isEqualTo "eden") exitWith {
            uiNamespace setVariable ["comspec_sse_iarpWsAttr", _x1];
            uiNamespace setVariable ["comspec_sse_iarpWsUnits", []];
            (ctrlParent _x1) createDisplay "COMSPEC_SSE_IarpWorkshop";
            true
        };
        private _units = (_x1 select { _x isEqualType objNull && {!isNull _x} && {_x isKindOf "CAManBase"} && {!isPlayer _x} && {alive _x} });
        if (_units isEqualTo []) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; false };
        uiNamespace setVariable ["comspec_sse_iarpWsUnits", _units];
        if (!isNull _d) then { _d closeDisplay 2; };
        createDialog "COMSPEC_SSE_IarpWorkshop";
        true
    };
    case "onLoad": {
        _a params [["_disp", displayNull]];
        uiNamespace setVariable ["comspec_sse_iarpWsDisplay", _disp];
        _d = _disp;
        private _mode = uiNamespace getVariable ["comspec_sse_iarpWsMode", "zeus"];
        private _units = uiNamespace getVariable ["comspec_sse_iarpWsUnits", []];
        private _labels = createHashMapFromArray [
            ["role", ["W_RoleAuto", "W_RoleCivil", "W_RoleInsurge", "W_RoleHvt", "W_RoleAgent", "W_RolePolice"]],
            ["lang", ["W_LangCommon", "W_LangLocal"]],
            ["country", ["W_CountryAuto", "W_CountryALT", "W_CountryTNA", "W_CountryLVN", "W_CountryMLD"]],
            ["unlock", ["W_UnlockTrust", "W_UnlockBribe", "W_UnlockWord", "W_UnlockNever"]]
        ];
        {
            _x params ["_idc", "_k"];
            private _c = _idc call _ctl;
            lbClear _c;
            { _c lbAdd (localize ("STR_COMSPEC_IARP_" + _x)); } forEach (_labels get _k);
        } forEach _combos;
        {
            private _c = (_x select 0) call _ctl;
            lbClear _c;
            { _c lbAdd (localize ("STR_COMSPEC_IARP_W_React_" + _x)); } forEach _reactVals;
        } forEach _reacts;
        { private _c = (_x select 1) call _ctl; _c sliderSetRange [0, 100]; _c sliderSetSpeed [5, 10]; } forEach _sliders;
        private _pc = 94710 call _ctl;
        lbClear _pc;
        { private _i = _pc lbAdd (_x select 1); _pc lbSetData [_i, _x select 0]; } forEach (["presets"] call comspec_sse_fnc_iarpProfile);
        _pc lbSetCurSel 0;
        // Zeus seulement : générer pour la sélection, faire parler ; Eden : le profil part dans l'attribut.
        private _zeus = _mode isEqualTo "zeus";
        { (_x call _ctl) ctrlShow _zeus; } forEach [94747, 94748, 94754, 94791];
        (94701 call _ctl) ctrlSetText format [localize "STR_COMSPEC_IARP_W_Title", if (_zeus) then {
            if ((count _units) isEqualTo 1) then { [(_units select 0) getVariable ["comspec_sse_idName", ""], name (_units select 0)] select (((_units select 0) getVariable ["comspec_sse_idName", ""]) isEqualTo "") } else { format [localize "STR_COMSPEC_IARP_W_NUnits", count _units] }
        } else { localize "STR_COMSPEC_IARP_W_Eden" }];
        uiNamespace setVariable ["comspec_sse_iarpWsRecent", []];
        if (_zeus) then {
            [createHashMap] call _write;
            (localize "STR_COMSPEC_IARP_W_Loading") call _status;
            ["send", ["profileGet", [player, _units select 0]]] call comspec_sse_fnc_iarpClient;
        } else {
            private _raw = (uiNamespace getVariable ["comspec_sse_iarpWsAttr", controlNull]) getVariable ["value", ""];
            [["parse", _raw] call comspec_sse_fnc_iarpProfile] call _write;
            (localize "STR_COMSPEC_IARP_W_EdenReady") call _status;
        };
        ["help", ["Intro"]] call comspec_sse_fnc_iarpWorkshop;
    };
    case "onUnload": { uiNamespace setVariable ["comspec_sse_iarpWsDisplay", displayNull]; };
    case "loaded": {
        _a params [["_t", objNull], ["_raw", ""]];
        if (isNull _d || {_t isNotEqualTo ((uiNamespace getVariable ["comspec_sse_iarpWsUnits", []]) param [0, objNull])}) exitWith {};
        [["parse", _raw] call comspec_sse_fnc_iarpProfile] call _write;
        (localize "STR_COMSPEC_IARP_W_Loaded") call _status;
    };
    case "help": {
        _a params [["_k", "Intro"]];
        if (isNull _d) exitWith {};
        (94752 call _ctl) ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#73CC80'>%1</t><br/>%2",
            localize ("STR_COMSPEC_IARP_W_" + _k), localize ("STR_COMSPEC_IARP_W_" + _k + "_D")];
    };
    case "slider": {
        _a params [["_idc", 0], ["_v", 0]];
        if (isNull _d) exitWith {};
        (( _idc + 56) call _ctl) ctrlSetText str (round _v);
    };
    case "preset": {
        if (isNull _d) exitWith {};
        private _pc = 94710 call _ctl;
        private _id = _pc lbData (lbCurSel _pc);
        private _cur = call _read;
        private _pre = ["preset", _id] call comspec_sse_fnc_iarpProfile;
        // Champs verrouillés : gardés ; le reste vient du profil de bibliothèque.
        { if (_x in (_cur get "locks")) then { _pre set [_x, _cur get _x]; }; } forEach (keys _cur);
        _pre set ["locks", _cur get "locks"];
        uiNamespace setVariable ["comspec_sse_iarpWsPreset", _id];
        [_pre] call _write;
        (format [localize "STR_COMSPEC_IARP_W_PresetDone", _pc lbText (lbCurSel _pc)]) call _status;
    };
    case "generate": {
        if (isNull _d) exitWith {};
        private _cur = call _read;
        [["generate", [_cur, _cur get "locks"]] call comspec_sse_fnc_iarpProfile] call _write;
        (localize "STR_COMSPEC_IARP_W_Generated") call _status;
    };
    case "test": {
        if (isNull _d) exitWith {};
        private _p = call _read;
        private _txt = ctrlText (94744 call _ctl);
        ([_txt] call comspec_sse_fnc_iarpIntent) params ["_intent", "_score"];
        private _facts = ["facts", _p] call comspec_sse_fnc_iarpProfile;
        if ((_p get "name") isNotEqualTo "") then { _facts set ["name", _p get "name"]; };
        private _vars = +_facts;
        _vars set ["first", ((_facts getOrDefault ["name", ""]) splitString " ") param [0, ""]];
        _vars set ["know", _p get "know"];
        _vars set ["sir", ["monsieur", "sir"]];
        _vars set ["weapon", ""];
        // Aperçu : même table que le serveur, sans hasard sur la décision (le contexte du jeu manque ici).
        private _key = ["city", _intent] select (_intent isNotEqualTo "origin");
        private _gi = switch (true) do {
            case (_intent in ["origin", "employer", "job", "travel", "pob", "contact", "address", "name", "age", "family"]): { ["dunno", _intent] select ((_facts getOrDefault [_key, ""]) isNotEqualTo "") };
            case (_intent in ["handsup", "halt", "kneel", "follow", "leave", "dismount", "trunk"]): { ["noComply", "comply"] select ((_p get "fear") >= (_p get "aggro")) };
            case (_intent isEqualTo "bribe"): { ["bribeNo", "bribeYes"] select ((_p get "bribe") >= 50) };
            case (_intent isEqualTo "intel"): { ["intelNo", "intelYes"] select ((_p get "know") isNotEqualTo "" && {(_p get "unlock") isNotEqualTo "never"}) };
            case (_intent isEqualTo "search"): { ["searchNo", "searchYes"] select ((((_p get "fear") + (_p get "honesty")) / 2) >= 50) };
            case (_intent isEqualTo "weapons"): { "weaponsNo" };
            case (_intent isEqualTo "confront"): { ["confrontInnocent", "confrontDeny"] select ((_p get "secrets") isNotEqualTo [] || {(_p get "role") in ["insurge", "hvt", "agent"]}) };
            case (_intent isEqualTo "papers"): { "comply" };
            default { _intent };
        };
        private _recent = uiNamespace getVariable ["comspec_sse_iarpWsRecent", []];
        private _g = [_gi, _p, createHashMap, _recent] call comspec_sse_fnc_iarpGen;
        _recent pushBack (_g select 4);
        if ((count _recent) > 14) then { _recent deleteAt 0; };
        uiNamespace setVariable ["comspec_sse_iarpWsRecent", _recent];
        private _lang = ["en", "fr"] select (language isEqualTo "French");
        private _out = [[_g select 0, _g select 1] select (_lang isEqualTo "en"), _vars, _lang] call comspec_sse_fnc_iarpFill;
        if ((_g select 2) isNotEqualTo "") then { _out = format ["%1 [%2]", _g select 2, _out]; };
        private _esc = { private _s = [_this, "&", "&amp;"] call CBA_fnc_replace; _s = [_s, "<", "&lt;"] call CBA_fnc_replace; [_s, ">", "&gt;"] call CBA_fnc_replace };
        (94746 call _ctl) ctrlSetStructuredText parseText format ["<t color='#9EAFA6' size='0.85'>%1</t><br/><t color='#9EE6FF'>%2</t>",
            format [localize "STR_COMSPEC_IARP_W_TestInfo", _intent, _score, _gi, _g select 3], _out call _esc];
        missionNamespace setVariable ["comspec_sse_iarpWsLastTest", [_intent, _gi, _out]];
    };
    case "apply": {
        if (isNull _d) exitWith {};
        private _raw = ["serialize", call _read] call comspec_sse_fnc_iarpProfile;
        if ((uiNamespace getVariable ["comspec_sse_iarpWsMode", "zeus"]) isEqualTo "eden") exitWith {
            ["set", [uiNamespace getVariable ["comspec_sse_iarpWsAttr", controlNull], _raw]] call comspec_sse_fnc_iarpEden;
            _d closeDisplay 1;
        };
        ["send", ["profileSet", [player, uiNamespace getVariable ["comspec_sse_iarpWsUnits", []], _raw, "apply"]]] call comspec_sse_fnc_iarpClient;
        (localize "STR_COMSPEC_IARP_W_Sent") call _status;
    };
    case "applyGen": {
        if (isNull _d) exitWith {};
        private _raw = ["serialize", call _read] call comspec_sse_fnc_iarpProfile;
        ["send", ["profileSet", [player, uiNamespace getVariable ["comspec_sse_iarpWsUnits", []], _raw, "generate"]]] call comspec_sse_fnc_iarpClient;
        (localize "STR_COMSPEC_IARP_W_Sent") call _status;
    };
    case "say": {
        if (isNull _d) exitWith {};
        private _txt = trim (ctrlText (94747 call _ctl));
        if (_txt isEqualTo "") exitWith {};
        { ["send", ["puppet", [player, _x, _txt]]] call comspec_sse_fnc_iarpClient; } forEach ((uiNamespace getVariable ["comspec_sse_iarpWsUnits", []]) select [0, 1]);
        (94747 call _ctl) ctrlSetText "";
        (localize "STR_COMSPEC_IARP_W_Said") call _status;
    };
    case "export": {
        if (isNull _d) exitWith {};
        private _raw = ["serialize", call _read] call comspec_sse_fnc_iarpProfile;
        copyToClipboard _raw;
        (94749 call _ctl) ctrlSetText _raw;
        (localize "STR_COMSPEC_IARP_W_Exported") call _status;
    };
    case "import": {
        if (isNull _d) exitWith {};
        private _p = ["parse", trim (ctrlText (94749 call _ctl))] call comspec_sse_fnc_iarpProfile;
        if ((count _p) isEqualTo 0) exitWith { (localize "STR_COMSPEC_IARP_N_BadProfile") call _status; };
        [_p] call _write;
        (localize "STR_COMSPEC_IARP_W_Imported") call _status;
    };
};
