/*
    App Athena : connexion explicite au compte Athena.
    En haut, une carte d'état en toutes lettres (Non connecté, Connexion en cours, Connecté en tant que X, Hors connexion,
    Erreur avec sa cause et l'action à faire : fn_athenaStatus), le bouton qui règle le problème (RECONNECTER, OUVRIR
    LE CANAL, OBTENIR MON CODE…), puis les étapes de la liaison (module, service, serveur, compte, identité du
    téléphone, canal du poste), dépliées tant que la connexion n'est pas faite.
    Connecté : fiche opérateur. Sinon : moyens de connexion par onglets, CODE (à autoriser sur athena-comspec.eu/lier,
    sans clé ni identifiant de communauté, fn_athenaLink), E-MAIL (mot de passe masqué ou code e-mail), STEAM.
    Plus d'onglet PORTAIL (2.9.24) : il demandait un code créé sur le site, à l'envers de la page /lier, qui attend le
    code affiché par le jeu (onglet CODE). RedeemGameLink reste servi pour les anciennes versions et Overwatch connect.
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
private _logo = "\z\comspec_atak_native\addons\main\data\logo_athena.paa";
// Une fois connecté, la photo de profil Athena remplace le logo (si le portail en a une).
if (_logged) then { private _av = [player] call comspec_atak_native_fnc_avatarPath; if (_av isNotEqualTo "") then { _logo = _av; }; };

// Connecté : la carte (photo, indicatif) ouvre l'app Profil.
private _rows = [["status", _logo, _st get "color", _st get "title", _st get "text", _st get "action", [{}, { ["PROFILE"] call comspec_atak_native_fnc_navigate; }] select _logged]];
if (_hText isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#9fd3c0", (COMSPEC_ATAK_Th get "hexWarn")] select _hWarn, _hText], [0.06, 0.075, 0.07, 1]]; };

// Bouton qui règle la situation, sous la carte d'état.
private _btns = switch (true) do {
    case (_key isEqualTo "channel"): { [["OUVRIR LE CANAL", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true], ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]] };
    case (_logged): { [["RECONNECTER", { ["reconnect"] call comspec_atak_native_fnc_athenaAction; }, false, true, "Relance la liaison avec Athena et le poste"], ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]] };
    default {
        switch (_st get "fix") do {
            case "service": { [["ACTIVER LE SERVICE ATHENA", { ["on"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true]] };
            // Service Athéna pas encore choisi : la réponse se donne ici, plus dans l'ancienne fenêtre d'Overwatch.
            case "choose": { [
                ["OUI, MON UNITÉ UTILISE ATHENA", { ["on"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true],
                ["NON", { ["off"] call comspec_atak_native_fnc_athenaChoice; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]
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
    [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
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
            // Fonction de la communauté (site), puis rôle affiché (fn_displayRole, réglé dans l'app Profil).
            ["info", "Fonction", [([player, "sources"] call comspec_atak_native_fnc_displayRole) select 2, [missionNamespace getVariable ["comspec_profile_unit", ""]]] call comspec_atak_native_fnc_abbrev],
            ["info", "Rôle affiché", [[[player] call comspec_atak_native_fnc_displayRole, "—"] select (([player] call comspec_atak_native_fnc_displayRole) isEqualTo ""), [missionNamespace getVariable ["comspec_profile_unit", ""]]] call comspec_atak_native_fnc_abbrev],
            ["info", "Grade", ["comspec_profile_grade", "—"] call _v],
            ["info", "Communauté", ["comspec_tenant_name", "—"] call _v],
            ["info", "Identifiant ATAK", ["COMSPEC_AtakId", "—"] call _v],
            ["gap"],
            ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Rôle affiché, qualifications, certificat et batterie : app Profil.</t>"],
            ["buttons", [["MON PROFIL", { ["PROFILE"] call comspec_atak_native_fnc_navigate; }, true, true, "Ouvrir l'app Profil"]]]
        ];
    };
} else {
    if !(_key in ["battleye", "off", "unset"]) then {
        _rows pushBack ["section", "Se connecter", "Une seule fois : les parties suivantes se connectent seules"];
        private _tab = uiNamespace getVariable ["COMSPEC_ATAK_AthenaTab", "account"];
        if !(_tab in ["account", "email", "steam"]) then { _tab = "account"; };
        private _tabBtn = {
            params ["_label", "_tk", "_tip"];
            [_label, compile format ["uiNamespace setVariable ['COMSPEC_ATAK_AthenaTab', '%1']; ['ATHENA'] call comspec_atak_native_fnc_pageRender;", _tk], _tab isEqualTo _tk, _tip]
        };
        _rows pushBack ["segment", "", [
            ["CODE", "account", "Code à autoriser sur le site (recommandé)"] call _tabBtn,
            ["E-MAIL", "email", "E-mail et mot de passe, ou code reçu par e-mail"] call _tabBtn,
            ["STEAM", "steam", "Compte Steam déjà relié à Athena"] call _tabBtn
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
                    ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Mot de passe oublié ou pas de mot de passe : recevez un code par e-mail.</t>"],
                    ["edit", "otp", "Code reçu par e-mail", _draft getOrDefault ["otp", ""]],
                    ["buttons", [
                        ["RECEVOIR LE CODE", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["otp_ask"] call comspec_atak_native_fnc_athenaAction; }],
                        ["VALIDER LE CODE", { ["otp_ok"] call comspec_atak_native_fnc_athenaAction; }]
                    ]]
                ];
            };
            case "steam": {
                _rows append [
                    ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Connexion avec le compte Steam déjà relié à votre profil Athena.</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Steam : %1</t>", getPlayerUID player]],
                    ["buttons", [["CONNEXION STEAM", { ["steam"] call comspec_atak_native_fnc_athenaAction; }, true]]],
                    ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Steam pas encore relié ? Onglet CODE.</t>"]
                ];
            };
            default {
                // Code à autoriser sur le site, connecté à son compte. Rien d'autre à saisir.
                (uiNamespace getVariable ["COMSPEC_ATAK_AthenaLink", ["", "", 0, "", "", ""]]) params ["_ls", "_lc", "_lu", "_luri", "", "_ld"];
                if (_ls isEqualTo "pending" && {diag_tickTime >= _lu}) then { _ls = "expired"; };
                private _short = [_luri] call comspec_atak_native_fnc_athenaShortUrl;
                switch (_ls) do {
                    case "pending": {
                        _rows append [
                            ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>1. Sur votre ordinateur ou votre téléphone, connecté à votre compte Athena, ouvrez</t><br/><t size='1.2' font='COMSPEC_BarlowBold' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1</t>", _short], _card],
                            ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>2. Saisissez ce code :</t><br/><t size='2.3' font='COMSPEC_BarlowBold' align='center'>%1</t><br/><t size='0.85' align='center' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>En attente de votre accord · valable encore %2 min</t>", _lc, ceil ((_lu - diag_tickTime) / 60)], _card],
                            ["buttons", [
                                ["OUVRIR LA PAGE", { ["open"] call comspec_atak_native_fnc_athenaLink; }, true, true, "Ouvre la page dans votre navigateur, code déjà rempli"],
                                ["NOUVEAU CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }]
                            ]],
                            // QR code fixe de la page /lier (tools/gen_remote_qr.py) : à scanner avec son vrai téléphone,
                            // connecté à Athena, quand le PC n'a pas de navigateur sous la main (plein écran, second écran).
                            ["section", "Sur votre vrai téléphone", "Scannez ce QR code : la page Lier le jeu s'ouvre, saisissez-y le code ci-dessus."],
                            ["image", "\z\comspec_atak_native\addons\main\data\athena_link_qr_ca.paa"],
                            ["text", format ["<t size='0.85' align='center' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", _short]]
                        ];
                    };
                    case "asking": { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Demande d'un code à Athena…</t>", _card]; };
                    case "approved": { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>Compte autorisé : synchronisation de votre profil…</t>", _card]; };
                    case "expired": {
                        _rows append [
                            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Ce code a expiré sans être autorisé.</t>", _card],
                            ["buttons", [["NOUVEAU CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                    };
                    case "error": {
                        _rows append [
                            ["text", if (_ld isEqualTo "maintenance") then {
                                // Athena en maintenance (HTTP 503) : nouvel essai automatique toutes les 60 s, app ouverte.
                                "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "' font='COMSPEC_BarlowBold'>Athena est en maintenance.</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Le site est fermé le temps d'une mise à jour : aucun code ne peut être donné pour l'instant. Gardez l'app ouverte : nouvel essai automatique toutes les minutes.</t>"
                            } else { if ((_ld find "http_4") isEqualTo 0 || {_ld isEqualTo "client_untrusted"}) then {
                                // Athena a répondu mais refuse : cause et action lues dans fn_athenaStatus.
                                format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", _st getOrDefault ["text", ""], _st getOrDefault ["action", ""]]
                            } else {
                                format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Athena n'a pas donné de code (%1). Vérifiez la connexion internet du jeu.</t>", _ld]
                            } }, _card],
                            ["buttons", [["RÉESSAYER", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                    };
                    default {
                        _rows append [
                            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Un code s'affiche, vous l'autorisez sur le site Athena, et tout se synchronise : profil, communauté, réglages. Aucune clé ni identifiant de communauté à saisir.</t>"],
                            ["buttons", [["OBTENIR MON CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }, true]]]
                        ];
                        // App ouverte sans session ni code : le code est demandé tout de suite.
                        if (_mode isEqualTo "on") then { ["start"] call comspec_atak_native_fnc_athenaLink; };
                    };
                };
                _rows pushBack ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Pas d'accès au site ? Onglet E-MAIL. Votre Steam est associé au compte à cette occasion : aux parties suivantes, la connexion se fait seule.</t>"];
            };
        };
    };
    // Aide à la connexion (retour de jeu) : les étapes et les blocages courants, repliable.
    private _help = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHelp", false];
    _rows append [
        ["gap"],
        ["fold", "AIDE À LA CONNEXION", "Problèmes courants", _help, { uiNamespace setVariable ["COMSPEC_ATAK_AthenaHelp", !(uiNamespace getVariable ["COMSPEC_ATAK_AthenaHelp", false])]; [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]
    ];
    if (_help) then {
        _rows append [
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexText") + "' font='COMSPEC_BarlowBold'>1.</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Il faut un compte sur <t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>athena-comspec.eu</t> (gratuit), membre de votre communauté.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexText") + "' font='COMSPEC_BarlowBold'>2.</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Onglet CODE : OBTENIR MON CODE. Sur votre PC ou votre téléphone, connecté au site, ouvrez <t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>athena-comspec.eu/lier</t> et saisissez le code.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexText") + "' font='COMSPEC_BarlowBold'>3.</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Autorisez : le téléphone se connecte seul en quelques secondes, et les parties suivantes aussi (Steam associé).</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Pas de code ?</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Vérifiez la connexion internet du jeu et que COMSPEC Link est autorisé (BattlEye, pare-feu). Puis RÉESSAYER.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Code expiré ?</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Il ne dure que quelques minutes : NOUVEAU CODE.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Non lié ?</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Le serveur répond mais ce jeu n'est relié à aucun compte : OBTENIR MON CODE, puis autorisez-le sur le site.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Pack non reconnu ?</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Athena n'accepte que la version officielle : abonnez-vous à COMSPEC ATAK sur le Steam Workshop (ou mettez-le à jour), retirez les copies locales ou modifiées du mod, puis relancez Arma complètement.</t>"],
            ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Toujours bloqué ?</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Onglet E-MAIL (mot de passe ou code reçu par e-mail). Sinon, prévenez votre staff.</t>"]
        ];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
uiNamespace setVariable ["COMSPEC_ATAK_AthenaSig", (_st get "sig") + [_hText]];
true
