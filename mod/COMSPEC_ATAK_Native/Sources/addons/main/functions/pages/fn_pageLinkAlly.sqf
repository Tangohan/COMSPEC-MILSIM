/*
    App Liaison ATAK : relier mon téléphone à celui d'un équipier à terre (ou dont l'ATAK est hors service)
    pour lire son identité, faire remonter son rapport de décès ou de blessure et renvoyer ses données non transmises.
    Params (module de pageRender) : [page, rectangle]. Actions : comspec_atak_native_fnc_linkAllyAction.
*/
params [["_page", "LINKALLY"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _st = uiNamespace getVariable ["COMSPEC_ATAK_LinkAlly", createHashMap];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _na = "<t color='#8a9a93'>non renseigné</t>";
private _val = { params ["_v"]; if (_v isEqualType "" && {(trim _v) isNotEqualTo ""}) then { [_v] call _esc } else { _na } };
private _icon = "\z\comspec_atak_native\addons\main\data\app_linkally.paa";
private _phase = _st getOrDefault ["phase", ""];
private _target = _st getOrDefault ["target", objNull];
private _rows = [];

// Lien perdu (trop loin) : retour à la recherche.
if (_phase in ["LINKING", "LINKED"] && {isNull _target}) then { _phase = ""; };

switch (_phase) do {
    case "LINKING": {
        private _p = ((diag_tickTime - (_st getOrDefault ["start", diag_tickTime])) / ((_st getOrDefault ["dur", 4]) max 0.1)) min 1;
        private _n = round (_p * 10);
        private _on = ""; private _off = "";
        for "_i" from 1 to _n do { _on = _on + "● "; };
        for "_i" from 1 to (10 - _n) do { _off = _off + "○ "; };
        private _bar = format ["<t color='#5cc76b'>%1</t><t color='#4a5a53'>%2</t>", _on, _off];
        _rows append [
            ["title", "Liaison en cours"],
            ["person", _icon, format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · gardez le contact (2 m)</t>", [name _target] call _esc,
                ["Lien NFC : posez votre téléphone contre le sien", "Câble USB-C branché : lecture directe de la mémoire"] select ((_st getOrDefault ["mode", "NFC"]) isNotEqualTo "NFC")], [], [0.36, 0.78, 0.42, 1]],
            ["text", format ["<t size='1.2'>%1</t>  <t font='RobotoCondensedBold'>%2 %%</t>", _bar, round (_p * 100)]],
            ["buttons", [["ANNULER", { ["unlink"] call comspec_atak_native_fnc_linkAllyAction; }]]]
        ];
    };
    case "LINKED": {
        private _data = _st getOrDefault ["data", createHashMap];
        private _src = _st getOrDefault ["src", ""];
        private _sent = _st getOrDefault ["sent", createHashMap];
        private _dead = !alive _target;
        private _uncon = !_dead && {lifeState _target isEqualTo "INCAPACITATED" || {_target getVariable ["ACE_isUnconscious", false]}};
        private _stateTxt = switch (true) do {
            case (_dead): { "<t color='#e5483a' font='RobotoCondensedBold'>KIA</t>" };
            case (_uncon): { "<t color='#f2ab33' font='RobotoCondensedBold'>INCONSCIENT</t>" };
            case ((_target getVariable ["ace_medical_woundBleeding", 0]) > 0 || {(damage _target) > 0.1}): { "<t color='#e8b84a' font='RobotoCondensedBold'>BLESSÉ</t>" };
            default { "<t color='#5cc76b' font='RobotoCondensedBold'>VALIDE</t>" };
        };
        (_target getVariable ["COMSPEC_ATAK_Pub", []]) params [["_bat", -1], ["", 0], ["_dev", ""]];
        private _devTxt = createHashMapFromArray [["OK", "intact"], ["CRACKED", "écran fêlé"], ["OFF", "éteint"], ["BROKEN", "détruit"]] getOrDefault [_dev, "état inconnu"];
        if ((_data getOrDefault ["battery", _bat]) isEqualType 0 && {(_data getOrDefault ["battery", _bat]) >= 0}) then { _devTxt = format ["%1 · batterie %2 %%", _devTxt, _data getOrDefault ["battery", _bat]]; };
        private _name = _data getOrDefault ["name", ""];
        if (_name isEqualTo "") then { _name = _data getOrDefault ["tagName", ""]; };
        if (_name isEqualTo "") then { _name = name _target; };
        private _cs = _data getOrDefault ["callsign", ""];
        if (_cs isEqualTo "") then { _cs = [_target, true] call comspec_atak_native_fnc_unitCallsign; };
        // En-tête : l'équipier, son état, son téléphone et le type de lien.
        _rows append [
            ["person", _icon, format ["<t size='1.15' font='RobotoCondensedBold'>%1</t>  %2<br/><t size='0.8' color='#8a9a93'>%3 · téléphone %4 · lien %5 depuis %6</t>",
                [_name] call _esc, _stateTxt, [[_cs] call _esc, "sans indicatif"] select (_cs isEqualTo ""), _devTxt, _st getOrDefault ["mode", "NFC"], _st getOrDefault ["linkedAt", ""]], [],
                [[0.36, 0.78, 0.42, 1], [0.9, 0.28, 0.23, 1]] select _dead]
        ];
        private _srcTxt = switch (_src) do {
            case "LIVE": { format ["<t size='0.8' color='#5cc76b'>Mémoire du téléphone lue à %1.</t>", _data getOrDefault ["readAt", ""]] };
            case "WAIT": { "<t size='0.8' color='#f2ab33'>Lecture de la mémoire du téléphone…</t>" };
            case "MIRROR": { format ["<t size='0.8' color='#f2ab33'>Joueur déconnecté : dernière sauvegarde de l'appareil (%1). Les envois en file ne peuvent plus repartir, seule leur liste est lue.</t>", _st getOrDefault ["mirrorAt", "?"]] };
            default { "<t size='0.8' color='#e5483a'>Mémoire illisible : aucune sauvegarde de cet appareil. Identité lue sur la plaque et l'équipement.</t>" };
        };
        _rows pushBack ["text", _srcTxt];

        // Identité : profil Athena de son téléphone, plaque d'identité ACE, numéro de la SIM.
        private _bt = _data getOrDefault ["blood", ""];
        if (_bt isEqualTo "") then {
            private _i = _target getVariable ["ace_medical_bloodType", -1];
            if (_i isEqualType 0 && {_i >= 0 && _i < 8}) then { _bt = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"] select _i; };
        };
        private _mat = _data getOrDefault ["matricule", ""];
        if (_mat isEqualTo "" && {!isNil "ace_dogtags_fnc_getDogtagData"}) then { _mat = ([_target] call ace_dogtags_fnc_getDogtagData) param [1, ""]; };
        private _aff = _data getOrDefault ["unit", ""];
        if (_aff isEqualTo "") then { _aff = _target getVariable ["COMSPEC_ATAK_Orbat", ""]; };
        private _grp = groupId group _target;
        private _ph = [_target] call comspec_atak_native_fnc_phoneIdent;
        _rows append [
            ["section", "Identité", "Profil Athena du téléphone et plaque d'identité"],
            ["info", "Matricule", [_mat] call _val],
            ["info", "Nom et prénom", [_name] call _val],
            ["info", "Grade", [_data getOrDefault ["grade", ""]] call _val],
            ["info", "Groupe sanguin", [_bt] call _val],
            ["info", "Affectation", [_aff] call _val],
            ["info", "Fonction", [[_data getOrDefault ["function", ""], _data getOrDefault ["role", ""]] select ((_data getOrDefault ["function", ""]) isEqualTo "")] call _val],
            ["info", "Groupe en jeu", [[_grp, ""] select (_dead && {_grp isEqualTo ""})] call _val],
            ["info", "Numéro du téléphone", [_ph param [0, ""]] call _val],
            ["info", "IMEI", [_ph param [1, ""]] call _val]
        ];

        // Rapport de décès ou de blessure, prêt à partir au poste.
        private _r = ["build"] call comspec_atak_native_fnc_linkAllyAction;
        if ((count _r) > 0) then {
            private _isKia = (_r select 0) isEqualTo "KIA";
            private _key = ["injuryReport", "deathReport"] select _isKia;
            private _done = _sent getOrDefault [_key, ""];
            _rows pushBack ["section", ["Rapport de blessure", "Rapport de décès"] select _isKia, ["Bilan relevé sur place, envoyé au camp et au poste", "Constat de décès, envoyé au camp et au poste"] select _isKia];
            { _rows pushBack ["info", _x select 0, [_x select 1] call _esc]; } forEach (_r select 5);
            _rows pushBack ["buttons", [[[format ["ENVOYER %1", ["LE RAPPORT DE BLESSURE", "LE RAPPORT DE DÉCÈS"] select _isKia], format ["ENVOYÉ À %1 · RENVOYER", _done]] select (_done isNotEqualTo ""),
                compile format ["['%1'] call comspec_atak_native_fnc_linkAllyAction;", _key], _done isEqualTo ""]]];
        };

        // Données restées dans son téléphone.
        private _queue = _data getOrDefault ["queue", []];
        private _failed = _data getOrDefault ["photosFailed", []];
        private _pending = _data getOrDefault ["photosPending", 0];
        (_data getOrDefault ["frs", []]) params [["_fk", ""], ["", ""], ["", ""], ["_fb", ""]];
        (_data getOrDefault ["reco", []]) params [["_rt", ""], ["", ""], ["_rx", ""]];
        private _items = [];
        { _items pushBack format ["%1 <t size='0.8' color='#8a9a93'>· %2 Ko · en file sans réseau</t>", [_x select 0] call _esc, _x param [1, 1]]; } forEach _queue;
        { _items pushBack format ["Photo %1 <t size='0.8' color='#8a9a93'>· refusée par Athena</t>", [_x] call _esc]; } forEach _failed;
        if ((trim _fb) isNotEqualTo "") then { _items pushBack format ["Brouillon FRS %1 <t size='0.8' color='#8a9a93'>· « %2 »</t>", [_fk] call _esc, [[_fb, (_fb select [0, 70]) + "…"] select ((count _fb) > 70)] call _esc]; };
        if ((trim _rx) isNotEqualTo "") then { _items pushBack format ["Note de reco %1 <t size='0.8' color='#8a9a93'>· « %2 »</t>", [_rt] call _esc, [[_rx, (_rx select [0, 70]) + "…"] select ((count _rx) > 70)] call _esc]; };
        _rows pushBack ["section", "Données non transmises", [format ["%1 élément(s) à renvoyer, crédités « récupéré sur l'ATAK de %2 »", count _items, _name], "Rien en attente sur cet appareil"] select ((count _items) isEqualTo 0)];
        { _rows pushBack ["text", format ["• %1", _x]]; } forEach _items;
        if (_pending > 0) then { _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1 photo(s) encore en cours d'envoi par COMSPEC Link sur son poste.</t>", _pending]]; };
        private _rec = _sent getOrDefault ["recover", ""];
        if ((count _items) > 0) then {
            _rows pushBack ["buttons", [[["RENVOYER TOUT", format ["RENVOYÉ À %1", _rec]] select (_rec isNotEqualTo ""), { ["recover"] call comspec_atak_native_fnc_linkAllyAction; }, true, _rec isEqualTo "" && {_src in ["LIVE", "MIRROR"]}]]];
        };
        _rows append [
            ["gap"],
            ["buttons", [["RELIRE", { ["refresh"] call comspec_atak_native_fnc_linkAllyAction; }], ["DÉCONNECTER", { ["unlink"] call comspec_atak_native_fnc_linkAllyAction; }]]]
        ];
    };
    default {
        private _c = ["candidates"] call comspec_atak_native_fnc_linkAllyAction;
        uiNamespace setVariable ["COMSPEC_ATAK_LinkAllySeen", _c apply { _x select 0 }];
        _rows append [
            ["title", "Liaison ATAK"],
            ["text", "<t size='0.85' color='#c9d4cf'>Approchez-vous à moins de 2 m d'un équipier tué, inconscient ou dont l'ATAK est hors service (ou de son téléphone tombé au sol), puis reliez les deux appareils : identité, rapport de décès ou de blessure, et données qu'il n'a pas pu transmettre.</t>"]
        ];
        if ((count _c) isEqualTo 0) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Aucun équipier équipé d'un téléphone à portée de lien.</t>"];
        };
        {
            _x params ["_obj", "_owner", "_dist", "_what"];
            private _st2 = switch (true) do {
                case (!alive _owner): { "<t color='#e5483a'>KIA</t>" };
                case (lifeState _owner isEqualTo "INCAPACITATED" || {_owner getVariable ["ACE_isUnconscious", false]}): { "<t color='#f2ab33'>inconscient</t>" };
                default { "<t color='#5cc76b'>valide</t>" };
            };
            (_owner getVariable ["COMSPEC_ATAK_Pub", []]) params [["", -1], ["", 0], ["_dev", ""]];
            _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold'>%1</t>  %2<br/><t size='0.8' color='#8a9a93'>%3 · %4 m · téléphone %5</t>",
                [name _owner] call _esc, _st2, _what, _dist toFixed 1, createHashMapFromArray [["OK", "intact"], ["CRACKED", "fêlé"], ["OFF", "éteint"], ["BROKEN", "détruit"]] getOrDefault [_dev, "?"]],
                [["RELIER", compile format ["['link', objectFromNetId '%1'] call comspec_atak_native_fnc_linkAllyAction;", netId _obj], true]]];
        } forEach _c;
        _rows pushBack ["buttons", [["ACTUALISER", { ["refresh"] call comspec_atak_native_fnc_linkAllyAction; }]]];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
