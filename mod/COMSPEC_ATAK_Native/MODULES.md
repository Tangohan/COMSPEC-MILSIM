# COMSPEC Modules : créer une app pour le téléphone COMSPEC ATAK

Un module est un addon Arma 3 séparé qui ajoute une app au téléphone, ou un calque à sa carte,
sans modifier COMSPEC ATAK. Il se charge à côté de `@COMSPEC_ATAK_Native`.

## 1. Déclarer l'app

Dans le `config.cpp` de votre addon :

```cpp
class CfgPatches {
    class mon_module {
        name = "Mon module COMSPEC";
        requiredAddons[] = {"comspec_atak_native_main"};
        units[] = {}; weapons[] = {};
        requiredVersion = 2.14;
    };
};

class CfgFunctions {
    class mon_module {
        class main {
            file = "\mon_module\functions";
            class pageMeteoLocale {};
            class calqueCarte {};
        };
    };
};

// Ajoute une app au lanceur du téléphone.
class COMSPEC_ATAK_Apps {
    class MonModule {
        name = "Météo locale";           // nom sous l'icône
        page = "MONMODULE";              // identifiant de page, unique, en majuscules
        section = "Mission";             // section du lanceur (Opérations, Renseignement, Mission, Système…)
        order = 400;                     // position dans la section
        dock = 0;                        // 1 = proposée dans le dock
        icon = "\mon_module\data\icon.paa";
        function = "mon_module_fnc_pageMeteoLocale";   // fonction qui dessine la page
    };
};

// Optionnel : dessine sur la carte du téléphone à chaque image.
class COMSPEC_ATAK_MapLayers {
    class MonCalque { function = "mon_module_fnc_calqueCarte"; };
};
```

## 2. Dessiner la page

La fonction reçoit `[page, [0, 0, largeur, hauteur]]`. Le plus simple est le formulaire défilant
du téléphone, qui gère les thèmes, la taille de police et le défilement :

```sqf
// mon_module\functions\fn_pageMeteoLocale.sqf
params ["_page", "_rect"];
private _rows = [
    ["title", "Météo locale"],
    ["info", "Vent", format ["%1 m/s", round (vectorMagnitude wind)]],
    ["info", "Brouillard", format ["%1 %%", round (fog * 100)]],
    ["buttons", [["ACTUALISER", { ["MONMODULE"] call comspec_atak_native_fnc_pageRender; }, true]]]
];
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
```

Types de lignes disponibles : `title`, `text`, `edit`, `memo`, `combo`, `toggle`, `switch`, `segment`,
`buttons`, `section`, `info`, `hero`, `person`, `image`, `gap`. Le détail est en tête de
`functions/ui/fn_formRender.sqf`. Une valeur saisie se relit avec `["clé", défaut] call comspec_atak_native_fnc_formValue`.

Pour une mise en page libre, créez vos contrôles avec
`["COMSPEC_RscButton", [x, y, l, h], "TEXTE"] call comspec_atak_native_fnc_pageCtrl` :
ils sont supprimés automatiquement au changement de page.

## 3. Fonctions utiles

| Fonction | Rôle |
|---|---|
| `["INFO", "texte", durée, priorité] call comspec_atak_native_fnc_notify` | notification du téléphone (INFO, SUCCESS, WARNING, MESSAGE) |
| `[] call comspec_atak_native_fnc_vibrate` | vibration |
| `["PAGE"] call comspec_atak_native_fnc_navigate` | ouvre une autre app |
| `[code, arguments, "libellé", Ko] call comspec_atak_native_fnc_netSend` | envoi soumis au réseau simulé (délai, pertes, file sans réseau) |
| `[] call comspec_atak_native_fnc_linkQuality` | qualité du signal (barres, débit, latence) |
| `[unité] call comspec_atak_native_fnc_unitGroup` | groupe Athena d'une unité |
| `[position, 8] call comspec_atak_native_fnc_gridRef` | grille à 6, 8 ou 10 chiffres |
| `[player] call comspec_atak_native_fnc_hasDevice` | le joueur a-t-il un téléphone |

