// Contrôle de CfgFunctions : chaque fonction déclarée par nos mods existe et compile. Liste écrite au RPT (AUTOTEST|FCT|...).
if !("fonctions" call AT_fnc_groupe) exitWith {};
["fonctions_cfg", {
    private _n = 0;
    private _absentes = 0;
    private _sansFichier = 0;
    private _nonCompilees = 0;
    {
        private _cTag = _x;
        private _tag = getText (_cTag >> "tag");
        if (_tag isEqualTo "") then { _tag = configName _cTag; };
        if (((toLower _tag) select [0, 7]) isEqualTo "comspec") then {
            {
                private _cCat = _x;
                private _dir = getText (_cCat >> "file");
                {
                    private _cFn = _x;
                    private _nom = configName _cFn;
                    private _var = format ["%1_fnc_%2", _tag, _nom];
                    private _ext = getText (_cFn >> "ext");
                    if (_ext isEqualTo "") then { _ext = ".sqf"; };
                    private _chemin = getText (_cFn >> "file");
                    if (_chemin isEqualTo "") then { _chemin = format ["%1\fn_%2%3", _dir, _nom, _ext]; };
                    _n = _n + 1;
                    if (isNil _var) then {
                        _absentes = _absentes + 1;
                        diag_log format ["AUTOTEST|FCT|ABSENTE|%1|%2", _var, _chemin];
                    };
                    if (fileExists _chemin || {fileExists ("\" + _chemin)}) then {
                        private _src = trim (preprocessFile _chemin);
                        if (_src isNotEqualTo "") then {
                            private _c = compile _src;
                            if (isNil "_c" || {(str _c) in ["{}", "{ }"]}) then {
                                _nonCompilees = _nonCompilees + 1;
                                diag_log format ["AUTOTEST|FCT|NE_COMPILE_PAS|%1|%2", _var, _chemin];
                            };
                        };
                    } else {
                        _sansFichier = _sansFichier + 1;
                        diag_log format ["AUTOTEST|FCT|SANS_FICHIER|%1|%2", _var, _chemin];
                    };
                    if ((_n mod 25) isEqualTo 0) then { uiSleep 0.01; };
                } forEach ("true" configClasses _cCat);
            } forEach ("true" configClasses _cTag);
        };
    } forEach ("true" configClasses (configFile >> "CfgFunctions"));
    diag_log format ["AUTOTEST|FCT|TOTAL|declarees=%1|absentes=%2|sans_fichier=%3|ne_compile_pas=%4", _n, _absentes, _sansFichier, _nonCompilees];
    [_n > 0, "aucune fonction COMSPEC déclarée (mods absents ?)"] call AT_fnc_check;
    [(_absentes + _sansFichier + _nonCompilees) isEqualTo 0, format ["%1 absentes, %2 sans fichier, %3 ne compilent pas", _absentes, _sansFichier, _nonCompilees]] call AT_fnc_check;
    call AT_fnc_done;
}, 900] call AT_fnc_run;
