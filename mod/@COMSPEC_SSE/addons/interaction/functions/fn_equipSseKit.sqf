/*
    Ajoute le kit SSE de base à l'inventaire du joueur (si place).
    [] call comspec_sse_fnc_equipSseKit
    Ancien kit « qui apparaît » : remplacé par les caisses de kit réelles (COMSPEC_SSE_Box_Team,
    _Biometrics, _Digital, COMSPEC_SSE_DomexCase ; module « Poser une caisse de kit »). Gardé pour les
    scripts de mission qui l'appellent encore.
*/
private _items = [
    "COMSPEC_SSE_EvidenceBag",
    "COMSPEC_SSE_Gloves",
    "COMSPEC_SSE_Camera",
    "COMSPEC_SSE_FingerprintKit",
    "COMSPEC_SSE_DNKit",
    "COMSPEC_SSE_SEEKII",
    "COMSPEC_SSE_Terminal",
    "COMSPEC_SSE_DocumentBag"
];

private _added = [];
private _skipped = [];
{
    if (player canAdd _x) then {
        player addItem _x;
        _added pushBack _x;
    } else {
        _skipped pushBack _x;
    };
} forEach _items;

private _msg = format ["Kit SSE : %1 élément(s) ajouté(s).", count _added];
if (count _skipped > 0) then {
    _msg = _msg + format [" %1 non placé(s) (inventaire plein).", count _skipped];
};
[_msg, ["SUCCESS", "WARNING"] select (count _skipped > 0)] call comspec_sse_fnc_notify;
["equipSseKit", format ["+%1 / skip %2", count _added, count _skipped]] call comspec_sse_fnc_log;
count _added