Événement CBA local `comspec_atak_native_pageOpened` : `[page]`, émis à chaque affichage d'app.

## 4. Règles

- Restez réaliste : le téléphone ne sait que ce qu'il peut capter. Passez vos envois par `netSend`.
- Ne modifiez pas les variables `COMSPEC_ATAK_*` du téléphone : lisez-les seulement.
- Un serveur peut retirer n'importe quelle app, module compris, avec le réglage CBA
  « Apps désactivées » (noms de classe séparés par des virgules).
- Publiez votre module sous votre propre licence. COMSPEC ATAK reste sous sa licence (LICENSE, tous droits réservés) : votre module ne doit pas en recopier le code.

## 5. Brouilleurs : brouiller l'ATAK depuis une mission ou un autre mod

Un brouilleur déclaré ici agit comme ceux de l'app Guerre électronique et du module Zeus « ATAK · Brouilleur de zone » :
débit, GPS, suivi des forces amies, drones, vidéo et relais dans son rayon (plein effet au centre, plus faible vers le
bord), et la goniométrie de l'app GE peut le relever.

```sqf
// Serveur (l'init d'un objet dans Eden tourne aussi sur le serveur). Ailleurs, la demande part au serveur.
// [source (objet ou position), rayon m, force 1-100, durée s (0 = sans fin), camp épargné ("" , "WEST", "EAST", "GUER", "CIV"), id facultatif]
if (isServer) then { ["add", [this, 800, 80]] call comspec_atak_native_fnc_jammer; };
private _id = ["add", [getMarkerPos "zone_ge", 1500, 60, 600, "EAST"]] call comspec_atak_native_fnc_jammer;
["remove", _id] call comspec_atak_native_fnc_jammer;   // ou ["remove", objet]
```

- Un objet brouilleur s'arrête quand il est détruit. Pour l'éteindre sans le retirer :
  `objet setVariable ["COMSPEC_ATAK_JamActive", false, true]` (la variable `a3f_jammer_active` des scripts
  « TFAR Radio Jammer » est aussi respectée).
- Sans script, l'admin peut désigner des objets ou véhicules d'autres mods dans le réglage CBA
  « Objets brouilleurs d'autres mods » : `Classe:rayon:force`, séparés par des virgules (classes parentes acceptées).
- Déjà pris en compte sans rien faire : brouilleurs du mod Crows Electronic Warfare, et scripts de brouillage radio TFAR
  qui baissent `tf_sendingDistanceMultiplicator` du joueur (réglage « Brouillage des scripts TFAR »).

**English.** Any mission or mod can jam the ATAK with `["add", [object or position, radius m, strength 1-100,
duration s (0 = endless), spared side, optional id]] call comspec_atak_native_fnc_jammer` (runs on the server; returns
the id) and `["remove", id or object]`. A jammer object stops when destroyed; set `COMSPEC_ATAK_JamActive` (or
`a3f_jammer_active`) to `false` on it to switch it off. Admins can also list jammer classes from other mods in the CBA
setting "Jammer objects from other mods" (`Class:radius:strength`). Crows Electronic Warfare jammers and TFAR jamming
scripts (via `tf_sendingDistanceMultiplicator`) are already picked up.

## 6. Écrans COMSPEC : diffuser les diapositives du briefing sur un objet du jeu

Le module Eden / Zeus « ATAK · Écran COMSPEC » (catégorie réseau) transforme les objets synchronisés (TV, écran,
ordinateur, projecteur, tableau) en écrans. Un téléphone à portée les voit dans l'app Briefing, onglet ÉCRANS :
DIFFUSER y affiche la diapositive en cours, qui suit ensuite les pages tournées sur le téléphone. ACE (ou la molette)
sur l'écran : diapositive suivante, précédente, arrêt. Chaque joueur voit l'image s'il a COMSPEC Link.

