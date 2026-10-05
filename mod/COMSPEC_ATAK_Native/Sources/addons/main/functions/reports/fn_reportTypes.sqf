/*
    Catalogue de l'app Comptes rendus : tous les types de C.R. et tous leurs choix.
    Renvoie [type...] ; type = [code, libellé, famille, code Athena, aide, champs]
      code Athena : type enregistré par POST /api/atak/reports (AtakIcemanReportCatalog côté site) ;
                    "" pour un raccourci vers l'app qui gère déjà ce C.R. (champs = [page, onglet]).
      champ = [clé, genre, libellé, argument, aide]
        "edit"  : texte d'une ligne (argument = valeur par défaut)     "memo" : texte long (argument = lignes)
        "num"   : nombre (argument = défaut)                           "dtg"  : groupe date-heure, prérempli
        "grid"  : grille + boutons MA POSITION / SUR LA CARTE          "seg"  : choix court (argument = options, 5 au plus)
        "combo" : liste déroulante (argument = options)
    La première option d'un "seg" ou d'un "combo" est la valeur par défaut.
*/
private _dir = ["Statique", "Nord", "Nord-est", "Est", "Sud-est", "Sud", "Sud-ouest", "Ouest", "Nord-ouest", "Inconnue"];
private _lvl = ["VERT", "ORANGE", "ROUGE", "NOIR"];
private _lvlHelp = "vert > 75 %, orange 50 à 75 %, rouge 25 à 50 %, noir < 25 %";
private _prio = ["seg", "Priorité", ["ROUTINE", "PRIORITAIRE", "IMMÉDIAT", "FLASH"]];
[
    // ── Combat et renseignement ─────────────────────────────────────────────
    ["CONTACT", "Contact (TIC)", "Combat et renseignement", "TIC", "Premier compte rendu au contact : où, quoi, combien, ce qu'il vous faut.", [
        ["grid", "grid", "Position de l'ennemi", "", ""],
        ["nature", "seg", "Nature du contact", ["TIRS DIRECTS", "TIRS INDIRECTS", "IED", "OBSERVÉ"], ""],
        ["size", "combo", "Volume ennemi", ["Inconnu", "1 à 2 personnes", "Groupe (3 à 10)", "Section (10 à 30)", "Compagnie ou plus", "Véhicule(s) seul(s)"], ""],
        ["weapons", "combo", "Armement observé", ["Armes légères", "Mitrailleuses", "Lance-roquettes (RPG)", "Tireurs d'élite", "Mortiers / artillerie", "Véhicules armés", "Drones", "Inconnu"], ""],
        ["dir", "edit", "Direction et distance (ex. NE 300 m)", "", ""],
        ["status", "seg", "Situation", ["EN COURS", "FIXÉ", "ROMPU", "TERMINÉ"], ""],
        ["friendly", "seg", "Pertes amies", ["AUCUNE", "BLESSÉS", "TUÉS", "INCONNU"], ""],
        ["need", "seg", "Besoin immédiat", ["AUCUN", "APPUI FEU", "RENFORTS", "MEDEVAC", "QRF"], ""],
        ["desc", "memo", "Description", 3, ""]
    ]],
    ["SALUTE", "SALUTE", "Combat et renseignement", "SALUTE", "Observation ennemie détaillée : Size, Activity, Location, Unit, Time, Equipment.", [
        ["size", "edit", "S · Volume (ex. 6 hommes, 2 pick-up)", "", ""],
        ["activity", "combo", "A · Activité", ["Patrouille", "Déplacement", "En position défensive", "Retranchement / travaux", "Embuscade", "Rassemblement", "Ravitaillement", "Observation", "Au repos", "Inconnue"], ""],
        ["movement", "combo", "A · Direction de déplacement", _dir, ""],
        ["location", "grid", "L · Localisation", "", ""],
        ["unit", "edit", "U · Unité / tenue (ex. treillis sable, brassards rouges)", "", ""],
        ["time", "dtg", "T · Heure d'observation", "", ""],
        ["equipment", "edit", "E · Équipement (ex. AK, 1 PKM, technical DShK)", "", ""],
        ["confidence", "seg", "Fiabilité", ["CONFIRMÉ", "PROBABLE", "DOUTEUX"], ""],
        ["remarks", "memo", "Remarques", 2, ""]
    ]],
    ["SPOTREP", "Observation (SPOTREP)", "Combat et renseignement", "SPOTREP", "Observation ponctuelle : véhicule, mouvement, installation, activité suspecte.", [
        ["what", "combo", "Objet observé", ["Personnel", "Véhicule léger", "Véhicule blindé", "Aéronef", "Drone", "Installation / position", "Engin explosif suspect", "Civils", "Activité suspecte", "Autre"], ""],
        ["count", "num", "Nombre", "1", ""],
        ["grid", "grid", "Position", "", ""],
        ["movement", "combo", "Déplacement", _dir, ""],
        ["time", "dtg", "Heure", "", ""],
        ["action", "seg", "Action prise", ["AUCUNE", "SURVEILLANCE", "ENGAGÉ", "SIGNALÉ"], ""],
        ["remarks", "memo", "Détail", 3, ""]
    ]],
    ["SITREP", "Situation (SITREP)", "Combat et renseignement", "SITREP", "Point de situation de votre élément.", [
        ["time", "dtg", "Groupe date-heure", "", ""],
        ["grid", "grid", "Position de l'élément", "", ""],
        ["activity", "seg", "Activité", ["EN MOUVEMENT", "EN POSITION", "AU CONTACT", "EN ATTENTE"], ""],
        ["enemy", "memo", "Situation ennemie", 2, ""],
        ["friendly", "memo", "Situation amie", 2, ""],
        ["fit", "num", "Personnel apte", "", ""],
        ["wia", "num", "Blessés", "0", ""],
        ["kia", "num", "Tués", "0", ""],
        ["ammo", "seg", "Munitions", _lvl, _lvlHelp],
        ["fuel", "seg", "Carburant", _lvl, ""],
        ["capability", "seg", "Capacité opérationnelle", ["100 %", "75 %", "50 %", "25 % ET MOINS"], ""],
        ["next", "memo", "Intentions / prochaine action", 2, ""]
    ]],
    ["PATROLREP", "Fin de patrouille (PATROLREP)", "Combat et renseignement", "PATROLREP", "Compte rendu au retour de patrouille ou de mission.", [
        ["start", "edit", "Heure de départ", "", ""],
        ["end", "dtg", "Heure de retour", "", ""],
        ["route", "edit", "Itinéraire suivi (points de passage)", "", ""],
        ["result", "seg", "Mission", ["REMPLIE", "PARTIELLE", "NON REMPLIE"], ""],
        ["observations", "memo", "Observations (terrain, ennemi, population)", 3, ""],
        ["contacts", "memo", "Contacts et incidents", 2, ""],
        ["wia", "num", "Blessés", "0", ""],
        ["kia", "num", "Tués", "0", ""],
        ["recommendations", "memo", "Recommandations", 2, ""]
    ]],
    // ── Feux et effets ──────────────────────────────────────────────────────
    ["BDA", "Bilan des dégâts (BDA)", "Feux et effets", "BDA", "Effets observés après un tir ou une frappe.", [
        ["type", "seg", "Cible", ["PERSONNEL", "VÉHICULE", "BÂTIMENT", "POSITION"], ""],
        ["result", "seg", "Résultat", ["DÉTRUIT", "ENDOMMAGÉ", "NEUTRALISÉ", "SANS EFFET"], ""],
        ["ekia", "num", "Ennemis tués (EKIA)", "", ""],
        ["equip", "edit", "Matériel détruit (ex. 1 BMP, 2 PKM)", "", ""],
        ["ordnance", "edit", "Munition / moyen (ex. 2 x 81 mm, FPV)", "", ""],
        ["grid", "grid", "Grille de la cible", "", ""],
        ["collateral", "seg", "Dommages collatéraux", ["AUCUN", "CIVILS", "AMIS", "INCONNU"], ""],
        ["reattack", "seg", "Reprise", ["PAS DE REPRISE", "REPRISE REQUISE"], "faut-il frapper la cible à nouveau ?"],
        ["remarks", "memo", "Remarques", 2, ""]
    ]],
    ["@FIRES", "Demande d'appui feu", "Feux et effets", "", "Ouvre l'app Feux : demande de tir, réglage, fin de mission.", ["FIRES", ""]],
    ["@JTAC", "Appui aérien (CAS 9 lignes)", "Feux et effets", "", "Ouvre l'app JTAC : 9 lignes CAS, désignation, contrôle.", ["JTAC", ""]],
    // ── Santé ───────────────────────────────────────────────────────────────
    ["EAGLE_DOWN", "Opérateur à terre", "Santé", "EAGLE_DOWN", "Blessé ou opérateur hors de combat, avec l'état de la zone.", [
        ["casualty", "edit", "Blessé (nom ou indicatif)", "", ""],
        ["status", "seg", "État", ["STABLE", "URGENT", "CRITIQUE", "DÉCÉDÉ"], ""],
        ["mechanism", "combo", "Mécanisme", ["Balle", "Éclats / explosion", "IED", "Chute", "Brûlure", "Accident de véhicule", "Autre"], ""],
        ["situation", "seg", "Situation", ["CONTACT EN COURS", "ZONE SÛRE"], ""],
        ["medevac", "seg", "Évacuation", ["URGENTE", "PRIORITAIRE", "ROUTINE", "PAS BESOIN"], ""],
        ["lz", "seg", "Zone de poser", ["SÛRE", "NON SÛRE", "AUCUNE"], ""],
        ["grid", "grid", "Position du blessé", "", ""],
        ["treatment", "edit", "Traitement en cours (garrot, pansement, perfusion…)", "", ""],
        ["remarks", "memo", "Remarques", 2, ""]
    ]],
    ["@MEDEVAC", "MEDEVAC 9 lignes", "Santé", "", "Ouvre l'onglet MEDEVAC de l'app Médical : 9 lignes complètes, LZ sur la carte, suivi de l'évacuation.", ["MEDICAL", "MEDEVAC"]],
    // ── Logistique ──────────────────────────────────────────────────────────
    ["LOGREP", "État logistique (LOGREP)", "Logistique", "LOGREP", "Niveaux de l'élément et besoins.", [
        ["ammo", "seg", "Munitions", _lvl, _lvlHelp],
        ["fuel", "seg", "Carburant", _lvl, ""],
        ["water", "seg", "Eau", _lvl, ""],
        ["rations", "seg", "Rations", _lvl, ""],
        ["medical", "seg", "Matériel médical", _lvl, ""],
        ["batteries", "seg", "Piles et batteries", _lvl, ""],
        ["vehicles", "seg", "Véhicules", ["OPÉRATIONNELS", "DÉGRADÉS", "IMMOBILISÉS", "AUCUN"], ""],
        ["fit", "num", "Personnel apte", "", ""],
        ["wia", "num", "Blessés", "0", ""],
        ["kia", "num", "Tués", "0", ""],
        ["needs", "memo", "Besoins urgents", 2, ""]
    ]],
    ["@LOGI", "Demande de ravitaillement", "Logistique", "", "Ouvre l'app Logistique : ravitaillement, largage, suivi de la livraison.", ["LOGI", ""]],
    // ── Menaces ─────────────────────────────────────────────────────────────
    ["UXO", "Engin explosif (9 lignes)", "Menaces", "UXO", "Découverte d'un engin explosif (IED, munition non explosée, mine).", [
        ["time", "dtg", "1 · Date-heure de découverte", "", ""],
        ["grid", "grid", "2 · Lieu", "", ""],
        ["contact", "edit", "3 · Indicatif et moyen de contact", "", ""],
        ["type", "combo", "4 · Type d'engin", ["IED suspect", "IED véhicule (VBIED)", "Mine", "Obus / roquette / mortier", "Bombe ou sous-munition larguée", "Grenade", "Inconnu"], ""],
        ["cbrn", "seg", "5 · Contamination NRBC", ["AUCUNE", "SUSPECTÉE", "CONFIRMÉE"], ""],
        ["threatened", "edit", "6 · Ressources menacées (itinéraire, bâtiment, unité)", "", ""],
        ["impact", "seg", "7 · Impact sur la mission", ["AUCUN", "MINEUR", "BLOQUANT"], ""],
        ["protection", "seg", "8 · Mesures de protection", ["AUCUNE", "BOUCLAGE 100 M", "BOUCLAGE 300 M", "ÉVACUATION"], ""],
        ["priority", "seg", "9 · Priorité recommandée", ["IMMÉDIATE", "INDIRECTE", "MINEURE", "SANS MENACE"], ""],
        ["remarks", "memo", "Description de l'engin", 2, ""]
    ]],
    ["NBC1", "Attaque NRBC (NBC-1)", "Menaces", "NBC1", "Premier compte rendu d'attaque nucléaire, radiologique, biologique ou chimique.", [
        ["time", "dtg", "Heure de l'attaque", "", ""],
        ["grid", "grid", "Position de l'observateur", "", ""],
        ["direction", "combo", "Direction de l'attaque", _dir, ""],
        ["delivery", "seg", "Vecteur", ["ARTILLERIE", "AÉRIEN", "ROQUETTE / MISSILE", "INCONNU"], ""],
        ["agent", "seg", "Nature", ["CHIMIQUE", "BIOLOGIQUE", "RADIOLOGIQUE", "INCONNUE"], ""],
        ["symptoms", "seg", "Victimes ou symptômes", ["AUCUN", "OUI"], ""],
        ["mopp", "seg", "Tenue de protection", ["AUCUNE", "MASQUE", "TENUE COMPLÈTE"], ""],
        ["clues", "memo", "Indices (nuage, odeur, liquide, animaux morts…)", 2, ""]
    ]],
    // ── Ordres et divers ────────────────────────────────────────────────────
    ["FRAGO", "Ordre de conduite (FRAGO)", "Ordres et divers", "FRAGO", "Ordre court qui modifie l'ordre en cours.", [
        ["reference", "edit", "Référence de l'ordre modifié", "", ""],
        ["situation", "memo", "Situation", 2, ""],
        ["mission", "memo", "Mission", 2, ""],
        ["execution", "memo", "Exécution", 3, ""],
        ["support", "memo", "Soutien", 2, ""],
        ["command", "memo", "Commandement et transmissions", 2, ""],
        ["acknowledge", "seg", "Accusé de réception", ["REQUIS", "NON REQUIS"], ""]
    ]],
    ["OTHER", "Compte rendu libre", "Ordres et divers", "OTHER", "Tout ce qui n'entre pas dans un formulaire.", [
        ["title", "edit", "Objet", "", ""],
        ["text", "memo", "Texte", 5, ""],
        ["grid", "grid", "Position (facultatif)", "", ""]
    ]]
] apply {
    // Champs communs à tout C.R. rédigé ici : priorité et diffusion en jeu.
    if ((_x select 3) isNotEqualTo "") then {
        (_x select 5) pushBack (["prio"] + _prio + [""]);
        (_x select 5) pushBack ["dest", "seg", "Diffusion en jeu", ["CAMP", "MON GROUPE", "AUCUNE"], "Athena le reçoit aussi quand le téléphone est connecté"];
    };
    _x
}
