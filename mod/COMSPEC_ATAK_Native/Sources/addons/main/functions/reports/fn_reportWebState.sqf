/*
    Parcours Athena d'un compte rendu ou d'un BDA envoyé, pour la liste de l'app. Partagé par les apps Comptes rendus et BDA.
    Params : [état ("OK", "LOCAL", "EN ATTENTE", "ÉCHEC", "HORS LIGNE"), raison courte de l'échec (texte, facultatif)]
    Retour : [texte à afficher (HTML structuré, vide si rien à dire), renvoi possible (bool)]
    Aucun toast, aucun hint : le résultat se lit dans la liste de l'app.
*/
params [["_web", ""], ["_why", ""]];
private _col = { params ["_k"]; COMSPEC_ATAK_Th get _k };
switch (_web) do {
    case "OK": { ["<t color='" + (["hexOk"] call _col) + "'>Athena ✓</t>", false] };
    case "LOCAL": { ["", false] };
    case "EN ATTENTE": { ["<t color='" + (["hexTextMuted"] call _col) + "'>" + ("Athena : envoi en cours…" call comspec_atak_native_fnc_tr) + "</t>", false] };
    case "ÉCHEC": {
        private _txt = "Athena : non reçu" call comspec_atak_native_fnc_tr;
        if (_why isNotEqualTo "") then { _txt = format ["%1 (%2)", _txt, _why call comspec_atak_native_fnc_tr]; };
        ["<t color='" + (["hexAlert"] call _col) + "'>" + _txt + "</t>", true]
    };
    default { ["<t color='" + (["hexTextMuted"] call _col) + "'>" + ("Athena hors ligne" call comspec_atak_native_fnc_tr) + "</t>", true] };
}
