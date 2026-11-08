/*
    Présentation d'une notification de l'historique (fn_notify), partagée par le centre de notifications et
    l'écran de verrouillage : « Source : texte » donne le titre (sinon le type), le texte sans balises, l'icône et
    l'app à ouvrir au clic (champ « app » posé par l'appelant, sinon déduite du titre ou de mots-clés), la couleur de gravité.
    Params : [entrée de notifHistory]
    Retour : HashMap title, body, icon (chemin .paa), page ("" si aucune app), rgb, hex, sev (INFO, WARNING, ERROR),
             label (libellé de gravité)
*/
params [["_n", createHashMap]];
private _t = toUpper (_n getOrDefault ["type", "INFO"]);
private _sev = switch (_t) do { case "WARNING": { "WARNING" }; case "ERROR"; case "TACTICAL": { "ERROR" }; default { "INFO" }; };
// Couleurs de gravité : jetons du thème (fn_theme), lisibles sur les cartes des trois thèmes.
private _th = [] call comspec_atak_native_fnc_theme;
private _tok = switch (_t) do {
    case "WARNING": { "warn" };
    case "ERROR";
    case "TACTICAL": { "alert" };
    case "MESSAGE": { "info" };
    case "SUCCESS": { "ok" };
    default { "textDim" };
};
private _rgb = _th get _tok;
private _hex = _th get ("hex" + (toUpper (_tok select [0, 1])) + (_tok select [1]));
private _label = createHashMapFromArray [["INFO", "Info"], ["SUCCESS", "Réussi"], ["WARNING", "Attention"], ["ERROR", "Erreur"], ["MESSAGE", "Message"], ["TACTICAL", "Tactique"]] getOrDefault [_t, _t];

private _plain = ((_n getOrDefault ["message", ""]) regexReplace ["<[^>]*>", ""]) regexReplace ["[\r\n\t]+", " "];
{ _plain = [_plain, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&lt;", "<"], ["&gt;", ">"], ["&apos;", "'"], ["&quot;", """"], ["&amp;", "&"]];
private _title = createHashMapFromArray [["WARNING", "Alerte"], ["ERROR", "Erreur"], ["TACTICAL", "Tactique"], ["MESSAGE", "Message"], ["SUCCESS", "Terminé"]] getOrDefault [_t, "Information"];
private _cut = _plain find " : ";
if (_cut > 0 && {_cut <= 28}) then { _title = _plain select [0, _cut]; _plain = _plain select [_cut + 3]; };

// Icône (et app) par mot-clé du titre ou du texte.
private _icons = [
    ["brief", "app_briefing"], ["message", "app_chat"], ["chat", "app_chat"], ["photo", "app_photos"], ["cliché", "app_photos"], ["synchro", "app_resynch"],
    ["détecteur", "app_dronedetect"], ["drone", "app_drone"], ["athena", "app_athena"], ["réseau", "app_network"], ["batterie", "item_battery"], ["ordre", "app_tasks"],
    ["tâche", "app_tasks"], ["medevac", "app_medical"], ["méd", "app_medical"], ["sse", "app_sse"], ["fiche", "app_reco"], ["musique", "app_music"],
    ["géoloc", "app_gps"], ["goniométrie", "app_ew"], ["frappe", "app_fires"], ["tir", "app_fires"], ["ubereats", "app_food"], ["tinder", "app_dating"],
    ["bluetooth", "app_settings"], ["installation", "app_debug"], ["dll", "app_debug"], ["pbo", "app_debug"], ["mise à jour", "app_debug"], ["carte", "app_map"]
];
private _apps = [] call comspec_atak_native_fnc_appList;
private _page = toUpper (_n getOrDefault ["app", ""]);
private _icon = "";
// App nommée par l'appelant, sinon titre égal au nom d'une app (« Drone : … », « Photos : … »).
private _ai = if (_page isNotEqualTo "") then { _apps findIf { (_x get "page") isEqualTo _page } } else { _apps findIf { (toLower (_x get "name")) isEqualTo (toLower _title) } };
if (_ai >= 0) then { _page = (_apps select _ai) get "page"; _icon = (_apps select _ai) get "icon"; };
if (_icon isEqualTo "") then {
    private _lo = toLower (_title + " " + _plain);
    private _k = "";
    { if ((_lo find (_x select 0)) >= 0) exitWith { _k = _x select 1; }; } forEach _icons;
    if (_k isEqualTo "") then { _k = createHashMapFromArray [["WARNING", "app_alerts"], ["ERROR", "app_alerts"], ["TACTICAL", "app_alerts"], ["MESSAGE", "app_chat"], ["SUCCESS", "ui_check"]] getOrDefault [_t, "ui_bell"]; };
    _icon = format ["\z\comspec_atak_native\addons\main\data\%1.paa", _k];
    if (_page isEqualTo "") then {
        private _j = _apps findIf { ((toLower (_x get "icon")) find (toLower _k + ".paa")) >= 0 };
        if (_j >= 0) then { _page = (_apps select _j) get "page"; };
    };
};
createHashMapFromArray [["title", _title], ["body", _plain], ["icon", _icon], ["page", _page], ["rgb", _rgb], ["hex", _hex], ["sev", _sev], ["label", _label]]
