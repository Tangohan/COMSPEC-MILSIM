/*
    App « Vidéo YouTube » : colle un lien YouTube (watch, youtu.be, embed, shorts), LIRE le montre dans un navigateur
    intégré (CT_WEBBROWSER, lecteur youtube-nocookie.com/embed/ID) sous le champ. Réglage de l'administration
    comspec_atak_native_youtube (décoché par défaut). Repli : STEAM ouvre la vidéo dans la surcouche Steam.
    Vie privée : YouTube / Google n'est contacté que lorsque le joueur touche LIRE ; jamais pendant que la page est ouverte.
    NON VÉRIFIÉ sans le jeu : que le navigateur intégré accepte une adresse externe (allowExternalURL) et lise la vidéo
    (voir PR). Si la page ne se charge pas en 12 s, un message le dit et STEAM reste proposé.
    État : uiNamespace COMSPEC_ATAK_Yt = identifiant en cours de lecture ("" : champ seul).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _th = COMSPEC_ATAK_Th;
private _muted = "<t size='0.75' color='" + (_th get "hexTextMuted") + "'>%1</t>";
private _id = uiNamespace getVariable ["COMSPEC_ATAK_Yt", ""];
if !(_id isEqualType "") then { _id = ""; };
private _rows = [];
if !(["enabled"] call comspec_atak_native_fnc_youtube) exitWith {
    [[
        ["section", "Vidéo YouTube", ""],
        ["text", "<t color='" + (_th get "hexWarn") + "'>" + (("La lecture de vidéos YouTube est désactivée sur ce serveur.") call comspec_atak_native_fnc_tr) + "</t>"]
    ], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

if (_id isEqualTo "") exitWith {
    _rows append [
        ["section", "Vidéo YouTube", "Lien youtube.com, youtu.be, embed ou shorts"],
        ["edit", "ytUrl", "Adresse de la vidéo", ""],
        ["buttons", [["LIRE", {
            private _v = ["id", trim (["ytUrl", ""] call comspec_atak_native_fnc_formValue)] call comspec_atak_native_fnc_youtube;
            if (_v isEqualTo "") exitWith { ["Lien YouTube non reconnu", 3] call comspec_atak_native_fnc_toast; };
            uiNamespace setVariable ["COMSPEC_ATAK_Yt", _v];
            [{ ["YOUTUBE"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }, true]]],
        ["text", format [_muted, ("YouTube (Google) n'est contacté que lorsque vous touchez LIRE. Le lien n'est partagé avec personne.") call comspec_atak_native_fnc_tr]]
    ];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// Lecture : une rangée de boutons, le navigateur occupe le reste de la page.
private _barH = (_l get "font") * 1.55 * 0.9 + (_l get "pad") * 3.2;
[[["buttons", [
    ["ARRÊTER", { uiNamespace setVariable ["COMSPEC_ATAK_Yt", ""]; [{ ["YOUTUBE"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true],
    ["STEAM", { ["steam", uiNamespace getVariable ["COMSPEC_ATAK_Yt", ""]] call comspec_atak_native_fnc_youtube; }]
]]], [0, 0, _bw, _barH]] call comspec_atak_native_fnc_formRender;
private _d = [] call comspec_atak_native_fnc_display;
private _wb = _d ctrlCreate ["COMSPEC_RscWebBrowserExt", -1, _d displayCtrl 88531];
_wb ctrlSetPosition [0, _barH, _bw, (_bh - _barH) max 0.1];
_wb ctrlCommit 0;
private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
_list pushBack _wb;
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
_wb setVariable ["COMSPEC_ATAK_YtLoaded", false];
_wb ctrlAddEventHandler ["PageLoaded", { (_this select 0) setVariable ["COMSPEC_ATAK_YtLoaded", true]; }];
_wb ctrlSetURL (["embed", _id] call comspec_atak_native_fnc_youtube);
[{
    params ["_wb"];
    if (isNull _wb || {_wb getVariable ["COMSPEC_ATAK_YtLoaded", false]}) exitWith {};
    ["Vidéo non chargée : essayez STEAM", 5] call comspec_atak_native_fnc_toast;
}, [_wb], 12] call comspec_atak_native_fnc_waitAndExec;
true
