/* OSINT : onglets FIL PUBLIC (publications des joueurs + observations des habitants), LOCALITÉS (bilan par lieu), PUBLIER. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["osTab", "FEED"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
(["scan"] call comspec_atak_native_fnc_osintAction) params ["_locs", "_gen"];
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['osTab', '%1']; [{ ['OSINT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["FIL PUBLIC", "FEED"] call _tabBtn, ["LOCALITÉS", "LOCS"] call _tabBtn, ["PUBLIER", "POST"] call _tabBtn]]];
switch (_tab) do {
    case "LOCS": {
        _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Ce que rapportent les habitants des localités à moins de 5 km : chiffres approximatifs, mis à jour toutes les 5 min, sans camp ni position exacte.</t>"];
        {
            _x params ["_name", "_d", "_b", "_civ", "_armTxt", "_milTxt"];
            _rows pushBack ["person", "\A3\ui_f\data\map\mapcontrol\Tourism_CA.paa", format ["<t font='RobotoCondensedBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2 · %3° %4</t><br/><t size='0.85'>%5 civil(s)%6%7</t>",
                [_name] call _esc, [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000), round _b, [_b] call _card, _civ,
                ["", format [" · <t color='#f2ab33'>%1 hommes armés</t>", _armTxt]] select (_armTxt isNotEqualTo ""),
                ["", format [" · <t color='#e5483a'>%1 véhicules militaires</t>", _milTxt]] select (_milTxt isNotEqualTo "")], [], [0.9, 0.94, 0.91, 1]];
        } forEach _locs;
        if ((count _locs) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune localité à moins de 5 km.</t>"]; };
        _rows pushBack ["buttons", [["ACTUALISER", { ["scan", "force"] call comspec_atak_native_fnc_osintAction; [{ ['OSINT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
    };
    case "POST": {
        _rows append [
            ["text", "<t size='0.85' color='#8a9a93'>Le fil est public : tous les camps le lisent. Utile pour l'intox, dangereux pour la sécurité des opérations.</t>"],
            ["edit", "osintPseudo", "Pseudo", profileNamespace getVariable ["COMSPEC_ATAK_OsintPseudo", "anonyme"]],
            ["memo", "osintPost", "Message (200 caractères)", "", 3],
            ["buttons", [["PUBLIER", { ["post"] call comspec_atak_native_fnc_osintAction; (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['osTab', 'FEED']; }, true]]]
        ];
    };
    default {
        private _feed = (missionNamespace getVariable ["COMSPEC_ATAK_OsintFeed", []]) apply { [_x select 0, _x select 1, _x select 2, "", true] };
        reverse _feed;
        _feed append (_gen apply { [_x select 0, _x select 1, _x select 2, _x select 3, false] });
        {
            _x params ["_who", "_txt", "_when", "_loc", "_player"];
            _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_osint.paa", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.75' color='#8a9a93'>%3</t><br/><t size='0.9'>%4</t>",
                ["#7fb6e6", "#e8b84a"] select _player, [_who] call _esc, [_when] call _esc, [_txt] call _esc], [], [0.9, 0.94, 0.91, 1]];
        } forEach (_feed select [0, 30]);
        if ((count _feed) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Rien sur le fil public pour l'instant.</t>"]; };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
