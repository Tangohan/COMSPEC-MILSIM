/*
    Commandes du tchat (mini wiki : /aide). Une commande en tête de message fixe la priorité ou le type,
    on peut en enchaîner plusieurs : « /urgent /contact 3 BMP au nord ».
    Raccourcis dans le texte : @grille (ma grille), @heure, @cap, @alt.
    Params : [texte saisi]
    Renvoie [priorité, type, texte final, commandes inconnues, liste des préfixes à afficher, aide demandée]
      priorité : "" | ROUTINE | IMPORTANT | URGENT
      type     : "" | CONTACT | SITREP | SALUTE | MEDEVAC | TIC | LACE | INTEL | ORDRE | LOG
*/
params [["_raw", ""]];
private _cmds = createHashMapFromArray [
    ["routine", ["prio", "ROUTINE"]], ["r", ["prio", "ROUTINE"]],
    ["prioritaire", ["prio", "IMPORTANT"]], ["important", ["prio", "IMPORTANT"]], ["p", ["prio", "IMPORTANT"]],
    ["urgent", ["prio", "URGENT"]], ["u", ["prio", "URGENT"]], ["flash", ["prio", "URGENT"]],
    ["contact", ["kind", "CONTACT"]], ["c", ["kind", "CONTACT"]],
    ["sitrep", ["kind", "SITREP"]], ["salute", ["kind", "SALUTE"]], ["medevac", ["kind", "MEDEVAC"]],
    ["tic", ["kind", "TIC"]], ["lace", ["kind", "LACE"]], ["intel", ["kind", "INTEL"]],
    ["ordre", ["kind", "ORDRE"]], ["log", ["kind", "LOG"]],
    ["aide", ["help", ""]], ["wiki", ["help", ""]], ["?", ["help", ""]]
];
private _prio = "";
private _kind = "";
private _unknown = [];
private _help = false;
private _words = (trim _raw) splitString " ";
while { (count _words) > 0 && {((_words select 0) select [0, 1]) isEqualTo "/"} } do {
    private _w = toLower ((_words deleteAt 0) select [1]);
    private _c = _cmds getOrDefault [_w, []];
    switch (_c param [0, ""]) do {
        case "prio": { _prio = _c select 1; };
        case "kind": { _kind = _c select 1; };
        case "help": { _help = true; };
        default { _unknown pushBack ("/" + _w); };
    };
};
private _text = _words joinString " ";
// Raccourcis de position et d'heure
private _subst = [
    ["@grille", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef],
    ["@heure", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    ["@cap", format ["%1°", round getDir player]],
    ["@alt", format ["%1 m", round ((getPosASL player) select 2)]]
];
{ _text = [_text, _x select 0, _x select 1] call CBA_fnc_replace; } forEach _subst;
private _tags = [];
if (_prio isNotEqualTo "") then { _tags pushBack _prio; };
if (_kind isNotEqualTo "") then { _tags pushBack _kind; };
[_prio, _kind, _text, _unknown, _tags, _help]
