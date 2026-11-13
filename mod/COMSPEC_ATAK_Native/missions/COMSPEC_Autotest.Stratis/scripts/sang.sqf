// Sang sur l'écran (fn_screenDirt, fn_screenSurface) : chaque niveau forcé (léger à extrême), en main (plein écran,
// paysage) et en miniature portée (portrait), frais puis séché, avant et après un coup de chiffon. Captures à
// regarder : l'extrême doit ressembler à un écran couvert de sang, sans tampon répété, sans bord carré, sans étirement.
if !("sang" call AT_fnc_groupe) exitWith {};
["sang_niveaux", {
    if (["comspec_atak_native_fnc_screenDirt", "comspec_atak_native_fnc_screenSurface", "comspec_atak_native_fnc_deviceOverlay"] call AT_fnc_nil) exitWith {};
    if !(["enabled"] call comspec_atak_native_fnc_screenDirt) exitWith { "saleté de l'écran coupée (réglage)" call AT_fnc_skip; };
    private _mode0 = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    private _vide = {
        private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
        _n set ["stains", []]; _n set ["blood", 0]; _n set ["frameBlood", 0];
        missionNamespace setVariable ["COMSPEC_ATAK_HandsBlood", 0];
    };
    format ["niveau maximal permis : %1", ["maxLevel"] call comspec_atak_native_fnc_screenDirt] call AT_fnc_info;
    {
        _x params ["_mode", "_orient", "_main", "_nom"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        [] call comspec_atak_native_fnc_close;
        uiSleep 0.5;
        [_main] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, format ["téléphone non ouvert (%1)", _nom]] call AT_fnc_check;
        uiSleep 1.2;
        {
            call _vide;
            ["force", [_x]] call comspec_atak_native_fnc_screenDirt;
            [] call comspec_atak_native_fnc_deviceOverlay;
            private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
            format ["%1 niveau %2 : %3 taches, %4 %% de l'écran", _nom, _x, count (_n getOrDefault ["stains", []]), round (100 * (_n getOrDefault ["blood", 0]))] call AT_fnc_info;
            [format ["sang_%1_niveau%2_frais", _nom, _x]] call AT_fnc_shot;
        } forEach [1, 2, 3, 4];
        // Extrême séché (8 min), puis un coup de chiffon sur du sang frais et sur du sang séché.
        ["age", [480]] call comspec_atak_native_fnc_screenDirt;
        [] call comspec_atak_native_fnc_deviceOverlay;
        [format ["sang_%1_niveau4_seche", _nom]] call AT_fnc_shot;
        ["clean", false] call comspec_atak_native_fnc_screenDirt;
        [] call comspec_atak_native_fnc_deviceOverlay;
        [format ["sang_%1_seche_essuye", _nom]] call AT_fnc_shot;
        call _vide;
        ["force", [4]] call comspec_atak_native_fnc_screenDirt;
        ["clean", false] call comspec_atak_native_fnc_screenDirt;
        [] call comspec_atak_native_fnc_deviceOverlay;
        private _r = ["residue"] call comspec_atak_native_fnc_screenDirt;
        [(_r select 2) > 0, format ["%1 : un coup de chiffon sur l'extrême frais ne laisse aucune traînée", _nom]] call AT_fnc_check;
        [format ["sang_%1_frais_essuye", _nom]] call AT_fnc_shot;
        ["clean", "kit"] call comspec_atak_native_fnc_screenDirt;
        [(["stainsCount"] call comspec_atak_native_fnc_screenDirt) isEqualTo 0, format ["%1 : le kit ne nettoie pas tout", _nom]] call AT_fnc_check;
    } forEach [["FULL", "LANDSCAPE", true, "main_paysage"], ["MINI", "PORTRAIT", false, "mini_portrait"], ["MINI", "LANDSCAPE", true, "main_mini_paysage"]];
    "Sang : extrême = écran presque couvert, lisible entre les taches ; séché = brun mat et craquelé ; essuyé = traînées ; aucune tache étirée ni bord carré" call AT_fnc_visuel;
    call _vide;
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode0];
    profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient0];
    [] call comspec_atak_native_fnc_deviceOverlay;
    call AT_fnc_done;
}, 240] call AT_fnc_run;
