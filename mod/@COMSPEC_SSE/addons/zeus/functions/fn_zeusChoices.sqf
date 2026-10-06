/*
    Listes de choix partagées par les formulaires Zeus (mêmes libellés qu'Eden).
    [_kind] call comspec_sse_fnc_zeusChoices → [[valeurs], [libellés]]
    _kind : "profile" | "siteProfile" | "complexity" | "pack" | "entityType"
            | "evidence" | "level" | "device" | "security" | "domexProfile"
            | "packetType" | "quality" | "stage"
*/
params [["_kind", "", [""]]];

switch (toLower _kind) do {
    case "profile": {
        [
            ["INSURGENT", "CIVILIAN", "MILITARY", "COMMANDER", "COURIER", "FINANCIER", "TECHNICIAN", "INTELLIGENCE", "LOGISTICS", "RANDOM"],
            ["Insurgé", "Civil", "Militaire", "Chef / HVT", "Courrier", "Financier", "Technicien / artificier", "Renseignement", "Logistique", "Aléatoire"]
        ]
    };
    case "siteprofile": {
        [
            ["INSURGENT", "MILITARY", "CIVILIAN", "COMMANDER", "LOGISTICS", "FINANCIER", "TECHNICIAN", "RANDOM"],
            ["Cellule insurgée", "Position militaire", "Site civil", "Poste de commandement", "Nœud logistique", "Réseau financier", "Atelier technique / IED", "Aléatoire"]
        ]
    };
    case "complexity": {
        [
            ["LIGHT", "STANDARD", "DETAILED", "HIGH_VALUE"],
            ["Légère — quelques indices", "Standard", "Détaillée", "Haute valeur — dossier riche"]
        ]
    };
    case "pack": {
        [
            ["", "INSURGENT_CELL", "SMUGGLING_NETWORK", "WEAPONS_DEPOT", "COMMAND_POST", "SAFEHOUSE", "IED_WORKSHOP", "FINANCIAL_NODE", "INTELLIGENCE_CELL", "FALCON"],
            ["Aucun — utiliser le brief", "Cellule insurgée", "Réseau de contrebande", "Dépôt d'armes", "Poste de commandement", "Planque", "Atelier IED", "Nœud financier", "Cellule renseignement", "Dataset FALCON (Irak 2012)"]
        ]
    };
    case "entitytype": {
        [
            ["AUTO", "OBJECT", "DOCUMENT", "PHONE", "COMPUTER", "RADIO", "CONTAINER", "WEAPON", "VEHICLE"],
            ["Automatique (selon l'objet)", "Objet divers", "Document", "Téléphone", "Ordinateur", "Radio", "Conteneur / caisse", "Arme", "Véhicule"]
        ]
    };
    case "evidence": {
        [
            ["Land_Laptop_unfolded_F", "Land_MobilePhone_smart_F", "Land_MobilePhone_old_F", "Land_SatellitePhone_F", "Land_PortableLongRangeRadio_F", "Land_File1_F", "Land_FilePhotos_F", "Land_Suitcase_F", "Box_FIA_Wps_F"],
            ["Ordinateur portable", "Smartphone", "Téléphone basique", "Téléphone satellite", "Radio portable", "Dossier papier", "Photos / dossier", "Valise", "Caisse d'armes"]
        ]
    };
    case "level": {
        [
            [0, 1, 2, 3],
            ["0 — Surface", "1 — Tactique", "2 — Terrain", "3 — Vérité complète"]
        ]
    };
    case "device": {
        [
            ["ordinateur", "telephone", "tablette", "radio_numerique", "cle_usb", "gps"],
            ["Ordinateur", "Téléphone", "Tablette", "Radio numérique", "Clé USB", "GPS"]
        ]
    };
    case "security": {
        [["faible", "moyenne", "elevee"], ["Faible", "Moyenne", "Élevée"]]
    };
    case "domexprofile": {
        [
            ["generique", "logistique", "commandement", "personnel", "radio"],
            ["Générique", "Logistique", "Commandement", "Personnel", "Radio / liaisons"]
        ]
    };
    case "packettype": {
        [
            ["", "message", "document", "coordinate", "contact", "frequency"],
            ["(aucun)", "Message", "Document", "Coordonnée / point", "Contact", "Fréquence"]
        ]
    };
    case "quality": {
        [["complet", "fragment", "leurre_possible"], ["Complet", "Fragment (à croiser)", "Peut être un leurre"]]
    };
    case "stage": {
        [
            ["non_identifie", "decouvert", "acces_en_cours", "acces_etabli", "exploite"],
            ["Non identifié", "Découvert", "Accès en cours", "Accès établi", "Exploité"]
        ]
    };
    default { [[], []] };
}