```sqf
// Serveur (l'init d'un objet dans Eden tourne aussi sur le serveur). Ailleurs, la demande part au serveur.
// [objet, nom, portée de détection m, camp qui le pilote ("" = tous), index de texture (0 le plus souvent), contrôle ACE]
if (isServer) then { ["add", [this, "Salle de briefing", 30]] call comspec_atak_native_fnc_screenCast; };
["remove", objet] call comspec_atak_native_fnc_screenCast;
```

**English.** The "ATAK · COMSPEC screen" Eden/Zeus module (or `["add", [object, name, range, side, textureIndex, ace]]
call comspec_atak_native_fnc_screenCast`) turns TVs, screens and projectors into screens the Briefing app can cast its
slides to (SCREENS tab), then controlled from the phone or with ACE on the screen.

## 7. Écrans TOC : le réseau ATAK du camp sur un grand écran du jeu

Le module Eden / Zeus « ATAK · Écran TOC » (catégorie réseau) affiche en direct, sur une TV ou un écran du jeu, le
réseau ATAK d'un camp tel que le serveur de la mission le voit : carte avec les nœuds (joueurs et IA équipées, à leur
dernier point PLI, en direct / ancien / perdu / hors ligne), marqueurs de la mission, comptes rendus et alertes
géolocalisés, et un panneau réseau (nœuds, groupes, flux des comptes rendus, MEDEVAC, BDA, messages du poste TOC).
Synchronisé avec des objets, il les désigne ; sans objet, il pose lui-même l'écran choisi (grand écran sur trépied,
TV, écran de projection, poste informatique). ACE (ou la molette) sur l'écran : changer d'affichage (carte et flux,
carte seule, flux seul, PIP du poste), zoomer, dézoomer, centrer, cadrer tout le réseau, et « Ouvrir le PC du poste »,
qui ouvre l'écran du PC portable du poste (l'écran sert aussi de PC de TOC, `fn_tocPost`).

**PIP du poste.** L'affichage « PIP du poste » recopie sur l'écran ce que l'opérateur a sous les yeux sur le PC
(`fn_tocLaptop`, variable publique `COMSPEC_ATAK_TocView` de son unité) : même cadrage de carte, onglet en cours,
groupe ou compte rendu choisi, objectif de l'ordre en préparation. Sans opérateur, l'écran revient de lui-même à
carte et flux.

**PC du poste (`fn_tocLaptop`).** L'action « PC du poste TOC » (ACE ou molette, 2,5 m d'un PC de TOC, 4,5 m d'un
écran TOC) ouvre l'écran du PC lui-même, sans téléphone : vraie carte d'Arma en grand avec les marqueurs de la
mission, les nœuds ATAK du camp, les comptes rendus géolocalisés et le poste ; onglets GROUPES (chef, effectif,
nœuds, grille, ordre au groupe, message au chef), FLUX (C.R., alertes, MEDEVAC, BDA, ordres, messages),
ORDRES (type, destinataire, priorité, consigne, objectif par clic droit sur la carte, suivi des réponses) et
CAMP (message à tout le camp ou à un chef de groupe, indicatif du poste). Tout vient du serveur : le PC est sur le
réseau filaire du poste, sans signal ni batterie de téléphone. Camp vide : chaque joueur voit le
réseau de son propre camp sur le même écran. Les écrans sont gardés d'une partie à l'autre (réglage « Garder les
écrans TOC ») : posés au Zeus, ils sont recréés à leur place ; ceux de la mission retrouvent affichage et cadrage.

Fond de carte : la photo de la carte si le mod @COMSPEC_ATAK_Photo est chargé, sinon la carte d'ensemble du terrain
(une carte Arma ne se dessine pas dans une texture UI). Image noire ou décalée sur un objet : changer l'index de texture.

