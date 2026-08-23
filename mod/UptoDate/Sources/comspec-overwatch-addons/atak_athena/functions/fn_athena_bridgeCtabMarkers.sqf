/*
    Marqueurs utilisateur cTab / ATAK Enhanced → Athena (SendMarker).
    Sources :
      - cTabUserMarkerList (traduit) : [ [id, translatedData, raw?], … ]
      - cTab_userMarkerLists (brut, clé chiffrement) si la liste traduite est vide
      - variables Iceman / Dropper connues
    translatedData = [pos, iconPath, sizePath, dir, colorRGB, text, align, drawSize?]
    raw cTab     = [pos, iconIdx, sizeIdx, dirOctant, text, creator?]
*/
if (!hasInterface) exitWith {};
if (!(["ctab_markers"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _athenaReady = missionNamespace getVariable ["COMSPEC_AthenaReady", false];

private _list = [];
private _seenIds = createHashMap;

private _appendList = {
    params ["_cand"];
    if (_cand isEqualType createHashMap) then {
        private _vals = [];
        { _vals pushBack [_x, _cand get _x]; } forEach (keys _cand);
        _cand = _vals;
    };
    if (!(_cand isEqualType [])) exitWith {};
    {
        if (!(_x isEqualType [])) then { continue };
        if ((count _x) < 2) then { continue };
        private _id = _x select 0;
        private _key = str _id;
        if (_seenIds getOrDefault [_key, false]) then { continue };
        _seenIds set [_key, true];
        _list pushBack _x;
    } forEach _cand;
};

if (!isNil "cTabUserMarkerList") then { [cTabUserMarkerList] call _appendList; };
[missionNamespace getVariable ["Iceman_ATAK_UserMarkers", []]] call _appendList;
[missionNamespace getVariable ["cTab_userMarkerList", []]] call _appendList;
[uiNamespace getVariable ["cTabUserMarkerList", []]] call _appendList;
[uiNamespace getVariable ["Iceman_ATAK_UserMarkers", []]] call _appendList;

// Repli : listes brutes cTab (par clé de chiffrement) → traduction locale
// Toujours fusionner (BCE remplit cTabUserMarkerList avec un format mixte)
if (!isNil "cTab_userMarkerLists") then {
    private _pairs = missionNamespace getVariable ["cTab_userMarkerLists", []];
    if (_pairs isEqualType []) then {
        {
            if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
            private _rawList = _x select 1;
            if (!(_rawList isEqualType [])) then { continue };
            {
                if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
                private _id = _x select 0;
                private _raw = _x select 1;
                if (!(_raw isEqualType [])) then { continue };
                if (!isNil "cTab_fnc_translateUserMarker") then {
                    private _translated = _raw call cTab_fnc_translateUserMarker;
                    if (_translated isEqualType []) then {
                        [ [[_id, _translated, _raw]] ] call _appendList;
                    } else {
                        [ [[_id, _raw]] ] call _appendList;
                    };
                } else {
                    [ [[_id, _raw]] ] call _appendList;
                };
            } forEach _rawList;
        } forEach _pairs;
    };
};

if ((count _list) < 1) exitWith {};

private _prev = missionNamespace getVariable ["COMSPEC_Athena_CtabMarkerSnap", createHashMap];
if (!(_prev isEqualType createHashMap)) then { _prev = createHashMap; };
private _next = createHashMap;

private _colorFromRgb = {
    params ["_rgb"];
    if (!(_rgb isEqualType []) || {(count _rgb) < 3}) exitWith { "ColorRed" };
    private _r = _rgb select 0;
    private _g = _rgb select 1;
    private _b = _rgb select 2;
    if (_r > 0.7 && {_g < 0.35} && {_b < 0.35}) exitWith { "ColorRed" };
    if (_b > 0.7 && {_r < 0.45} && {_g < 0.55}) exitWith { "ColorBlue" };
    if (_g > 0.7 && {_r < 0.45} && {_b < 0.45}) exitWith { "ColorGreen" };
    if (_r > 0.7 && {_g > 0.7} && {_b < 0.45}) exitWith { "ColorYellow" };
    if (_r > 0.55 && {_g > 0.35} && {_b < 0.25}) exitWith { "ColorOrange" };
    if (_r < 0.25 && {_g < 0.25} && {_b < 0.25}) exitWith { "ColorBlack" };
    if (_r > 0.85 && {_g > 0.85} && {_b > 0.85}) exitWith { "ColorWhite" };
    "ColorRed"
};

private _typeFromTexture = {
    params ["_tex"];
    private _t = toLower _tex;

    if ((_t find "mplus_") >= 0) then {
        private _mp = "";
        {
            if ((_t find _x) >= 0) exitWith { _mp = _x; };
        } forEach [
            "mplus_aapoint","mplus_ambush","mplus_attackbyfire","mplus_breach","mplus_bypass","mplus_clear",
            "mplus_disengage","mplus_exfiltrate","mplus_followassume","mplus_followsupport","mplus_occupy",
            "mplus_retain","mplus_secure","mplus_seize","mplus_supportbyfire","mplus_block","mplus_canalize",
            "mplus_contain","mplus_destroy","mplus_disrupt","mplus_fix","mplus_isolate","mplus_interdict",
            "mplus_neutralize","mplus_supress","mplus_turn","mplus_cordonknock","mplus_cordonsearch",
            "mplus_guard","mplus_screen","mplus_cover","mplus_feintattack","mplus_mainattack","mplus_phaseline",
            "mplus_checkpoint","mplus_linkuppoint","mplus_passagepoint","mplus_rallypoint","mplus_releasepoint",
            "mplus_startpoint","mplus_departurepoint","mplus_civpoint","mplus_iprp","mplus_sarpoint",
            "mplus_ammopoint","mplus_ccppoint","mplus_medevac","mplus_r3p","mplus_waypoint"
        ];
        if (_mp isNotEqualTo "") exitWith { _mp };
    };
    if ((_t find "mts_markers_") >= 0 || {(_t find "\mts_") >= 0} || {(_t find "/mts_") >= 0}) then {
        private _bn = _t;
        private _parts = _bn splitString "\/";
        if ((count _parts) > 0) then { _bn = _parts select ((count _parts) - 1); };
        _bn = (_bn splitString ".") select 0;
        if ((_bn find "mts_markers_") == 0) then {
            private _rest = _bn select [12];
            exitWith { format ["mts_%1", _rest] };
        };
        if ((_bn find "mts_") == 0) exitWith { _bn };
    };

    if ((_t find "o_mech") >= 0) exitWith { "o_mech_inf" };
    if ((_t find "o_motor") >= 0) exitWith { "o_motor_inf" };
    if ((_t find "o_armor") >= 0) exitWith { "o_armor" };
    if ((_t find "o_air") >= 0) exitWith { "o_air" };
    if ((_t find "o_plane") >= 0) exitWith { "o_plane" };
    if ((_t find "o_uav") >= 0) exitWith { "o_uav" };
    if ((_t find "o_naval") >= 0 || {(_t find "o_ship") >= 0}) exitWith { "o_naval" };
    if ((_t find "o_support") >= 0 || {(_t find "o_maint") >= 0} || {(_t find "o_service") >= 0}) exitWith { "o_support" };
    if ((_t find "o_med") >= 0) exitWith { "o_med" };
    if ((_t find "o_hq") >= 0) exitWith { "o_hq" };
    if ((_t find "o_recon") >= 0) exitWith { "o_recon" };
    if ((_t find "o_art") >= 0) exitWith { "o_art" };
    if ((_t find "o_unknown") >= 0) exitWith { "o_unknown" };
    if ((_t find "o_inf_aa") >= 0 || {(_t find "o_inf_aa.") >= 0}) exitWith { "o_antiair" };
    if ((_t find "o_inf_mmortar") >= 0 || {(_t find "mmortar") >= 0}) exitWith { "o_mortar" };
    if ((_t find "o_inf_mat") >= 0 || {(_t find "o_inf_at") >= 0}) exitWith { "o_inf" };
    if ((_t find "o_inf_mmg") >= 0 || {(_t find "o_inf_mg") >= 0}) exitWith { "o_inf" };
    if ((_t find "o_inf_rifle") >= 0 || {(_t find "o_inf") >= 0}) exitWith { "o_inf" };
    if ((_t find "/o_") >= 0 || {(_t find "\o_") >= 0}) exitWith { "o_unknown" };

    if ((_t find "b_hq") >= 0) exitWith { "b_hq" };
    if ((_t find "b_mech") >= 0) exitWith { "b_mech_inf" };
    if ((_t find "b_motor") >= 0) exitWith { "b_motor_inf" };
    if ((_t find "b_armor") >= 0) exitWith { "b_armor" };
    if ((_t find "b_air") >= 0) exitWith { "b_air" };
    if ((_t find "b_plane") >= 0) exitWith { "b_plane" };
    if ((_t find "b_uav") >= 0) exitWith { "b_uav" };
    if ((_t find "b_recon") >= 0) exitWith { "b_recon" };
    if ((_t find "b_med") >= 0) exitWith { "b_med" };
    if ((_t find "b_support") >= 0 || {(_t find "b_maint") >= 0}) exitWith { "b_support" };
    if ((_t find "b_art") >= 0) exitWith { "b_art" };
    if ((_t find "b_unknown") >= 0) exitWith { "b_unknown" };
    if ((_t find "b_inf") >= 0) exitWith { "b_inf" };

    if ((_t find "n_inf") >= 0) exitWith { "n_inf" };
    if ((_t find "n_armor") >= 0) exitWith { "n_armor" };
    if ((_t find "n_air") >= 0) exitWith { "n_air" };
    if ((_t find "n_unknown") >= 0) exitWith { "n_unknown" };
    if ((_t find "c_unknown") >= 0 || {(_t find "civilian") >= 0}) exitWith { "c_unknown" };

    if ((_t find "hospital") >= 0) exitWith { "loc_Hospital" };
    if ((_t find "fuel") >= 0) exitWith { "loc_FuelStation" };
    if ((_t find "church") >= 0) exitWith { "loc_Church" };
    if ((_t find "transmitter") >= 0 || {(_t find "tower") >= 0}) exitWith { "loc_Transmitter" };
    if ((_t find "warning") >= 0) exitWith { "mil_warning" };
    if ((_t find "destroy") >= 0) exitWith { "mil_destroy" };
    if ((_t find "objective") >= 0) exitWith { "mil_objective" };
    if ((_t find "ambush") >= 0) exitWith { "mil_ambush" };
    if ((_t find "triangle") >= 0) exitWith { "mil_triangle" };
    if ((_t find "box") >= 0) exitWith { "mil_box" };
    if ((_t find "arrow") >= 0) exitWith { "mil_arrow" };
    if ((_t find "flag") >= 0) exitWith { "mil_flag" };
    if ((_t find "unknown") >= 0) exitWith { "mil_unknown" };
    if ((_t find "circle") >= 0 || {(_t find "m_circle") >= 0}) exitWith { "mil_circle" };
    if ((_t find "join") >= 0) exitWith { "mil_join" };
    if ((_t find "end_ca") >= 0 || {(_t find "end.") >= 0} || {(_t find "/end") >= 0}) exitWith { "mil_end" };
    if ((_t find "start") >= 0) exitWith { "mil_start" };
    if ((_t find "pickup") >= 0) exitWith { "mil_pickup" };
    if ((_t find "dot_ca") >= 0 || {(_t find "dot.") >= 0}) exitWith { "mil_dot" };
    if ((_t find "marker") >= 0) exitWith { "mil_marker" };
    if ((_t find "tic.paa") >= 0) exitWith { "mil_warning" };

    if ((_t find "10061500002101000000") >= 0) exitWith { "mil_warning" };
    if ((_t find "10061500002104000000") >= 0) exitWith { "mil_destroy" };
    if ((_t find "10032500003211000000") >= 0) exitWith { "loc_Hospital" };
    if ((_t find "10032500003207000000") >= 0) exitWith { "mil_objective" };
    if ((_t find "10032500003205000000") >= 0) exitWith { "mil_marker" };
    if ((_t find "10032500001603000000") >= 0) exitWith { "mil_objective" };
    if ((_t find "10032500001602050000") >= 0) exitWith { "mil_box" };
    if ((_t find "10032500001601000000") >= 0) exitWith { "mil_box" };
    if ((_t find "100325000013") >= 0) exitWith { "mil_marker" };
    if ((_t find "100325000016") >= 0) exitWith { "mil_marker" };
    if ((_t find "100325000032") >= 0) exitWith { "mil_marker" };
    if ((_t find "1006") >= 0) exitWith { "o_unknown" };
    if ((_t find "1003") >= 0) exitWith { "b_unknown" };

    "mil_dot"
};

private _groupFromSizeTex = {
    params ["_tex"];
    private _t = toLower _tex;
    if ((_t find "group_0") >= 0) exitWith { 1 };
    if ((_t find "group_1") >= 0) exitWith { 2 };
    if ((_t find "group_2") >= 0) exitWith { 3 };
    if ((_t find "group_3") >= 0) exitWith { 4 };
    0
};

private _labelFrFromType = {
    params ["_type", "_text"];
    if (!(_text isEqualTo "")) exitWith { _text };
    private _t = toLower _type;
    if ((_t find "mplus_") == 0) exitWith {
        switch (_t) do {
            case "mplus_ambush": { "Embuscade" };
            case "mplus_destroy": { "Détruire" };
            case "mplus_medevac": { "Point évacuation médicale" };
            case "mplus_ccppoint": { "Point de ramassage blessés" };
            case "mplus_checkpoint": { "Point de contrôle" };
            case "mplus_rallypoint": { "Point de ralliement" };
            case "mplus_mainattack": { "Flèche d'attaque principale" };
            case "mplus_feintattack": { "Flèche feinte" };
            case "mplus_waypoint": { "Point de passage" };
            default { "Repère MarkersPlus" };
        }
    };
    if ((_t find "mts_") == 0) exitWith {
        if ((_t find "_red_") >= 0 || {(_t find "mts_red") == 0}) exitWith { "Symbole Metis adverse" };
        if ((_t find "_blu_") >= 0 || {(_t find "mts_blu") == 0}) exitWith { "Symbole Metis ami" };
        if ((_t find "_neu_") >= 0) exitWith { "Symbole Metis neutre" };
        "Symbole Metis"
    };
    if ((_t find "o_") == 0) exitWith { "Contact adverse" };
    if ((_t find "b_") == 0) exitWith { "Repère ami" };
    if ((_t find "n_") == 0) exitWith { "Repère neutre" };
    if (_t isEqualTo "loc_hospital") exitWith { "Poste médical" };
    if ((_t find "warning") >= 0) exitWith { "Alerte" };
    if ((_t find "destroy") >= 0) exitWith { "Engin explosif" };
    if ((_t find "objective") >= 0) exitWith { "Objectif" };
    if ((_t find "join") >= 0) exitWith { "Ralliement" };
    if ((_t find "end") >= 0) exitWith { "Fin de parcours" };
    if ((_t find "circle") >= 0) exitWith { "Zone" };
    "Repère ATAK"
};

private _normalizeEntry = {
    params ["_entry"];
    // [id, translatedOrRaw]
    if (!(_entry isEqualType []) || {(count _entry) < 2}) exitWith { [] };
    private _id = _entry select 0;
    private _data = _entry select 1;
    if (!(_data isEqualType [])) exitWith { [] };

    // Déjà traduit : pos = array de coords
    private _pos0 = _data select 0;
    if (_pos0 isEqualType [] && {(count _pos0) >= 2} && {(_pos0 select 0) isEqualType 0}) exitWith {
        // Si index 1 est un nombre (icon idx) → brut cTab, traduire
        if ((count _data) > 1 && {(_data select 1) isEqualType 0} && {!isNil "cTab_fnc_translateUserMarker"}) then {
            private _tr = _data call cTab_fnc_translateUserMarker;
            if (_tr isEqualType [] && {(count _tr) > 0}) exitWith { [_id, _tr] };
        };
        [_id, _data]
    };
    []
};

private _sendMarker = {
    params ["_armaName", "_json", "_deleted"];
    if (_athenaReady) then {
        private _flag = if (_deleted) then { "1" } else { "0" };
        private _body = if (_deleted) then { "{}" } else { _json };
        "COMSPECExtension" callExtension ["SendMarker", [_armaName, _body, "1", _flag]];
    } else {
        [_armaName, _json, _deleted] call comspec_overwatch_connect_fnc_queueMapMarker;
    };
};

{
    private _norm = [_x] call _normalizeEntry;
    if ((count _norm) < 2) then { continue };
    _norm params ["_id", "_data"];
    if ((count _data) < 1) then { continue };
    private _pos = _data select 0;
    if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };

    private _q = toString [34];
    private _texRaw = if ((count _data) > 1) then { _data select 1 } else { "" };
    private _tex = if (_texRaw isEqualType "") then { _texRaw } else { str _texRaw };
    if ((count _tex) >= 2 && {(_tex select [0, 1]) isEqualTo _q} && {(_tex select [(count _tex) - 1, 1]) isEqualTo _q}) then {
        _tex = _tex select [1, (count _tex) - 2];
    };
    private _sizeTexRaw = if ((count _data) > 2) then { _data select 2 } else { "" };
    private _sizeTex = if (_sizeTexRaw isEqualType "") then { _sizeTexRaw } else { str _sizeTexRaw };
    if ((count _sizeTex) >= 2 && {(_sizeTex select [0, 1]) isEqualTo _q} && {(_sizeTex select [(count _sizeTex) - 1, 1]) isEqualTo _q}) then {
        _sizeTex = _sizeTex select [1, (count _sizeTex) - 2];
    };
    private _dirRaw = if ((count _data) > 3) then { _data select 3 } else { 0 };
    private _dir = if (_dirRaw isEqualType 0) then { _dirRaw } else { 0 };
    if (_dir >= 400) then { _dir = 0; };

    private _color = "ColorRed";
    if ((count _data) > 4) then {
        private _c = _data select 4;
        if (_c isEqualType []) then {
            _color = [_c] call _colorFromRgb;
        };
        if (_c isEqualType "") then { _color = _c; };
    };

    private _textRaw = "";
    if ((count _data) > 5) then {
        private _tr = _data select 5;
        _textRaw = if (_tr isEqualType "") then { _tr } else { str _tr };
        if ((count _textRaw) >= 2 && {(_textRaw select [0, 1]) isEqualTo _q} && {(_textRaw select [(count _textRaw) - 1, 1]) isEqualTo _q}) then {
            _textRaw = _textRaw select [1, (count _textRaw) - 2];
        };
    };
    private _type = [_tex] call _typeFromTexture;
    if (_type isEqualTo "") then { _type = "mil_dot"; };
    private _groupSize = [_sizeTex] call _groupFromSizeTex;
    private _drawSize = if ((count _data) > 7 && {(_data select 7) isEqualType 0}) then { _data select 7 } else { 1 };

    if ((_tex find "b_hq") >= 0 || {(_tex find "end_CA") >= 0} || {(_tex find "end_ca") >= 0}) then {
        if (_color isEqualTo "ColorRed") then { _color = "ColorBLUFOR"; };
    };
    if ((_tex find "join") >= 0 || {(_tex find "circle") >= 0} || {(_tex find "Hospital") >= 0} || {(_tex find "warning") >= 0}) then {
        if ((_tex find "o_") < 0 && {(_tex find "tic") < 0}) then {
            if (_color isEqualTo "ColorRed") then { _color = "ColorGreen"; };
        };
    };

    private _armaName = format ["ctab_u_%1", _id];
    private _text = [_type, _textRaw] call _labelFrFromType;
    _text = (_text splitString """" joinString "'");
    // Chemin texture PAA normalisé (/) pour le miroir web → PNG CDN
    private _bs = toString [92];
    private _texForJson = (_tex splitString _bs joinString "/");
    _texForJson = (_texForJson splitString """" joinString "'");
    private _sig = format ["%1|%2|%3|%4|%5|%6|%7|%8", _pos select 0, _pos select 1, _type, _text, _color, _dir, _groupSize, _texForJson];
    _next set [_armaName, _sig];

    if ((_prev getOrDefault [_armaName, ""]) isEqualTo _sig) then { continue };

    private _sizeA = (1 max _drawSize) * (if (_groupSize > 0) then { 1 + (_groupSize * 0.15) } else { 1 });
    private _json = format [
        "{""pos"":[%1,%2,0],""type"":""%3"",""text"":""%4"",""color"":""%5"",""dir"":%6,""alpha"":1,""shape"":""ICON"",""size"":[%7,%7],""brush"":""Solid"",""polyline"":[],""source"":""ctab_user"",""groupSize"":%8,""texture"":""%9""}",
        (_pos select 0) toFixed 2,
        (_pos select 1) toFixed 2,
        _type,
        _text,
        _color,
        _dir toFixed 2,
        _sizeA toFixed 2,
        _groupSize,
        _texForJson
    ];
    [_armaName, _json, false] call _sendMarker;
    [format ["Marqueur ATAK · %1", _text]] call comspec_overwatch_connect_fnc_appendModuleLog;
} forEach _list;

{
    if (!(_x in _next)) then {
        [_x, "{}", true] call _sendMarker;
    };
} forEach (keys _prev);

missionNamespace setVariable ["COMSPEC_Athena_CtabMarkerSnap", _next, false];
