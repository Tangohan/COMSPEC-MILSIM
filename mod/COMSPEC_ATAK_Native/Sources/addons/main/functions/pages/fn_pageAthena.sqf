/*
    App Athena : état de la liaison et du compte, connexion (Steam, e-mail, code e-mail, code d'appairage),
    ouverture du canal poste et déconnexion.
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
private _linkLabel = switch (_link) do {
    case "linked": { "<t color='#5cc76b'>CONNECTÉ</t>" };
    case "degraded": { "<t color='#f2ab33'>DÉGRADÉ</t>" };
    case "connecting": { "<t color='#f2ab33'>CONNEXION…</t>" };
    default { "<t color='#e5483a'>HORS LIGNE</t>" };
};
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_AthenaHint", ["", false]];
private _rows = [
    ["title", "Liaison Athena"],
    ["text", format ["%1   <t color='#8a9a93'>%2</t><br/>Session : %3%4<br/>Canal poste : %5%6",
        _linkLabel, ["COMSPEC_LinkDetail"] call _v, _state, ["", format [" <t color='#e5483a'>(%1)</t>", _err]] select (_err isNotEqualTo "" && {_err isNotEqualTo "-"}),
        ["<t color='#e5483a'>fermé</t>", "<t color='#5cc76b'>ouvert</t>"] select _ready,
        ["", format ["<br/>Latence : %1 ms", missionNamespace getVariable ["COMSPEC_LastLatencyMs", "?"]]] select _bridge]]
];
if (_bridge && {_state isEqualTo "READY"}) then {
    _rows pushBack ["text", format ["<t color='#5cc76b'>%1</t> · %2<br/>%3 · %4<br/><t color='#8a9a93'>%5</t>",
        ["comspec_profile_callsign"] call _v, ["comspec_profile_name"] call _v, ["comspec_tenant_name"] call _v, ["comspec_profile_unit"] call _v, ["comspec_profile_role"] call _v]];
};
if ((_hint select 0) isNotEqualTo "") then {
    _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), _hint select 0]];
};
_rows pushBack ["buttons", [
    ["ENTRER / ROUVRIR", { ["enter"] call comspec_atak_native_fnc_athenaAction; }, true],
    ["DÉCONNEXION", { ["logout"] call comspec_atak_native_fnc_athenaAction; }]
]];
_rows append [
    ["title", "Se connecter"],
    ["buttons", [["CONNEXION STEAM", { ["steam"] call comspec_atak_native_fnc_athenaAction; }, true]]],
    ["edit", "email", "E-mail", profileNamespace getVariable ["COMSPEC_ATAK_Email", ""]],
    ["edit", "password", "Mot de passe (affiché en clair : attention au stream)", ""],
    ["buttons", [["SE CONNECTER", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["password"] call comspec_atak_native_fnc_athenaAction; }]]],
    ["edit", "otp", "Code reçu par e-mail", ""],
    ["buttons", [
        ["RECEVOIR LE CODE", { profileNamespace setVariable ["COMSPEC_ATAK_Email", ["email"] call comspec_atak_native_fnc_formValue]; ["otp_ask"] call comspec_atak_native_fnc_athenaAction; }],
        ["VALIDER LE CODE", { ["otp_ok"] call comspec_atak_native_fnc_athenaAction; }]
    ]],
    ["title", "Appairer avec le portail"],
    ["text", "<t color='#8a9a93'>Sur le portail Athena, page Appairer, générez un code (valable 30 min) et collez-le ici.</t>"],
    ["edit", "pair", "Code d'appairage", ""],
    ["buttons", [["LIER", { ["pair"] call comspec_atak_native_fnc_athenaAction; }, true]]]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
