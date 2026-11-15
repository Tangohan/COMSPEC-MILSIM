/*
    Dossier SSE (page SSEHUB, tuile « SSE » du lanceur) : la liste des apps SSE présentes, une ligne par app avec son icône,
    un nom clair, une phrase qui dit à quoi elle sert, sa pastille s'il y a quelque chose en attente, et « Ouvrir ».
    Les apps s'ouvrent par leur page habituelle (fn_navigate) : la touche retour ramène ici, « Accueil » au lanceur.
    Table des apps, noms et descriptions : fn_sseHub. Params (fn_pageRender) : [page, rectangle].
*/
params [["_page", "SSEHUB"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _warn = COMSPEC_ATAK_Th get "hexWarn";
private _apps = ["apps"] call comspec_atak_native_fnc_sseHub;
private _rows = [];
if ((count _apps) isEqualTo 0) then {
    _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_sse.paa", "Aucune app SSE sur ce téléphone",
        "Elles sont désinstallées ou retirées par l'encadrement. L'App Store permet de les réinstaller."];
} else {
    {
        _x params ["_app", "_name", "_desc"];
        private _target = _app get "page";
        private _n = [_target] call comspec_atak_native_fnc_appBadge;
        private _mark = ["", format ["  <t size='0.8' color='%1'>● %2</t>", _warn, [str _n, "99+"] select (_n > 99)]] select (_n > 0);
        _rows pushBack ["person", _app get "icon", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.8' color='%3'>%4</t>",
            _name call comspec_atak_native_fnc_tr, _mark, _muted, _desc call comspec_atak_native_fnc_tr], [
            ["Ouvrir", compile format ["[%1] call comspec_atak_native_fnc_navigate;", str _target], true]
        ]];
    } forEach _apps;
};
_rows pushBack ["buttons", [
    ["Accueil", { ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }, false]
]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
