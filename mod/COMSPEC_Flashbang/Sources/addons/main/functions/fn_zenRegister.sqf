/*
    comspec_fb_fnc_zenRegister : modules Zeus (Zeus Enhanced, facultatif), catégorie « COMSPEC Flashbang ».
      « Lancer flash »  : engin posé au point cliqué, nombre de détonations, grenade d'entraînement, mèche
                          (par défaut = réglage « Mèche », 3 s, 8 s ou personnalisée 0,5-20 s), son de la DRILL,
                          modèle (standard ou BANG spécial : JAPAN, WOMEN, SUPRISE, GERMAN).
      « Effet de zone » : effet sur toutes les unités dans un rayon, sans grenade (scénario).
    Exécution sur le serveur s'il a le mod (événements CBA), sinon par ce poste Zeus (fn_serverDetonate / fn_serverArea).
*/
private _icon = "\z\comspec_flashbang\addons\main\ui\fb_module_ca.paa";
private _cat = "BetterFlash";

// ZEN appelle la fonction d'un module sur le poste du Zeus avec [position ASL, objet sous le curseur].
[_cat, localize "STR_COMSPEC_FB_Zen_Throw", {
    params [["_pos", [0, 0, 0], [[]], 3]];
    ["module", format ["type=ZEN/lancer pos=%1 : fenêtre ouverte", _pos apply { round _x }]] call comspec_fb_fnc_log;
    private _keys = getArray (configFile >> "COMSPEC_FB_Sons" >> "drillChoix");
    [localize "STR_COMSPEC_FB_Zen_Throw", [
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Bursts", localize "STR_COMSPEC_FB_Module_Bursts_Tip"], [1, 50, 3, 0]],
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Zen_Drill", localize "STR_COMSPEC_FB_Zen_Drill_Tip"], false],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_FuseMode", localize "STR_COMSPEC_FB_Zen_FuseMode_Tip"], [
            ["default", "3", "8", "custom"],
            [localize "STR_COMSPEC_FB_Fuse_DefaultZen", localize "STR_COMSPEC_FB_Fuse_3", localize "STR_COMSPEC_FB_Fuse_8", localize "STR_COMSPEC_FB_Fuse_Custom"],
            0]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_FuseCustom", localize "STR_COMSPEC_FB_Module_FuseCustom_Tip"], [0.5, 20, 1.5, 1]],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_Rate", localize "STR_COMSPEC_FB_Set_RateMode_Tip"], [
            [-1, 0, 1, 2, 3],
            [localize "STR_COMSPEC_FB_DrillSound_Setting", localize "STR_COMSPEC_FB_Rate_Normal", localize "STR_COMSPEC_FB_Rate_Fast",
                localize "STR_COMSPEC_FB_Rate_Hyper", localize "STR_COMSPEC_FB_Rate_Random"],
            0]],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_DrillSound", localize "STR_COMSPEC_FB_Module_DrillSound_Tip"], [
            [""] + _keys,
            [localize "STR_COMSPEC_FB_DrillSound_Setting"] + (_keys apply { localize format ["STR_COMSPEC_FB_DrillSound_%1", _x] }),
            0]],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_Theme", localize "STR_COMSPEC_FB_Module_Theme_Tip"], [
            ["standard", "Japan", "Women", "Surprise", "German"],
            [localize "STR_COMSPEC_FB_Theme_Standard", localize "STR_COMSPEC_FB_Japan_Short", localize "STR_COMSPEC_FB_Women_Short",
                localize "STR_COMSPEC_FB_Surprise_Short", localize "STR_COMSPEC_FB_German_Short"],
            0]]
    ], {
        params ["_values", "_pos"];
        _values params ["_n", "_drill", "_fuseMode", "_custom", "_rate", "_snd", ["_theme", "standard", [""]]];
        _n = ((round _n) max 1) min 50;
        private _fuse = switch (_fuseMode) do {
            case "3": { 3 };
            case "8": { 8 };
            case "custom": { (_custom max 0.5) min 20 };
            default { ((missionNamespace getVariable ["comspec_fb_fuse", 1.5]) max 0.5) min 4 };
        };
        private _sfx = ["", format ["_D%1", _fuseMode]] select (_fuseMode in ["3", "8"]);
        private _ammo = if (_drill) then { "COMSPEC_FB_Ammo_Drill" + _sfx } else { format ["COMSPEC_FB_Ammo_%1%2", [_n min 9, 50] select (_n > 9), _sfx] };
        // BANG spécial : munition du thème, nombre et instants des flashs du thème (le curseur est ignoré).
        if (!_drill && {isClass (configFile >> "COMSPEC_FB_Sons" >> ("Theme_" + _theme))}) then {
            _ammo = "COMSPEC_FB_Ammo_" + _theme;
            _n = -1;
        };
        ["module", format ["type=ZEN/lancer pos=%1 N=%2 drill=%3 mèche=%4 s (%5) son=%6 munition=%7", _pos apply { round _x }, _n, _drill,
            _fuse toFixed 1, _fuseMode, [_snd, "réglage"] select (_snd == ""), _ammo]] call comspec_fb_fnc_log;
        if !(isClass (configFile >> "CfgAmmo" >> _ammo)) exitWith {
            ["module", format ["ÉCHEC : munition %1 introuvable", _ammo]] call comspec_fb_fnc_log;
        };
        [_ammo, _pos vectorAdd [0, 0, 0.05], [[0, 1, 0], [0, 0, 1]], [0, 0, 0], objNull,
            sideUnknown, _n, _fuse, -1, _snd, _rate] call comspec_fb_fnc_serverDetonate;
    }, {}, _pos] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;

