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
- Publiez votre module sous votre propre licence. COMSPEC ATAK est non commercial (APL-SA).

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
["spawn", [getPosATL player, getDir player, "Land_TripodScreen_01_large_F", "TOC", "WEST"]] call comspec_atak_native_fnc_tocScreen;
```

**English.** The "ATAK · TOC screen" Eden/Zeus module shows a side's live ATAK network, as computed by the mission
server (`fn_tocFeed`), on a game TV or screen: map with nodes, mission markers, geolocated reports, network panel and
feed. ACE/scroll actions change the display (map and feed, map, feed, post PIP), zoom and open the post laptop. The
"TOC post laptop" action opens the laptop's own screen (`fn_tocLaptop`), with no phone: the real Arma map, groups,
feed, order composer and side messages. "Post PIP" mirrors the operator's laptop on any in-game screen. Screens
persist across sessions (`fn_tocPersist`, setting "Keep TOC screens").

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

## 9. Relais : condition d'activation (mât déployé, à l'arrêt, code)

Un relais ou une passerelle de véhicule peut ne porter le réseau ATAK que sous condition : champ « Actif seulement si »
du module « ATAK · Relais » (toujours, mât ou antenne déployé(e), véhicule à l'arrêt, moteur coupé) ou champ
« Condition (code) » du module dans Eden. Condition non remplie : le relais apparaît hors service avec la raison (page
Réseau, fiche du relais sur la carte).

```sqf
// Préréglage, n'importe où (la variable est publique) : "" (toujours), "mast", "stopped" ou "engineoff".
_veh setVariable ["COMSPEC_AtakRelayCond", "mast", true];
// Condition en code : sur le serveur seulement (initServer.sqf, ou init d'un objet dans Eden sous « if (isServer) »).
// _this = l'objet ; le code rend vrai quand le relais doit marcher. Le serveur le réévalue toutes les 2 s.
if (isServer) then {
    [_veh, "_this animationSourcePhase ""mast_src"" > 0.9"] call comspec_atak_native_fnc_relayCondSet;
    _veh setVariable ["COMSPEC_AtakRelayCond", "code", true];   // tout texte hors préréglage : « appliquer le code »
};
[_veh, ""] call comspec_atak_native_fnc_relayCondSet;   // serveur : retire le code
```

- Le code reste sur le serveur, dans un registre que le réseau ne synchronise jamais : aucun joueur ne peut le lire ni
  le remplacer. Seul le résultat (actif ou non, et la raison) est envoyé aux joueurs. Appelée ailleurs que sur le
  serveur, ou à distance par un client, la fonction refuse (`false`) et l'écrit au journal.
- **Rupture (sécurité)** : la variable d'objet `COMSPEC_AtakRelayCondSrv`, donnée en exemple depuis la 2.4.1, n'est
  plus lue, car une variable d'objet est synchronisable. Une mission qui la posait voit son relais rester « condition non
  remplie » : remplacer cette ligne par l'appel à `comspec_atak_native_fnc_relayCondSet` ci-dessus. Le module posé
  dans Eden et les préréglages ne changent pas.

**English.** Relays and vehicle gateways can carry the ATAK network only under a condition: `COMSPEC_AtakRelayCond` =
`""`, `"mast"`, `"stopped"` or `"engineoff"` (public variable), or a code condition set on the server with
`[object, "code"] call comspec_atak_native_fnc_relayCondSet` (`_this` = the object, `""` removes it) together with
`COMSPEC_AtakRelayCond` set to any other text, such as `"code"`. The code stays in a server-only registry that is never
synchronised. **Breaking change (security):** the `COMSPEC_AtakRelayCondSrv` object variable is no longer read, since
object variables can be synchronised; missions that set it must call `comspec_atak_native_fnc_relayCondSet` instead.
