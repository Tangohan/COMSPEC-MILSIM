/*
    Entrées d'Overwatch connect dans la Configuration ATHENA (menu Échap > ATHENA Configuration, fenêtre de l'ATAK
    natif ou du SSE : fn_athenaConfig, contrat du registre dans son en-tête). Appelé au preInit (XEH_preInit).
    Remplace l'ancienne barre COMSPEC du menu Échap (fn_onInterruptLoad) : pastille « Liaison OK », bouton
    « Dépannage liaison », bouton « Service Athéna : oui / non ».
      - état (comspec_athena_config_status) : couleur de la pastille du bouton et en-tête de la fenêtre ;
      - « Diagnostic et dépannage de la liaison » (« Arrêter le pas à pas » pendant un pas à pas) : la fenêtre et le
        menu Échap se ferment, puis Dépannage liaison s'ouvre (fn_diagIsolateLaunch) ;
      - « Service Athéna » : la fenêtre se ferme, la question Oui / Non s'ouvre par-dessus le menu Échap
        (fn_athenaChoiceShow) ; absente en mode full local imposé par le serveur.
    Ancien bouton « Service Athéna » : avec le téléphone ATAK, son clic fermait le menu Échap depuis le gestionnaire
    d'un contrôle de ce même menu, puis sortait le téléphone sur Réglages à l'image suivante, menu à moitié détruit :
    rien de visible et un menu Échap qui ne répondait plus. Ici, aucune fermeture dans un gestionnaire de clic : la
    fenêtre agit à l'image suivante, et la question s'ouvre dans sa propre fenêtre, sans fermer le menu Échap.
*/

// État : liaison Athena et service Athéna (lignes déjà traduites), entrée mise en avant quand la liaison est coupée.
missionNamespace setVariable ["comspec_athena_config_status", {
    private _T = { _this call comspec_overwatch_connect_fnc_tr };
    private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
    private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    private _st = switch (true) do {
        case (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]): { ["warn", "Liaison : pas à pas en cours"] };
        case (missionNamespace getVariable ["comspec_atak_native_local_only", false]): { ["off", "Liaison : ATAK local (serveur)"] };
        case (_mode isEqualTo "off"): { ["off", "Liaison : ATAK local"] };
        case (_mode isEqualTo ""): { ["warn", "Liaison : en attente du choix du service Athéna"] };
        case (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])): { ["off", "Liaison : Overwatch coupé"] };
        case (_link isEqualTo "linked"): { ["ok", "Liaison : OK"] };
        case (_link in ["connecting", "degraded"]): { ["warn", "Liaison : instable"] };
        default { ["err", "Liaison : hors ligne"] };
    };
    private _ath = switch (_mode) do {
        case "on": { "Service Athéna : oui" };
        case "off": { "Service Athéna : non" };
        default { "Service Athéna : pas encore choisi" };
    };
    [_st select 0, [(_st select 1) call _T, _ath call _T], ["", "ow_diag"] select ((_st select 0) in ["warn", "err"])]
}];

private _r = (missionNamespace getVariable ["comspec_athena_config_entries", []]) select { !((_x select 0) in ["ow_diag", "ow_athena"]) };
_r pushBack ["ow_diag",
    { (["Diagnostic et dépannage de la liaison", "Arrêter le pas à pas"] select (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])) call comspec_overwatch_connect_fnc_tr },
    { (["Contrôle la liaison Athena, la DLL et les versions, puis propose une correction.", "Arrête le dépannage pas à pas et remet la liaison comme avant."] select (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])) call comspec_overwatch_connect_fnc_tr },
    ["\z\comspec_atak_native\addons\main\data\app_debug.paa"],
    { hasInterface && {!isNull player} },
    {
        if (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]) exitWith {
            ["INFO", "Esc", "Pas à pas arrêté depuis la Configuration ATHENA"] call comspec_overwatch_connect_fnc_log;
            [] call comspec_overwatch_connect_fnc_diagIsolateStop;
        };
        ["INFO", "Esc", "Dépannage liaison ouvert depuis la Configuration ATHENA"] call comspec_overwatch_connect_fnc_log;
        [] call comspec_overwatch_connect_fnc_diagIsolateLaunch;
    },
    10, "menu"];
_r pushBack ["ow_athena",
    { "Service Athéna" call comspec_overwatch_connect_fnc_tr },
    { "Votre unité utilise-t-elle Athéna ? Revoir la réponse : Oui, liaison avec le poste ; Non, ATAK local." call comspec_overwatch_connect_fnc_tr },
    ["\z\comspec_atak_native\addons\main\data\app_athena.paa"],
    { hasInterface && {!(missionNamespace getVariable ["comspec_atak_native_local_only", false])} },
    {
        ["INFO", "Esc", "Service Athéna ouvert depuis la Configuration ATHENA"] call comspec_overwatch_connect_fnc_log;
        [true] call comspec_overwatch_connect_fnc_athenaChoiceShow;
    },
    40, "close"];
missionNamespace setVariable ["comspec_athena_config_entries", _r];
