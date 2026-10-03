/*
    OSINT : renseignement d'origine publique.
      "scan"  : relit les localités proches (5 km) : civils, présence armée et véhicules observés par les habitants,
                arrondis et en retard (cache 5 min, sans camp ni position exacte), et génère le fil public.
      "post"  : publie un message sur le fil public (tous les camps le voient : intox possible).
    Les messages publiés arrivent par l'événement comspec_atak_native_osintPost.
*/
params [["_act", "scan"], ["_arg", ""]];
switch (_act) do {
    case "scan": {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_OsintScan", []];
        if ((count _cache) isEqualTo 2 && {time - (_cache select 0) < 300} && {_arg isNotEqualTo "force"}) exitWith { _cache select 1 };
        private _out = [];
        private _locs = nearestLocations [getPosATL player, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 5000];
        {
            private _lp = locationPosition _x;
            private _name = text _x;
            if (_name isEqualTo "") then { continue };
            private _r = [350, 600] select ((type _x) in ["NameCityCapital", "NameCity"]);
            private _men = (_lp nearEntities ["CAManBase", _r]) select { alive _x };
            private _civ = { side group _x isEqualTo civilian } count _men;
            private _arm = { side group _x isNotEqualTo civilian } count _men;
            private _veh = (_lp nearEntities [["Car", "Tank", "Air"], _r]) select { alive _x && {(count crew _x) > 0} };
            private _mil = { side group _x isNotEqualTo civilian } count _veh;
            // Les habitants ne comptent pas juste : arrondi au « quelques / une dizaine / beaucoup ».
            private _fuzzy = { params ["_n"]; switch (true) do { case (_n <= 0): { "" }; case (_n <= 3): { "quelques" }; case (_n <= 8): { "une dizaine de" }; default { "beaucoup de" }; } };
            _out pushBack [_name, player distance2D _lp, player getDir _lp, _civ, [_arm] call _fuzzy, [_mil] call _fuzzy, _arm, _mil];
        } forEach (_locs select [0, 8]);
        // Fil public généré à partir des observations des habitants.
        // Villes et villages : nom propre (« près de Gravia ») ; lieux-dits : « du côté de la base aérienne ».
        private _handles = ["habitant", "berger", "commercant", "etudiante", "chauffeur", "mamie", "pecheur", "instit", "boulanger", "taxi"];
        private _plain = {
            params ["_t"];
            private _r = toLower _t;
            { _r = [_r, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["é", "e"], ["è", "e"], ["ê", "e"], ["à", "a"], ["â", "a"], ["ç", "c"], ["ô", "o"], ["î", "i"], ["ï", "i"], ["ù", "u"], ["û", "u"], [" ", ""], ["'", ""], ["-", ""]];
            _r select [0, 12]
        };
        private _types = createHashMapFromArray ((_locs select [0, 8]) apply { [text _x, type _x] });
        private _posts = [];
        {
            _x params ["_name", "_d", "_b", "_civ", "_armTxt", "_milTxt", "_arm", "_mil"];
            private _proper = (_types getOrDefault [_name, ""]) isNotEqualTo "NameLocal";
            private _near = if (_proper) then { format ["près de %1", _name] } else { format ["du côté de « %1 »", _name] };
            private _to = if (_proper) then { format ["vers %1", _name] } else { format ["vers « %1 »", _name] };
            private _at = if (_proper) then { format ["à %1", _name] } else { format ["vers « %1 »", _name] };
            private _tag = [_name] call _plain;
            private _h = format ["@%1_%2", selectRandom _handles, [_tag, "ducoin"] select (!_proper || {_tag isEqualTo ""})];
            private _ago = { params ["_m"]; format ["il y a %1 min", _m] };
            if (_mil > 0) then {
                _posts pushBack [_h, selectRandom [
                    format ["Il y a %1 véhicules militaires qui passent %2, ça roule vite. #%3", _milTxt, _to, _tag],
                    format ["Encore %1 blindés %2 ce matin, la route est bloquée.", _milTxt, _near],
                    format ["Convoi militaire %1 : %2 véhicules, peut-être plus.", _to, _milTxt]
                ], [5 + floor random 25] call _ago, _name];
            };
            if (_arm > 0) then {
                _posts pushBack [_h, selectRandom [
                    format ["Vu %1 hommes armés %2, restez chez vous.", _armTxt, _near],
                    format ["Des soldats %1, on ne laisse pas sortir les enfants.", _near],
                    format ["On a vu %1 hommes en armes %2, ils fouillent les maisons ?", _armTxt, _at]
                ], [5 + floor random 40] call _ago, _name];
            };
            if (_arm isEqualTo 0 && {_mil isEqualTo 0} && {_civ > 0} && {random 1 < 0.4}) then {
                _posts pushBack [_h, selectRandom [
                    format ["Calme %1 aujourd'hui, le marché est ouvert.", _at],
                    format ["Rien à signaler %1, les gens sortent de nouveau.", _at]
                ], [10 + floor random 50] call _ago, _name];
            };
        } forEach _out;
        private _res = [_out, _posts];
        uiNamespace setVariable ["COMSPEC_ATAK_OsintScan", [time, _res]];
        _res
    };
    case "post": {
        private _txt = trim (["osintPost"] call comspec_atak_native_fnc_formValue);
        if (_txt isEqualTo "") exitWith { false };
        private _pseudo = trim (["osintPseudo"] call comspec_atak_native_fnc_formValue);
        if (_pseudo isEqualTo "") then { _pseudo = "anonyme"; };
        profileNamespace setVariable ["COMSPEC_ATAK_OsintPseudo", _pseudo];
        [{
            params ["_p", "_t"];
            ["comspec_atak_native_osintPost", [format ["@%1", _p select [0, 20]], _t select [0, 200], [dayTime, "HH:MM"] call BIS_fnc_timeToString]] call CBA_fnc_globalEvent;
        }, [_pseudo, _txt], "Publication", 1] call comspec_atak_native_fnc_netSend;
        [{ ["OSINT"] call comspec_atak_native_fnc_pageRender; }, [], 1] call CBA_fnc_waitAndExecute;
        true
    };
};
