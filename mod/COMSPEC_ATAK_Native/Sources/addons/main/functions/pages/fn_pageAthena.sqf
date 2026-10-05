/*
    App Athena : en-tête (logo, état de la liaison), fiche opérateur une fois connecté,
    sinon connexion par onglets : Steam, e-mail (mot de passe masqué ou code e-mail), code du portail.
    La page se redessine seule quand l'état change (fn_statusUpdate), en gardant ce qui est saisi.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _v = { params ["_k", ["_d", ""]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { _r } else { str _r } };
private _state = ["comspec_overwatch_auth_state", "—"] call _v;
private _err = ["comspec_overwatch_auth_error"] call _v;
private _link = toLower (["COMSPEC_LinkState", "offline"] call _v);
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
if !(_bridge) then {
    private _cells = (["GetAuthState", []] call comspec_atak_native_fnc_extensionCall) splitString "|";
    _state = _cells param [1, "—"];
    _err = _cells param [4, ""];
    _ready = _state isEqualTo "READY";
    _link = ["offline", "linked"] select _ready;
};
private _logged = _state isEqualTo "READY";
private _pill = switch (true) do {
    case (_logged && {_link isEqualTo "linked"}): { ["CONNECTÉ", "#5cc76b"] };
    case (_link isEqualTo "degraded"): { ["DÉGRADÉ", "#f2ab33"] };
    case (_logged): { ["SESSION OUVERTE", "#f2ab33"] };
    case (_state in ["AUTHENTICATING", "PENDING", "CONNECTING"] || {_link isEqualTo "connecting"}): { ["CONNEXION…", "#f2ab33"] };
    default { ["HORS LIGNE", "#e5483a"] };
};
// Message d'action : effacé une fois connecté ou après 30 s.
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHint", ["", false, 0]];
_hint params ["_hText", ["_hWarn", false], ["_hTime", 0]];
if ((_logged && {!_hWarn}) || {diag_tickTime - _hTime > 30}) then { _hText = ""; };
private _lat = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
private _latText = if (_lat isEqualType 0 && {_lat >= 0}) then { format ["%1 ms", round _lat] } else { "—" };
private _logo = "\z\comspec_atak_native\addons\main\data\logo_atak.paa";
// Une fois connecté, la photo de profil Athena remplace le logo (si le portail en a une).
if (_state isEqualTo "READY") then { private _av = [player] call comspec_atak_native_fnc_avatarPath; if (_av isNotEqualTo "") then { _logo = _av; }; };
private _rows = [];
if (_logged) then {
    // Fiche : photo (ou logo), indicatif, nom, état de la liaison
    private _cs = ["comspec_profile_callsign", [player, true] call comspec_atak_native_fnc_unitCallsign] call _v;
    _rows pushBack ["hero", _logo, format ["<t size='1.4' color='#5cc76b' font='RobotoCondensedBold'>%1</t>  <t size='1.1'>%2</t><br/><t color='%3' font='RobotoCondensedBold'>● %4</t>  <t color='#8a9a93' size='0.85'>%5</t>",
        _cs, ["comspec_profile_name", name player] call _v, _pill select 1, _pill select 0, ["comspec_tenant_name", "Athena"] call _v]];
    if (_hText isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select _hWarn, _hText]]; };
    _rows append [
        ["section", "Liaison", ["Canal ouvert avec le poste de commandement", "Canal du poste fermé : rouvrez-le pour recevoir ordres et messages"] select !_ready],
        ["info", "Canal poste", ["<t color='#e5483a'>fermé</t>", "<t color='#5cc76b'>ouvert</t>"] select _ready],
        ["info", "Latence", _latText],
        ["info", "Détail", [["COMSPEC_LinkDetail"] call _v, "—"] select ((["COMSPEC_LinkDetail"] call _v) isEqualTo "")],
        ["buttons", [
            [["ROUVRIR LE CANAL", "OUVRIR LE CANAL POSTE"] select !_ready, { ["enter"] call comspec_atak_native_fnc_athenaAction; }, !_ready],
            ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]
        ]]
    ];
    if (_bridge) then {
        _rows append [
            ["section", "Opérateur", "Fiche Effectifs sur Athena"],
            ["info", "Unité (ORBAT)", [[player, true] call comspec_atak_native_fnc_unitGroup, "—"] select ((["comspec_profile_unit", ""] call _v) in ["", "—"])],
            ["info", "Fonction", [["comspec_profile_role", "—"] call _v, [missionNamespace getVariable ["comspec_profile_unit", ""]]] call comspec_atak_native_fnc_abbrev],
            ["info", "Grade", ["comspec_profile_grade", "—"] call _v],
            ["info", "Identifiant ATAK", ["COMSPEC_AtakId", "—"] call _v],
            ["info", "Communauté", ["comspec_tenant_name", "—"] call _v],
            ["text", "<t size='0.8' color='#8a9a93'>Qualifications, certificat et batterie : app Profil.</t>"]
        ];
    };
} else {
    _rows pushBack ["hero", _logo, format ["<t size='1.35' font='RobotoCondensedBold'>ATHENA</t>  <t color='%1' font='RobotoCondensedBold'>● %2</t><br/><t color='#8a9a93' size='0.85'>%3</t>",
        _pill select 1, _pill select 0, ["Liaison avec le portail et le poste de commandement", ["COMSPEC_LinkDetail"] call _v] select ((["COMSPEC_LinkDetail"] call _v) isNotEqualTo "")]];
    if (_err isNotEqualTo "" && {_err isNotEqualTo "-"}) then { _rows pushBack ["text", format ["<t color='#e5483a'>%1</t>", _err]]; };
    if (_hText isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select _hWarn, _hText]]; };
    private _tab = uiNamespace getVariable ["COMSPEC_ATAK_AthenaTab", "steam"];
    private _tabBtn = {
        params ["_label", "_key"];
        [_label, compile format ["uiNamespace setVariable ['COMSPEC_ATAK_AthenaTab', '%1']; ['ATHENA'] call comspec_atak_native_fnc_pageRender;", _key], _tab isEqualTo _key]
    };
    _rows pushBack ["buttons", [["STEAM", "steam"] call _tabBtn, ["E-MAIL", "email"] call _tabBtn, ["CODE PORTAIL", "pair"] call _tabBtn]];
    _rows pushBack ["gap"];
    private _draft = uiNamespace getVariable ["COMSPEC_ATAK_AthenaDraft", createHashMap];
    switch (_tab) do {
        case "email": {
            _rows append [
                ["edit", "email", "E-mail", _draft getOrDefault ["email", profileNamespace getVariable ["COMSPEC_ATAK_Email", ""]]],
                ["password", "password", "Mot de passe"],
                ["buttons", [["SE CONNECTER", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["password"] call comspec_atak_native_fnc_athenaAction; }, true]]],
                ["gap"],
                ["text", "<t color='#8a9a93'>Sans mot de passe : recevez un code par e-mail.</t>"],
                ["edit", "otp", "Code reçu par e-mail", _draft getOrDefault ["otp", ""]],
                ["buttons", [
                    ["RECEVOIR LE CODE", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["otp_ask"] call comspec_atak_native_fnc_athenaAction; }],
                    ["VALIDER LE CODE", { ["otp_ok"] call comspec_atak_native_fnc_athenaAction; }]
                ]]
            ];
        };
        case "pair": {
            _rows append [
                ["text", "<t color='#c9d4cf'>Sur le portail Athena, page <t font='RobotoCondensedBold'>Appairer</t>, générez un code (valable 30 min) et saisissez-le ici.</t>"],
                ["edit", "pair", "Code d'appairage", _draft getOrDefault ["pair", ""]],
                ["buttons", [["LIER CE JEU À MON COMPTE", { ["pair"] call comspec_atak_native_fnc_athenaAction; }, true]]]
            ];
        };
        default {
            _rows append [
                ["text", format ["<t color='#c9d4cf'>Connexion avec le compte Steam lié à votre profil Athena.</t><br/><t color='#8a9a93'>Steam : %1</t>", getPlayerUID player]],
                ["buttons", [["CONNEXION STEAM", { ["steam"] call comspec_atak_native_fnc_athenaAction; }, true]]],
                ["text", "<t color='#8a9a93'>Compte pas encore lié ? Onglet CODE PORTAIL.</t>"]
            ];
        };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
uiNamespace setVariable ["COMSPEC_ATAK_AthenaSig", [_state, _link, _ready, _hText, _err]];
true
