/*
    Apps regroupées et profils de rôle du lanceur. Aucune app n'est fusionnée ni modifiée : ce fichier ne fait que ranger les
    apps déjà visibles (fn_appVisible : une app retirée, interdite ou désinstallée n'apparaît jamais ici).
    Réglage serveur comspec_atak_native_launcher_spaces : 0 au choix du joueur (profil COMSPEC_ATAK_SpacesOn, coupé par
    défaut, lanceur inchangé), 1 activé pour tous, 2 coupé pour tous.
    Profil de rôle (profil COMSPEC_ATAK_RoleProfile) : "all" par défaut (aucun filtre), "auto" (détection, sans résultat
    sûr : aucun filtre), "pilot", "jtac", "medic", "infantry". Rien n'est interdit : « Toutes les apps » montre tout.
    Params : [action, argument]
      "on" -> apps regroupées actives ; "locked" -> imposé par le serveur ; "setOn", bool : choix du joueur
      "role" -> profil appliqué ("all" : aucun filtre) ; "rolePref" -> choix du joueur ; "setRole", clé ; "roleName", clé -> libellé
      "publish" : rend public le rôle choisi (les autres téléphones reconnaissent pilotes et JTAC)
      "detect", unité -> "pilot" | "jtac" | "medic" | "infantry" | ""
      "kindOf", unité -> "pilot" | "jtac" | "" : pilote ou JTAC du camp, pour l'envoi d'une demande FIRES court
      "filter", apps -> apps gardées par le profil
      "spaces" -> [[clé, titre, icône, [[sous-groupe, [apps...]]...], pastille]...], espaces et sous-groupes vides retirés
      "launcherView" -> vrai si le lanceur doit montrer les apps regroupées ; "showAll", bool : tout montrer pour cette session
      "viewBar" -> libellé du bouton en tête du lanceur classique, "" s'il n'y a rien à proposer ; "toggleAll"
*/
params [["_act", ""], ["_arg", ""]];
private _always = ["Settings", "Store", "Alerts", "Admin", "Toc"];
private _roles = createHashMapFromArray [
    ["pilot", ["Pilot", "FiresQuick", "Fires", "Jtac", "Map", "Gps", "Waypoints", "Weather", "Bft", "Chat", "Group", "Tasks", "Briefing"]],
    ["jtac", ["FiresQuick", "Fires", "Jtac", "Drone", "Map", "Gps", "Relief", "Weather", "Bft", "Chat", "Group", "Reco", "Salute", "Tasks", "Reports", "Briefing"]],
    ["medic", ["Medical", "Map", "Gps", "Waypoints", "Bft", "Chat", "Group", "Tasks", "Reports", "Briefing"]],
    ["infantry", ["FiresQuick", "Map", "Gps", "Waypoints", "Bft", "Chat", "Group", "Salute", "Reco", "Photos", "Medical", "Tasks", "Reports", "Briefing"]]
];
private _textRole = {
    params ["_t"];
    switch (true) do {
        case (_t regexMatch ".*(t[^ ]*l[^ ]*pilot|uav|drone).*"): { "" };
        case (_t regexMatch ".*(pilot|helicrew|aircrew|aviator|aviateur|chef de bord).*"): { "pilot" };
        case (_t regexMatch ".*(jtac|jfo|forward air|controleur aerien|fac\(a\)|(^| )fac( |$)|(^| )fist( |$)).*"): { "jtac" };
        case (_t regexMatch ".*(medic|m.{1,2}decin|infirmier|auxiliaire sanitaire|corpsman|combat life saver|(^| )cls( |$)).*"): { "medic" };
        case (_t regexMatch ".*(rifleman|fusilier|fantassin|infantry|grenadier|squad leader|team leader|chef de groupe|chef d.{1,2}quipe|autorifle|marksman).*"): { "infantry" };
        default { "" };
    }
};
// Poste tenu dans l'ORBAT du site (fn_bftOrbat) : seulement s'il a déjà été lu, jamais de lecture lancée d'ici.
private _orbatRole = {
    params ["_u"];
    private _st = ["store"] call comspec_atak_native_fnc_bftOrbat;
    if ((_st get "state") isNotEqualTo "OK") exitWith { "" };
    private _hit = "";
    private _pres = (["presence"] call comspec_atak_native_fnc_bftOrbat) param [1, createHashMap];
    {
        if (_y isEqualTo _u) exitWith {
            private _posts = ((_st get "people") getOrDefault [_x, []]) param [5, []];
            { private _r = [toLower (_x param [1, ""])] call _textRole; if (_r isNotEqualTo "") exitWith { _hit = _r; }; } forEach _posts;
        };
    } forEach _pres;
    _hit
};
private _seat = {
    params ["_u"];
    private _v = objectParent _u;
    !isNull _v && {_v isKindOf "Air"} && {!unitIsUAV _v} && {driver _v isEqualTo _u || {gunner _v isEqualTo _u} || {commander _v isEqualTo _u} || {((assignedVehicleRole _u) param [0, ""]) isEqualTo "Turret"}}
};
private _detect = {
    params ["_u"];
    if (isNull _u) exitWith { "" };
    private _m = _u getVariable ["COMSPEC_ATAK_Role", ""];
    if (_m isEqualType "" && {_m in ["pilot", "jtac", "medic", "infantry"]}) exitWith { _m };
    if ([_u] call _seat) exitWith { "pilot" };
    private _o = [_u] call _orbatRole;
    if (_o isNotEqualTo "") exitWith { _o };
    private _r = [toLower format ["%1 %2", roleDescription _u, typeOf _u]] call _textRole;
    if (_r isNotEqualTo "") exitWith { _r };
    if ((_u getUnitTrait "Medic") || {(_u getVariable ["ace_medical_medicClass", 0]) > 0}) exitWith { "medic" };
    [toLower getText (configOf _u >> "displayName")] call _textRole
};
private _rolePref = {
    private _p = profileNamespace getVariable ["COMSPEC_ATAK_RoleProfile", "all"];
    if (_p isEqualType "" && {_p in ["all", "auto", "pilot", "jtac", "medic", "infantry"]}) then { _p } else { "all" }
};
private _role = {
    private _p = call _rolePref;
    if (uiNamespace getVariable ["COMSPEC_ATAK_LauncherAll", false]) exitWith { "all" };
    if (_p isEqualTo "auto") then { _p = [player] call _detect; };
    ["all", _p] select (_p in _roles)
};
private _on = {
    switch (missionNamespace getVariable ["comspec_atak_native_launcher_spaces", 0]) do {
        case 1: { true };
        case 2: { false };
        default { profileNamespace getVariable ["COMSPEC_ATAK_SpacesOn", false] };
    }
};
private _filter = {
    params ["_apps"];
    private _r = call _role;
    if (_r isEqualTo "all") exitWith { _apps };
    private _keep = (_roles get _r) + _always;
    _apps select { (_x get "id") in _keep }
};
private _redraw = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) in ["LAUNCHER", "SETTINGS", "SPACE"]) then {
        [(uiNamespace getVariable "COMSPEC_ATAK_State") get "activePage"] call comspec_atak_native_fnc_pageRender;
    }; }] call comspec_atak_native_fnc_nextFrame;
};
switch (_act) do {
    case "on": { call _on };
    case "locked": { (missionNamespace getVariable ["comspec_atak_native_launcher_spaces", 0]) isNotEqualTo 0 };
    case "setOn": {
        profileNamespace setVariable ["COMSPEC_ATAK_SpacesOn", _arg isEqualTo true];
        [] call comspec_atak_native_fnc_saveProfile;
        call _redraw;
        true
    };
    case "role": { call _role };
    case "rolePref": { call _rolePref };
    case "setRole": {
        profileNamespace setVariable ["COMSPEC_ATAK_RoleProfile", _arg];
        [] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherAll", false];
        ["publish"] call comspec_atak_native_fnc_appSpaces;
        call _redraw;
        true
    };
    case "roleName": {
        (createHashMapFromArray [["all", "Toutes les apps"], ["auto", "Automatique"], ["pilot", "Pilote"], ["jtac", "JTAC"], ["medic", "Médecin"], ["infantry", "Fantassin"]]) getOrDefault [_arg, _arg]
    };
    case "publish": {
        // Rien de public pour qui n'a rien choisi : aucun trafic de plus.
        private _p = call _rolePref;
        if (_p isEqualTo "auto") then { _p = [player] call _detect; };
        if (_p in ["pilot", "jtac"]) then {
            if ((player getVariable ["COMSPEC_ATAK_RoleOpt", ""]) isNotEqualTo _p) then { player setVariable ["COMSPEC_ATAK_RoleOpt", _p, true]; };
        } else {
            if ((player getVariable ["COMSPEC_ATAK_RoleOpt", ""]) isNotEqualTo "") then { player setVariable ["COMSPEC_ATAK_RoleOpt", nil, true]; };
        };
    };
    case "detect": { [[_arg, player] select !(_arg isEqualType objNull)] call _detect };
    case "kindOf": {
        private _u = _arg;
        if ([_u] call _seat) exitWith { "pilot" };
        private _o = _u getVariable ["COMSPEC_ATAK_RoleOpt", ""];
        if (_o isEqualType "" && {_o in ["pilot", "jtac"]}) exitWith { _o };
        private _d = [_u] call _detect;
        ["", _d] select (_d in ["pilot", "jtac"])
    };
    case "filter": { [_arg] call _filter };
    case "launcherView": { (call _on) && {!(uiNamespace getVariable ["COMSPEC_ATAK_LauncherAll", false])} };
    case "showAll": {
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherAll", _arg isEqualTo true];
        call _redraw;
        true
    };
    case "viewBar": {
        private _all = uiNamespace getVariable ["COMSPEC_ATAK_LauncherAll", false];
        if (_all) exitWith { [["AFFICHER MON PROFIL", "VUE REGROUPÉE"] select (call _on), ""] select (!(call _on) && {(call _rolePref) isEqualTo "all"}) };
        if ((call _role) isEqualTo "all") exitWith { "" };
        "TOUTES LES APPS"
    };
    case "toggleAll": { ["showAll", !(uiNamespace getVariable ["COMSPEC_ATAK_LauncherAll", false])] call comspec_atak_native_fnc_appSpaces };
    case "spaces": {
        private _table = [
            ["NAV", "Navigation et feux", "app_map", [
                ["Navigation", ["Map", "Gps", "Waypoints", "Route", "Bft", "Relief", "Weather", "Distance", "Garmin"]],
                ["Feux", ["Pilot", "FiresQuick", "Fires", "Jtac", "Drone"]]
            ]],
            ["INTEL", "Renseignement", "app_intel", [
                ["Recueil", ["Reco", "Salute", "Photos", "Gallery", "Livecam", "Tracking"]],
                ["Exploitation", ["Intel", "Sse", "DataLink", "Osint", "Wanted"]]
            ]],
            ["SPEC", "Spécialités", "app_medical", [
                ["Santé", ["Medical"]],
                ["Tir et explosifs", ["Sniper", "Breach", "Explo"]],
                ["Guerre électronique", ["Ew", "DroneDetect"]],
                ["Liaison", ["LinkAlly"]]
            ]],
            ["MSG", "Messages", "app_chat", [
                ["Messages", ["Chat", "Alerts", "Numbers", "Discord"]],
                ["Groupes et réseau", ["Group", "InterTeam", "WaveRelay"]]
            ]],
            ["OPS", "Mission et téléphone", "app_tasks", [
                ["Mission", ["Tasks", "Briefing", "Plan", "Zones", "Logistics", "Reports", "Debrief", "Aar", "Academy", "Tenant", "Toc", "YearReview"]],
                ["Téléphone", ["Store", "Settings", "Athena", "Network", "Bluetooth", "Resynch", "Status", "Profile", "Accessories", "ScreenTime", "Debug", "Admin", "Credits"]],
                ["Civil", ["Music", "Youtube", "Calc", "Notes", "Food", "Dating"]]
            ]]
        ];
        private _apps = [(([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible })] call _filter;
        private _byId = createHashMap;
        { _byId set [_x get "id", _x]; } forEach _apps;
        private _listed = [];
        { { _listed append (_x select 1); } forEach (_x select 3); } forEach _table;
        // App d'un autre mod ou nouvelle : rangée d'après sa section, jamais perdue.
        private _secOf = createHashMapFromArray [["Navigation", "NAV"], ["Appui et feux", "NAV"], ["Renseignement", "INTEL"], ["Spécialités", "SPEC"], ["Communication", "MSG"]];
        private _extra = createHashMap;
        { if !((_x get "id") in _listed) then { private _k = _secOf getOrDefault [_x get "section", "OPS"]; _extra set [_k, (_extra getOrDefault [_k, []]) + [_x]]; }; } forEach _apps;
        private _out = [];
        {
            _x params ["_key", "_title", "_icon", "_subs"];
            private _groups = _subs apply { [_x select 0, ((_x select 1) apply { _byId getOrDefault [_x, createHashMap] }) select { (count _x) > 0 }] };
            if (_key in _extra) then { _groups pushBack ["Autres", _extra get _key]; };
            _groups = _groups select { (count (_x select 1)) > 0 };
            if ((count _groups) > 0) then {
                private _badge = 0;
                { { _badge = _badge + ([_x get "page"] call comspec_atak_native_fnc_appBadge); } forEach (_x select 1); } forEach _groups;
                _out pushBack [_key, _title, format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon], _groups, _badge];
            };
        } forEach _table;
        _out
    };
    default { false };
}
