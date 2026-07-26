/*
    Autorise les bandeaux BIS / toasts ATAK / systemChat Overwatch à l’écran.
    Maître : CBA « Afficher les notifications à l’écran » (défaut OFF).
    Mode discret et mode milsim peuvent encore masquer.
*/
(
    missionNamespace getVariable ["comspec_overwatch_screen_notifications", false]
    && {!(missionNamespace getVariable ["comspec_overwatch_quiet_mode", false])}
    && {!(missionNamespace getVariable ["comspec_overwatch_milsim_ui", false])}
)
