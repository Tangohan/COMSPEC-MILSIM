/*
    Groupe : onglet MON GROUPE (fiche, nom et type modifiables par le chef, membres, quitter)
    et onglet GROUPES (autres groupes de mon camp avec joueurs, rejoindre).
    Membres : principe de la liste de groupe de BCE (Aaren, APL-SA).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["grpTab", "MINE"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _types = [] call comspec_atak_native_fnc_groupTypes;
private _typeOf = { params ["_g"]; private _k = _g getVariable ["COMSPEC_GroupType", "INF"]; (_types select { (_x select 0) isEqualTo _k }) param [0, _types select 0] };
private _grp = group player;
private _isLead = (leader _grp) isEqualTo player || {(count units _grp) isEqualTo 1};
private _others = allGroups select { side _x isEqualTo side _grp && {_x isNotEqualTo _grp} && {(count units _x) > 0} && {((units _x) findIf { isPlayer _x }) >= 0} };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['grpTab', '%1']; [{ ['GROUP'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["MON GROUPE", "MINE"] call _tabBtn, [format ["GROUPES (%1)", count _others], "ALL"] call _tabBtn]]];
private _state = {
    params ["_u"];
    switch (true) do {
        case (!alive _u): { "<t color='#e5483a'>Mort</t>" };
        case (lifeState _u isEqualTo "INCAPACITATED" || {_u getVariable ["ACE_isUnconscious", false]}): { "<t color='#e5483a'>Inconscient</t>" };
        case ((damage _u) > 0.25): { "<t color='#f2ab33'>Blessé</t>" };
        default { "<t color='#5cc76b'>Apte</t>" };
    }
};

if (_tab isEqualTo "MINE") then {
    ([_grp] call _typeOf) params ["_tk", "_tname", "_ticon"];
    private _locked = _grp getVariable ["COMSPEC_GroupLocked", false];
    _rows pushBack ["hero", _ticon, format ["<t size='1.3' font='RobotoCondensedBold'>%1</t><br/><t color='#7fb6e6'>%2</t> · %3 membre(s)<br/><t size='0.85' color='#8a9a93'>Chef : %4 · %5</t>",
        [groupId _grp] call _esc, _tname, count units _grp, [name leader _grp] call _esc, ["groupe ouvert", "<t color='#f2ab33'>groupe fermé</t>"] select _locked]];
    if (_isLead) then {
        _rows append [
            ["section", "Personnaliser", "Visible par tout votre camp (carte, BFT, Groupe)"],
            ["edit", "grpName", "Nom du groupe", groupId _grp],
            ["buttons", [["RENOMMER", { ["rename"] call comspec_atak_native_fnc_groupAction; }, true]]],
            ["combo", "grpType", "Type", _types apply { [_x select 1, _x select 0, _x select 2, [0.28, 0.70, 1, 1]] }, _tk],
            ["buttons", [["APPLIQUER LE TYPE", { ["type", ["grpType"] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_groupAction; }]]],
            ["switch", "Groupe fermé", _locked, { ["lock"] call comspec_atak_native_fnc_groupAction; }, "Personne ne peut le rejoindre depuis son téléphone"]
        ];
    };
    _rows pushBack ["section", "Membres", ""];
    {
        private _u = _x;
        private _avatar = [_u] call comspec_atak_native_fnc_avatarPath;
        private _pic = [_avatar, [_u, "texture"] call BIS_fnc_rankParams] select (_avatar isEqualTo "");
        private _role = getText (configOf _u >> "displayName");
        private _btns = [];
        if (_isLead && {_u isNotEqualTo player} && {isPlayer _u}) then { _btns pushBack ["CHEF", compile format ["['lead', '%1'] call comspec_atak_native_fnc_groupAction;", netId _u]]; };
        _rows pushBack ["person", _pic, format ["<t font='RobotoCondensedBold'>%1</t>%2<br/><t size='0.8' color='#8a9a93'>%3 · %4 · %5</t>",
            [name _u] call _esc, ["", " <t color='#e8b84a'>● chef</t>"] select (_u isEqualTo leader _grp), [_role] call _esc, [_u] call _state,
            ["moi", format ["%1 m", round (_u distance2D player)]] select (_u isNotEqualTo player)], _btns];
    } forEach units _grp;
    _rows pushBack ["buttons", [["QUITTER LE GROUPE", { ["leave"] call comspec_atak_native_fnc_groupAction; }, false]]];
    _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Quitter crée un groupe à votre nom, que d'autres peuvent rejoindre.</t>"];
} else {
    _rows pushBack ["section", "Groupes de mon camp", "Groupes avec au moins un joueur, du plus proche au plus loin"];
    private _sorted = _others apply { [(leader _x) distance2D player, _x] };
    _sorted sort true;
    {
        _x params ["_d", "_g"];
        ([_g] call _typeOf) params ["", "_tname", "_ticon"];
        private _locked = _g getVariable ["COMSPEC_GroupLocked", false];
        _rows pushBack ["person", _ticon, format ["<t font='RobotoCondensedBold'>%1</t>  <t size='0.8' color='#7fb6e6'>%2</t><br/><t size='0.8' color='#8a9a93'>%3 membre(s) · chef %4 · %5</t>",
            [groupId _g] call _esc, _tname, count units _g, [name leader _g] call _esc, [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000)],
            [[["REJOINDRE", "FERMÉ"] select _locked, compile format ["['join', '%1'] call comspec_atak_native_fnc_groupAction;", netId _g], !_locked, !_locked]], [0.28, 0.70, 1, 1]];
    } forEach _sorted;
    if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun autre groupe avec des joueurs dans votre camp.</t>"]; };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
