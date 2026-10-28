/*
    Nettoie et vérifie un lien Google Slides saisi dans les paramètres du briefing.
    [texte saisi] call comspec_atak_native_fnc_briefingUrl -> [valide, lien propre, message, retouches]
    - retire les espaces, retours à la ligne et caractères invisibles (copier-coller depuis Discord, un PDF, un mail) ;
    - accepte un identifiant seul, un lien sans https://, en http://, avec /u/0/ (compte Google), /edit, /pub,
      /embed, /present, ?usp=sharing ou #slide=… : le lien rendu est la forme courte que la DLL sait charger,
      https://docs.google.com/presentation/d/ID/edit ;
    - refuse un lien « Publier sur le Web » (/d/e/2PACX-…/pub) : son identifiant n'est pas celui de la présentation,
      COMSPEC Link ne peut pas en exporter les diapositives (le site Athena le refuse aussi) ;
    - message : l'erreur expliquée (lien Drive, Docs, Sheets, raccourci…) ou ce qui a été reconnu.
    Aucun appel réseau : le chargement réel est fait par TESTER / CHARGER (fn_briefingAction).
*/
params [["_raw", ""]];
if !(_raw isEqualType "") then { _raw = str _raw; };
private _codes = toArray _raw;
// Seuls les caractères ASCII visibles ont leur place dans un lien.
private _keep = _codes select { _x > 32 && {_x < 127} };
private _junk = { !(_x in [9, 10, 13, 32]) && {_x < 33 || {_x > 126}} } count _codes;
private _u = toString _keep;
private _fixes = [];
if (_junk > 0) then { _fixes pushBack format ["%1 caractère(s) invisible(s) retiré(s)", _junk]; };
// Guillemets ou chevrons autour du lien (copie depuis un message).
while { (count _u) > 0 && {(_u select [0, 1]) in ["""", "'", "<", "(", "["]} } do { _u = _u select [1]; };
while { (count _u) > 0 && {(_u select [(count _u) - 1, 1]) in ["""", "'", ">", ")", "]", ".", ","]} } do { _u = _u select [0, (count _u) - 1]; };
if (_u isEqualTo "") exitWith { [false, "", "Collez le lien de la présentation Google Slides (bouton Partager puis Copier le lien).", _fixes] };

private _idChars = toArray "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-";
private _isId = { params ["_t"]; (count _t) >= 10 && {((toArray _t) findIf { !(_x in _idChars) }) < 0} };

// Identifiant seul (la partie entre /d/ et /edit).
if ((count _u) >= 25 && {[_u] call _isId}) exitWith {
    _fixes pushBack "identifiant complété en lien";
    [true, format ["https://docs.google.com/presentation/d/%1/edit", _u], format ["Présentation %1 (partagée par lien).", (_u select [0, 8]) + "…"], _fixes]
};

private _low = toLowerANSI _u;
if ((_low select [0, 7]) isEqualTo "http://") then { _u = "https://" + (_u select [7]); _low = toLowerANSI _u; _fixes pushBack "http:// remplacé par https://"; };
if ((_low select [0, 8]) isNotEqualTo "https://") then {
    if ((_low find "://") >= 0) exitWith {};
    _u = "https://" + _u; _low = toLowerANSI _u; _fixes pushBack "https:// ajouté";
};

private _host = "docs.google.com/presentation/";
private _at = _low find _host;
if (_at < 0) exitWith {
    private _msg = switch (true) do {
        case ((_low find "://") >= 0 && {(_low select [0, 8]) isNotEqualTo "https://"}): { "Seuls les liens web (https://) sont acceptés." };
        case ((_low find "drive.google.com") >= 0): { "Lien Google Drive : ouvrez la présentation dans Google Slides, puis Partager > Copier le lien." };
        case ((_low find "docs.google.com/document/") >= 0): { "Lien Google Docs (texte) : il faut une présentation Google Slides." };
        case ((_low find "docs.google.com/spreadsheets/") >= 0): { "Lien Google Sheets : il faut une présentation Google Slides." };
        case ((_low find "goo.gl") >= 0 || {(_low find "bit.ly") >= 0} || {(_low find "tinyurl") >= 0}): { "Lien raccourci : ouvrez-le dans un navigateur et collez l'adresse complète docs.google.com/presentation/…" };
        case ((_low find "canva.com") >= 0 || {(_low find "onedrive") >= 0} || {(_low find "sharepoint") >= 0} || {(_low find "prezi") >= 0}): { "Seul Google Slides est pris en charge. Exportez la présentation vers Google Slides, ou publiez les diapositives sur Athena." };
        default { "Ce n'est pas un lien Google Slides : il doit contenir docs.google.com/presentation/." };
    };
    [false, "", _msg, _fixes]
};

// Chemin après /presentation/ : [u/N/]d/ID/… (d/e/2PACX-…/pub refusé)
private _rest = _u select [_at + count _host];
private _cut = { params ["_t"]; private _end = count _t; { private _p = _t find _x; if (_p >= 0 && {_p < _end}) then { _end = _p; }; } forEach ["/", "?", "#", "&"]; _t select [0, _end] };
if ((toLowerANSI (_rest select [0, 2])) isEqualTo "u/") then {
    private _p = (_rest select [2]) find "/";
    _rest = if (_p >= 0) then { _rest select [3 + _p] } else { "" };
};
if ((toLowerANSI (_rest select [0, 2])) isNotEqualTo "d/") exitWith {
    [false, "", "Lien Google Slides incomplet : l'identifiant de la présentation (après /d/) manque. Copiez le lien depuis Partager.", _fixes]
};
_rest = _rest select [2];
if ((toLowerANSI (_rest select [0, 2])) isEqualTo "e/") exitWith {
    [false, "", "Lien « Publier sur le Web » (…/d/e/…/pub) : COMSPEC Link ne peut pas le lire. Collez le lien de la présentation elle-même (Partager > Copier le lien, …/presentation/d/…/edit), ouverte à « Tous les utilisateurs disposant du lien ».", _fixes]
};
private _id = [_rest] call _cut;
if !([_id] call _isId) exitWith {
    [false, "", "Identifiant de présentation illisible dans le lien. Recopiez-le depuis Google Slides (Partager > Copier le lien).", _fixes]
};
private _clean = format ["https://docs.google.com/presentation/d/%1/edit", _id];
if ((toLowerANSI _clean) isNotEqualTo (toLowerANSI _u)) then { _fixes pushBack "lien remis en forme simple"; };
[true, _clean, format ["Présentation %1 (partagée par lien).", (_id select [0, 8]) + "…"], _fixes]