```sqf
// Serveur (l'init d'un objet dans Eden tourne aussi sur le serveur). Ailleurs, la demande part au serveur.
// [objet, nom, camp ("" = celui de chaque joueur), index de texture, "SPLIT" | "MAP" | "FEED" | "TOC", sert de poste TOC]
if (isServer) then { ["add", [this, "TOC Bravo", "WEST", 0, "SPLIT", true]] call comspec_atak_native_fnc_tocScreen; };
["remove", objet] call comspec_atak_native_fnc_tocScreen;
// Pose d'un écran du jeu : [position ATL, direction, classe, nom, camp, affichage, poste TOC]
["spawn", [getPosATL player, getDir player, "COMSPEC_ATAK_Screen_Tripod", "TOC", "WEST"]] call comspec_atak_native_fnc_tocScreen;
```

**Écrans COMSPEC dédiés.** Catégorie Eden / Zeus « COMSPEC › Écrans TOC et projection » : les vrais écrans du jeu
(grand écran et double écran sur trépied, téléviseur, moniteur de bureau) réglés pour afficher une image : une seule
dalle (les dalles superposées du grand écran, noires et opaques, sont vides dès la config), matières d'écran du mod
(lisibles de jour comme de nuit), double écran : même image sur les deux dalles. Même menu ACE « Écran COMSPEC »
(Diffusion, Affichage TOC, Carte).

**Vidéoprojecteur et tableau (`fn_projector`).** Le vidéoprojecteur du jeu projette sur un tableau blanc du jeu (sur
trépied ou mural) à moins de 15 m. ACE › Vidéoprojecteur › Projecteur COMSPEC : Allumer / Éteindre, Projeter sur…
(tableaux en vue), puis Diffusion, Affichage TOC et Carte comme sur un écran, appliqués au tableau. Allumé sans tableau
choisi, il prend le plus proche dans l'axe de son objectif. L'image (16:9, posée devant la face blanche du côté du
projecteur, à sa taille et à son inclinaison, `tools/model_projsurface.py`) n'apparaît que si le projecteur est allumé,
intact, tourné vers le tableau et que rien ne coupe la ligne objectif - tableau (personne, mur, véhicule) ; sinon
tableau blanc. Attribut Eden « Allumé au lancement ».

```sqf
["power", [projo1, true]] call comspec_atak_native_fnc_projector;      // allume (tableau le plus proche dans l'axe de l'objectif)
["link", [projo1, tableau1]] call comspec_atak_native_fnc_projector;   // associe un tableau précis
["castOn", tableau1] call comspec_atak_native_fnc_screenCast;           // mes diapositives sur le tableau
["ensure", [tableau1, "MAP"]] call comspec_atak_native_fnc_tocScreen;   // carte du TOC sur le tableau
```

### Menu ACE des écrans TOC

Un seul arbre (`fn_tocMenu`, mode `tree`) sert au menu ACE « Écran COMSPEC » de chaque écran, aux actions de la
molette sans ACE (un niveau à la fois : « Écran TOC · Afficher · Carte et flux », « Retour » pour remonter) et au
tableau des écrans. Libellés courts, verbe d'abord ; l'état courant est écrit dans le libellé (« Luminosité : 75 % »,
« Salle : TOC 1 ») et la vue en cours est cochée (« Carte et flux ✓ ») ; 7 entrées au plus par niveau (au-delà :
« Suite… ») ; une entrée qui ne s'applique pas à l'écran n'est pas montrée (un bandeau 4:1 ne propose que bandeau,
plaque et texte ; « Disposition… » seulement sur un écran à plusieurs dalles). Les retours passent par le téléphone,
jamais par une fenêtre du jeu.

