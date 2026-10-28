/*
    Catalogue de l'app Comptes rendus : chaque type de C.R. avec sa fiche, ses lignes et sa présentation.
    Renvoie [type...] ; type = [code, libellé, famille, code Athena, aide, champs, présentation]
      code Athena : type enregistré par POST /api/atak/reports (AtakIcemanReportCatalog côté site) ;
                    "" pour un raccourci vers l'app qui gère déjà ce C.R. (champs = [page, onglet]).
      champ = [clé, genre, libellé, argument, aide, repère, requis, proposition]
        genres : "edit" texte d'une ligne     "memo" texte long (argument = lignes)   "num" nombre entier
                 "dtg" groupe date-heure      "grid" grille (MA POSITION / CARTE)     "seg" choix court
                 "combo" liste déroulante     "dir" direction (8 points cardinaux)    "big" gros boutons colorés
                                                                                      (argument = [[texte, couleur]...])
                 "level" niveau VERT / ORANGE / ROUGE / NOIR                          "sec" titre de paragraphe
        repère : pastille de la ligne (lettre SALUTE, numéro de ligne, paragraphe, classe logistique) ;
        requis : le C.R. ne part pas tant que la ligne est vide ;
        proposition : valeur tirée du jeu au premier affichage (fn_reportAuto), en couleur d'accent.
      présentation : HashMap
        "tag"    code court affiché en tête de fiche         "icon"   icône de la liste
        "color"  couleur de la fiche (#hex)                  "banner" bandeau d'alerte (vide : aucun)
        "prio"   priorité par défaut                         "tools"  boutons de propositions (enemy, group, target, here)
        "jnet"   choix « Remonter à » (relais JNET)           "photo"  photo jointe possible
        "flash"  bouton d'envoi rouge (C.R. d'urgence)        "title"  titre militaire de la fiche
    La première option d'un "seg", "combo", "big", "dir" ou "level" est la valeur par défaut (sauf "dir" : vide).
*/
private _ic = { "\a3\ui_f\data\igui\cfg\simpletasks\types\" + _this + "_ca.paa" };
private _dir = ["N", "NE", "E", "SE", "S", "SO", "O", "NO", "STATIQUE", "INCONNUE"];
private _lvl = ["VERT", "ORANGE", "ROUGE", "NOIR"];
// Sans « < » ni « > » nus : le texte passe dans du texte structuré (parseText).
private _lvlHelp = "vert plus de 75 %, orange 50 à 75 %, rouge 25 à 50 %, noir moins de 25 %";
private _meta = {
    params ["_tag", "_icon", ["_color", "#5cc76b"], ["_extra", []]];
    private _m = createHashMapFromArray [["tag", _tag], ["icon", _icon call _ic], ["color", _color], ["banner", ""], ["prio", "ROUTINE"],
        ["tools", []], ["jnet", false], ["photo", false], ["flash", false], ["title", ""]];
    { _m set [_x select 0, _x select 1]; } forEach _extra;
    _m
};
[
    // ── Combat et renseignement ─────────────────────────────────────────────
    ["CONTACT", "Contact (TIC)", "Combat et renseignement", "TIC", "Premier compte rendu au contact : où, quoi, combien, ce qu'il vous faut. Trois touches suffisent.", [
        ["nature", "big", "Nature du contact", [["TIRS DIRECTS", "red"], ["TIRS INDIRECTS", "red"], ["IED", "amber"], ["OBSERVÉ", ""]], "", "1", true, ""],
        ["grid", "grid", "Position de l'ennemi", "", "grille estimée", "2", true, "enemyGrid"],
        ["dir", "dir", "Direction depuis moi", _dir, "", "3", false, "enemyDir"],
        ["dist", "seg", "Distance", ["< 100 M", "100-300 M", "300-600 M", "600 M-1 KM", "> 1 KM"], "", "4", false, "enemyDist"],
        ["size", "combo", "Volume ennemi", ["Inconnu", "1 à 2 personnes", "Groupe (3 à 10)", "Section (10 à 30)", "Compagnie ou plus", "Véhicule(s) seul(s)"], "", "5", false, "enemySizeClass"],
        ["weapons", "combo", "Armement observé", ["Armes légères", "Mitrailleuses", "Lance-roquettes (RPG)", "Tireurs d'élite", "Mortiers / artillerie", "Véhicules armés", "Drones", "Inconnu"], "", "6", false, ""],
        ["status", "seg", "Situation", ["EN COURS", "FIXÉ", "ROMPU", "TERMINÉ"], "", "7", false, ""],
        ["friendly", "seg", "Pertes amies", ["AUCUNE", "BLESSÉS", "TUÉS", "INCONNU"], "", "8", false, "groupLoss"],
        ["need", "big", "Besoin immédiat", [["AUCUN", ""], ["APPUI FEU", "red"], ["RENFORTS", "amber"], ["MEDEVAC", "red"], ["QRF", "amber"]], "", "9", false, ""],
        ["desc", "memo", "Description (facultatif)", 2, "", "", false, ""]
    ], ["TIC", "attack", "#e5483a", [["banner", "TROUPES AU CONTACT"], ["prio", "IMMÉDIAT"], ["tools", ["enemy", "here"]], ["flash", true], ["title", "COMPTE RENDU DE CONTACT"]]] call _meta],
    ["SALUTE", "SALUTE", "Combat et renseignement", "SALUTE", "Observation ennemie détaillée, lettre par lettre : Size, Activity, Location, Unit, Time, Equipment.", [
        ["size", "edit", "Volume (Size)", "", "ex. 6 hommes, 2 pick-up", "S", true, "enemySize"],
        ["activity", "combo", "Activité (Activity)", ["Patrouille", "Déplacement", "En position défensive", "Retranchement / travaux", "Embuscade", "Rassemblement", "Ravitaillement", "Observation", "Au repos", "Inconnue"], "", "A", true, ""],
        ["movement", "dir", "Direction de déplacement", _dir, "", "A", false, ""],
        ["location", "grid", "Localisation (Location)", "", "grille estimée", "L", true, "enemyGrid"],
        ["unit", "edit", "Unité, tenue (Unit)", "", "ex. treillis sable, brassards rouges", "U", false, "enemyUnit"],
        ["time", "dtg", "Heure d'observation (Time)", "", "", "T", true, "dtg"],
        ["equipment", "edit", "Équipement (Equipment)", "", "ex. AK, 1 PKM, technical DShK", "E", false, "enemyEquip"],
        ["confidence", "seg", "Fiabilité", ["CONFIRMÉ", "PROBABLE", "DOUTEUX"], "", "", false, ""],
        ["remarks", "memo", "Remarques", 2, "", "", false, ""]
    ], ["SALUTE", "scout", "#e8c547", [["tools", ["enemy"]], ["jnet", true], ["photo", true], ["title", "COMPTE RENDU D'OBSERVATION SALUTE"]]] call _meta],
    ["SPOTREP", "Observation (SPOTREP)", "Combat et renseignement", "SPOTREP", "Observation ponctuelle : véhicule, mouvement, installation, activité suspecte.", [
        ["what", "combo", "Objet observé", ["Personnel", "Véhicule léger", "Véhicule blindé", "Aéronef", "Drone", "Installation / position", "Engin explosif suspect", "Civils", "Activité suspecte", "Autre"], "", "1", true, "enemyKind"],
        ["count", "num", "Nombre", "1", "", "2", false, "enemyCount"],
        ["grid", "grid", "Position", "", "grille estimée", "3", true, "enemyGrid"],
        ["movement", "dir", "Déplacement", _dir, "", "4", false, ""],
        ["time", "dtg", "Heure", "", "", "5", true, "dtg"],
        ["action", "seg", "Action prise", ["AUCUNE", "SURVEILLANCE", "ENGAGÉ", "SIGNALÉ"], "", "6", false, ""],
        ["remarks", "memo", "Détail", 3, "", "", false, ""]
    ], ["SPOT", "search", "#e8c547", [["tools", ["enemy", "here"]], ["jnet", true], ["photo", true], ["title", "COMPTE RENDU D'OBSERVATION"]]] call _meta],
    ["SITREP", "Situation (SITREP)", "Combat et renseignement", "SITREP", "Point de situation de votre élément, en six paragraphes. Les effectifs et les niveaux sont proposés d'après votre groupe.", [
        ["#1", "sec", "1 · Généralités", "", ""],
        ["time", "dtg", "Groupe date-heure", "", "", "DTG", true, "dtg"],
        ["grid", "grid", "Position de l'élément", "", "", "POS", true, "here"],
        ["activity", "seg", "Activité", ["EN MOUVEMENT", "EN POSITION", "AU CONTACT", "EN ATTENTE"], "", "ACT", false, "activity"],
        ["#2", "sec", "2 · Situation ennemie", "", ""],
        ["enemy", "memo", "", 2, "", "", false, "enemySummary"],
        ["#3", "sec", "3 · Situation amie", "", ""],
        ["friendly", "memo", "", 2, "", "", false, "friendlySummary"],
        ["#4", "sec", "4 · Effectifs", "", ""],
        ["fit", "num", "Personnel apte", "", "", "APT", true, "fit"],
        ["wia", "num", "Blessés", "0", "", "BLE", false, "wia"],
        ["kia", "num", "Tués", "0", "", "TUÉ", false, "kia"],
        ["#5", "sec", "5 · Logistique", "", ""],
        ["ammo", "level", "Munitions", _lvl, _lvlHelp, "CL V", false, "ammo"],
        ["fuel", "level", "Carburant", _lvl, "", "CL III", false, "fuel"],
        ["capability", "seg", "Capacité opérationnelle", ["100 %", "75 %", "50 %", "25 % ET MOINS"], "", "CAP", false, "capability"],
        ["#6", "sec", "6 · Intentions", "", ""],
        ["next", "memo", "", 2, "prochaine action", "", true, ""]
    ], ["SITREP", "map", "#5cc76b", [["tools", ["group", "enemy"]], ["jnet", true], ["title", "COMPTE RENDU DE SITUATION"]]] call _meta],
    ["PATROLREP", "Fin de patrouille (PATROLREP)", "Combat et renseignement", "PATROLREP", "Compte rendu au retour de patrouille ou de mission : chronologie, résultat, observations.", [
        ["#1", "sec", "Chronologie", "", ""],
        ["start", "edit", "Heure de départ", "", "", "DÉP", true, "missionStart"],
        ["end", "dtg", "Heure de retour", "", "", "RET", true, "dtg"],
        ["route", "edit", "Itinéraire suivi", "", "points de passage", "ITI", false, "route"],
        ["result", "big", "Mission", [["REMPLIE", "green"], ["PARTIELLE", "amber"], ["NON REMPLIE", "red"]], "", "RÉS", true, ""],
        ["#2", "sec", "Compte rendu", "", ""],
        ["observations", "memo", "Observations (terrain, ennemi, population)", 3, "", "", true, ""],
        ["contacts", "memo", "Contacts et incidents", 2, "", "", false, ""],
        ["#3", "sec", "Bilan", "", ""],
        ["wia", "num", "Blessés", "0", "", "BLE", false, "wia"],
        ["kia", "num", "Tués", "0", "", "TUÉ", false, "kia"],
        ["recommendations", "memo", "Recommandations", 2, "", "", false, ""]
    ], ["PATROL", "walk", "#5cc76b", [["tools", ["group"]], ["jnet", true], ["photo", true], ["title", "COMPTE RENDU DE PATROUILLE"]]] call _meta],
    // ── Feux et effets ──────────────────────────────────────────────────────
    ["BDA", "Bilan des dégâts (BDA)", "Feux et effets", "BDA", "Effets observés après un tir ou une frappe. La grille vient de votre désignateur, sinon de ce que vous visez.", [
        ["type", "big", "Cible", [["PERSONNEL", ""], ["VÉHICULE", ""], ["BÂTIMENT", ""], ["POSITION", ""]], "", "1", true, ""],
        ["grid", "grid", "Grille de la cible", "", "", "2", true, "targetGrid"],
        ["result", "big", "Résultat", [["DÉTRUIT", "green"], ["ENDOMMAGÉ", "amber"], ["NEUTRALISÉ", "green"], ["SANS EFFET", "red"]], "", "3", true, ""],
        ["ekia", "num", "Ennemis tués (EKIA)", "", "", "4", false, ""],
        ["equip", "edit", "Matériel détruit", "", "ex. 1 BMP, 2 PKM", "5", false, ""],
        ["ordnance", "edit", "Munition / moyen", "", "ex. 2 x 81 mm, FPV", "6", false, ""],
        ["collateral", "seg", "Dommages collatéraux", ["AUCUN", "CIVILS", "AMIS", "INCONNU"], "", "7", false, ""],
        ["reattack", "big", "Nouvelle frappe", [["PAS DE REPRISE", "green"], ["REPRISE REQUISE", "red"]], "faut-il frapper la cible à nouveau ?", "8", false, ""],
        ["remarks", "memo", "Remarques", 2, "", "", false, ""]
    ], ["BDA", "destroy", "#f08a3c", [["tools", ["target"]], ["photo", true], ["title", "BILAN DES DOMMAGES DE COMBAT"]]] call _meta],
    ["@FIRES", "Demande d'appui feu", "Feux et effets", "", "Ouvre l'app CAS : demande de tir, réglage, fin de mission.", ["FIRES", ""], ["CFF", "target", "#f08a3c"] call _meta],
    ["@JTAC", "Appui aérien (CAS 9 lignes)", "Feux et effets", "", "Ouvre l'app JTAC : 9 lignes CAS, désignation, contrôle.", ["JTAC", ""], ["9L", "plane", "#f08a3c"] call _meta],
    // ── Santé ───────────────────────────────────────────────────────────────
    ["EAGLE_DOWN", "Opérateur à terre", "Santé", "EAGLE_DOWN", "Blessé ou opérateur hors de combat, au format MIST (Mécanisme, Blessures, Signes, Traitement), avec l'état de la zone.", [
        ["#1", "sec", "Blessé", "", ""],
        ["casualty", "edit", "Nom ou indicatif", "", "", "ID", true, "casualty"],
        ["status", "big", "État", [["STABLE", "green"], ["URGENT", "amber"], ["CRITIQUE", "red"], ["DÉCÉDÉ", ""]], "", "ÉT", true, ""],
        ["grid", "grid", "Position du blessé", "", "", "POS", true, "here"],
        ["#2", "sec", "MIST", "", ""],
        ["mechanism", "combo", "Mécanisme", ["Balle", "Éclats / explosion", "IED", "Chute", "Brûlure", "Accident de véhicule", "Autre"], "", "M", false, ""],
        ["injuries", "edit", "Blessures", "", "ex. plaie par balle cuisse gauche", "I", false, ""],
        ["signs", "seg", "Signes (conscience)", ["CONSCIENT", "CONFUS", "INCONSCIENT", "ARRÊT"], "", "S", false, "casualtySigns"],
        ["treatment", "edit", "Traitement en cours", "", "garrot, pansement, perfusion…", "T", false, ""],
        ["#3", "sec", "Évacuation", "", ""],
        ["situation", "seg", "Situation", ["CONTACT EN COURS", "ZONE SÛRE"], "", "SIT", false, ""],
        ["medevac", "big", "Évacuation", [["URGENTE", "red"], ["PRIORITAIRE", "amber"], ["ROUTINE", "green"], ["PAS BESOIN", ""]], "", "ÉVA", false, ""],
        ["lz", "seg", "Zone de poser", ["SÛRE", "NON SÛRE", "AUCUNE"], "", "LZ", false, ""],
        ["remarks", "memo", "Remarques", 2, "", "", false, ""]
    ], ["EAGLE", "heal", "#e5483a", [["banner", "OPÉRATEUR À TERRE"], ["prio", "IMMÉDIAT"], ["tools", ["here"]], ["flash", true], ["title", "COMPTE RENDU DE BLESSÉ (MIST)"]]] call _meta],
    ["@MEDEVAC", "MEDEVAC 9 lignes", "Santé", "", "Ouvre l'onglet MEDEVAC de l'app Médical : 9 lignes complètes, LZ sur la carte, suivi de l'évacuation.", ["MEDICAL", "MEDEVAC"], ["9L", "heli", "#e5483a"] call _meta],
    // ── Logistique ──────────────────────────────────────────────────────────
    ["LOGREP", "État logistique (LOGREP)", "Logistique", "LOGREP", "Niveaux de l'élément par classe d'approvisionnement, matériel et besoins. Munitions, carburant et véhicules sont proposés d'après votre groupe.", [
        ["#1", "sec", "Niveaux par classe", _lvlHelp, ""],
        ["rations", "level", "Rations", _lvl, "", "CL I", false, ""],
        ["water", "level", "Eau", _lvl, "", "EAU", false, ""],
        ["fuel", "level", "Carburant", _lvl, "", "CL III", false, "fuel"],
        ["ammo", "level", "Munitions", _lvl, "", "CL V", false, "ammo"],
        ["medical", "level", "Matériel médical", _lvl, "", "CL VIII", false, "medical"],
        ["batteries", "level", "Piles et batteries", _lvl, "", "PIL", false, ""],
        ["#2", "sec", "Matériel et personnel", "", ""],
        ["vehicles", "seg", "Véhicules", ["OPÉRATIONNELS", "DÉGRADÉS", "IMMOBILISÉS", "AUCUN"], "", "VÉH", false, "vehicles"],
        ["fit", "num", "Personnel apte", "", "", "APT", false, "fit"],
        ["wia", "num", "Blessés", "0", "", "BLE", false, "wia"],
        ["kia", "num", "Tués", "0", "", "TUÉ", false, "kia"],
        ["#3", "sec", "Besoins", "", ""],
        ["needs", "memo", "", 2, "", "", false, ""]
    ], ["LOGREP", "rearm", "#4aa3df", [["tools", ["group"]], ["jnet", true], ["title", "COMPTE RENDU LOGISTIQUE"]]] call _meta],
    ["@LOGI", "Demande de ravitaillement", "Logistique", "", "Ouvre l'app Logistique : ravitaillement, largage, suivi de la livraison.", ["LOGI", ""], ["RAV", "container", "#4aa3df"] call _meta],
    // ── Menaces ─────────────────────────────────────────────────────────────
    ["UXO", "Engin explosif (9 lignes)", "Menaces", "UXO", "Découverte d'un engin explosif (IED, munition non explosée, mine), en 9 lignes.", [
        ["time", "dtg", "Date-heure de découverte", "", "", "1", true, "dtg"],
        ["grid", "grid", "Lieu", "", "", "2", true, "here"],
        ["contact", "edit", "Indicatif et moyen de contact", "", "", "3", true, "contact"],
        ["type", "combo", "Type d'engin", ["IED suspect", "IED véhicule (VBIED)", "Mine", "Obus / roquette / mortier", "Bombe ou sous-munition larguée", "Grenade", "Inconnu"], "", "4", true, ""],
        ["cbrn", "seg", "Contamination NRBC", ["AUCUNE", "SUSPECTÉE", "CONFIRMÉE"], "", "5", false, ""],
        ["threatened", "edit", "Ressources menacées", "", "itinéraire, bâtiment, unité", "6", false, ""],
        ["impact", "seg", "Impact sur la mission", ["AUCUN", "MINEUR", "BLOQUANT"], "", "7", false, ""],
        ["protection", "seg", "Mesures de protection", ["AUCUNE", "BOUCLAGE 100 M", "BOUCLAGE 300 M", "ÉVACUATION"], "", "8", false, ""],
        ["priority", "big", "Priorité recommandée", [["IMMÉDIATE", "red"], ["INDIRECTE", "amber"], ["MINEURE", "green"], ["SANS MENACE", ""]], "", "9", true, ""],
        ["remarks", "memo", "Description de l'engin", 2, "", "", false, ""]
    ], ["UXO", "mine", "#f08a3c", [["banner", "ENGIN EXPLOSIF · ÉLOIGNEZ-VOUS"], ["prio", "PRIORITAIRE"], ["tools", ["here"]], ["photo", true], ["title", "COMPTE RENDU UXO / IED 9 LIGNES"]]] call _meta],
    ["NBC1", "Attaque NRBC (NBC-1)", "Menaces", "NBC1", "Premier compte rendu d'attaque nucléaire, radiologique, biologique ou chimique, lignes de l'OTAN.", [
        ["grid", "grid", "BRAVO · Position de l'observateur", "", "", "B", true, "here"],
        ["direction", "dir", "CHARLIE · Direction de l'attaque", _dir, "", "C", false, ""],
        ["time", "dtg", "DELTA · Heure de l'attaque", "", "", "D", true, "dtg"],
        ["impact", "grid", "FOXTROT · Lieu de l'attaque", "", "si connu", "F", false, ""],
        ["delivery", "seg", "GOLF · Vecteur", ["ARTILLERIE", "AÉRIEN", "ROQUETTE / MISSILE", "INCONNU"], "", "G", false, ""],
        ["agent", "seg", "HOTEL · Nature", ["CHIMIQUE", "BIOLOGIQUE", "RADIOLOGIQUE", "INCONNUE"], "", "H", true, ""],
        ["symptoms", "seg", "Victimes ou symptômes", ["AUCUN", "OUI"], "", "VIC", false, ""],
        ["mopp", "seg", "Tenue de protection", ["AUCUNE", "MASQUE", "TENUE COMPLÈTE"], "", "MOPP", false, ""],
        ["clues", "memo", "Indices (nuage, odeur, liquide, animaux morts…)", 2, "", "", false, ""]
    ], ["NBC-1", "danger", "#e8c547", [["banner", "ALERTE NRBC · MASQUE"], ["prio", "FLASH"], ["tools", ["here"]], ["flash", true], ["title", "COMPTE RENDU NBC-1"]]] call _meta],
    // ── Ordres et divers ────────────────────────────────────────────────────
    ["FRAGO", "Ordre de conduite (FRAGO)", "Ordres et divers", "FRAGO", "Ordre court qui modifie l'ordre en cours, en cinq paragraphes. Sur Athena, il descend vers les sous-unités.", [
        ["reference", "edit", "Ordre modifié", "", "ex. OPORD 03", "RÉF", true, ""],
        ["#1", "sec", "1 · Situation", "", ""],
        ["situation", "memo", "", 2, "", "", false, ""],
        ["#2", "sec", "2 · Mission", "qui, quoi, où, quand, pourquoi", ""],
        ["mission", "memo", "", 2, "", "", true, ""],
        ["#3", "sec", "3 · Exécution", "", ""],
        ["execution", "memo", "", 3, "", "", true, ""],
        ["#4", "sec", "4 · Soutien", "", ""],
        ["support", "memo", "", 2, "", "", false, ""],
        ["#5", "sec", "5 · Commandement et transmissions", "", ""],
        ["command", "memo", "", 2, "", "", false, ""],
        ["acknowledge", "seg", "Accusé de réception", ["REQUIS", "NON REQUIS"], "", "ACK", false, ""]
    ], ["FRAGO", "documents", "#b58cf0", [["prio", "PRIORITAIRE"], ["title", "ORDRE DE CONDUITE"]]] call _meta],
    ["OTHER", "Compte rendu libre", "Ordres et divers", "OTHER", "Tout ce qui n'entre pas dans une fiche.", [
        ["title", "edit", "Objet", "", "", "OBJ", true, ""],
        ["text", "memo", "Texte", 5, "", "", true, ""],
        ["grid", "grid", "Position (facultatif)", "", "", "POS", false, ""]
    ], ["MSG", "talk", "#8a9a93", [["jnet", true], ["photo", true], ["title", "MESSAGE"]]] call _meta]
]
