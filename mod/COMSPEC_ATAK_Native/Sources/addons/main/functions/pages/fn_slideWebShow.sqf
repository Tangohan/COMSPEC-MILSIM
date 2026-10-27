/*
    Diapositive (JPEG/PNG en base64, fn_slideWebData) dans un navigateur intégré (CT_WEBBROWSER) : app Briefing et
    écrans COMSPEC. Page locale du PBO (data\web\slide.html, LoadFile), puis image passée par ExecJS, par morceaux,
    une fois la page chargée (PageLoaded).
    Avant : toute la page en base64 dans une seule adresse data: (OpenDataAsURL). Captures de TTRD du 26/10 : zone noire
    et barre de défilement horizontale, la page n'était pas affichée. Ce chemin reste un repli si la page locale ne se
    charge pas en 4 s.
    Params : [contrôle navigateur, type MIME ("jpeg" | "png"), base64] ; [contrôle] seul : renvoie l'image à la page.
*/
params [["_wb", controlNull, [controlNull]], ["_mime", "", [""]], ["_b64", "", [""]]];
if (isNull _wb) exitWith { false };
// [contrôle] : la page est chargée, l'image part en morceaux (une très longue chaîne JavaScript passe mal d'un bloc).
if (_b64 isEqualTo "") exitWith {
    (_wb getVariable ["COMSPEC_ATAK_SlideWeb", ["jpeg", ""]]) params ["_m", "_b"];
    if (_b isEqualTo "") exitWith { false };
    _wb setVariable ["COMSPEC_ATAK_SlideWebOk", true];
    _wb ctrlWebBrowserAction ["ExecJS", "if(window.cs){cs.reset();}"];
    for "_o" from 0 to ((count _b) - 1) step 100000 do {
        _wb ctrlWebBrowserAction ["ExecJS", "if(window.cs){cs.add('" + (_b select [_o, 100000]) + "');}"];
    };
    _wb ctrlWebBrowserAction ["ExecJS", "if(window.cs){cs.show('" + _m + "');}"];
    true
};
_wb setVariable ["COMSPEC_ATAK_SlideWeb", [["jpeg", _mime] select (_mime in ["jpeg", "png"]), _b64]];
_wb setVariable ["COMSPEC_ATAK_SlideWebOk", false];
if !(_wb getVariable ["COMSPEC_ATAK_SlideWebEh", false]) then {
    _wb setVariable ["COMSPEC_ATAK_SlideWebEh", true];
    _wb ctrlAddEventHandler ["PageLoaded", { [_this select 0] call comspec_atak_native_fnc_slideWebShow; }];
};
_wb ctrlWebBrowserAction ["LoadFile", "z\comspec_atak_native\addons\main\data\web\slide.html"];
// Repli : page locale absente ou PageLoaded jamais reçu (ancienne version du jeu) : page data: d'un bloc, comme avant.
[{
    params ["_wb"];
    if (isNull _wb || {_wb getVariable ["COMSPEC_ATAK_SlideWebOk", true]}) exitWith {};
    (_wb getVariable ["COMSPEC_ATAK_SlideWeb", ["jpeg", ""]]) params ["_m", "_b"];
    if (_b isEqualTo "") exitWith {};
    ["WARNING", "BRIEFING", "Navigateur intégré : page locale de diapositive non chargée en 4 s, repli sur une page data:"] call comspec_atak_native_fnc_log;
    // Concaténation (pas format) : l'image dépasse de loin la longueur qu'accepte format.
    private _html = "<html><head><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#000}::-webkit-scrollbar{display:none}</style></head><body><img src='data:image/" + _m + ";base64," + _b + "' style='position:absolute;left:0;top:0;width:100%;height:100%;object-fit:contain'></body></html>";
    private _enc = controlNull ctrlWebBrowserAction ["ToBase64", _html];
    _wb ctrlWebBrowserAction ["OpenDataAsURL", if (_enc isEqualType "" && {_enc isNotEqualTo ""}) then { "data:text/html;base64," + _enc } else { "data:text/html;charset=utf-8," + _html }];
}, [_wb], 4] call CBA_fnc_waitAndExecute;
true