| Entrée | Aide (une ligne, avec un exemple) |
| --- | --- |
| **Ouvrir le PC du poste** | Ouvre le PC du poste TOC : carte, groupes, flux, ordres. Exemple : donner un ordre à Alpha depuis la salle. |
| **Afficher…** | Choisit ce que montre l'écran ; la vue en cours est cochée. Exemple : Carte et réseau, puis Carte seule. |
| Afficher › Carte et réseau… | Carte et flux, Carte seule, Flux seul, Réseau, ORBAT du site, PIP du poste, Vue suivante. Exemple : carte et flux pour l'écran principal. |
| Afficher › Tableaux du poste… | Horloge et chronos, Main courante, Liste d'exécution, Pertes et résultats, Demandes des éléments. Exemple : la main courante sur l'écran de droite. |
| Afficher › Diffusion et mire (BROADCAST) | Montre un autre écran ou, sans source, la mire. Exemple : le même écran recopié dans toute la salle. |
| Afficher › Texte à LED… | Ouvre la saisie du texte de l'écran. Exemple : « BRIEFING 21:00 ZULU » sur le bandeau. |
| Afficher › Bandeau horloge ZULU / LOCAL (bandeau 4:1) | Bandeau avec les deux heures. Exemple : au-dessus du mur d'écrans. |
| Afficher › Plaque au nom d'un groupe… (bandeau 4:1) | Plaque de nom d'un groupe du camp. Exemple : nommer l'écran d'une escouade. |
| Afficher › Colonne… (écran incurvé) | Colonne étroite : heures et chronos, ou réseau et derniers comptes rendus. Exemple : la colonne gauche d'un écran incurvé. |
| Afficher › Diapositives… | Diffuser mes diapositives, puis Diapositive suivante / précédente, Arrêter la diffusion. Exemple : briefing avant le départ. |
| Afficher › Cadrer la carte… | Zoomer, Dézoomer, Centrer sur ma position, Cadrer tout le réseau. Exemple : zoomer sur l'objectif. |
| Afficher › Sur une dalle du mur… | Choisit le contenu d'une seule dalle du mur (Dalle 1, Dalle 2…). Exemple : l'horloge sur la dalle 4. |
| **Disposition…** | Répartit les contenus sur les dalles du mur en une fois (`fn_tocLayout`) ; la disposition en cours est cochée. Exemple : une disposition de briefing. |
| **Source / caméra…** | Choisit la source montrée : satellite, escouade, caméra, mosaïque. Exemple : la caméra d'un drone. |
| Source › Satellite SAT 1 / SAT 2 | Vue satellite et suivi des alliés. Exemple : SAT 1 sur l'objectif, SAT 2 sur l'itinéraire. |
| Source › Escouade… | Écran consacré à un groupe du camp. Exemple : un écran par escouade engagée. |
| Source › Caméra en direct… | Caméra suivante, ou une source de la liste (drone, caméra fixe, caméra partagée). Exemple : la caméra de l'entrée nord. |
| Source › Mosaïque de caméras… | Plusieurs caméras sur le même écran. Exemple : 2x2 de tout le camp. |
| **Écran…** | Réglages de cet écran : luminosité, texte, diffusion, diagnostic, retrait. Exemple : baisser la luminosité. |
| Écran › Luminosité : n % | 100 %, 75 %, 50 %, 25 % ; valeur en cours dans le libellé. Exemple : 25 % pour une opération de nuit. |
| Écran › Modifier le texte LED… (vue texte) | Ouvre la saisie du texte affiché. Exemple : changer « ALERTE » en « FIN D'ALERTE ». |
| Écran › Diffusion (BROADCAST)… | Recopie un écran sur d'autres, ou affiche la mire (`fn_tocBroadcast`). Exemple : le même écran dans toute la salle. |
| Écran › Diagnostic… | Image de test (8 s), « Tenu par : le serveur » ou « les joueurs (repli) », nom, vue et salle de l'écran. Exemple : un écran resté noir. |
| Écran › Retirer l'écran TOC | L'objet n'est plus un écran TOC et reprend son image d'origine. Exemple : libérer la TV pour un briefing. |
| **Salle : nom** | Salle TOC de l'écran, nom en cours dans le libellé (« Salle : aucune » sans salle). Exemple : lier l'écran à la salle la plus proche. |
| Salle › Lier à la salle la plus proche : nom | Lie l'écran à la salle de l'écran ou du PC le plus proche (30 m). Exemple : un écran ajouté dans la salle TOC 1. |
| Salle › Lier à une (autre) salle… | Liste des salles du camp. Exemple : choisir la salle du PC maître. |
| Salle › Délier de la salle nom | L'écran n'appartient plus à cette salle. Exemple : un écran déplacé dans une autre pièce. |
| Salle › Identifier (clignote 2 s) | L'écran clignote 2 s chez les joueurs proches. Exemple : retrouver la dalle 3 du mur. |
| Salle › Éteindre l'écran / Allumer l'écran | Met l'écran en veille (réglage gardé) ou l'en sort. Exemple : salle vide, puis avant le briefing. |
| **Gérer les écrans (tableau)…** | Ouvre le tableau de tous les écrans TOC (`fn_tocManager`). Exemple : changer la vue de dix écrans d'un coup. |
| Utiliser comme écran TOC (objet pas encore désigné) | Fait de cet objet un écran TOC (carte et flux). Exemple : la TV de la salle de briefing. |

