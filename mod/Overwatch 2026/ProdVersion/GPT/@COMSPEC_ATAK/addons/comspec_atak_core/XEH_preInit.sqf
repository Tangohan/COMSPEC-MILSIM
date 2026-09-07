// Réglages d’addon : toutes les machines (serveur compris), avant l’interface.
[
    "COMSPEC_ATAK_api_url",
    "EDITBOX",
    ["Adresse du poste Athena", "Adresse du poste de votre communauté. Exemple : https://athena.ttrd.fr/public"],
    ["COMSPEC ATAK", "Liaison Athena"],
    "https://athena.ttrd.fr/public",
    0
] call CBA_fnc_addSetting;

[
    "COMSPEC_ATAK_api_key",
    "EDITBOX",
    ["Jeton de la communauté", "Jeton d’accès fourni par l’administrateur de votre communauté. Le téléphone s’en sert pour joindre le poste, sans autre pack."],
    ["COMSPEC ATAK", "Liaison Athena"],
    "",
    0
] call CBA_fnc_addSetting;

[
    "COMSPEC_ATAK_tenant_id",
    "EDITBOX",
    ["Identifiant de la communauté", "Identifiant de votre communauté sur le poste. Laissez vide si l’administrateur ne vous en a pas communiqué."],
    ["COMSPEC ATAK", "Liaison Athena"],
    "",
    0
] call CBA_fnc_addSetting;

[
    "COMSPEC_ATAK_steam_id",
    "EDITBOX",
    ["Identifiant Steam", "Numéro de compte Steam (17 chiffres), visible sur votre profil. S’il est déjà détecté en jeu, vous pouvez le laisser vide ou le remplacer. Il est envoyé au poste lors de la connexion."],
    ["COMSPEC ATAK", "Liaison Athena"],
    "",
    0
] call CBA_fnc_addSetting;

if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_ATAK_apps", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_capabilities", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_state", createHashMap];

missionNamespace setVariable ["COMSPEC_ATAK_P2P_Peers", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_P2P_SeenChat", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_P2P_PFH", -1];

missionNamespace setVariable ["COMSPEC_ATAK_NetworkMode", "NONE"];
missionNamespace setVariable ["COMSPEC_ATAK_BlockAthena", true];
missionNamespace setVariable ["COMSPEC_ATAK_AthenaReady", false];
missionNamespace setVariable ["COMSPEC_ATAK_AthenaPFH", -1];
missionNamespace setVariable ["COMSPEC_ATAK_AthenaUnits", []];
missionNamespace setVariable ["COMSPEC_ATAK_ChatSeenIds", []];
missionNamespace setVariable ["COMSPEC_ATAK_ChatSentFp", []];
missionNamespace setVariable ["COMSPEC_ATAK_ChatBootstrapped", false];
