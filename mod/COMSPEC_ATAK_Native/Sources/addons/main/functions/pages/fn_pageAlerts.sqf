/*
    App Alertes : bouton PANIQUE (double appui), alertes rapides (contact, fin de contact, appareil abattu)
    et compte rendu SALUTE. Envoi par Overwatch connect vers Athena (fil « ALERTE TACTIQUE »)
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
if (_hint isNotEqualTo "") then { _rows insert [0, [["text", format ["<t color='#7aa89a'>%1</t>", _hint]]]]; };
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
