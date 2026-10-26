/*
    App Athena : connexion explicite au compte Athena.
    En haut, une carte d'état en toutes lettres (Non connecté, Connexion en cours, Connecté en tant que X, Hors connexion,
    Erreur avec sa cause et l'action à faire : fn_athenaStatus), le bouton qui règle le problème (RECONNECTER, OUVRIR
    LE CANAL, OBTENIR MON CODE…), puis les étapes de la liaison (module, service, serveur, compte, identité du
    téléphone, canal du poste), dépliées tant que la connexion n'est pas faite.
    Connecté : fiche opérateur. Sinon : moyens de connexion par onglets, CODE (à autoriser sur athena-comspec.eu/lier,
    sans clé ni identifiant de communauté, fn_athenaLink), E-MAIL (mot de passe masqué ou code e-mail), STEAM, PORTAIL.
    La page se redessine seule quand l'état change (fn_statusUpdate), en gardant ce qui est saisi.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _v = { params ["_k", ["_d", ""]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
private _st = [] call comspec_atak_native_fnc_athenaStatus;
private _key = _st get "key";
private _logged = _st get "logged";
private _mode = [] call comspec_atak_native_fnc_athenaMode;
// Message d'action (bouton touché) : effacé une fois connecté ou après 30 s.
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHint", ["", false, 0]];
_hint params ["_hText", ["_hWarn", false], ["_hTime", 0]];
if ((_logged && {!_hWarn}) || {diag_tickTime - _hTime > 30}) then { _hText = ""; };
private _logo = "\z\comspec_atak_native\addons\main\data\logo_atak.paa";
// Une fois connecté, la photo de profil Athena remplace le logo (si le portail en a une).
if (_logged) then { private _av = [player] call comspec_atak_native_fnc_avatarPath; if (_av isNotEqualTo "") then { _logo = _av; }; };

private _rows = [["status", _logo, _st get "color", _st get "title", _st get "text", _st get "action"]];
if (_hText isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#9fd3c0", "#f2c66b"] select _hWarn, _hText], [0.06, 0.075, 0.07, 1]]; };

// Bouton qui règle la situation, sous la carte d'état.
private _btns = switch (true) do {
    case (_key isEqualTo "channel"): { [["OUVRIR LE CANAL", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true], ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]] };
    case (_logged): { [["RECONNECTER", { ["reconnect"] call comspec_atak_native_fnc_athenaAction; }, false, true, "Relance la liaison avec Athena et le poste"], ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]] };
    default {
        switch (_st get "fix") do {
            case "service": { [["ACTIVER LE SERVICE ATHENA", { ["on"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, true]] };
            // Service Athéna pas encore choisi : la réponse se donne ici, plus dans l'ancienne fenêtre d'Overwatch.
            case "choose": { [
                ["OUI, MON UNITÉ UTILISE ATHENA", { ["on"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, true],
                ["NON", { ["off"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]
            ] };
            case "reconnect": { [["RECONNECTER", { ["reconnect"] call comspec_atak_native_fnc_athenaAction; }, true, true, "Relance la liaison avec Athena"]] };
            default { [] };
        };
    };
};
if (_btns isNotEqualTo []) then { _rows pushBack ["buttons", _btns]; };

// Étapes de la liaison : dépliées tant que la connexion n'est pas faite, repliables ensuite.
private _steps = _st get "steps";
private _okN = { (_x select 1) isEqualTo "ok" } count _steps;
private _open = uiNamespace getVariable ["COMSPEC_ATAK_AthenaSteps", !_logged];
uiNamespace setVariable ["COMSPEC_ATAK_AthenaStepsNow", _open];
_rows pushBack ["fold", "ÉTAPES DE LA LIAISON", format ["%1 sur %2 en ordre", _okN, count _steps], _open, {
    uiNamespace setVariable ["COMSPEC_ATAK_AthenaSteps", !(uiNamespace getVariable ["COMSPEC_ATAK_AthenaStepsNow", true])];
    [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
if (_open) then {
    { _rows pushBack (["check", _x select 1, _x select 0, _x select 2, _x select 3]); } forEach _steps;
};

if (_logged) then {
    if (_bridge) then {
        _rows append [
            ["section", "Opérateur", "Fiche Effectifs sur Athena"],
            ["info", "Indicatif", ["comspec_profile_callsign", [player, true] call comspec_atak_native_fnc_unitCallsign] call _v],
            ["info", "Nom", ["comspec_profile_name", name player] call _v],
            ["info", "Unité (ORBAT)", [[player, true] call comspec_atak_native_fnc_unitGroup, "—"] select ((["comspec_profile_unit", ""] call _v) in ["", "—"])],
            ["info", "Fonction", [["comspec_profile_role", "—"] call _v, [missionNamespace getVariable ["comspec_profile_unit", ""]]] call comspec_atak_native_fnc_abbrev],
            ["info", "Grade", ["comspec_profile_grade", "—"] call _v],
            ["info", "Communauté", ["comspec_tenant_name", "—"] call _v],
            ["info", "Identifiant ATAK", ["COMSPEC_AtakId", "—"] call _v],
            ["gap"],
            ["text", "<t size='0.8' color='#a9b8b1'>Qualifications, certificat et batterie : app Profil.</t>"]
        ];
    };
} else {
    if !(_key in ["battleye", "off", "unset"]) then {
        _rows pushBack ["section", "Se connecter", "Une seule fois : les parties suivantes se connectent seules"];
        private _tab = uiNamespace getVariable ["COMSPEC_ATAK_AthenaTab", "account"];
        private _tabBtn = {
            params ["_label", "_tk", "_tip"];
            [_label, compile format ["uiNamespace setVariable ['COMSPEC_ATAK_AthenaTab', '%1']; ['ATHENA'] call comspec_atak_native_fnc_pageRender;", _tk], _tab isEqualTo _tk, _tip]
        };
        _rows pushBack ["segment", "", [
            ["CODE", "account", "Code à autoriser sur le site (recommandé)"] call _tabBtn,
            ["E-MAIL", "email", "E-mail et mot de passe, ou code reçu par e-mail"] call _tabBtn,
            ["STEAM", "steam", "Compte Steam déjà relié à Athena"] call _tabBtn,
            ["PORTAIL", "pair", "Code généré sur la page Appairer du portail"] call _tabBtn
        ]];
        private _draft = uiNamespace getVariable ["COMSPEC_ATAK_AthenaDraft", createHashMap];
        private _card = [0.06, 0.075, 0.07, 1];
        switch (_tab) do {
            case "email": {
                _rows append [
                    ["edit", "email", "E-mail", _draft getOrDefault ["email", profileNamespace getVariable ["COMSPEC_ATAK_Email", ""]]],
                    ["password", "password", "Mot de passe"],
                    ["buttons", [["SE CONNECTER", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["password"] call comspec_atak_native_fnc_athenaAction; }, true]]],
                    ["gap"],
                    ["text", "<t color='#c9d4cf'>Mot de passe oublié ou pas de mot de passe : recevez un code par e-mail.</t>"],
                    ["edit", "otp", "Code reçu par e-mail", _draft getOrDefault ["otp", ""]],
                    ["buttons", [
                        ["RECEVOIR LE CODE", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["otp_ask"] call comspec_atak_native_fnc_athenaAction; }],
                        ["VALIDER LE CODE", { ["otp_ok"] call comspec_atak_native_fnc_athenaAction; }]
                    ]]
                ];
            };
            case "pair": {
                _rows append [
                    ["text", "<t color='#c9d4cf'>Sur le portail Athena, page <t font='COMSPEC_BarlowBold'>Appairer</t>, générez un code (valable 30 min) et saisissez-le ici.</t>"],
                    ["edit", "pair", "Code d'appairage", _draft getOrDefault ["pair", ""]],
                    ["buttons", [["LIER CE JEU", { ["pair"] call comspec_atak_native_fnc_athenaAction; }, true, true, "Relie ce jeu au compte qui a généré le code"]]]
                ];
            };
            case "steam": {
                _rows append [
                    ["text", format ["<t color='#c9d4cf'>Connexion avec le compte Steam déjà relié à votre profil Athena.</t><br/><t size='0.85' color='#a9b8b1'>Steam : %1</t>", getPlayerUID player]],
                    ["buttons", [["CONNEXION STEAM", { ["steam"] call comspec_atak_native_fnc_athenaAction; }, true]]],
                    ["text", "<t size='0.85' color='#a9b8b1'>Steam pas encore relié ? Onglet CODE.</t>"]
                ];
            };
            default {
                // Code à autoriser sur le site, connecté à son compte. Rien d'autre à saisir.
                (uiNamespace getVariable ["COMSPEC_ATAK_AthenaLink", ["", "", 0, "", "", ""]]) params ["_ls", "_lc", "_lu", "_luri", "", "_ld"];
                if (_ls isEqualTo "pending" && {diag_tickTime >= _lu}) then { _ls = "expired"; };
                private _short = (_luri regexReplace ["^https?://", ""]) regexReplace ["/public/", "/"];
                switch (_ls) do {
                    case "pending": {
                        _rows append [
                            ["text", format ["<t color='#c9d4cf'>1. Sur votre ordinateur ou votre téléphone, connecté à votre compte Athena, ouvrez</t><br/><t size='1.2' font='COMSPEC_BarlowBold' color='#8fe39b'>%1</t>", _short], _card],
                            ["text", format ["<t color='#c9d4cf'>2. Saisissez ce code :</t><br/><t size='2.3' font='COMSPEC_BarlowBold' align='center'>%1</t><br/><t size='0.85' align='center' color='#a9b8b1'>En attente de votre accord · valable encore %2 min</t>", _lc, ceil ((_lu - diag_tickTime) / 60)], _card],
                            ["buttons", [
                                ["OUVRIR LA PAGE", { ["open"] call comspec_atak_native_fnc_athenaLink; }, true, true, "Ouvre la page dans votre navigateur, code déjà rempli"],
                                ["NOUVEAU CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }]
                            ]]
                        ];
                    };
                    case "asking": { _rows pushBack ["text", "<t color='#c9d4cf'>Demande d'un code à Athena…</t>", _card]; };
                    case "approved": { _rows pushBack ["text", "<t color='#8fe39b'>Compte autorisé : synchronisation de votre profil…</t>", _card]; };
                    case "expired": {
                        _rows append [
                            ["text", "<t color='#f2c66b'>Ce code a expiré sans être autorisé.</t>", _card],
                            ["buttons", [["NOUVEAU CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                    };
                    case "error": {
                        _rows append [
                            ["text", if ((_ld find "http_4") isEqualTo 0) then {
                                // Athena a répondu mais refuse : cause et action lues dans fn_athenaStatus.
                                format ["<t color='#f2c66b'>%1</t><br/><t color='#c9d4cf'>%2</t>", _st getOrDefault ["text", ""], _st getOrDefault ["action", ""]]
                            } else {
                                format ["<t color='#f2c66b'>Athena n'a pas donné de code (%1). Vérifiez la connexion internet du jeu.</t>", _ld]
                            }, _card],
                            ["buttons", [["RÉESSAYER", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                    };
                    default {
                        _rows append [
                            ["text", "<t color='#c9d4cf'>Un code s'affiche, vous l'autorisez sur le site Athena, et tout se synchronise : profil, communauté, réglages. Aucune clé ni identifiant de communauté à saisir.</t>"],
                            ["buttons", [["OBTENIR MON CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                        // App ouverte sans session ni code : le code est demandé tout de suite.
                        if (_mode isEqualTo "on") then { ["start"] call comspec_atak_native_fnc_athenaLink; };
                    };
                };
                _rows pushBack ["text", "<t size='0.85' color='#a9b8b1'>Pas d'accès au site ? Onglet E-MAIL. Votre Steam est associé au compte à cette occasion : aux parties suivantes, la connexion se fait seule.</t>"];
            };
        };
    };
    // Aide à la connexion (retour de jeu) : les étapes et les blocages courants, repliable.
    private _help = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHelp", false];
    _rows append [
        ["gap"],
        ["fold", "AIDE À LA CONNEXION", "Problèmes courants", _help, { uiNamespace setVariable ["COMSPEC_ATAK_AthenaHelp", !(uiNamespace getVariable ["COMSPEC_ATAK_AthenaHelp", false])]; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]
    ];
    if (_help) then {
        _rows append [
            ["text", "<t color='#f2f6f3' font='COMSPEC_BarlowBold'>1.</t> <t color='#c9d4cf'>Il faut un compte sur <t color='#8fe39b'>athena-comspec.eu</t> (gratuit), membre de votre communauté.</t>"],
            ["text", "<t color='#f2f6f3' font='COMSPEC_BarlowBold'>2.</t> <t color='#c9d4cf'>Onglet CODE : OBTENIR MON CODE. Sur votre PC ou votre téléphone, connecté au site, ouvrez <t color='#8fe39b'>athena-comspec.eu/lier</t> et saisissez le code.</t>"],
            ["text", "<t color='#f2f6f3' font='COMSPEC_BarlowBold'>3.</t> <t color='#c9d4cf'>Autorisez : le téléphone se connecte seul en quelques secondes, et les parties suivantes aussi (Steam associé).</t>"],
            ["text", "<t color='#f2c66b'>Pas de code ?</t> <t color='#c9d4cf'>Vérifiez la connexion internet du jeu et que COMSPEC Link est autorisé (BattlEye, pare-feu). Puis RÉESSAYER.</t>"],
            ["text", "<t color='#f2c66b'>Code expiré ?</t> <t color='#c9d4cf'>Il ne dure que quelques minutes : NOUVEAU CODE.</t>"],
            ["text", "<t color='#f2c66b'>Non lié ?</t> <t color='#c9d4cf'>Le serveur répond mais ce jeu n'est relié à aucun compte : OBTENIR MON CODE, puis autorisez-le sur le site.</t>"],
            ["text", "<t color='#f2c66b'>Toujours bloqué ?</t> <t color='#c9d4cf'>Onglet E-MAIL (mot de passe ou code reçu par e-mail), ou PORTAIL (page Appairer du site). Sinon, prévenez votre staff.</t>"]
        ];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
uiNamespace setVariable ["COMSPEC_ATAK_AthenaSig", (_st get "sig") + [_hText]];
true
