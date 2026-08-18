/*
    Nettoyage à la fermeture du rédacteur de fiche.

    Les pièces retenues sont volontairement conservées : une fermeture
    accidentelle ne doit pas obliger à re-choisir les photographies. Elles sont
    vidées à la prochaine ouverture (fn_intelNoteOnLoad) ou après transmission.
*/
uiNamespace setVariable ["COMSPEC_IntelNote_Display", displayNull];
uiNamespace setVariable ["COMSPEC_IntelNote_Pane", "redaction"];
