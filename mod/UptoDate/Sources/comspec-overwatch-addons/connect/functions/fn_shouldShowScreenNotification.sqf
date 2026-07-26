/*
    Autorise les bandeaux BIS / toasts ATAK / systemChat Overwatch à l’écran.
    Masqué si : notifications écran OFF, mode discret, milsim UI, réalisme communauté,
    ou mode roleplay (immersion : sons / tablette, pas de spam chat).
*/
(
    hasInterface
    && { missionNamespace getVariable ["comspec_overwatch_screen_notifications", false] }
    && { !(missionNamespace getVariable ["comspec_overwatch_quiet_mode", false]) }
    && { !(missionNamespace getVariable ["comspec_overwatch_milsim_ui", false]) }
    && { !(missionNamespace getVariable ["COMSPEC_TenantRealism", false]) }
    && { !(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false]) }
)
