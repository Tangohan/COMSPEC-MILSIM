/*
    Pools narratifs enrichis pour la génération SSE.
    [_region] call comspec_sse_fnc_getNarrativePools
    _region: IRAQ | SYRIA | LEVANT | AFRICA_SAHEL | GENERIC | RANDOM
*/
params [
    ["_region", "GENERIC", [""]]
];

_region = toUpper _region;
if (_region == "RANDOM") then {
    _region = ["IRAQ", "SYRIA", "LEVANT", "AFRICA_SAHEL", "GENERIC"] select ((floor random 5));
};

private _firstNames = switch (_region) do {
    case "IRAQ";
    case "SYRIA";
    case "LEVANT": {
        ["Karim","Omar","Hassan","Farid","Mustafa","Youssef","Ali","Samir","Rami","Nabil","Tarek","Walid","Adnan","Bilal","Faisal","Mahmoud","Ibrahim","Khaled","Ziad","Bassam","Ghassan","Marwan","Sami","Amine","Hicham","Yasin","Anas","Othman","Jamil","Rafik"]
    };
    case "AFRICA_SAHEL": {
        ["Amadou","Ibrahim","Moussa","Oumar","Abdoulaye","Boubacar","Seydou","Mamadu","Issa","Bakary","Cheick","Modibo","Youssouf","Souleymane","Hamza","Idrissa","Aboubacar","Mahamadou","Sidi","Lassana"]
    };
    default {
        ["Karim","Omar","Hassan","Farid","Mustafa","Youssef","Ali","Samir","Rami","Nabil","John","Viktor","Sergei","Andrei","Pavel","Ivan","Dmitri","Alex","Mark","Daniel"]
    };
};

private _lastNames = switch (_region) do {
    case "IRAQ";
    case "SYRIA";
    case "LEVANT": {
        ["Haddad","Mansour","Khoury","Nasser","Saleh","Farouk","Abbas","Rahman","Qasim","Hussein","Darwish","Amari","Saad","Zidan","Khalil","Alami","Badr","Hamdan","Jaber","Kassab","Najjar","Sabbagh","Tawil","Younes","Zahra","Barakat","Fakhoury","Ghazi","Halabi","Issa"]
    };
    case "AFRICA_SAHEL": {
        ["Traoré","Diallo","Keita","Touré","Cissé","Konaté","Sangaré","Coulibaly","Dembélé","Diop","Ba","Sow","Camara","Sy","Kane","Ndiaye","Fall","Gueye","Diarra","Fofana"]
    };
    default {
        ["Haddad","Mansour","Petrov","Ivanov","Kowalski","Novak","Horvat","Popescu","Dimitrov","Rossi","Garcia","Silva","Smith","Brown","Miller"]
    };
};

private _aliases = [
    "ABU HAMZA","ABU YASSIN","ABU MARIAM","ABU ZAYD","ABU BAKR","AL FARIQ","AL SAQR","THE COURIER","SHADOW","RAVEN","HUNTER","GHOST","FALCON","THE DRIVER","WAREHOUSE","ENGINEER","THE ACCOUNTANT","NIGHT OWL","SANDMAN","BROTHER 7"
];

private _nats = switch (_region) do {
    case "IRAQ": { ["Irakienne","Syrienne","Inconnue","Jordanienne"] };
    case "SYRIA": { ["Syrienne","Irakienne","Libanaise","Inconnue"] };
    case "LEVANT": { ["Libanaise","Syrienne","Jordanienne","Palestinienne","Inconnue"] };
    case "AFRICA_SAHEL": { ["Malienne","Nigérienne","Burkinabè","Mauritanienne","Inconnue"] };
    default { ["Irakienne","Syrienne","Libanaise","Jordanienne","Inconnue","Locale"] };
};

private _languages = switch (_region) do {
    case "AFRICA_SAHEL": { ["Français","Arabe","Bambara","Haoussa"] };
    default { ["Arabe","Arabe dialectal","Anglais limité","Kurde","Français"] };
};

