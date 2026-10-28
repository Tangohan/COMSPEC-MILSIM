/*
    Libellé, couleur et explication d'un état de synchro du panneau Tenues Athena (voir arsenalOverlayRefresh).
    Params : [état, onglet communauté ?]. Retour : [libellé, couleur rgba, explication, couleur #hex, ordre de tri].
*/
params [["_state", "", [""]], ["_cloud", false, [false]]];
private _info = switch (true) do {
    case (_cloud && {_state isEqualTo "new"}): { ["Nouvelle", [0.36, 0.78, 0.42, 1], "Partagée depuis votre dernière visite.", 0] };
    case (_cloud && {_state isEqualTo "diff"}): { ["Différente de mon arsenal", [0.93, 0.80, 0.30, 1], "Une tenue du même nom est dans votre arsenal, mais elle n'est pas identique.", 1] };
    case (_cloud && {_state isEqualTo "in"}): { ["Dans mon arsenal", [0.50, 0.66, 0.55, 1], "Déjà dans votre arsenal, identique.", 3] };
    case (_cloud && {_state isEqualTo "mine"}): { ["À moi", [0.56, 0.65, 0.59, 1], "Vous l'avez partagée. Elle n'est pas dans l'arsenal de cet ordinateur.", 2] };
    case (_cloud): { ["", [0.56, 0.65, 0.59, 1], "Pas encore dans votre arsenal : importez-la pour l'avoir hors ligne.", 2] };
    case (_state isEqualTo "new"): { ["Pas sur le site", [0.95, 0.67, 0.20, 1], "Uniquement sur cet ordinateur. Envoyez-la au site pour la partager à l'organisation.", 0] };
    case (_state isEqualTo "gone"): { ["Retirée du site", [0.90, 0.42, 0.36, 1], "Elle était sur le site et n'y est plus. Renvoyez-la si c'est une erreur.", 1] };
    case (_state isEqualTo "local"): { ["Modifiée ici", [0.93, 0.80, 0.30, 1], "Modifiée depuis votre dernier envoi. Envoyez-la pour mettre le site à jour.", 2] };
    case (_state isEqualTo "diff"): { ["Différente du site", [0.93, 0.80, 0.30, 1], "Votre version et celle du site ne sont pas identiques. Envoyez la vôtre ou prenez celle du site.", 2] };
    case (_state isEqualTo "remote"): { ["Modifiée sur le site", [0.42, 0.70, 0.90, 1], "Le site a une version plus récente. Prenez-la pour mettre votre arsenal à jour.", 3] };
    case (_state isEqualTo "synced"): { ["Sur le site", [0.36, 0.78, 0.42, 1], "Identique à la version du site.", 5] };
    case (_state isEqualTo "unknown"): { ["Sur le site", [0.50, 0.66, 0.55, 1], "Présente sur le site. Mettez à jour COMSPEC Link pour savoir si elle a changé.", 5] };
    case (_state isEqualTo "comm"): { ["De la communauté", [0.55, 0.72, 0.66, 1], "Importée depuis les tenues partagées par un autre membre.", 6] };
    default { ["Hors ligne", [0.55, 0.60, 0.57, 1], "Athena est injoignable : état inconnu. Reliez votre compte Athena (ATAK, app Athena).", 7] };
};
_info params ["_label", "_rgba", "_note", "_order"];
private _hex = "#" + ((_rgba select [0, 3]) apply { private _v = round ((_x max 0 min 1) * 255); private _d = "0123456789abcdef"; (_d select [floor (_v / 16), 1]) + (_d select [_v mod 16, 1]) } joinString "");
[_label, _rgba, _note, _hex, _order]
