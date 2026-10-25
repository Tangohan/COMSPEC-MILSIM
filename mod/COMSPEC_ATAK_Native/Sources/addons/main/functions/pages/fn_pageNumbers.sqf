/*
    App Numéros : mes numéros de téléphone et leurs types (mobile, satellite, fixe, radio IP, chiffré, bip, urgence),
    l'annuaire des joueurs de mon camp, mon carnet de contacts et le fichier de configuration locale (fn_localCfg).
    En ATAK local (sans Athena), mon numéro principal remplace le numéro calculé du téléphone (fn_phoneIdent) ;
    avec Athena, le numéro principal reste celui du poste et les autres numéros s'ajoutent.
    Onglets : uiNamespace COMSPEC_ATAK_State "numTab" = MINE | DIR | BOOK | FILE ; brouillon : COMSPEC_ATAK_NumDraft (fn_numbersAction).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["numTab", "MINE"];
private _cfg = ["get"] call comspec_atak_native_fnc_localCfg;
private _phone = _cfg get "phone";
private _nums = _phone get "numbers";
private _local = ([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on";
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_NumDraft", createHashMap];
private _esc = { [_this select 0, "<", "&lt;"] call CBA_fnc_replace };
private _typeOf = { ["type", _this] call comspec_atak_native_fnc_localCfg };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_numbersAction;", str _a, str _v] };
private _d = "\z\comspec_atak_native\addons\main\data\";
private _myNum = ([player] call comspec_atak_native_fnc_phoneIdent) select 0;
private _rows = [
    ["status", _d + "app_numbers.paa", [[0.36, 0.78, 0.42, 1], [0.42, 0.70, 0.90, 1]] select !_local,
        format ["Mon numéro : %1", [_myNum] call _esc],
        ["Numéro principal attribué par Athena. Vos autres numéros s'ajoutent à votre fiche dans la partie.",
         "ATAK local : vos numéros et leurs types sont gérés ici et enregistrés dans config.json, aussi hors ligne."] select _local],
    ["segment", "", [
        ["MES NUMÉROS", ["tab", "MINE"] call _act, _tab isEqualTo "MINE"],
        ["ANNUAIRE", ["tab", "DIR"] call _act, _tab isEqualTo "DIR"],
        ["CARNET", ["tab", "BOOK"] call _act, _tab isEqualTo "BOOK"],
        ["FICHIER", ["tab", "FILE"] call _act, _tab isEqualTo "FILE"]
    ]]
];
private _typeCombo = {
    params ["_key", "_cur"];
    ["combo", _key, "Type", (["types"] call comspec_atak_native_fnc_localCfg) apply { [_x select 1, _x select 0, _x select 2] }, _cur]
};
switch (_tab) do {
    case "DIR": {
        // Annuaire de la partie : joueurs de mon camp, numéro du téléphone et numéros qu'ils ont rendus visibles.
        private _ppl = (allPlayers select { side group _x isEqualTo side group player && {alive _x} }) - [player];
        _rows pushBack ["section", "Annuaire de la partie", format ["%1 joueur(s) de mon camp", count _ppl]];
        if (_ppl isEqualTo []) then {
            _rows pushBack ["empty", _d + "app_numbers.paa", "Personne d'autre dans votre camp", "Les numéros des autres joueurs s'afficheront ici."];
        };
        {
            private _u = _x;
            private _main = ([_u] call comspec_atak_native_fnc_phoneIdent) select 0;
            private _extra = (_u getVariable ["COMSPEC_ATAK_Numbers", []]) select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo _main} };
            private _lines = _extra apply {
                _x params [["_n", ""], ["_t", "mobile"], ["_lb", ""]];
                (_t call _typeOf) params ["", "_tl", "", "_tc"];
                format ["<t color='%1'>%2</t> %3%4", _tc, _tl, [_n] call _esc, ["", format [" · %1", [_lb] call _esc]] select (_lb isNotEqualTo "")]
            };
            private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
            _rows pushBack ["person", [_pic, _d + "app_profile.paa"] select (_pic isEqualTo ""),
                format ["<t font='COMSPEC_BarlowBold'>%1</t> <t size='0.8' color='#8a9a93'>%2</t><br/><t size='0.85'>%3</t>%4",
                    [name _u] call _esc, [groupId group _u] call _esc, [_main] call _esc,
                    ["", "<br/><t size='0.78'>" + (_lines joinString "<br/>") + "</t>"] select (_lines isNotEqualTo [])],
                [["SMS", ["sms", name _u] call _act, true, true, "Ouvre la conversation dans Messages"],
                 ["+ CARNET", ["ctFrom", [name _u, _main]] call _act, false, true, "Ajoute ce joueur à mon carnet"]]];
        } forEach _ppl;
    };
    case "BOOK": {
        private _ct = _cfg get "contacts";
        private _ei = _draft getOrDefault ["ct", -2];
        if (_ei > -2) then {
            private _c = [createHashMap, _ct select _ei] select (_ei >= 0 && {_ei < count _ct});
            _rows append [
                ["section", ["Nouveau contact", "Modifier le contact"] select (_ei >= 0), ""],
                ["edit", "ctName", "Nom", _draft getOrDefault ["ctName", _c getOrDefault ["name", ""]]],
                ["edit", "ctNumber", "Numéro", _draft getOrDefault ["ctNumber", _c getOrDefault ["number", ""]]],
                ["ctType", _draft getOrDefault ["ctType", _c getOrDefault ["type", "mobile"]]] call _typeCombo,
                ["edit", "ctNote", "Note (facultatif)", _draft getOrDefault ["ctNote", _c getOrDefault ["note", ""]]],
                ["buttons", [["ENREGISTRER", ["ctSave"] call _act, true], ["ANNULER", ["ctCancel"] call _act]]]
            ];
        } else {
            _rows pushBack ["section", "Mon carnet", format ["%1 contact(s) · enregistrés dans config.json", count _ct]];
            if (_ct isEqualTo []) then {
                _rows pushBack ["empty", _d + "app_numbers.paa", "Carnet vide", "Ajoutez un contact, ou enregistrez un joueur depuis l'annuaire."];
            };
            private _arm = _s getOrDefault ["numDelArm", ""];
            {
                (_x get "type") call _typeOf params ["", "_tl", "_ti", "_tc"];
                private _k = format ["ct%1", _forEachIndex];
                _rows pushBack ["person", _ti, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85'>%2</t> <t size='0.78' color='%3'>%4</t>%5",
                    [_x get "name"] call _esc, [_x get "number"] call _esc, _tc, _tl, ["", format ["<br/><t size='0.75' color='#8a9a93'>%1</t>", [_x get "note"] call _esc]] select ((_x get "note") isNotEqualTo "")],
                    [["MODIFIER", ["ctEdit", _forEachIndex] call _act],
                     [["SUPPR.", "CONFIRMER"] select (_arm isEqualTo _k), ["ctDel", _forEachIndex] call _act, ["", "red"] select (_arm isEqualTo _k), true, "Deux appuis pour supprimer"]]];
            } forEach _ct;
            _rows pushBack ["buttons", [["AJOUTER UN CONTACT", ["ctEdit", -1] call _act, true]]];
        };
    };
    case "FILE": {
        (["status"] call comspec_atak_native_fnc_localCfg) params ["_src", "_dir", "_err"];
        _rows append [
            ["section", "Configuration locale", "Fichier JSON modifiable à la main"],
            ["info", "Source", createHashMapFromArray [["file", "<t color='#5cc76b'>config.json</t>"], ["profile", "<t color='#f2ab33'>profil Arma (copie)</t>"], ["default", "<t color='#8a9a93'>valeurs par défaut</t>"]] getOrDefault [_src, _src]],
            ["info", "Dossier", [[_dir] call _esc, "<t color='#f2ab33'>DLL native à mettre à jour</t>"] select (_dir isEqualTo "")]
        ];
        if (_err isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e04038'>config.json illisible : %1</t><br/><t size='0.8' color='#8a9a93'>La copie du profil reste utilisée. Corrigez le fichier puis rechargez-le ; l'ancien est gardé en config.json.bak à chaque enregistrement.</t>", [_err] call _esc], [0.2, 0.06, 0.05, 1]]; };
        _rows append [
            ["buttons", [
                ["OUVRIR LE DOSSIER", ["open"] call _act, false, _dir isNotEqualTo "", "Ouvre Documents\Arma 3\COMSPEC_Local dans l'explorateur"],
                ["RECHARGER", ["reload"] call _act, false, true, "Relit config.json après une modification à la main"],
                ["ENREGISTRER", ["write"] call _act, true, true, "Écrit config.json avec l'état actuel"]
            ]],
            ["section", "Format du fichier", "config.json"],
            ["text", "<t size='0.8' color='#c9d4cf'>{<br/>  ""callsign"": ""Viper 2-1"",<br/>  ""phone"": { ""plan"": ""FR"", ""numbers"": [<br/>    { ""number"": ""06 12 34 56 78"", ""type"": ""mobile"", ""label"": ""Perso"", ""primary"": true, ""shared"": true }<br/>  ] },<br/>  ""contacts"": [ { ""name"": ""TOC"", ""number"": ""+8816 1234 5678"", ""type"": ""satellite"", ""note"": """" } ],<br/>  ""toc"": { ""name"": ""TOC"", ""callsign"": ""OVERWATCH"" }<br/>}</t>", [0.05, 0.065, 0.06, 1]],
            ["text", format ["<t size='0.78' color='#8a9a93'>Types : %1. Plans : FR, US, UK, DE, BE, RU, ES. Les commentaires // sont acceptés à la lecture.</t>", ((["types"] call comspec_atak_native_fnc_localCfg) apply { _x select 0 }) joinString ", "]]
        ];
    };
    default {
        private _ei = _draft getOrDefault ["num", -2];
        if (_ei > -2) then {
            private _n = [createHashMap, _nums select _ei] select (_ei >= 0 && {_ei < count _nums});
            private _prim = _draft getOrDefault ["numPrimary", _n getOrDefault ["primary", (count _nums) isEqualTo 0]];
            private _shared = _draft getOrDefault ["numShared", _n getOrDefault ["shared", true]];
            _rows append [
                ["section", ["Nouveau numéro", "Modifier le numéro"] select (_ei >= 0), ""],
                ["numType", _draft getOrDefault ["numType", _n getOrDefault ["type", "mobile"]]] call _typeCombo,
                ["field", "numNumber", "Numéro", _draft getOrDefault ["numNumber", _n getOrDefault ["number", ""]], "GÉNÉRER", ["gen"] call _act, false],
                ["edit", "numLabel", "Libellé (facultatif : Perso, Service, Iridium…)", _draft getOrDefault ["numLabel", _n getOrDefault ["label", ""]]],
                ["switch", "Numéro principal", _prim, ["flip", "numPrimary"] call _act, ["Celui des SMS et de la recherche par numéro sur la carte.", "Avec Athena, le numéro principal reste celui du poste."] select !_local],
                ["switch", "Visible dans l'annuaire", _shared, ["flip", "numShared"] call _act, "Les joueurs de votre camp le voient dans leur annuaire."],
                ["buttons", [["ENREGISTRER", ["save"] call _act, true], ["ANNULER", ["cancel"] call _act]]]
            ];
        } else {
            _rows pushBack ["section", "Mes numéros", format ["%1 / 8", count _nums]];
            if (_nums isEqualTo []) then {
                _rows pushBack ["empty", _d + "app_numbers.paa", "Aucun numéro enregistré", ["Le numéro calculé du téléphone est utilisé.", "Le numéro attribué par Athena est utilisé."] select !_local];
            };
            private _arm = _s getOrDefault ["numDelArm", ""];
            {
                (_x get "type") call _typeOf params ["", "_tl", "_ti", "_tc"];
                private _k = format ["num%1", _forEachIndex];
                private _btns = [];
                if !(_x get "primary") then { _btns pushBack ["PRINCIPAL", ["primary", _forEachIndex] call _act, false, true, "En faire mon numéro principal"]; };
                _btns pushBack ["MODIFIER", ["edit", _forEachIndex] call _act];
                _btns pushBack [["SUPPR.", "CONFIRMER"] select (_arm isEqualTo _k), ["del", _forEachIndex] call _act, ["", "red"] select (_arm isEqualTo _k), true, "Deux appuis pour supprimer"];
                _rows pushBack ["person", _ti, format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.8' color='%3'>%4</t><t size='0.8' color='#8a9a93'>%5 · %6</t>",
                    [_x get "number"] call _esc, ["", "  <t size='0.75' color='#5cc76b'>PRINCIPAL</t>"] select (_x get "primary"), _tc, _tl,
                    ["", format [" · %1", [_x get "label"] call _esc]] select ((_x get "label") isNotEqualTo ""), ["privé", "visible"] select (_x get "shared")], _btns];
            } forEach _nums;
            _rows pushBack ["buttons", [["AJOUTER UN NUMÉRO", ["edit", -1] call _act, true, (count _nums) < 8]]];
            private _plan = _phone get "plan";
            _rows pushBack ["segment", "Format des numéros générés", ["FR", "US", "UK", "DE", "BE", "RU", "ES"] apply { [_x, ["plan", _x] call _act, _plan isEqualTo _x] }, "Utilisé par le bouton GÉNÉRER"];
        };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