[_cat, localize "STR_COMSPEC_FB_Zen_Area", {
    params [["_pos", [0, 0, 0], [[]], 3]];
    ["module", format ["type=ZEN/zone pos=%1 : fenêtre ouverte", _pos apply { round _x }]] call comspec_fb_fnc_log;
    [localize "STR_COMSPEC_FB_Zen_Area", [
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Radius", localize "STR_COMSPEC_FB_Module_Radius_Tip"], [1, 50, 10, 0]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Strength", localize "STR_COMSPEC_FB_Module_Strength_Tip"], [0.1, 1, 0.8, 2]],
        ["SLIDER", [localize "STR_COMSPEC_FB_Module_Bursts", localize "STR_COMSPEC_FB_Module_Bursts_Tip"], [1, 50, 1, 0]],
        ["CHECKBOX", [localize "STR_COMSPEC_FB_Module_ShowFx", localize "STR_COMSPEC_FB_Module_ShowFx_Tip"], true],
        ["SLIDER", [localize "STR_COMSPEC_FB_Zen_AreaDelay", localize "STR_COMSPEC_FB_Zen_AreaDelay_Tip"], [0, 20, 0, 1]],
        ["COMBO", [localize "STR_COMSPEC_FB_Module_Rate", localize "STR_COMSPEC_FB_Set_RateMode_Tip"], [
            [-1, 0, 1, 2, 3],
            [localize "STR_COMSPEC_FB_DrillSound_Setting", localize "STR_COMSPEC_FB_Rate_Normal", localize "STR_COMSPEC_FB_Rate_Fast",
                localize "STR_COMSPEC_FB_Rate_Hyper", localize "STR_COMSPEC_FB_Rate_Random"],
            0]]
    ], {
        params ["_values", "_pos"];
        _values params ["_radius", "_strength", "_n", "_fx", "_lead", "_rate"];
        ["module", format ["type=ZEN/zone pos=%1 N=%2 rayon=%3 m force=%4 délai=%5 s", _pos apply { round _x }, round _n, round _radius,
            _strength toFixed 2, _lead toFixed 1]] call comspec_fb_fnc_log;
        [_pos vectorAdd [0, 0, 0.05], _radius, _strength, round _n, _fx, _lead, _rate] call comspec_fb_fnc_serverArea;
    }, {}, _pos] call zen_dialog_fnc_create;
}, _icon] call zen_custom_modules_fnc_register;
