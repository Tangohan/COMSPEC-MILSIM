/*
    App Alertes : bouton PANIQUE (double appui), alertes rapides (contact, fin de contact, appareil abattu)
    et compte rendu SALUTE. Envoi par COMSPEC Link vers Athena (fil « ALERTE TACTIQUE »)
    et, pour la panique, directement aux téléphones alliés du camp (fonctionne sans Athena).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _armed = diag_tickTime < (_s getOrDefault ["panicArmedUntil", -1]);
private _d = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
private _v = { params ["_k", ["_def", ""]]; _d getOrDefault [_k, _def] };
private _hint = _s getOrDefault ["alertsHint", ""];
private _rows = [
    ["section", "Détresse", "Alerte tout le camp et le poste avec votre position"],
    ["buttons", [[["PANIQUE", "CONFIRMER LA PANIQUE"] select _armed, { ["panic"] call comspec_atak_native_fnc_alertsAction; }, true]]],
    ["text", ["<t size='0.8' color='#8a9a93'>Deux appuis pour éviter les fausses alertes. Raccourci à choisir dans les touches CBA, catégorie COMSPEC ATAK.</t>", "<t color='#e5483a' font='RobotoCondensedBold'>Appuyez encore pour envoyer la PANIQUE (5 s).</t>"] select _armed],
    ["section", "Alertes rapides", "Envoyées avec votre grille et l'heure"],
    ["buttons", [
        ["CONTACT (TIC)", { ["quick", "TIC"] call comspec_atak_native_fnc_alertsAction; }],
        ["FIN DE CONTACT", { ["quick", "TIC_CLEAR"] call comspec_atak_native_fnc_alertsAction; }],
        ["APPAREIL ABATTU", { ["quick", "EAGLE_DOWN"] call comspec_atak_native_fnc_alertsAction; }]
    ]],
    ["section", "Compte rendu SALUTE", "Taille, activité, lieu, unité, heure, équipement"],
    ["edit", "sS", "S · Taille (nombre, type)", ["sS"] call _v],
    ["edit", "sA", "A · Activité", ["sA"] call _v],
    ["edit", "sL", "L · Lieu (grille)", ["sL", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef] call _v],
    ["edit", "sU", "U · Unité / tenue", ["sU"] call _v],
    ["edit", "sT", "T · Heure d'observation", ["sT", [daytime, "HH:MM"] call BIS_fnc_timeToString] call _v],
    ["edit", "sE", "E · Équipement", ["sE"] call _v],
    ["buttons", [["ENVOYER LE SALUTE", { ["salute"] call comspec_atak_native_fnc_alertsAction; }, true], ["EFFACER", { ["saluteClear"] call comspec_atak_native_fnc_alertsAction; }]]]
];
// Journal : alertes reçues du camp et envoyées, les plus récentes d'abord.
private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
private _lab = createHashMapFromArray [
    ["PANIC", ["PANIQUE", "#e5483a", [0.9, 0.28, 0.23, 1]]], ["TIC", ["CONTACT", "#f2ab33", [0.95, 0.67, 0.2, 1]]], ["TIC_CLEAR", ["FIN DE CONTACT", "#5cc76b", [0.36, 0.78, 0.42, 1]]],
    ["EAGLE_DOWN", ["APPAREIL ABATTU", "#e5483a", [0.9, 0.28, 0.23, 1]]], ["SALUTE", ["SALUTE", "#4d9ffa", [0.3, 0.62, 0.98, 1]]]
];
_rows pushBack ["section", "Journal", ["Aucune alerte depuis le début de la mission.", format ["%1 alerte(s)", count _log]] select ((count _log) > 0)];
for "_i" from ((count _log) - 1) to (((count _log) - 12) max 0) step -1 do {
    (_log select _i) params ["_type", "_who", "_pos", "_grid", "_text", "_hour"];
    (_lab getOrDefault [_type, [_type, "#c9d4cf", [0.8, 0.83, 0.81, 1]]]) params ["_t", "_c", "_rgb"];
    _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_alerts.paa", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t color='#8a9a93'>%3 · %4 · %5</t>%6", _c, _t, _who, _hour, _grid, ["", format ["<br/><t size='0.8'>%1</t>", _text]] select (_text isNotEqualTo "")],
        [["CARTE", compile format ["['map', %1] call comspec_atak_native_fnc_alertsAction;", _i]]], _rgb];
};
if ((count _log) > 0) then { _rows pushBack ["buttons", [["VIDER LE JOURNAL", { ["clearLog"] call comspec_atak_native_fnc_alertsAction; }]]]; };
if (_hint isNotEqualTo "") then { _rows insert [0, [["text", format ["<t color='#7aa89a'>%1</t>", _hint]]]]; };
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