private _phonePrefixes = switch (_region) do {
    case "IRAQ": { ["+964 750", "+964 770", "+964 780"] };
    case "SYRIA": { ["+963 944", "+963 955", "+963 933"] };
    case "LEVANT": { ["+961 3", "+962 79", "+970 59"] };
    case "AFRICA_SAHEL": { ["+223 76", "+227 90", "+226 70"] };
    default { ["+964 750", "+963 944", "+961 3"] };
};

private _roles = createHashMapFromArray [
    ["CIVILIAN", ["Commerçant","Ouvrier","Chauffeur","Étudiant","Agriculteur","Mécanicien","Épicier","Taxi","Instituteur","Infirmier"]],
    ["INSURGENT", ["Combattant","Recruteur","Observateur","Armurier","Guetteur","Poseur IED","Chef d'équipe","Propagandiste"]],
    ["MILITARY", ["Soldat","Sergent","Officier","Tireur","Radiotélégraphiste","Conducteur","Médecin de combat"]],
    ["COMMANDER", ["Chef de cellule","Commandant local","Coordinateur","Émir de secteur","Responsable opérationnel"]],
    ["COURIER", ["Courrier","Passeur","Transporteur","Messager","Relais frontière"]],
    ["FINANCIER", ["Collecteur de fonds","Comptable","Intermédiaire","Changeur","Responsable caisses"]],
    ["TECHNICIAN", ["Technicien radio","Spécialiste IED","Informaticien","Réparateur téléphones","Opérateur drone"]],
    ["INTELLIGENCE", ["Collecteur HUMINT","Analyste","Indicateur","Observateur d'objectifs","Contre-surveillance"]],
    ["LOGISTICS", ["Logisticien","Magasinier","Conducteur logistique","Gestionnaire dépôt","Approvisionneur"]]
];

private _noiseSms = [
    "N'oublie pas le pain en rentrant.",
    "Appelle ta mère ce soir.",
    "Le match est à 20h.",
    "Prix du gazole encore monté.",
    "Tu viens au mariage samedi ?",
    "Facture électricité reçue.",
    "Le frigo est en panne.",
    "Bonne nuit.",
    "OK.",
    "On se voit au café ?"
];

private _falseLeads = [
    "Le dépôt est au nord du marché — ne pas vérifier.",
    "Rendez-vous pont EST demain 06h (leurre).",
    "Armes dans l'école abandonnée secteur 9 (faux).",
    "Le chef s'appelle ABU KARIM (identité leurre).",
    "Grid 999999 000000 — ignorez ce message.",
    "Transfert vers le port SUD annulé depuis longtemps."
];

private _phoneModels = [
    "Android endommagé","Smartphone générique","Feature phone","Téléphone saturé",
    "Android cracké","iPhone d'occasion","Nokia basique","Téléphone dual-SIM"
];

private _apps = [
    "Messages","Contacts","Maps","Notes","WhatsApp","Telegram","Gallery","Calculator","Chrome","Files","Recorder","Clock"
];

private _vehicleTypes = ["camionnette","pickup","berline usée","camion citerne","motocyclette","utilitaire blanc"];
private _docTypes = ["Facture","Bordereau","Liste manuscrite","Carte annotée","Reçu","Permis","Photo imprimée","Carnet","Ordre de mission","Relevé"];

createHashMapFromArray [
    ["region", _region],
    ["firstNames", _firstNames],
    ["lastNames", _lastNames],
    ["aliases", _aliases],
    ["nationalities", _nats],
    ["languages", _languages],
    ["phonePrefixes", _phonePrefixes],
    ["roles", _roles],
    ["noiseSms", _noiseSms],
    ["falseLeads", _falseLeads],
    ["phoneModels", _phoneModels],
    ["applications", _apps],
    ["vehicleTypes", _vehicleTypes],
    ["documentTypes", _docTypes],
    ["themes", ["fuel_delivery","weapons_cache","meeting_alpha","courier_run","finance_drop","ied_cell","safehouse","recruitment","smuggling","drone_ops","propaganda","medical_logistics"]]
]
