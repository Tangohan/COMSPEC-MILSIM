/*
    Autorise les bandeaux BIS et toasts ATAK à l’écran.
    Ne concerne pas le chat natif Arma (Overwatch n’y écrit plus).
    Masqué si : notifications écran OFF, mode discret, milsim UI, réalisme communauté,
    ou mode roleplay (immersion : sons / tablette, pas de bandeaux).
*/
(
    hasInterface
    && { missionNamespace getVariable ["comspec_overwatch_screen_notifications", false] }
    && { !(missionNamespace getVariable ["comspec_overwatch_quiet_mode", false]) }
    && { !(missionNamespace getVariable ["comspec_overwatch_milsim_ui", false]) }
    && { !(missionNamespace getVariable ["COMSPEC_TenantRealism", false]) }
    && { !(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false]) }
)
