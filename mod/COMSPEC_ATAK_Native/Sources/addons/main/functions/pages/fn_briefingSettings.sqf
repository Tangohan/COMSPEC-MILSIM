/*
    App Briefing, panneau PARAMÈTRES (engrenage en haut à droite) : lien de la présentation Google Slides,
    source des diapositives et actualisation automatique. Réglages gardés dans le profil du joueur :
      COMSPEC_ATAK_BriefSource     "AUTO" (Google partagé sinon Athena), "ATHENA" ou "GOOGLE"
      COMSPEC_ATAK_BriefGoogleUrl  lien Google Slides enregistré (nettoyé par fn_briefingUrl)
      COMSPEC_ATAK_BriefAuto       relecture des diapositives Athena toutes les 60 s
    L'adresse du portail Athena n'est pas réglable (fixe depuis 1.72.0) : seules les présentations le sont.
    Params : [rectangle de la zone sous les onglets]
*/
params ["_rect"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _src = profileNamespace getVariable ["COMSPEC_ATAK_BriefSource", "AUTO"];
private _saved = profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""];
private _auto = profileNamespace getVariable ["COMSPEC_ATAK_BriefAuto", false];
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_BriefUrlDraft", _saved];
private _test = uiNamespace getVariable ["COMSPEC_ATAK_BriefTest", []];
private _athenaUrl = missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };

private _rows = [
    ["section", "Paramètres du briefing", "Lien de la présentation et source des diapositives"],
    ["segment", "Source des diapositives", [
        ["AUTOMATIQUE", { ["source", "AUTO"] call comspec_atak_native_fnc_briefingCfg; }, _src isEqualTo "AUTO", "Google Slides partagé par le présentateur s'il y en a un, sinon les diapositives Athena"],
        ["ATHENA", { ["source", "ATHENA"] call comspec_atak_native_fnc_briefingCfg; }, _src isEqualTo "ATHENA", "Toujours les diapositives publiées sur Athena"],
        ["GOOGLE SLIDES", { ["source", "GOOGLE"] call comspec_atak_native_fnc_briefingCfg; }, _src isEqualTo "GOOGLE", "La présentation Google Slides enregistrée ci-dessous"]
    ]],
    ["edit", "briefUrl", "Lien Google Slides", _draft],
    ["text", "<t size='0.8' color='#a9b8b1'>Collez le lien Partager (docs.google.com/presentation/d/…), un lien « Publier sur le Web » (…/d/e/…/pub) ou l'identifiant seul. La présentation doit être ouverte à « Tous les utilisateurs disposant du lien ».</t>"]
];
if ((count _test) > 1) then {
    _test params ["_kind", "_msg"];
    private _col = createHashMapFromArray [["ok", "#5cc76b"], ["err", "#e86a5a"], ["warn", "#e0a040"], ["wait", "#7fb6e6"]] getOrDefault [_kind, "#c9d4cf"];
    private _lines = ([[_msg], _msg] select (_msg isEqualType [])) apply { [_x] call _esc };
    _rows pushBack ["text", format ["<t color='%1'>%2</t>", _col, _lines joinString "<br/>"]];
};
_rows append [
    ["buttons", [
        ["TESTER LE LIEN", { ["test"] call comspec_atak_native_fnc_briefingCfg; }, true, _bridge, "Vérifie le lien puis charge la présentation sur votre téléphone seulement"],
        ["ENREGISTRER", { ["save"] call comspec_atak_native_fnc_briefingCfg; }, false, true, "Nettoie et garde le lien dans votre profil"],
        ["EFFACER", { ["clear"] call comspec_atak_native_fnc_briefingCfg; }, false, true, "Vide le champ et oublie le lien enregistré"]
    ]],
    ["buttons", [
        ["DIFFUSER À TOUS", { ["share"] call comspec_atak_native_fnc_briefingCfg; }, false, _bridge, "Affiche cette présentation sur les téléphones et le tableau de briefing de tous les joueurs, comme le tableau de briefing"]
    ]]
];
if !(_bridge) then {
    _rows pushBack ["text", "<t size='0.85' color='#e0a040'>Google Slides et Athena demandent COMSPEC Link : le lien est enregistré mais ne peut pas être chargé sans lui.</t>"];
};
if (_athenaUrl isNotEqualTo "" && {_athenaUrl isNotEqualTo _draft}) then {
    _rows append [
        ["info", "Lien publié sur Athena", [(_athenaUrl select [0, 48]) + (["", "…"] select ((count _athenaUrl) > 48))] call _esc],
        ["buttons", [["REPRENDRE LE LIEN D'ATHENA", { ["athena"] call comspec_atak_native_fnc_briefingCfg; }]]]
    ];
};
_rows append [
    ["gap"],
    ["switch", "Actualisation automatique", _auto, { ["auto"] call comspec_atak_native_fnc_briefingCfg; }, "Relit les diapositives Athena toutes les 60 s tant que l'app Briefing est ouverte"],
    ["gap"],
    ["buttons", [["RETOUR AUX DIAPOSITIVES", { ["close"] call comspec_atak_native_fnc_briefingCfg; }]]]
];
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
