/*
    Accueil de l'app SSE (page SSEHUB, la seule tuile « SSE » du lanceur) : recherche d'une fonction, compteurs (avis en vigueur,
    envois en échec), puis les fonctions par section repliable, une ligne par fonction avec son icône, sa phrase et sa pastille.
    Une fonction s'ouvre par sa page habituelle (fn_navigate) et s'affiche dans la coque de l'app (barre SOMMAIRE + fonctions de
    la section, fn_sseHub « drawStrip ») ; la touche retour ramène ici, « Accueil » au lanceur.
    Rien n'est lu en continu : sections, pastilles et compteurs sont relus à chaque dessin de cet écran.
    Table des fonctions, sections, noms et descriptions : fn_sseHub. Params (fn_pageRender) : [page, rectangle].
*/
params [["_page", "SSEHUB"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _th = [] call comspec_atak_native_fnc_theme;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Arrivée depuis une fonction ou un autre écran : la recherche repart de zéro (un nouveau dessin de la même page la garde).
if ((_st getOrDefault ["pagePrev", ""]) isNotEqualTo "SSEHUB") then { _st set ["sseQuery", ""]; };
private _query = toLower (_st getOrDefault ["sseQuery", ""]);
private _folds = _st getOrDefault ["sseFolds", createHashMap];
private _sections = ["sections"] call comspec_atak_native_fnc_sseHub;
private _rows = [];
// Une ligne de fonction : icône, nom, phrase, pastille à droite.
private _fnRow = {
    params ["_app", "_name", "_desc"];
    private _target = _app get "page";
    private _n = [_target] call comspec_atak_native_fnc_appBadge;
    [
        "row", _app get "icon", _name call comspec_atak_native_fnc_tr, _desc call comspec_atak_native_fnc_tr,
        ([["", str _n] select (_n > 0), "99+"] select (_n > 99)), compile format ["[%1, true, true] call comspec_atak_native_fnc_navigate;", str _target],
        _th get "textDim", "", [[], _th get "warn"] select (_n > 0)
    ]
};
_rows pushBack ["field", "sseQ", "Chercher une fonction", _st getOrDefault ["sseQuery", ""], "Chercher", {
    ["search", ["sseQ"] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_sseHub;
}, true];
// Compteurs : seulement si l'app Avis et vigilance est là ; un clic ouvre l'app concernée.
if ((_sections findIf { ((_x select 2) findIf { ((_x select 0) get "id") isEqualTo "SseAlert" }) >= 0 }) >= 0) then {
    (["counters"] call comspec_atak_native_fnc_sseHub) params ["_active", "_failed"];
    private _go = { ["SSEALERT", true, true] call comspec_atak_native_fnc_navigate; };
    private _chips = [[format [("Avis en vigueur : %1") call comspec_atak_native_fnc_tr, _active], _th get "ok", _go]];
    if (_failed > 0) then { _chips pushBack [format [("Envois en échec : %1") call comspec_atak_native_fnc_tr, _failed], _th get "alert", _go]; };
    _rows pushBack ["chips", _chips];
};
if (_query isNotEqualTo "") then {
    // Recherche : une liste à plat des fonctions dont le nom, la phrase ou la section contient le texte.
    private _found = 0;
    {
        _x params ["", "_secTitle", "_apps"];
        private _secTxt = toLower (_secTitle call comspec_atak_native_fnc_tr);
        {
            _x params ["", "_name", "_desc"];
            private _hay = toLower format ["%1 %2 %3", _name call comspec_atak_native_fnc_tr, _desc call comspec_atak_native_fnc_tr, _secTxt];
            if ((_hay find _query) >= 0) then { _rows pushBack (_x call _fnRow); _found = _found + 1; };
        } forEach _apps;
    } forEach _sections;
    if (_found isEqualTo 0) then {
        _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_sse.paa", "Aucune fonction trouvée",
            "Essayez un autre mot ou affichez tout.", ["Tout afficher", { ["search", ""] call comspec_atak_native_fnc_sseHub; }]];
    };
} else {
    if ((count _sections) isEqualTo 0) then {
        _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_sse.paa", "Aucune app SSE sur ce téléphone",
            "Elles sont désinstallées ou retirées par l'encadrement. L'App Store permet de les réinstaller."];
    };
    {
        _x params ["_sid", "_secTitle", "_apps"];
        private _closed = _folds getOrDefault [_sid, false];
        private _todo = 0;
        { _todo = _todo + ([(_x select 0) get "page"] call comspec_atak_native_fnc_appBadge); } forEach _apps;
        private _sum = [format [("%1 à voir") call comspec_atak_native_fnc_tr, _todo], format [("%1 fonctions") call comspec_atak_native_fnc_tr, count _apps]] select (_todo isEqualTo 0);
        _rows pushBack ["fold", _secTitle, _sum, !_closed, compile format ["['fold', %1] call comspec_atak_native_fnc_sseHub;", str _sid], "sse_" + _sid];
        if (!_closed) then { { _rows pushBack (_x call _fnRow); } forEach _apps; };
    } forEach _sections;
};
_rows pushBack ["buttons", [
    ["Accueil", { ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }, false]
]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
