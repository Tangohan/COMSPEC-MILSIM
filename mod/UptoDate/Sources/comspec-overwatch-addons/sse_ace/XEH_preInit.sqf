// Réglage mission : permet de couper la couche SSE/ACE sans retirer le PBO.
[
    "comspec_sse_ace_enabled", "CHECKBOX",
    [
        "Fiche SSE depuis le menu ACE",
        "Ajoute « Renseignement SSE » sur une personne (blessé, détenu, corps). Sans effet si ACE n’est pas chargé."
    ],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

diag_log "[COMSPEC Overwatch][sse_ace] PreInit OK";
