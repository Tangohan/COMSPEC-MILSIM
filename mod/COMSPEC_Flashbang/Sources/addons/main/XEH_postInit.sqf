/*
    COMSPEC Flashbang : initialisation. Aucune boucle permanente : les effets ne tournent que pendant une détonation.
    Une ligne RPT toujours écrite (sans réglage) : elle prouve que la logique du mod s'est initialisée sur ce poste.
*/
if (hasInterface) then {
    // Changement d'unité (réapparition, Zeus « contrôler l'unité », sélection d'une autre unité) : on remet tout à zéro.
    ["unit", { [] call comspec_fb_fnc_playerReset }] call CBA_fnc_addPlayerEventHandler;
    // Modules Zeus (ZEN, facultatif).
    if (isClass (configFile >> "CfgPatches" >> "zen_custom_modules")) then {
        [] call comspec_fb_fnc_zenRegister;
    };
};
// Filet de sécurité du lancer (fn_projectileCreated) : toute grenade du mod créée sur cette machine est vérifiée une
// image plus tard ; coût par projectile = une recherche dans un dictionnaire (munition -> grenade du mod ou non).
comspec_fb_ammoCache = createHashMap;
addMissionEventHandler ["ProjectileCreated", {
    params ["_projectile"];
    private _ammo = typeOf _projectile;
    if (comspec_fb_ammoCache getOrDefaultCall [_ammo, { isNumber (configFile >> "CfgAmmo" >> _ammo >> "comspec_fb_bangs") }, true]) then {
        [comspec_fb_fnc_projectileCreated, [_projectile]] call CBA_fnc_execNextFrame;
    };
}];

private _version = getText (configFile >> "CfgPatches" >> "comspec_flashbang_main" >> "versionStr");
// Le mod doit être chargé sur les clients ; le serveur n'est pas obligatoire. Un serveur qui l'a publie sa version :
// les clients lui confient alors les grenades (engin synchronisé, supprimable par Zeus). Sinon chaque client lanceur
// fait autorité (fn_serverDetonate).
if (isServer) then { missionNamespace setVariable ["comspec_fb_serverVersion", _version, true] };
private _has = { parseNumber (isClass (configFile >> "CfgPatches" >> _this)) };
diag_log text format ["[COMSPEC FB] chargé %1 · CBA ok · ACE=%2 ACRE=%3 TFAR=%4 ZEN=%5 · autres flashbang: tsp=%6/dka=%7 · serveur=%8 interface=%9 multijoueur=%10 · journal de diagnostic=%11",
    _version, "ace_common" call _has, "acre_main" call _has,
    ("tfar_core" call _has) max ("task_force_radio" call _has),
    "zen_custom_modules" call _has, "tsp_flashbang" call _has, "KA_Flashbang" call _has,
    isServer, hasInterface, isMultiplayer, missionNamespace getVariable ["comspec_fb_debug", true]];
// Client de partie multijoueur : une ligne sur le serveur, une fois la variable publique reçue (ou pas).
if (isMultiplayer && {!isServer}) then {
    [{
        private _sv = missionNamespace getVariable ["comspec_fb_serverVersion", ""];
        private _mine = getText (configFile >> "CfgPatches" >> "comspec_flashbang_main" >> "versionStr");
        diag_log text (switch (true) do {
            case (_sv isEqualTo ""): { "[COMSPEC FB] SERVEUR : mod absent (clients seulement) : chaque client lanceur fait autorité, engins locaux, pas de suppression par Zeus, IA du serveur non touchées" };
            case (_sv != _mine): { format ["[COMSPEC FB] SERVEUR : mod présent en version %1, ce client a la version %2 : mettre les deux à jour", _sv, _mine] };
            default { format ["[COMSPEC FB] SERVEUR : mod présent (version %1)", _sv] };
        });
    }, [], 15] call CBA_fnc_waitAndExecute;
};
