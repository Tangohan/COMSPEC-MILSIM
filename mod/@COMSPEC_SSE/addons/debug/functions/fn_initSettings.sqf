/*
    CBA Settings — isolation debug + safe toggles.
*/
private _cat = ["COMSPEC Debug", "Isolation"];

[
    "COMSPEC_DEBUG_ENABLE_SSE_CORE",
    "CHECKBOX",
    ["Enable SSE Core", "Autorise le noyau SSE (données, events)."],
    _cat,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_ACE",
    "CHECKBOX",
    ["Enable SSE ACE", "Menus ACE interaction (inspect, fouille, photo…)."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_INTERACTION", !_this];
    },
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_DIGITAL",
    "CHECKBOX",
    ["Enable SSE Digital", "Menus ACE exploitation numérique."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_DIGITAL", !_this];
    },
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_BIOMETRICS",
    "CHECKBOX",
    ["Enable SSE Biometrics", "Menus ACE biométrie / SEEK."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_BIOMETRICS", !_this];
    },
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_SSE_ZEUS",
    "CHECKBOX",
    ["Enable SSE Zeus", "Modules Zeus SSE."],
    _cat,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_MARKERS",
    "CHECKBOX",
    ["Enable Markers", "Sync / init marqueurs (si présent)."],
    _cat,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_ATAK",
    "CHECKBOX",
    ["Enable ATAK", "Pont ATAK / Overwatch lié au debug."],
    _cat,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_COMPAT_ACE",
    "CHECKBOX",
    ["Enable Compat ACE dogtags", "Passerelle plaques ACE."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_ACE", !_this];
    },
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_COMPAT_BII",
    "CHECKBOX",
    ["Enable Compat BII", "Passerelle BII Identifi."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_BII", !_this];
    },
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_ENABLE_OVERWATCH_SSE_ACE",
    "CHECKBOX",
    ["Enable Overwatch SSE ACE", "Greffe fiche Athena Overwatch."],
    _cat,
    true,
    0,
    {
        missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE", !_this];
    },
    true
] call CBA_fnc_addSetting;

private _cat2 = ["COMSPEC Debug", "Diagnostics"];

[
    "COMSPEC_DEBUG_TRACE",
    "CHECKBOX",
    ["Trace ENTER/EXIT", "Journalise chaque enter/exit instrumenté."],
    _cat2,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_BLOCK_DANGEROUS_ACE",
    "CHECKBOX",
    ["Block dangerous ACE inheritance", "Refuse Thing/All/AllVehicles avec héritage."],
    _cat2,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_THROW_ON_RECURSION",
    "CHECKBOX",
    ["Throw on recursion > 100", "Mode développement uniquement."],
    _cat2,
    false,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_DEBUG_FORCE_RPT",
    "CHECKBOX",
    ["Force all debug lines to RPT", "Sinon WARN+ seulement hors mode debug."],
    _cat2,
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "COMSPEC_SAFE_MODE",
    "CHECKBOX",
    ["Safe Mode", "Core + logger uniquement — aucune interaction ACE globale."],
    _cat2,
    false,
    0,
    {
        missionNamespace setVariable ["COMSPEC_SAFE_MODE", _this];
    },
    true
] call CBA_fnc_addSetting;

true
