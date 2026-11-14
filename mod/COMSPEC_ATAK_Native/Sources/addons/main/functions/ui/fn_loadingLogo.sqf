/*
    Écran de chargement d'Arma (RscDisplayLoadMission, RscDisplayLoading : Extended_DisplayLoad de config.cpp) : une des
    trois affiches ATHENA en plein écran (data\loading_1_co.paa à loading_3_co.paa), une différente à chaque chargement
    (TTRD, 14/11 : « une rotation des images »). Rien d'autre : pas de son, pas de vidéo, pas d'animation.
    Les affiches portent le logo, la devise et, pour deux d'entre elles, le QR de https://athena.ttrd.fr
    (tools/gen_loading.py : le QR décoratif des affiches d'origine est remplacé par le vrai).
    Jamais par-dessus l'écran de chargement d'un autre mod (TTRD, 14/11 : « notre mod ne doit pas écraser les autres
    chargements ») : si un autre mod a retouché cet écran (sa classe d'écran, ou un gestionnaire Extended_DisplayLoad),
    rien n'est masqué ni recouvert ; seule la petite marque du coin (fn_loadingBadge, comme avant 4.14.0) est posée.
    Les contrôles sont créés par script (ctrlCreate) : ils passent devant ceux de l'écran du jeu, qui sont masqués, sans patcher ses classes.
    Réglage par joueur « Écran de chargement ATHENA » (Options > Réglages de l'addon > COMSPEC) : décoché, rien n'est touché.
    Appelée par chemin : sur ces écrans les CfgFunctions ne sont pas toujours compilées.
    Textures en 2048 x 1024 (puissances de deux) : l'image d'origine est en 16:9, le contrôle lui rend ce rapport.
    Params : [display, nom de la classe de l'écran]
*/
params [["_display", displayNull, [displayNull]], ["_class", "", [""]]];
if (isNull _display) exitWith {};
// Réglage « Écran de chargement ATHENA » décoché (XEH_preInit, recopié dans le profil) : l'écran du jeu reste tel quel.
if !(profileNamespace getVariable ["COMSPEC_ATAK_LoadingScreen", true]) exitWith {};
if (!isNull (_display getVariable ["COMSPEC_ATAK_LoadingLogo", controlNull])) exitWith {};

// Un autre mod s'occupe-t-il de cet écran ? Réponse gardée pour la session (la config ne change pas en cours de jeu).
private _memo = uiNamespace getVariable ["COMSPEC_ATAK_LoadingForeign", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_LoadingForeign", _memo];
if !(_class in _memo) then {
    // Sont de la maison : le jeu (a3_…, curator, 3den…), CBA (il porte les gestionnaires d'écran) et nos propres mods.
    private _ours = {
        private _n = toLower _this;
        (_n select [0, 3]) in ["a3_", "cba"] || {(_n select [0, 7]) isEqualTo "comspec"} || {(_n select [0, 11]) isEqualTo "curatoronly"} || {_n in ["3den", "a3data"]}
    };
    private _foreign = [];
    if (_class isNotEqualTo "") then {
        // Addons qui définissent ou retouchent la classe de l'écran.
        { if !(_x call _ours) then { _foreign pushBackUnique _x; }; } forEach (configSourceAddonList (configFile >> _class));
        // Gestionnaires Extended_DisplayLoad d'autres mods sur ce même écran.
        {
            private _n = configName _x;
            if !(_n call _ours) then { _foreign pushBackUnique _n; };
        } forEach (configProperties [configFile >> "Extended_DisplayLoad_EventHandlers" >> _class, "true", true]);
    };
    _memo set [_class, _foreign];
    diag_log format ["[COMSPEC ATAK NATIVE][CHARGEMENT] %1 : %2", _class, if (_foreign isEqualTo []) then { "affiche ATHENA plein écran" } else { format ["écran d'un autre mod respecté (%1), marque du coin seulement", _foreign joinString ", "] }];
};
if ((_memo get _class) isNotEqualTo []) exitWith {
    [_display] call compile preprocessFileLineNumbers "\z\comspec_atak_native\addons\main\functions\ui\fn_loadingBadge.sqf";
};

// Affiche suivante : la première est tirée au sort, puis elles se suivent.
private _idx = uiNamespace getVariable ["COMSPEC_ATAK_LoadingIdx", -1];
_idx = if (_idx < 0) then { floor (random 3) } else { (_idx + 1) mod 3 };
uiNamespace setVariable ["COMSPEC_ATAK_LoadingIdx", _idx];

// Les contrôles de l'écran du jeu (image, titre, astuce du bas, barre) sont masqués : il ne reste que l'affiche.
{ _x ctrlShow false; } forEach (allControls _display);
private _bg = _display ctrlCreate ["RscText", -1];
_bg ctrlSetBackgroundColor [0.02, 0.025, 0.022, 1];
_bg ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_bg ctrlCommit 0;
// Image en 16:9 qui couvre tout l'écran : sur un écran plus large ou plus haut, elle déborde et reste centrée (jamais déformée).
private _k = (16 / 9) * pixelW / pixelH;
private _h = safeZoneH;
private _w = _h * _k;
if (_w < safeZoneW) then { _w = safeZoneW; _h = _w / _k; };
private _logo = _display ctrlCreate ["RscPicture", -1];
_logo ctrlSetText format ["\z\comspec_atak_native\addons\main\data\loading_%1_co.paa", _idx + 1]; // i18n:brut (image)
_logo ctrlSetTextColor [1, 1, 1, 1];
_logo ctrlSetPosition [safeZoneX + (safeZoneW - _w) / 2, safeZoneY + (safeZoneH - _h) / 2, _w, _h];
_logo ctrlCommit 0;
_display setVariable ["COMSPEC_ATAK_LoadingLogo", _logo];
[_bg, _logo]
