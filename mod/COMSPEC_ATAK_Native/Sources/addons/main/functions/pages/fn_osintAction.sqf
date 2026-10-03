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
        private _handles = ["habitant", "berger", "commercant", "etudiante", "chauffeur", "mamie", "pecheur", "instit"];
        private _posts = [];
        {
            _x params ["_name", "_d", "_b", "_civ", "_armTxt", "_milTxt", "_arm", "_mil"];
            private _h = format ["@%1_%2", selectRandom _handles, toLower ((_name splitString " ") param [0, "local"])];
            if (_mil > 0) then { _posts pushBack [_h, format ["%1 véhicules militaires vers %2, ça roule fort #%2", _milTxt, _name], format ["il y a %1 min", 5 + floor random 25], _name]; };
            if (_arm > 0) then { _posts pushBack [_h, format ["Vu %1 hommes armés près de %2, restez chez vous", _armTxt, _name], format ["il y a %1 min", 5 + floor random 40], _name]; };
            if (_arm isEqualTo 0 && {_mil isEqualTo 0} && {_civ > 0} && {random 1 < 0.4}) then { _posts pushBack [_h, format ["Calme à %1 aujourd'hui, le marché est ouvert", _name], format ["il y a %1 min", 10 + floor random 50], _name]; };
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