```sqf
// Arbre d'un écran : [[identifiant, libellé, aide, icône, clé ("" : groupe), enfants, coché]...]
private _arbre = ["tree", cursorObject] call comspec_atak_native_fnc_tocMenu;
// Exécuter une entrée par sa clé (les mêmes clés pour ACE, la molette et le tableau des écrans)
[cursorObject, "bright|50"] call COMSPEC_ATAK_TocDo;
```

**English.** The "ATAK · TOC screen" Eden/Zeus module shows a side's live ATAK network, as computed by the mission
server (`fn_tocFeed`), on a game TV or screen: map with nodes, mission markers, geolocated reports, network panel and
feed. ACE/scroll actions change the display (map and feed, map, feed, post PIP), zoom and open the post laptop. The
"TOC post laptop" action opens the laptop's own screen (`fn_tocLaptop`), with no phone: the real Arma map, groups,
feed, order composer and side messages. "Post PIP" mirrors the operator's laptop on any in-game screen. Screens
persist across sessions (`fn_tocPersist`, setting "Keep TOC screens").
COMSPEC screens are the game's own screens (tripod, dual, TV, desk monitor) set up to show one image day and night. The
COMSPEC video projector (`fn_projector`, the game's projector) projects slides, TOC display, map or camera onto a game
whiteboard within 15 m when switched on, intact, aimed and in line of sight; otherwise the board stays white.

### Afficher un texte

Tout écran capable de l'affichage « Texte » montre un message à LED : bandeaux LED 8:1 et 16:1 (`COMSPEC_ATAK_Screen_LedText8`,
`COMSPEC_ATAK_Screen_LedText16`), mur LED, écrans et dalles 16:9, bandeau 4:1, colonnes d'un écran incurvé. Une plaque de nom
ne le peut pas. Le texte s'écrit à quatre endroits, tous par le même chemin (`fn_tocScreen` « view », clé « text », réglage
gardé d'une partie à l'autre) :

- **Module « ATAK · Afficher un texte »** (Eden et Zeus, `fn_moduleTocText`). Eden : attributs Message affiché, Couleur du
  texte, Vitesse, Défilement, Écrans visés (écrans synchronisés ; écran texte le plus proche ; toute la salle la plus proche ;
  tous les écrans texte dans le rayon) et Rayon. Le message est posé par le serveur au lancement, après la reprise de la
  partie précédente : celui du module l'emporte. Rien de synchronisé : l'écran texte le plus proche. Zeus : posé sur un écran
  ou au sol, il ouvre la fenêtre de saisie pour cet écran, sinon pour l'écran texte le plus proche de l'endroit visé. Le
  module se repose autant de fois que voulu, la fenêtre revient avec le texte en cours.
- **Menu de l'écran** (ACE ou molette) : « Afficher un texte… » ouvre l'éditeur de l'écran (`fn_tocText` « edit » : liste
  des messages, aperçu au format de l'écran).
