/*
    Thème du téléphone : la seule source de couleurs de toutes les apps (système de design 3.1.0,
    docs/ui/atak-design-system.md). Trois thèmes, profil COMSPEC_ATAK_Theme (Réglages > Personnalisation, volet rapide) :
      DARK  sombre (défaut) : écran nuit, texte clair ;
      DAY   jour : surfaces claires et encres foncées pour le plein soleil ;
      NVG   nuit JVN : vert phosphore à faible luminance, aucun blanc, l'accent est toujours vert.
    La couleur d'accent choisie par le joueur (fn_accent) est fondue dedans. Les jetons sont calculés une fois par thème
    et par accent (cache uiNamespace COMSPEC_ATAK_ThemeCache) : l'appel courant coûte une lecture de profil.
    Params :
      []                 -> HashMap des jetons (ci-dessous)
      ["hex", rgba]      -> "#rrggbb" (texte structuré)
      ["set", clé]       -> change le thème (profil enregistré, mise en page et page redessinées)
      ["list"]           -> [[libellé, clé]...] pour un sélecteur
      ["state", clé]     -> rgba d'un état : ok | warn | now | bad | ko | alert | error | info | off | todo
      ["aff", clé]       -> rgba d'une affiliation : friend | hostile | neutral | unknown | civil (ally, enemy… acceptés)
    Jetons rgba :
      bg0 écran et fond des bandeaux ; bg1 fond de page ; surface carte ou ligne ; surfaceAlt ligne alternée ;
      surface2 bouton secondaire, relief ; surfaceHi survol, choisi ; border filet ; chrome barres système (toujours
      sombres) ; onChrome texte des barres ; text, textDim (aides), textMuted (légendes) ; accent (fond), accentInk
      (accent en texte, assombri en thème jour), onAccent (texte sur accent), accentDim, accentHi ; accentSoft,
      accentSoftHi (survol, pressé) et onAccentSoft : bouton tonal teinté d'accent ; ok, warn, alert,
      info, off ; warnDim (fond « imposé ») ; friend, hostile, neutral, unknown, civil ; field (champ de saisie) ;
      veil (voile sombre) ; rail (pastille neutre) ; onState (texte sur un fond d'état) ; scrollbar.
    Jetons hexadécimaux (balises <t color=…>) : hexText, hexTextDim, hexTextMuted, hexAccent (= accentInk), hexOnAccent,
      hexOk, hexWarn, hexAlert, hexInfo, hexOff, hexFriend, hexHostile, hexNeutral, hexUnknown, hexCivil.
    Autres : name (clé du thème), dark (fond sombre : DARK et NVG).
*/
params [["_act", "", ["", []]], ["_arg", ""]];
if (_act isEqualType []) then { _act = ""; };
private _hex = {
    params ["_c"];
    "#" + (((_c select [0, 3]) apply {
        private _v = round (((_x max 0) min 1) * 255);
        ("0123456789abcdef" select [floor (_v / 16), 1]) + ("0123456789abcdef" select [_v mod 16, 1])
    }) joinString "")
};
if (_act isEqualTo "hex") exitWith { [_arg] call _hex };
if (_act isEqualTo "list") exitWith { [["SOMBRE", "DARK"], ["JOUR", "DAY"], ["NUIT JVN", "NVG"]] };
if (_act isEqualTo "set") exitWith {
    private _k = toUpper _arg;
    if !(_k in ["DARK", "DAY", "NVG"]) then { _k = "DARK"; };
    profileNamespace setVariable ["COMSPEC_ATAK_Theme", _k];
    [] call comspec_atak_native_fnc_saveProfile;
    uiNamespace setVariable ["COMSPEC_ATAK_ThemeCache", ["", createHashMap]];
    [] call comspec_atak_native_fnc_theme;
    // Mise en page (barres, fond) puis page redessinée à l'image suivante ; le défilement est gardé par fn_pageRender.
    [{
        if (isNull ([] call comspec_atak_native_fnc_display)) exitWith {};
        [] call comspec_atak_native_fnc_layoutApply;
        [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender;
    }] call comspec_atak_native_fnc_nextFrame;
    true
};

private _theme = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Theme", "DARK"]);
if !(_theme in ["DARK", "DAY", "NVG"]) then { _theme = "DARK"; };
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _key = format ["%1|%2", _theme, _accHex];
(uiNamespace getVariable ["COMSPEC_ATAK_ThemeCache", ["", createHashMap]]) params ["_ck", "_cv"];
private _th = _cv;
if (_ck isNotEqualTo _key) then {
    private _dark = _theme isNotEqualTo "DAY";
    // Accent : celui du joueur ; en nuit JVN, toujours le vert phosphore. En jour, l'accent en texte est assombri
    // (un vert clair sur fond blanc ne se lit pas), le fond d'accent reste tel quel.
    if (_theme isEqualTo "NVG") then { _acc = [0.36, 0.78, 0.42, 1]; };
    private _mul = { params ["_c", "_k", ["_a", 1]]; [(_c select 0) * _k, (_c select 1) * _k, (_c select 2) * _k, _a] };
    private _lift = { params ["_c", "_k"]; [(_c select 0) + (1 - (_c select 0)) * _k, (_c select 1) + (1 - (_c select 1)) * _k, (_c select 2) + (1 - (_c select 2)) * _k, 1] };
    private _accInk = [_acc, [_acc, 0.62] call _mul] select (_theme isEqualTo "DAY");
    _th = switch (_theme) do {
        case "DAY": {
            createHashMapFromArray [
                ["bg0", [0.86, 0.89, 0.87, 1]], ["bg1", [0.91, 0.93, 0.92, 1]], ["surface", [0.985, 0.99, 0.985, 1]], ["surfaceAlt", [0.95, 0.965, 0.955, 1]],
                ["surface2", [0.80, 0.85, 0.82, 1]], ["surfaceHi", [0.71, 0.79, 0.74, 1]], ["border", [0, 0, 0, 0.14]],
                ["text", [0.06, 0.09, 0.07, 1]], ["textDim", [0.24, 0.30, 0.27, 1]], ["textMuted", [0.30, 0.36, 0.33, 1]],
                ["ok", [0.09, 0.42, 0.18, 1]], ["warn", [0.56, 0.33, 0.0, 1]], ["alert", [0.72, 0.14, 0.11, 1]], ["info", [0.12, 0.38, 0.66, 1]], ["off", [0.45, 0.50, 0.47, 1]],
                ["friend", [0.10, 0.40, 0.85, 1]], ["hostile", [0.72, 0.14, 0.11, 1]], ["neutral", [0.09, 0.42, 0.18, 1]], ["unknown", [0.52, 0.39, 0.0, 1]], ["civil", [0.60, 0.20, 0.52, 1]],
                ["field", [1, 1, 1, 1]], ["veil", [0, 0, 0, 0.28]], ["rail", [0.45, 0.52, 0.48, 1]], ["onState", [1, 1, 1, 1]]
            ]
        };
        case "NVG": {
            createHashMapFromArray [
                ["bg0", [0, 0, 0, 1]], ["bg1", [0.010, 0.018, 0.012, 1]], ["surface", [0.025, 0.045, 0.030, 1]], ["surfaceAlt", [0.018, 0.032, 0.022, 1]],
                ["surface2", [0.045, 0.085, 0.055, 1]], ["surfaceHi", [0.06, 0.12, 0.075, 1]], ["border", [0.35, 0.75, 0.42, 0.18]],
                ["text", [0.52, 0.88, 0.58, 1]], ["textDim", [0.38, 0.66, 0.43, 1]], ["textMuted", [0.32, 0.57, 0.37, 1]],
                ["ok", [0.36, 0.78, 0.42, 1]], ["warn", [0.75, 0.78, 0.30, 1]], ["alert", [0.88, 0.30, 0.22, 1]], ["info", [0.40, 0.70, 0.62, 1]], ["off", [0.28, 0.47, 0.33, 1]],
                ["friend", [0.35, 0.62, 0.85, 1]], ["hostile", [0.88, 0.30, 0.22, 1]], ["neutral", [0.36, 0.78, 0.42, 1]], ["unknown", [0.80, 0.72, 0.30, 1]], ["civil", [0.70, 0.40, 0.62, 1]],
                ["field", [0.010, 0.020, 0.012, 1]], ["veil", [0, 0, 0, 0.6]], ["rail", [0.25, 0.45, 0.30, 1]], ["onState", [0, 0.04, 0.01, 1]]
            ]
        };
        default {
            createHashMapFromArray [
                ["bg0", [0.025, 0.030, 0.027, 1]], ["bg1", [0.035, 0.045, 0.040, 1]], ["surface", [0.07, 0.088, 0.080, 1]], ["surfaceAlt", [0.055, 0.070, 0.064, 1]],
                ["surface2", [0.12, 0.145, 0.130, 1]], ["surfaceHi", [0.15, 0.185, 0.165, 1]], ["border", [1, 1, 1, 0.10]],
                ["text", [0.93, 0.96, 0.94, 1]], ["textDim", [0.68, 0.73, 0.71, 1]], ["textMuted", [0.56, 0.62, 0.60, 1]],
                ["ok", [0.36, 0.78, 0.42, 1]], ["warn", [0.95, 0.67, 0.20, 1]], ["alert", [0.90, 0.28, 0.23, 1]], ["info", [0.44, 0.71, 0.91, 1]], ["off", [0.42, 0.47, 0.44, 1]],
                ["friend", [0.30, 0.62, 0.98, 1]], ["hostile", [0.90, 0.28, 0.23, 1]], ["neutral", [0.36, 0.78, 0.42, 1]], ["unknown", [0.95, 0.79, 0.30, 1]], ["civil", [0.85, 0.42, 0.76, 1]],
                ["field", [0.025, 0.030, 0.027, 1]], ["veil", [0, 0, 0, 0.45]], ["rail", [0.55, 0.62, 0.58, 1]], ["onState", [0.03, 0.05, 0.04, 1]]
            ]
        };
    };
    _th set ["name", _theme];
    _th set ["dark", _dark];
    // Barres système (état, app, dock, rail) : toujours sombres, les icônes blanches de fn_statusUpdate y restent lisibles.
    _th set ["chrome", [[0.025, 0.030, 0.027, 1], [0, 0, 0, 1]] select (_theme isEqualTo "NVG")];
    _th set ["onChrome", [[0.90, 0.94, 0.91, 1], [0.52, 0.88, 0.58, 1]] select (_theme isEqualTo "NVG")];
    // Bandeaux posés sur les barres (toast de notification, bulle en bas) : un cran plus clair que la barre.
    _th set ["chromeHi", [[0.05, 0.065, 0.06, 0.97], [0.03, 0.06, 0.035, 0.97]] select (_theme isEqualTo "NVG")];
    _th set ["onChromeDim", [[0.62, 0.70, 0.66, 1], [0.38, 0.66, 0.43, 1]] select (_theme isEqualTo "NVG")];
    _th set ["accent", _acc];
    _th set ["accentInk", _accInk];
    _th set ["onAccent", [[0.03, 0.05, 0.04, 1], [0, 0.06, 0.02, 1]] select (_theme isEqualTo "NVG")];
    _th set ["accentDim", [_acc, 0.30] call _mul];
    _th set ["accentHi", [_acc, 0.45] call _lift];
    // Bouton tonal (4.1.1, déverrouillage) : fond teinté d'accent, sobre, encre d'accent lisible dessus (AA, 4,5).
    // Sombre : accent fondu à 26 % dans la surface, encre éclaircie ; jour : 20 % dans le blanc, encre très assombrie ;
    // nuit JVN : vert à 16 % dans le noir, encre au vert du texte (jamais de blanc).
    private _mix = { params ["_a", "_b", "_k"]; [(_a select 0) + ((_b select 0) - (_a select 0)) * _k, (_a select 1) + ((_b select 1) - (_a select 1)) * _k, (_a select 2) + ((_b select 2) - (_a select 2)) * _k, 1] };
    _th set ["accentSoft", switch (_theme) do {
        case "DAY": { [[1, 1, 1, 1], _acc, 0.20] call _mix };
        case "NVG": { [[0, 0, 0, 1], _acc, 0.16] call _mix };
        default { [_th get "surface", _acc, 0.26] call _mix };
    }];
    _th set ["accentSoftHi", switch (_theme) do {
        case "DAY": { [[1, 1, 1, 1], _acc, 0.34] call _mix };
        case "NVG": { [[0, 0, 0, 1], _acc, 0.26] call _mix };
        default { [_th get "surface", _acc, 0.40] call _mix };
    }];
    _th set ["onAccentSoft", switch (_theme) do {
        case "DAY": { [_acc, 0.36] call _mul };
        case "NVG": { _th get "text" };
        default { [_acc, 0.62] call _lift };
    }];
    _th set ["warnDim", [_th get "warn", 0.58] call _mul];
    _th set ["scrollbar", _acc];
    // Calques posés sur la carte (bandeau du curseur, menu et barre d'outils, cartouches) : panneaux toujours sombres,
    // quel que soit le thème (la carte est claire ou sombre à part, Réglages > Carte) ; leurs encres restent claires.
    private _nvg = _theme isEqualTo "NVG";
    _th set ["mapPanel", [[0.025, 0.030, 0.027, 0.78], [0, 0, 0, 0.82]] select _nvg];
    _th set ["mapActive", [[(_acc select 0) * 0.28, (_acc select 1) * 0.28, (_acc select 2) * 0.28, 0.92], [0.04, 0.10, 0.05, 0.92]] select _nvg];
    _th set ["mapText", _th get "onChrome"];
    _th set ["mapDim", _th get "onChromeDim"];
    _th set ["mapOk", [[0.36, 0.78, 0.42, 1], [0.36, 0.78, 0.42, 1]] select _nvg];
    _th set ["mapWarn", [[0.95, 0.67, 0.20, 1], [0.75, 0.78, 0.30, 1]] select _nvg];
    _th set ["mapAlert", [[0.90, 0.28, 0.23, 1], [0.88, 0.30, 0.22, 1]] select _nvg];
    _th set ["mapInfo", [[0.44, 0.71, 0.91, 1], [0.40, 0.70, 0.62, 1]] select _nvg];
    // Fiche de contact et affiliations sur la carte (fn_contactStyle, fn_contactCard) : fond de fiche et filet, puis une encre
    // par affiliation, toutes lisibles (4,5) sur le fond de fiche dans les trois thèmes (AtakThemeContrastTest). Même jeu
    // pour le thème jour : la fiche est un calque sombre posé sur la carte, pas une surface de page.
    _th set ["mapCard", [[0.035, 0.042, 0.038, 0.97], [0, 0.020, 0.008, 0.97]] select _nvg];
    _th set ["mapCardEdge", [[0.30, 0.36, 0.32, 0.9], [0.20, 0.45, 0.25, 0.9]] select _nvg];
    _th set ["mapFriend", [[0.30, 0.62, 0.98, 1], [0.35, 0.62, 0.85, 1]] select _nvg];
    _th set ["mapHostile", [[0.90, 0.28, 0.23, 1], [0.88, 0.30, 0.22, 1]] select _nvg];
    _th set ["mapNeutral", [[0.36, 0.78, 0.42, 1], [0.36, 0.78, 0.42, 1]] select _nvg];
    _th set ["mapUnknown", [[0.95, 0.79, 0.30, 1], [0.80, 0.72, 0.30, 1]] select _nvg];
    _th set ["mapCivil", [[0.85, 0.42, 0.76, 1], [0.70, 0.40, 0.62, 1]] select _nvg];
    {
        _th set ["hexMap" + _x, [_th get ("map" + _x)] call _hex];
    } forEach ["Text", "Dim", "Ok", "Warn", "Alert", "Info", "Friend", "Hostile", "Neutral", "Unknown", "Civil"];
    {
        _th set ["hex" + (toUpper (_x select [0, 1])) + (_x select [1]), [_th get _x] call _hex];
    } forEach ["text", "textDim", "textMuted", "onAccent", "ok", "warn", "alert", "info", "off", "friend", "hostile", "neutral", "unknown", "civil"];
    _th set ["hexAccent", [_accInk] call _hex];
    uiNamespace setVariable ["COMSPEC_ATAK_ThemeCache", [_key, _th]];
};
// Jetons lisibles de partout (pages, codes compilés plus tard, boucles rangées en uiNamespace) : COMSPEC_ATAK_Th, posé au
// preInit puis à chaque appel. Hors du calcul : le cache vit dans uiNamespace (gardé d'une mission à l'autre), alors que
// missionNamespace repart à zéro. Posé seulement au calcul (3.1.0 à 4.1.1), il restait indéfini dès la deuxième mission de
// la session (menu principal puis partie) : tous les textes bâtis sur COMSPEC_ATAK_Th get … devenaient vides, sans erreur
// (lignes des Comptes rendus sans titre, deuxième ligne du bandeau de la carte, « Wrong color format any » au journal).
missionNamespace setVariable ["COMSPEC_ATAK_Th", _th];
switch (_act) do {
    case "state": {
        switch (toLower _arg) do {
            case "ok": { _th get "ok" };
            case "warn"; case "now"; case "warning": { _th get "warn" };
            case "bad"; case "ko"; case "alert"; case "error"; case "hot": { _th get "alert" };
            case "info"; case "message": { _th get "info" };
            case "off"; case "todo": { _th get "off" };
            default { _th get "text" };
        }
    };
    case "aff": {
        switch (toLower _arg) do {
            case "friend"; case "friendly"; case "ally"; case "blue": { _th get "friend" };
            case "hostile"; case "enemy"; case "red": { _th get "hostile" };
            case "neutral"; case "green": { _th get "neutral" };
            case "civil"; case "civilian": { _th get "civil" };
            default { _th get "unknown" };
        }
    };
    default { _th };
}
