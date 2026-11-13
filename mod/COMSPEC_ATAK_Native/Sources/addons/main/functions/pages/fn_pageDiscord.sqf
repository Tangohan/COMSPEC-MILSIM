/*
    App Discord : écrire dans le salon Discord de l'unité depuis le téléphone (relais réglé par la communauté sur Athena).
    Raccourcis CONTACT / SITREP / SOUTIEN / RTB, message libre de 500 caractères, journal des derniers envois.
    Params (module de pageRender) : [page, rectangle]. Actions : comspec_atak_native_fnc_discordAction.
*/
params [["_page", "DISCORD"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    // Pas de titre : la barre d'app dit déjà « DISCORD ».
    [[["empty", "\z\comspec_atak_native\addons\main\data\app_discord.paa", "Discord indisponible sur ce serveur",
        "Les messages passent par Athena avec COMSPEC Overwatch, qui n'est pas chargé ici. Demandez à l'administrateur du serveur de l'ajouter."]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _kind = _s getOrDefault ["discordKind", "MSG"];
private _labels = createHashMapFromArray [["MSG", "MESSAGE"], ["CONTACT", "CONTACT"], ["SITREP", "SITREP"], ["SUPPORT", "SOUTIEN"], ["RTB", "RTB"]];
private _rows = [
    ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>Les messages arrivent dans le salon Discord choisi par votre communauté sur Athena, signés « COMSPEC ATAK · votre indicatif ». Mentions retirées, un message toutes les 5 s.</t>"],
    ["section", "Envoi rapide", "Un appui : le message part avec votre grille"],
    ["buttons", [
        ["CONTACT", { ["quick", "CONTACT"] call comspec_atak_native_fnc_discordAction; }, true],
        ["SITREP", { ["quick", "SITREP"] call comspec_atak_native_fnc_discordAction; }]
    ]],
    ["buttons", [
        ["BESOIN DE SOUTIEN", { ["quick", "SUPPORT"] call comspec_atak_native_fnc_discordAction; }],
        ["RTB", { ["quick", "RTB"] call comspec_atak_native_fnc_discordAction; }]
    ]],
    ["section", "Message", "500 caractères au plus"],
    ["segment", "Type", ["MSG", "CONTACT", "SITREP", "SUPPORT", "RTB"] apply { [_labels get _x, compile format ["['kind', '%1'] call comspec_atak_native_fnc_discordAction;", _x], _x isEqualTo _kind] }],
    ["memo", "msg", "Texte", _s getOrDefault ["discordDraft", ""], 4],
    ["switch", "Joindre ma grille", _s getOrDefault ["discordGrid", true], { ["grid"] call comspec_atak_native_fnc_discordAction; }, "Grille à 8 chiffres de ma position au moment de l'envoi"],
    ["buttons", [["ENVOYER SUR DISCORD", { ["send"] call comspec_atak_native_fnc_discordAction; }, true]]]
];
private _hist = missionNamespace getVariable ["COMSPEC_ATAK_DiscordLog", []];
private _rev = +_hist;
reverse _rev;
_rows pushBack ["section", "Derniers envois", ["", format ["%1 message(s)", count _hist]] select ((count _hist) > 0)];
if ((count _hist) isEqualTo 0) then {
    _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_discord.paa", "Aucun message envoyé", "Un appui sur CONTACT, SITREP, BESOIN DE SOUTIEN ou RTB, ou un message écrit ci-dessus : il s'affiche ici avec son état."];
};
{
    _x params ["", "_time", "_k", "_text", "_state", ["_err", ""]];
    private _col = createHashMapFromArray [["PUBLIÉ", (COMSPEC_ATAK_Th get "hexOk")], ["REFUSÉ", (COMSPEC_ATAK_Th get "hexAlert")]] getOrDefault [_state, (COMSPEC_ATAK_Th get "hexWarn")];
    _rows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1 · %2</t>  <t size='0.8' color='%3'>%4</t><br/>%5%6",
        _time, _labels getOrDefault [_k, _k], _col, _state, [[_text, (_text select [0, 120]) + "…"] select ((count _text) > 120)] call _esc,
        ["", format ["<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1</t>", [_err] call _esc]] select (_err isNotEqualTo "")]];
} forEach _rev;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