- **Gestionnaire d'écrans et PC du poste** : même fenêtre de saisie, pour l'écran choisi.
- **Module « ATAK · Écran TOC »** : affichage « Texte à LED » et attribut Texte, pour l'écran que le module pose ou désigne.

**Fenêtre de saisie** (`fn_tocTextTool` « dialog ») : modèle de message, message (un par ligne), couleur, vitesse,
défilement, écrans visés (cet écran ; tous les écrans texte de la salle ; tous les écrans texte à moins de 50 m) et action
(Afficher, Effacer le message). Le résultat s'écrit dans la fenêtre (« Texte affiché sur 6 écran(s) »), qui reste ouverte
pour l'envoi suivant. Un écran visé qui montrait autre chose passe en affichage Texte ; « Effacer le message » rend le
texte par défaut de l'affichage (le nom de l'écran).

**Écriture.** `message ; couleur ; durée ; clignotant`, messages séparés par `|` (ou un par ligne dans la fenêtre), huit au
plus, 300 caractères chacun, montrés à tour de rôle. Couleur : `G` vert, `A` ambre, `R` rouge, `W` blanc (ou le mot : vert,
ambre, rouge, blanc). Durée en secondes (10 sans indication). Un texte plus large que l'écran défile.

```
Bienvenue au TOC ; G ; 8 | ALERTE ; R ; 5 ; clignotant
HEURE H {h} ; A ; 10
SITUATION : RAS ; G ; 10 | {dtg} ; W ; 5
```

**Variables** (remplacées à chaque image) :

| Variable | Remplacée par | Exemple |
|---|---|---|
| `{zulu}` | heure Zulu, heures et minutes | 14:32 |
| `{zulus}` | heure Zulu avec les secondes | 14:32:07 |
| `{local}` | heure de la mission | 06:15 |
| `{date}` | date de la mission | 11 NOV 2035 |
| `{dtg}` | groupe date-heure Zulu | 111432Z NOV 26 |
| `{h}` | chrono « heure H » du poste (sinon le premier chrono) | H-00:12:30 |
| `{camp}` | camp de l'écran | BLUFOR |
| `{poste}` | nom de l'écran | TOC BRAVO |
| `{groupe}` | escouade liée à l'écran | Alpha 1-1 |

**Modèles** proposés par la fenêtre : Bienvenue, Alerte, Exercice en cours, Heure H, Situation, Briefing en cours, Silence
radio, Heure et date, Accès réservé, Fin d'exercice. Le modèle choisi remplace le message, qui se corrige avant de valider.

```sqf
// Écrans capables de texte : "object" (l'objet), "nearest", "near" (rayon en m), "hall" (même salle), "all".
private _ecrans = ["targets", ["hall", ecran1]] call comspec_atak_native_fnc_tocTextTool;
// [écrans, texte, couleur des messages sans couleur, vitesse (0 lent, 1 normal, 2 rapide), défilement (1 : toujours)]
["send", [_ecrans, "ALERTE ; R ; 5 ; clignotant | {dtg}", "G", 1, 0]] call comspec_atak_native_fnc_tocTextTool;
["clear", _ecrans] call comspec_atak_native_fnc_tocTextTool;
// Fenêtre de saisie pour un écran (joueur ou Zeus) ; ["presets"] et ["variables"] rendent les deux tables ci-dessus.
["dialog", [ecran1]] call comspec_atak_native_fnc_tocTextTool;
```

