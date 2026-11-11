/*
    Fenêtre « Gérer les écrans » du TOC : tables et règles sans effet (demande de TTRD : « il faut vraiment améliorer
    l'UI du menu des écrans » ; conception docs/audit/2026-11-gestionnaire-ecrans-ui.md). La fenêtre est dans
    fn_tocManagerUi, l'exécution dans fn_tocManagerUiApply. Rien ici ne lit le jeu : chaque mode est une fonction pure,
    rejouée hors du jeu par tests/Unit/AtakTocManagerUiAssetTest.php et tests/Unit/AtakTocManagerUiPlanAssetTest.php
    sur les tables de cas (_planCases, _confirmCases, _listCases), et en jeu par « selfTest ».
    Les tables entre « TABLE » et « FIN » sont du JSON strict.
    Modes :
      ["ids"]                     contrôles à identifiant : [[clé, idc, classe, zone]...] (8700 à 8799, uniques)
      ["tabs"]                    onglets de l'inspecteur : [[clé, libellé, idc du bouton, idc de la page, aide]...]
      ["families"]                familles de sources, dans l'ordre : [[titre, famille, aide]...]
      ["fields"]                  champs de l'inspecteur : [[clé, onglet, genre, libellé, aide avec un exemple]...]
      ["field", clé]              la ligne d'un champ ([] : inconnu)
      ["acts"]                    boutons : [[clé, libellé, aide avec un exemple, réservé à qui peut poser]...]
      ["family", clé de source]   famille d'une source (PLAN, TEXT et MISC en plus des familles de fn_tocManager)
      ["viewOf", [clé, affichages permis]]   affichage que demande une source ("" : éteindre, toujours permis)
      ["kindOf", clé]             genre de réglage de vue : "map" | "sat" | "mos" | "cam" | "text" | "other"
      ["sourceList", [sources, usages, recherche]]
                                  sources [[libellé, clé, état "" | "ok" | "off" | "no"]...], usages [[clé, [noms]]...]
                                  -> [[titre, famille, nombre, [[libellé, clé, état, aussi montrée sur]...]]...]
                                  (les neuf familles, toujours ; la recherche filtre les libellés, sans la casse)
      ["plan", [changements, cibles]]
                                  changements [[champ, valeur]...], cibles [[dalle, écran hôte, affichages permis, source]...]
                                  -> opérations dans l'ordre d'exécution [[opération, dalle ou écran, valeur]...] ;
                                  "refuse" : source ou texte impossible sur cette dalle ; "skip" : champ réservé à une seule dalle
      ["confirm", [état, événement]]
                                  boîte de confirmation dans la fenêtre : état [genre, cible, garder les écrans],
                                  événement "ask:genre:cible" | "toggle" | "ok" | "cancel" | "esc" | "enter"
                                  -> [état suivant, action "" | "roomRemove:id" | "roomDissolve:id" | "resetAll:" | "screenDelete:dalle" | "sceneSave:id"]
                                  Le bouton par défaut est ANNULER : Entrée et Échap annulent.
      ["grid", [classe, nombre de dalles]]   colonnes et rangées du schéma d'un mur (« Wall4x3 » : [4, 3])
      ["w2p", largeur m] / ["p2w", position]   curseur « largeur montrée » : 0 à 100 <-> 60 m à 20 km (échelle logarithmique)
      ["selfTest"]                [réussis, cas en échec]
*/
params [["_mode", "ids"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocManagerUiData;
switch (_mode) do {
    case "ids": {
        // ---- TABLE _ids (JSON) : contrôles à identifiant [clé, idc, classe, zone].
        private _ids = [
["veil", 8700, "COMSPEC_RscTocMgrText", "fond"], ["frame", 8701, "COMSPEC_RscTocMgrText", "fond"],
["title", 8702, "COMSPEC_RscTocMgrStx", "haut"], ["topRule", 8703, "COMSPEC_RscTocMgrText", "haut"],
["hall", 8710, "COMSPEC_RscTocMgrCombo", "haut"], ["scene", 8711, "COMSPEC_RscTocMgrCombo", "haut"], ["search", 8712, "COMSPEC_RscTocMgrEdit", "haut"],
["bLoad", 8713, "COMSPEC_RscTocMgrButton", "haut"], ["bSave", 8714, "COMSPEC_RscTocMgrButton", "haut"], ["bRooms", 8715, "COMSPEC_RscTocMgrButton", "haut"],
["bLog", 8716, "COMSPEC_RscTocMgrButton", "haut"], ["bLinks", 8717, "COMSPEC_RscTocMgrButton", "haut"], ["bClose", 8718, "COMSPEC_RscTocMgrButton", "haut"],
["tree", 8720, "COMSPEC_RscTocMgrTree", "gauche"],
["bAdd", 8721, "COMSPEC_RscTocMgrButton", "gauche"], ["bDup", 8722, "COMSPEC_RscTocMgrButton", "gauche"], ["bDel", 8723, "COMSPEC_RscTocMgrButton", "gauche"],
["bGo", 8724, "COMSPEC_RscTocMgrButton", "gauche"], ["bBlink", 8725, "COMSPEC_RscTocMgrButton", "gauche"],
["treeHead", 8726, "COMSPEC_RscTocMgrStx", "gauche"], ["kind", 8727, "COMSPEC_RscTocMgrCombo", "gauche"],
["vScreen", 8730, "COMSPEC_RscTocMgrGroup", "centre"], ["vRooms", 8731, "COMSPEC_RscTocMgrGroup", "centre"], ["vLog", 8732, "COMSPEC_RscTocMgrGroup", "centre"], ["vRoute", 8733, "COMSPEC_RscTocMgrGroup", "centre"],
["views", 8734, "COMSPEC_RscTocMgrTbxViews", "centre"],
["cTitle", 8736, "COMSPEC_RscTocMgrStx", "centre"], ["cHint", 8738, "COMSPEC_RscTocMgrStx", "centre"], ["cReady", 8739, "COMSPEC_RscTocMgrStx", "centre"],
["rooms", 8740, "COMSPEC_RscTocMgrTable", "centre"], ["queue", 8741, "COMSPEC_RscTocMgrTable", "droite"], ["log", 8742, "COMSPEC_RscTocMgrTable", "centre"], ["links", 8743, "COMSPEC_RscTocMgrTable", "centre"],
["tab0", 8750, "COMSPEC_RscTocMgrTab", "droite"], ["tab1", 8751, "COMSPEC_RscTocMgrTab", "droite"], ["tab2", 8752, "COMSPEC_RscTocMgrTab", "droite"],
["tab3", 8753, "COMSPEC_RscTocMgrTab", "droite"], ["tab4", 8754, "COMSPEC_RscTocMgrTab", "droite"], ["tab5", 8755, "COMSPEC_RscTocMgrTab", "droite"],
["tabRule", 8756, "COMSPEC_RscTocMgrText", "droite"], ["iTitle", 8757, "COMSPEC_RscTocMgrStx", "droite"],
["page0", 8760, "COMSPEC_RscTocMgrGroup", "droite"], ["page1", 8761, "COMSPEC_RscTocMgrGroup", "droite"], ["page2", 8762, "COMSPEC_RscTocMgrGroup", "droite"],
["page3", 8763, "COMSPEC_RscTocMgrGroup", "droite"], ["page4", 8764, "COMSPEC_RscTocMgrGroup", "droite"], ["page5", 8765, "COMSPEC_RscTocMgrGroup", "droite"],
["fams", 8770, "COMSPEC_RscTocMgrList", "droite"], ["srcs", 8771, "COMSPEC_RscTocMgrTable", "droite"], ["srcFind", 8772, "COMSPEC_RscTocMgrEdit", "droite"],
["camFilter", 8773, "COMSPEC_RscTocMgrTbxCams", "droite"], ["srcNow", 8774, "COMSPEC_RscTocMgrStx", "droite"], ["layouts", 8775, "COMSPEC_RscTocMgrTable", "droite"],
["msg", 8780, "COMSPEC_RscTocMgrEditMulti", "droite"],
["scope", 8790, "COMSPEC_RscTocMgrTbxScope", "droite"], ["help", 8791, "COMSPEC_RscTocMgrStx", "droite"],
["status", 8792, "COMSPEC_RscTocMgrStx", "bas"],
["bReset", 8793, "COMSPEC_RscTocMgrButton", "bas"], ["bCopy", 8794, "COMSPEC_RscTocMgrButton", "bas"], ["bCancel", 8795, "COMSPEC_RscTocMgrButton", "bas"], ["bApply", 8796, "COMSPEC_RscTocMgrButtonPrimary", "bas"],
["cfVeil", 8797, "COMSPEC_RscTocMgrText", "confirmation"], ["cfBox", 8798, "COMSPEC_RscTocMgrGroupFixed", "confirmation"]
];
        // ---- FIN
        _ids
    };
    case "tabs": {
        // ---- TABLE _tabs (JSON) : onglets de l'inspecteur [clé, libellé, idc du bouton, idc de la page, aide].
        private _tabs = [
["SRC", "SOURCE", 8750, 8760, "Ce que montre la dalle : choisissez la famille à gauche, puis la source à droite. Double-clic sur une source : appliquée tout de suite. Exemple : CAMÉRAS, puis Caméra poitrine · TA1."],
["VIEW", "VUE", 8751, 8761, "Cadrage de ce que montre la dalle : largeur de carte, centre, suivi, capteur du satellite. Exemple : une carte de 3 km de large centrée sur le point regardé."],
["LAY", "DISPOSITION", 8752, 8762, "Contenu de toutes les dalles d'un mur ou d'un écran incurvé, d'un coup. Exemple : Quad (carte, satellite, caméras, réseau)."],
["TXT", "TEXTE", 8753, 8763, "Messages d'un bandeau à LED ou d'un écran en affichage Texte : file des messages, couleur, durée, défilement. Exemple : EXERCICE EN COURS en ambre."],
["PWR", "ALIMENTATION", 8754, 8764, "Allumée, en veille ou éteinte, luminosité, distance d'affichage et mise en veille automatique. Exemple : 50 % de luminosité la nuit."],
["ADV", "AVANCÉ", 8755, 8765, "Nom, salle, titre de diffusion, droits de la salle et diagnostic de l'écran. Exemple : renommer l'écran en MUR NORD."]
];
        // ---- FIN
        _tabs
    };
    case "families": {
        // ---- TABLE _families (JSON) : familles de sources, dans l'ordre de la liste [titre, famille, aide].
        private _families = [
["CARTES", "MAP", "Carte de situation, avec ou sans le flux du camp. Exemple : Carte seule sur la dalle centrale."],
["CAMÉRAS", "CAM", "Caméras en direct du camp : drones, caméras fixes, caméras poitrine, mosaïques. Exemple : Caméra fixe · PORTE NORD."],
["SATELLITE-ISR", "SAT", "Les deux satellites du camp, vus de très haut. Exemple : satellite 1 en thermique sur l'écran de gauche."],
["DONNÉES", "DATA", "Tableaux du poste : flux, réseau, ORBAT, main courante, exécution, demandes, pertes, escouades. Exemple : escouade Alpha 1-1."],
["TEMPS", "TIME", "Horloges ZULU et locale, chronos, bandeau horloge. Exemple : Horloge sur le bandeau au-dessus du mur."],
["PLANS", "PLAN", "Le plan du camp dessiné sur la carte (zones, lignes de phase, axes), cadré en entier ou sur un élément. Exemple : Plan du camp en entier."],
["DIFFUSION", "BC", "La mire, ou la recopie d'un autre écran de la mission. Exemple : recopie de MUR NORD sur chaque poste."],
["TEXTE", "TEXT", "Panneau à LED : vos messages, à écrire dans l'onglet TEXTE. Exemple : RÉUNION 14:00 Z."],
["SPÉCIAL", "MISC", "Plaque de nom, éteindre la dalle. Exemple : éteint (veille) sur la dalle 4."]
];
        // ---- FIN
        _families
    };
    case "fields": {
        // ---- TABLE _fields (JSON) : champs de l'inspecteur [clé, onglet, genre, libellé, aide avec un exemple].
        // Genres : cmb liste déroulante · sld:min:max:pas curseur et saisie · tbx:Classe choix exclusif · chk case · edt saisie
        // · mle saisie sur plusieurs lignes · txt ligne d'information · btn:action,action… rangée de boutons.
        private _fields = [
["vFrame", "VIEW", "cmb", "Cadrage", "Cadrage tout prêt de la carte ou du satellite. Exemple : secteur (1,5 km)."],
["vWidth", "VIEW", "sld:0:100:1", "Largeur montrée", "Largeur de terrain visible sur la dalle, en mètres ; la molette sur la vignette fait la même chose. Exemple : 3000 pour voir 3 km."],
["vCenter", "VIEW", "tbx:COMSPEC_RscTocMgrTbxCenter", "Centre", "Où centrer l'image : inchangé, le point que vous regardez, ou votre position. Exemple : POINT REGARDÉ, en visant le village."],
["vFollow", "VIEW", "cmb", "Suivi", "Ce que la vue garde au centre : rien, tout le réseau du camp (carte), ou un groupe (satellite). Exemple : suivre le groupe Alpha 1-1."],
["vSensor", "VIEW", "cmb", "Capteur", "Capteur du satellite : automatique, jour, vision nocturne, thermique, fusion. Exemple : thermique blanc chaud la nuit."],
["vJit", "VIEW", "chk", "Dérive de la plateforme", "L'image du satellite bouge un peu, comme une vraie plateforme. Exemple : décochez pour une image fixe pendant un briefing."],
["vTerr", "VIEW", "chk", "Suivi du relief", "Le satellite garde sa hauteur au-dessus du relief. Exemple : cochez en montagne."],
["vPan", "VIEW", "btn:panW,panN,panS,panE", "Visée", "Déplace la visée du satellite d'un quart d'image, tout de suite. Exemple : NORD deux fois pour remonter d'une demi-image."],
["vGrid", "VIEW", "tbx:COMSPEC_RscTocMgrTbxGrid", "Mosaïque", "Nombre de caméras montrées ensemble. Exemple : 3 × 3 pour neuf caméras."],
["vRot", "VIEW", "cmb", "Rotation des pages", "Quand il y a plus de caméras que de cases, durée de chaque page. Exemple : 20 s."],
["vQual", "VIEW", "txt", "Qualité de l'image", "Réglage de chaque joueur (Options, Addons, COMSPEC ATAK) : qualité de la vue satellite. Exemple : Basse sur un PC modeste."],
["vNone", "VIEW", "txt", "", "Cette source n'a pas de réglage de vue. Exemple : une horloge se règle au PC du poste, pas ici."],
["lName", "LAY", "edt", "Nom à garder", "Nom sous lequel garder le contenu actuel des dalles de cet écran. Exemple : BRIEFING."],
["lBtns", "LAY", "btn:laySave,layDel,feeds", "", "GARDER enregistre le contenu actuel des dalles sous le nom écrit ; OUBLIER retire la disposition gardée choisie ; FLUX STANDARD donne un flux type à chaque dalle visée. Exemple : GARDER sous BRIEFING."],
["tBtns", "TXT", "btn:msgAdd,msgSet,msgDel,msgUp,msgDown,msgClear", "", "AJOUTER met le message écrit à la fin de la file ; REMPLACER change le message choisi ; RETIRER l'enlève ; MONTER et DESCENDRE changent l'ordre ; VIDER efface tout. Exemple : écrire puis AJOUTER."],
["tColor", "TXT", "tbx:COMSPEC_RscTocMgrTbxColor", "Couleur", "Couleur du message choisi. Exemple : Rouge pour une alerte."],
["tSecs", "TXT", "cmb", "Durée", "Temps pendant lequel le message reste avant le suivant. Exemple : 20 s."],
["tBlink", "TXT", "chk", "Clignotant (alerte)", "Le message clignote. Exemple : ALERTE NRBC."],
["tSpeed", "TXT", "tbx:COMSPEC_RscTocMgrTbxSpeed", "Vitesse", "Vitesse du défilement d'un texte trop long. Exemple : Lent pour un long message."],
["tScroll", "TXT", "tbx:COMSPEC_RscTocMgrTbxScroll", "Défilement", "Défiler seulement si le texte dépasse, ou toujours. Exemple : Toujours défiler pour attirer l'œil."],
["tAlign", "TXT", "tbx:COMSPEC_RscTocMgrTbxAlign", "Alignement", "Place d'un texte qui tient sur le panneau. Exemple : Centré."],
["tBorder", "TXT", "chk", "Bordure fine", "Filet autour du panneau. Exemple : décochez sur un bandeau très étroit."],
["tSaved", "TXT", "cmb", "Messages enregistrés", "Messages tout prêts : le choix remplit la saisie. Exemple : EXERCICE EN COURS."],
["tVar", "TXT", "cmb", "Variables", "Valeur écrite à votre place dans le message : le choix l'ajoute à la saisie. Exemple : {zulu} écrit l'heure ZULU."],
["tPrev", "TXT", "txt", "Aperçu", "Le message choisi, dans sa couleur. Exemple : OPÉRATION HORIZON · H-00:42."],
["tNone", "TXT", "txt", "", "Cette dalle ne peut pas montrer de texte. Exemple : un bandeau horloge ne montre que l'heure et la plaque."],
["pPower", "PWR", "tbx:COMSPEC_RscTocMgrTbxPower", "Alimentation", "ALLUMÉE ; EN VEILLE : éteinte, elle se rallume seule quand quelqu'un approche ; ÉTEINTE : elle le reste. La source est gardée. Exemple : VEILLE sur les écrans d'une salle vide."],
["pBright", "PWR", "sld:25:100:5", "Luminosité", "Image assombrie pour une salle dans le noir, de 25 à 100 %. Exemple : 50 la nuit."],
["pDist", "PWR", "sld:0:150:5", "Distance d'affichage", "Distance en mètres sous laquelle l'écran dessine son image ; 0 : le réglage de chaque joueur. Exemple : 40 pour un mur géant."],
["pAuto", "PWR", "cmb", "Veille automatique", "Délai sans personne près de l'écran avant sa mise en veille. Exemple : 15 min."],
["pState", "PWR", "txt", "État du rendu", "Ce que fait cet écran sur votre poste : en direct, figé, en veille ou éteint. Exemple : lointaine, image figée."],
["pAll", "PWR", "btn:allOn,allOff", "Toute la salle", "Allume ou éteint d'un coup tous les écrans de la salle de la dalle choisie. Exemple : TOUT ÉTEINDRE en fin de mission."],
["aName", "ADV", "edt", "Nom", "Nom de l'écran, écrit dans les listes et sur son image. Exemple : MUR NORD."],
["aHall", "ADV", "cmb", "Salle", "Salle à laquelle l'écran est lié. Exemple : SALLE TOC BRAVO."],
["aTitle", "ADV", "edt", "Titre de diffusion", "Texte écrit sur la mire et à côté du témoin EN DIRECT. Exemple : TOC ALPHA · CANAL 1."],
["aTally", "ADV", "chk", "Témoin EN DIRECT", "Montre le témoin EN DIRECT sur un écran en diffusion. Exemple : cochez pendant un briefing retransmis."],
["aBc", "ADV", "btn:bcOn,bcOff", "Diffusion", "DIFFUSER montre la dalle choisie sur tous les écrans 16:9 de sa salle ; ARRÊTER rend à chacun son affichage. Exemple : le mur recopié sur chaque poste."],
["aAccess", "ADV", "txt", "Droits", "Qui peut régler les écrans de cette salle, et son verrou. Exemple : chef de poste."],
["aLock", "ADV", "btn:lockOn,lockOff", "Verrou de la salle", "Gèle les écrans et les salles pour tous sauf vous et le Zeus. Exemple : VERROUILLER pendant un briefing."],
["aDiag", "ADV", "txt", "Diagnostic", "Qui tient l'écran (serveur ou joueurs), son format et son image. Exemple : serveur · 16:9 · image en direct."],
["aThumbs", "ADV", "chk", "Vignettes en direct", "Montre dans le schéma l'image déjà rendue de chaque dalle ; décoché : des cases sans image. Exemple : décochez si les vignettes s'affichent mal."],
["aTools", "ADV", "btn:test,classic", "Outils", "TESTER L'IMAGE montre une image de test 8 s sur la dalle ; ANCIEN TABLEAU rouvre le tableau d'avant. Exemple : TESTER pour repérer une dalle."]
];
        // ---- FIN
        _fields
    };
    case "field": { ((["fields"] call _fn) select { (_x select 0) isEqualTo _a }) param [0, []] };
    case "acts": {
        // ---- TABLE _acts (JSON) : boutons [clé, libellé, aide avec un exemple, réservé à qui peut poser].
        private _acts = [
["load", "CHARGER", "Remet la scène ou le préréglage choisi dans la liste. Exemple : VEILLE COURANTE.", false],
["save", "ENREGISTRER", "Garde l'état actuel des écrans sous un nom : scène de la salle choisie, sinon préréglage de votre profil. Exemple : BRIEFING.", false],
["rooms", "SALLES", "Liste des salles : créer, générer, renommer, supprimer, liaisons. Exemple : générer une salle moyenne devant vous.", false],
["log", "JOURNAL", "Qui a changé quoi dans le TOC. Exemple : voir qui a éteint le mur.", false],
["links", "LIAISONS", "Au Zeus : montre ou cache dans le monde les traits entre écrans, postes, caméras, mobilier et leur salle. Exemple : repérer un écran oublié hors de la salle.", false],
["close", "FERMER", "Ferme la fenêtre. Exemple : Échap fait la même chose quand rien n'est en attente.", false],
["add", "AJOUTER", "Pose un écran du type choisi au-dessus, devant vous (Zeus : à la caméra ; Eden : au centre de la vue). Exemple : un écran 98 pouces sur pied.", true],
["dup", "DUPLIQUER", "Pose une copie de l'écran choisi, 2 m à sa droite, avec la même source. Exemple : un second écran satellite.", true],
["del", "SUPPRIMER", "Retire l'écran choisi, après confirmation. Exemple : un écran posé au mauvais endroit.", true],
["go", "ALLER À L'ÉCRAN", "Amène la caméra (Zeus, Eden) devant l'écran choisi ; en jeu : donne sa distance et son cap. Exemple : retrouver un écran dans une grande base.", false],
["blink", "IDENTIFIER", "L'écran choisi clignote 2 s chez les joueurs proches. Exemple : savoir lequel est ÉCRAN GAUCHE.", false],
["reset", "RÉINITIALISER", "Remet les dalles visées à leur flux standard, allumées, à 100 % de luminosité. Exemple : après des essais.", false],
["copy", "COPIER VERS…", "Garde la source et la luminosité de la dalle choisie : cliquez ensuite chaque dalle qui doit les recevoir, Échap pour finir. Exemple : la même caméra sur trois dalles.", false],
["cancel", "ANNULER", "Abandonne les changements non appliqués. Exemple : après un mauvais réglage.", false],
["apply", "APPLIQUER", "Applique les changements aux dalles de la portée choisie. Exemple : Entrée fait la même chose.", false],
["panW", "OUEST", "Visée du satellite vers l'ouest. Exemple : un quart d'image à gauche.", false],
["panN", "NORD", "Visée du satellite vers le nord. Exemple : un quart d'image vers le haut.", false],
["panS", "SUD", "Visée du satellite vers le sud. Exemple : un quart d'image vers le bas.", false],
["panE", "EST", "Visée du satellite vers l'est. Exemple : un quart d'image à droite.", false],
["laySave", "GARDER", "Garde le contenu actuel des dalles sous le nom écrit. Exemple : BRIEFING.", false],
["layDel", "OUBLIER", "Retire la disposition gardée choisie dans la liste. Exemple : une disposition d'essai.", false],
["feeds", "FLUX STANDARD", "Donne un flux type à chaque dalle visée : mur = situation, côtés = satellite, caméras, réseau, bandeaux = horloge et texte. Exemple : après avoir généré une salle.", false],
["msgAdd", "AJOUTER", "Met le message écrit à la fin de la file (8 au plus). Exemple : RÉUNION 14:00 Z.", false],
["msgSet", "REMPLACER", "Remplace le message choisi par le texte écrit. Exemple : corriger une heure.", false],
["msgDel", "RETIRER", "Retire le message choisi de la file. Exemple : une alerte levée.", false],
["msgUp", "MONTER", "Fait passer le message choisi avant le précédent. Exemple : l'alerte en premier.", false],
["msgDown", "DESCENDRE", "Fait passer le message choisi après le suivant. Exemple : l'information en dernier.", false],
["msgClear", "VIDER", "Efface tous les messages : l'écran montre l'invite à écrire. Exemple : fin d'exercice.", false],
["allOn", "TOUT ALLUMER", "Allume tous les écrans de la salle. Exemple : début de mission.", false],
["allOff", "TOUT ÉTEINDRE", "Éteint tous les écrans de la salle. Exemple : fin de mission.", false],
["bcOn", "DIFFUSER DANS LA SALLE", "Tous les écrans 16:9 de la salle montrent la dalle choisie. Exemple : le mur recopié sur chaque poste.", false],
["bcOff", "ARRÊTER LA DIFFUSION", "Chaque écran de la salle reprend son affichage d'avant. Exemple : fin du briefing.", false],
["lockOn", "VERROUILLER", "Verrouille la salle de la dalle choisie. Exemple : pendant un briefing.", false],
["lockOff", "DÉVERROUILLER", "Lève le verrou de la salle. Exemple : fin du briefing.", false],
["test", "TESTER L'IMAGE", "Image de test 8 s sur la dalle choisie, sur votre poste. Exemple : repérer la dalle 3.", false],
["classic", "ANCIEN TABLEAU", "Rouvre le tableau des écrans d'avant, une ligne par dalle. Exemple : si cette fenêtre s'affiche mal.", false],
["roomNew", "CRÉER", "Crée une salle vide du nom écrit. Exemple : SALLE BRIEFING.", true],
["roomGen", "GÉNÉRER", "Pose devant vous une salle complète de la taille choisie, écrans et postes déjà liés. Exemple : salle moyenne, 11 × 8,5 m.", true],
["roomName", "RENOMMER", "Donne le nom écrit à la salle choisie. Exemple : SALLE TOC BRAVO.", false],
["roomFeeds", "TOUS LES FLUX", "Donne un flux type à chaque écran de la salle choisie. Exemple : après GÉNÉRER.", false],
["roomDel", "SUPPRIMER LA SALLE", "Supprime la salle choisie, après confirmation ; vous choisissez de garder ses écrans ou non. Exemple : retirer SALLE TOC 2.", true],
["resetAll", "TOUT RÉINITIALISER", "Remet tous les écrans à leur flux standard, allumés, à 100 %, et arrête les diffusions, après confirmation. Exemple : repartir d'un état propre.", true],
["logRefresh", "ACTUALISER", "Redemande le journal au serveur. Exemple : après une action d'un autre opérateur.", false]
];
        // ---- FIN
        _acts
    };
    case "family": {
        private _h = if (_a isEqualType "") then { (_a splitString "|") param [0, _a] } else { "" };
        switch (true) do {
            case (_h in ["SPLIT", "MAP", "TOC"]): { "MAP" };
            case (_h in ["CAM", "MOS", "MOSAIC", "COLC"]): { "CAM" };
            case (_h in ["SAT", "SAT1", "SAT2"]): { "SAT" };
            case (_h in ["CLOCK", "STRIP", "COLA"]): { "TIME" };
            case (_h in ["BARS", "BC", "BROADCAST"]): { "BC" };
            case (_h isEqualTo "PLAN"): { "PLAN" };
            case (_h isEqualTo "TEXT"): { "TEXT" };
            case (_h in ["OFF", "PLATE"]): { "MISC" };
            default { "DATA" };
        }
    };
    case "viewOf": {
        _a params [["_k", ""], ["_ok", []]];
        private _h = (_k splitString "|") param [0, _k];
        switch (true) do {
            case (_k isEqualTo "OFF"): { "" };
            case (_h in ["SAT1", "SAT2"]): { "SAT" };
            case (_h isEqualTo "SQ"): { ["SQUAD", "PLATE"] select (!("SQUAD" in _ok) && {"PLATE" in _ok}) };
            case (_h isEqualTo "MOS"): { "MOSAIC" };
            case (_h in ["BARS", "BC"]): { "BROADCAST" };
            case (_h isEqualTo "PLAN"): { "MAP" };
            default { _h };
        }
    };
    case "kindOf": {
        private _h = if (_a isEqualType "") then { (_a splitString "|") param [0, _a] } else { "" };
        switch (true) do {
            case (_h in ["SPLIT", "MAP", "TOC", "SQ", "SQUAD", "PLAN"]): { "map" };
            case (_h in ["SAT", "SAT1", "SAT2"]): { "sat" };
            case (_h in ["MOS", "MOSAIC"]): { "mos" };
            case (_h isEqualTo "CAM"): { "cam" };
            case (_h isEqualTo "TEXT"): { "text" };
            default { "other" };
        }
    };
    case "sourceList": {
        _a params [["_items", []], ["_used", []], ["_find", ""]];
        _find = toLower (trim _find);
        private _usedMap = createHashMap;
        { _usedMap set [_x param [0, ""], _x param [1, []]]; } forEach _used;
        (["families"] call _fn) apply {
            private _title = _x select 0;
            private _id = _x select 1;
            private _rows = [];
            {
                _x params [["_lbl", ""], ["_key", ""], ["_state", ""]];
                if ((["family", _key] call _fn) isEqualTo _id && {_find isEqualTo "" || {((toLower _lbl) find _find) >= 0}}) then {
                    _rows pushBack [_lbl, _key, _state, ((_usedMap getOrDefault [_key, []]) select [0, 4]) joinString ", "];
                };
            } forEach _items;
            [_title, _id, count _rows, _rows]
        }
    };
    case "plan": {
        _a params [["_changes", []], ["_targets", []]];
        // ---- TABLE _order (JSON) : ordre d'exécution des champs [champ, portée "pane" (chaque dalle) | "host" (une fois
        // par écran) | "one" (une seule dalle visée, sinon ignoré), genre de source exigé ("" : aucun)].
        private _order = [
["power", "pane", ""], ["source", "pane", ""], ["layout", "host", ""],
["frame", "pane", "map,sat"], ["width", "pane", "map,sat"], ["center", "pane", "map,sat"], ["follow", "pane", "map,sat"],
["sensor", "pane", "sat"], ["jit", "pane", "sat"], ["terr", "pane", "sat"], ["mosaic", "pane", "mos"],
["bright", "host", ""], ["dist", "host", ""], ["autoOff", "host", ""], ["text", "pane", ""],
["name", "one", ""], ["hall", "host", ""], ["title", "one", ""], ["tally", "pane", ""]
];
        // ---- FIN
        private _ch = createHashMap;
        { _ch set [_x param [0, ""], _x param [1, ""]]; } forEach _changes;
        private _newSrc = _ch getOrDefault ["source", ""];
        private _ops = [];
        {
            _x params ["_field", "_scope", "_need"];
            if !(_field in _ch) then { continue };
            private _val = _ch get _field;
            if (_scope isEqualTo "one" && {(count _targets) isNotEqualTo 1}) then { _ops pushBack ["skip", _field, "multi"]; continue };
            private _done = [];
            {
                _x params [["_key", ""], ["_host", ""], ["_ok", []], ["_src", ""]];
                private _id = [_key, _host] select (_scope isEqualTo "host");
                if (_id in _done) then { continue };
                switch (true) do {
                    case (_field isEqualTo "source"): {
                        private _view = ["viewOf", [_val, _ok]] call _fn;
                        if (_view isEqualTo "" || {_view in _ok}) then { _ops pushBack ["source", _id, _val]; } else { _ops pushBack ["refuse", _id, _val]; };
                    };
                    case (_field isEqualTo "text"): {
                        if ("TEXT" in _ok) then { _ops pushBack ["text", _id, _val]; } else { _ops pushBack ["refuse", _id, "TEXT"]; };
                    };
                    default {
                        // Source après le changement : celle demandée si la dalle l'accepte, sinon celle qu'elle montre.
                        private _eff = _src;
                        if (_newSrc isNotEqualTo "") then {
                            private _nv = ["viewOf", [_newSrc, _ok]] call _fn;
                            if (_nv isNotEqualTo "" && {_nv in _ok}) then { _eff = _newSrc; };
                        };
                        if (_need isEqualTo "" || {(["kindOf", _eff] call _fn) in (_need splitString ",")}) then { _ops pushBack [_field, _id, _val]; };
                    };
                };
                _done pushBack _id;
            } forEach _targets;
        } forEach _order;
        _ops
    };
    case "confirm": {
        _a params [["_state", ["", "", true]], ["_ev", ""]];
        _state params [["_kind", ""], ["_id", ""], ["_keep", true]];
        private _p = _ev splitString ":";
        switch (true) do {
            // La cible garde ses deux-points (un netId s'écrit « 2:14 »).
            case ((_p param [0, ""]) isEqualTo "ask"): { [[_p param [1, ""], (_p select [2]) joinString ":", true], ""] };
            case (_kind isEqualTo ""): { [["", "", true], ""] };
            case (_ev isEqualTo "toggle"): { [[_kind, _id, !_keep], ""] };
            case (_ev isEqualTo "ok"): {
                private _act = switch (_kind) do {
                    case "room": { (["roomRemove:", "roomDissolve:"] select _keep) + _id };
                    case "resetAll": { "resetAll:" };
                    case "screen": { "screenDelete:" + _id };
                    case "scene": { "sceneSave:" + _id };
                    default { "" };
                };
                [["", "", true], _act]
            };
            // Annuler, Échap et Entrée (le bouton par défaut est ANNULER) : rien n'est fait.
            default { [["", "", true], ""] };
        }
    };
    case "grid": {
        _a params [["_cls", ""], ["_n", 1]];
        _n = _n max 1;
        private _m = _cls regexFind ["([0-9])x([0-9])"];
        if (_m isEqualTo []) exitWith { [_n, 1] };
        private _cols = parseNumber (((_m select 0) select 1) select 0);
        private _rws = parseNumber (((_m select 0) select 2) select 0);
        if (_cols * _rws < _n || {_cols < 1}) exitWith { [_n, 1] };
        [_cols, _rws]
    };
    // Curseur de largeur : position 0 à 100 <-> 60 m à 20 km, échelle logarithmique (précis sur les petites largeurs).
    case "w2p": { ((100 * (ln (((_a max 60) min 20000) / 60)) / (ln (20000 / 60))) max 0) min 100 };
    case "p2w": { round (60 * ((20000 / 60) ^ (((_a max 0) min 100) / 100))) };
    case "selfTest": {
        // ---- TABLE _planCases (JSON) : [nom, changements, cibles, opérations attendues].
        private _planCases = [
["source acceptée puis vue", [["width", 3000], ["source", "MAP"]], [["a", "h", ["MAP", "SAT"], "CLOCK"]], [["source", "a", "MAP"], ["width", "a", 3000]]],
["source refusée par un bandeau", [["source", "SAT1"]], [["a", "h", ["STRIP", "TEXT"], "STRIP"], ["b", "h", ["MAP", "SAT"], "MAP"]], [["refuse", "a", "SAT1"], ["source", "b", "SAT1"]]],
["éteindre est toujours permis", [["source", "OFF"]], [["a", "h", ["STRIP"], "STRIP"]], [["source", "a", "OFF"]]],
["luminosité une fois par écran", [["bright", 50]], [["a", "h", ["MAP"], "MAP"], ["b", "h", ["MAP"], "MAP"], ["c", "c", ["MAP"], "MAP"]], [["bright", "h", 50], ["bright", "c", 50]]],
["nom réservé à une seule dalle", [["name", "MUR"], ["bright", 75]], [["a", "h", ["MAP"], "MAP"], ["b", "h", ["MAP"], "MAP"]], [["bright", "h", 75], ["skip", "name", "multi"]]],
["capteur seulement sur un satellite", [["sensor", "NV"]], [["a", "h", ["MAP", "SAT"], "SAT2"], ["b", "h", ["MAP", "SAT"], "MAP"]], [["sensor", "a", "NV"]]],
["alimentation avant la source", [["source", "CLOCK"], ["power", 0]], [["a", "a", ["CLOCK"], "OFF"]], [["power", "a", 0], ["source", "a", "CLOCK"]]],
["texte refusé hors panneau", [["text", "T"]], [["a", "a", ["MAP"], "MAP"], ["b", "b", ["TEXT"], "TEXT"]], [["refuse", "a", "TEXT"], ["text", "b", "T"]]],
["escouade sur une plaque", [["source", "SQ|Alpha 1-1"]], [["a", "a", ["PLATE", "STRIP"], "PLATE"]], [["source", "a", "SQ|Alpha 1-1"]]],
["plan = carte", [["source", "PLAN|ALL"], ["width", 800]], [["a", "a", ["MAP"], "CLOCK"]], [["source", "a", "PLAN|ALL"], ["width", "a", 800]]]
];
        // ---- FIN
        // ---- TABLE _confirmCases (JSON) : [nom, événements dans l'ordre, action attendue à la fin].
        private _confirmCases = [
["supprimer une salle garde ses écrans par défaut", ["ask:room:H1", "ok"], "roomDissolve:H1"],
["supprimer aussi les écrans se choisit", ["ask:room:H1", "toggle", "ok"], "roomRemove:H1"],
["deux bascules reviennent à garder", ["ask:room:H1", "toggle", "toggle", "ok"], "roomDissolve:H1"],
["Entrée annule (bouton par défaut)", ["ask:room:H1", "enter"], ""],
["Échap annule", ["ask:room:H1", "toggle", "esc"], ""],
["annuler puis confirmer ne fait rien", ["ask:room:H1", "cancel", "ok"], ""],
["confirmer sans question ne fait rien", ["ok"], ""],
["tout réinitialiser", ["ask:resetAll:", "ok"], "resetAll:"],
["supprimer un écran", ["ask:screen:2:14", "ok"], "screenDelete:2:14"],
["enregistrer une scène", ["ask:scene:H1", "ok"], "sceneSave:H1"],
["une nouvelle question remplace l'ancienne", ["ask:room:H1", "ask:room:H2", "ok"], "roomDissolve:H2"]
];
        // ---- FIN
        // ---- TABLE _listCases (JSON) : [nom, sources, usages, recherche, nombres attendus par famille (ordre de _families)].
        private _listCases = [
["chaque source dans sa famille", [["Carte et flux", "SPLIT", ""], ["Caméra · TA1", "CAM|2:3", "ok"], ["satellite 1", "SAT1", ""], ["escouade Alpha", "SQ|Alpha", ""], ["Horloge", "CLOCK", ""], ["Plan du camp", "PLAN|ALL", ""], ["mire", "BARS", ""], ["Texte", "TEXT", ""], ["éteint", "OFF", ""]], [], "", [1, 1, 1, 1, 1, 1, 1, 1, 1]],
["la recherche filtre sans la casse", [["Carte et flux", "SPLIT", ""], ["Carte seule", "MAP", ""], ["Horloge", "CLOCK", ""]], [], "CARTE", [2, 0, 0, 0, 0, 0, 0, 0, 0]],
["familles vides gardées", [], [], "", [0, 0, 0, 0, 0, 0, 0, 0, 0]],
["mosaïque et colonne caméras", [["mosaïque 2 × 2", "MOS|2|AUTO", ""], ["Colonne caméras", "COLC", ""], ["Radio", "RADIO", ""]], [["MOS|2|AUTO", ["MUR 1", "MUR 2"]]], "", [0, 2, 0, 1, 0, 0, 0, 0, 0]]
];
        // ---- FIN
        private _bad = [];
        private _okN = 0;
        {
            _x params ["_name", "_chg", "_tg", "_want"];
            if ((["plan", [_chg, _tg]] call _fn) isEqualTo _want) then { _okN = _okN + 1; } else { _bad pushBack _name; };
        } forEach _planCases;
        {
            _x params ["_name", "_evs", "_want"];
            private _state = ["", "", true];
            private _act = "";
            { (["confirm", [_state, _x]] call _fn) params ["_s2", "_a2"]; _state = _s2; _act = _a2; } forEach _evs;
            if (_act isEqualTo _want) then { _okN = _okN + 1; } else { _bad pushBack _name; };
        } forEach _confirmCases;
        {
            _x params ["_name", "_items", "_used", "_find", "_want"];
            if (((["sourceList", [_items, _used, _find]] call _fn) apply { _x select 2 }) isEqualTo _want) then { _okN = _okN + 1; } else { _bad pushBack _name; };
        } forEach _listCases;
        [_okN, _bad]
    };
    default { [] };
};
