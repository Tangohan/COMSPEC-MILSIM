/*
    CBA Settings — isolation debug + safe toggles.
*/
private _cat = ["COMSPEC Debug", "Isolation"];

[
    "COMSPEC_DEBUG_ENABLE_SSE_CORE",
    "CHECKBOX",
    ["Activer le cœur du SSE", "Données et événements du SSE."],
    _cat,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_ACE",
    "CHECKBOX",
    ["Activer les menus ACE du SSE", "Menus ACE d'interaction (inspecter, fouiller, photographier...)."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_INTERACTION", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_DIGITAL",
    "CHECKBOX",
    ["Activer l'exploitation numérique", "Menus ACE d'exploitation des téléphones et ordinateurs."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_DIGITAL", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_BIOMETRICS",
    "CHECKBOX",
    ["Activer la biométrie", "Menus ACE de biométrie et du SEEK."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_BIOMETRICS", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_ZEUS",
    "CHECKBOX",
    ["Activer les modules Zeus du SSE", "Modules Zeus du SSE."],
    _cat,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_MARKERS",
    "CHECKBOX",
    ["Activer les marqueurs", "Création et synchronisation des marqueurs (si présents)."],
    _cat,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_ATAK",
    "CHECKBOX",
    ["Activer le lien avec l'ATAK", "Lien entre le SSE et l'ATAK / Overwatch."],
    _cat,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_COMPAT_ACE",
    "CHECKBOX",
    ["Activer la compatibilité plaques ACE", "Plaques d'identité ACE reliées au SSE."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_ACE", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_COMPAT_BII",
    "CHECKBOX",
    ["Activer la compatibilité BII", "Lien avec le mod BII Identifi."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_BII", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_OVERWATCH_SSE_ACE",
    "CHECKBOX",
    ["Activer la fiche Athena dans ACE", "Ajoute la fiche Athena d'Overwatch au menu ACE du SSE."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE", !_this];
    },
    true
] call comspec_sse_fnc_addSetting;

private _cat2 = ["COMSPEC Debug", "Diagnostics"];

[
    "COMSPEC_DEBUG_TRACE",
    "CHECKBOX",
    ["Tracer les entrées / sorties de fonctions", "Écrit dans le RPT chaque entrée et sortie de fonction suivie."],
    _cat2,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_BLOCK_DANGEROUS_ACE",
    "CHECKBOX",
    ["Bloquer les menus ACE trop larges", "Refuse les actions ACE ajoutées à Thing, All ou AllVehicles avec héritage (risque de lag)."],
    _cat2,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_THROW_ON_RECURSION",
    "CHECKBOX",
    ["Arrêter au-delà de 100 appels imbriqués", "Réservé au développement."],
    _cat2,
    false,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_DEBUG_FORCE_RPT",
    "CHECKBOX",
    ["Tout écrire dans le RPT", "Sinon, hors mode debug, seuls les avertissements et erreurs sont écrits."],
    _cat2,
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "COMSPEC_SAFE_MODE",
    "CHECKBOX",
    ["Mode sans échec", "Seulement le cœur du SSE et les logs, aucun menu ACE."],
    _cat2,
    false,
    0,
    {
        missionNamespace setVariable ["COMSPEC_SAFE_MODE", _this];
    },
    true
] call comspec_sse_fnc_addSetting;

true