**English.** Any screen able to run the "Text" display (LED strips, LED wall, 16:9 screens and wall panels, columns) shows
an LED message. Write it with the "ATAK · Show a text" module (Editor: message set at mission start on the synchronized
screens, the nearest text screen, the whole nearest room or every text screen within the radius; Zeus: opens the text
window for the screen under the module or the nearest text screen), the screen's ACE menu ("Show a text…"), the screen
manager, or the Text attribute of the "ATAK · TOC screen" module. Writing: `message ; colour ; duration ; blinking`,
messages separated by `|`; colours `G` `A` `R` `W`; variables `{zulu}` `{zulus}` `{local}` `{date}` `{dtg}` `{h}` `{camp}`
`{poste}` `{groupe}`. The window offers ready-made messages and sends to one screen, its whole room or every text screen
within 50 m; the result is written inside the window.

## 8. IA équipées d'un ATAK

Réglage serveur « IA équipées d'un ATAK » (aucune, chefs de groupe, toutes), module « ATAK · IA équipées » (unités
synchronisées et leurs groupes ; Zeus : le groupe de l'unité visée), ou par script. Le serveur leur donne un vrai
téléphone (réglage « Objet donné aux IA ») et un indicatif propre. Avec « IA réalistes sur le réseau » (coché par
défaut), une IA n'apparaît sur le BFT que si elle porte vraiment un ATAK, et ses points PLI sont envoyés par le serveur
au rythme du réseau, avec une erreur GPS propre à chaque IA (toit, forêt), identiques pour tous les joueurs et perdus
ou dégradés près d'un brouilleur.

```sqf
// Imposer ou interdire l'ATAK à une IA (n'importe où : la variable est publique).
_unit setVariable ["COMSPEC_ATAK_AiAtak", true, true];   // false : jamais d'ATAK, même avec le réglage serveur
// Serveur : équiper tout de suite (sinon au prochain passage, 10 s).
if (isServer) then { ["set", [units group _unit, true]] call comspec_atak_native_fnc_aiAtak; };
```

**English.** Server setting "AI with an ATAK" (none, leaders, all), the "ATAK · AI with ATAK" module, or
`_unit setVariable ["COMSPEC_ATAK_AiAtak", true, true]`. With "Realistic AI on the network" (default on), AI only show
on the BFT if they really carry an ATAK, and the server sends their PLI points at the network rate with a per-AI GPS
error, the same for every player, lost or degraded near jammers.

## 9. Appui feu typé : mortiers, artillerie, naval et aérien

Réglages serveur « ATAK · Appui feu » (forçables par la mission avec les réglages CBA de la mission) : système activé
(décoché = appel de feu générique, comme avant), moyens autorisés, tir exécuté par l'IA, batteries simulées, délais de
réaction ; réglage personnel des cercles de portée. L'app CAS (onglet APPEL DE FEU) propose alors un MOYEN, les pièces
de la mission sont reconnues par leur config (calculateur d'artillerie, mortiers statiques, support « Artillery », calibre
des munitions, mortiers ACE) ; l'IA du camp tire après vérification des droits par le serveur, un servant joueur reçoit
la mission dans l'app Tâches avec sa solution de tir.

```sqf
// Forcer le moyen d'une pièce (MORTAR_L, MORTAR_M, MORTAR_H, HOWITZER, MLRS, NAVAL, CAS) : variable publique.
_gun setVariable ["COMSPEC_ATAK_FsType", "MORTAR_H", true];
// Pièce (ou groupe) qui ne prend pas de mission de tir, comme pour les ordres aux IA.
_gun setVariable ["COMSPEC_ATAK_NoOrders", true, true];
// Donner à un joueur le droit de demander un tir aux pièces IA (sinon : observateur, JTAC, chef, commandant, TOC, Zeus).
_unit setVariable ["COMSPEC_ATAK_FsCan", true, true];
```

**English.** "ATAK · Fire support" server settings (mission-overridable): typed fire support on/off (off = generic call
for fire as before), allowed assets, AI execution, simulated batteries, reaction times; personal range rings setting.
Force a gun's asset type with `_gun setVariable ["COMSPEC_ATAK_FsType", "MORTAR_H", true]`, protect it with
`COMSPEC_ATAK_NoOrders`, grant request rights with `_unit setVariable ["COMSPEC_ATAK_FsCan", true, true]`.
