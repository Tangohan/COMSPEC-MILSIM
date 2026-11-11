# Activité radio au poste TOC : qui parle, sur quelle fréquence (novembre 2026)

Branche `feat/atak-toc-radio-ptt` (base : `feat/atak-toc-tableau-ecrans`, PR #1556). Demande de TTRD : « Permets
d'avoir une UI PTT pour savoir qui parle, sur quelle fréquence. » Rien n'a été lancé dans Arma : tout ce qui suit vient
de la lecture du code du mod, du code d'ACRE2 et de TFAR déjà cité dans `fn_radioGuard` et `fn_mpuVoice`, et de tests
statiques.

## Ce qui existait

| Sujet | Où | Ce que le mod sait déjà |
| --- | --- | --- |
| Porte vers ACRE2 et TFAR | `network/fn_radioGuard.sqf` (non modifié) | `api` teste la fonction avant de l'appeler, `has`, `read` (faux une fois « Couper tout lien avec la radio » coché), coupe-circuit de la couche voix. |
| Mon émission ACRE2 | `network/fn_mpu5.sqf` | Événements CBA `acre_startedSpeaking` / `acre_stoppedSpeaking` (unité, sur radio, identifiant de radio), `freq` rend `[émission, réception, libellé]` d'une radio, `radios` la liste des radios portées. |
| Émissions des autres (ACRE2) | `network/fn_mpu5.sqf` | `acre_remoteStartedSpeaking` part sur chaque client, que l'émetteur soit audible ou non ; aucun moyen sûr d'en tirer la fréquence de l'autre. |
| Alternat TFAR | `network/fn_mpuVoice.sqf` | Gestionnaires `OnBeforeTangent` et `OnTangent` posés par `TFAR_fnc_addEventHandler` : `[unité, radio, type (1 : longue portée), canal additionnel, appui]`. Local à l'émetteur. |
| Voix par le maillage MPU5 | `network/fn_mpuVoice.sqf`, `fn_mpuHud.sqf`, `fn_relayTxBadge.sqf` | `COMSPEC_ATAK_VoiceMe` dit si ma radio est pontée ; l'encadré MPU et la case RELAIS ATAK le montrent à l'émetteur seulement. |
| Onglet RADIO du PC du poste | `toc/fn_tocLaptop.sqf` | Les radios de l'opérateur lui-même (canal, fréquence, voyants RX / TX). Rien sur le reste du camp. |
| Relais, couverture, liens | `network/fn_relayNet.sqf`, `fn_meshRead.sqf`, pages Wave Relay | Réseau de données (qui est couvert, par combien de sauts). Aucune notion de réseau vocal ni de fréquence nommée. |
| Dépendances | `config.cpp` `requiredAddons` | Ni ACRE2 ni TFAR : tout est testé à l'exécution. |

Constat : chaque joueur connaît sa propre émission, personne ne connaît celles du camp. Il manquait un point de
rassemblement et un affichage.

## Ce qui est ajouté

Trois fichiers : `toc/fn_tocRadio.sqf` (modèle), `toc/fn_tocRadioUi.sqf` (mur, console, encadré),
`toc/fn_moduleTocRadio.sqf` (module Zeus et Eden).

### D'où viennent les émissions

| Mod chargé | Détection | Réseau et fréquence | Limites |
| --- | --- | --- | --- |
| ACRE2 | `acre_startedSpeaking` avec « sur radio » vrai, `acre_stoppedSpeaking` | Fréquence d'émission et libellé du canal par `fn_mpu5 "freq"` ; genre (portative, longue portée, véhicule) par la classe de base ; volume par `acre_api_fnc_getRadioVolume` | Une radio sans fréquence lisible porte le nom de sa classe. |
| TFAR | `OnTangent` (appui et relâche) | Fréquence de la radio de l'alternat, canal additionnel compris (`TFAR_fnc_getChannelFrequency`), canal, volume | TFAR ne nomme pas ses canaux : « Canal 3 ». Signature 1.x, comme le reste du mod ; jamais joué. |
| Aucun | Touches d'alternat du jeu (`pushToTalk`, camp, commandement, groupe, véhicule) | Pas de fréquence : `VON-CAMP`, `VON-CMDT`, `VON-GRP <groupe>`, `VON-VEH`, ou le nom d'un canal créé par la mission | La touche est vue, pas la voix : un appui sans micro compte. Canal général et voix directe ignorés. |
| Mission, Zeus | `["srvInject", [camp, indicatif, réseau, secondes, libellé]]` (serveur) | Ce que donne la mission | Sert aussi à essayer l'affichage sans radio. |

Tous les appels à ACRE2 et à TFAR passent par `fn_radioGuard "api"` (nom de fonction en texte), jamais en direct :
`tests/Unit/AtakTocRadioAssetTest.php` le vérifie. « Couper tout lien avec la radio » coupe aussi cette collecte.
L'émission dit si la voix passe par le maillage MPU5 (`COMSPEC_ATAK_VoiceMe`). Le niveau affiché est le volume de la
radio, pas un niveau de voix : ni ACRE2 ni TFAR ne donnent le niveau du micro à un script.

### Coût et réseau

- Aucune boucle dans le modèle. Chaque début et chaque fin d'émission est un événement.
- Collecte éteinte tant que personne ne regarde : la variable publique `COMSPEC_ATAK_TocRadioOn` n'est posée par le
  serveur qu'au premier abonné (console ouverte, écran RADIO dessiné, encadré activé), ou 20 s après le lancement s'il
  existe un écran ou une salle TOC, ou par le module Eden. Éteinte, un appui d'alternat ne coûte qu'une lecture de variable.
- Allumée : un message au serveur à l'appui (11 champs courts), un à la relâche. Le serveur regroupe ses envois
  (0,25 s) et ne les adresse qu'aux abonnés du camp ; Zeus, administrateur et hôte : tous les camps.
- Un joueur qui rejoint reçoit l'état complet en s'abonnant. Chaque message porte un numéro de révision : un trou
  redemande l'état complet, un message déjà contenu est ignoré.
- Émission sans fin (joueur parti, jeu figé) : fermée à la déconnexion, sinon d'office au bout de 3 minutes.
- Historique : 50 dernières émissions par camp ; 40 réseaux entendus au plus. Rien n'est gardé d'une partie à l'autre.

### Ce que voit le poste

Le poste ne voit que sa liste de veille : `COMSPEC_ATAK_TocRadioNets_<CAMP>` (console radio, module), sinon le réglage
serveur « Réseaux surveillés par défaut ». Le réglage serveur « Le TOC voit tous les réseaux du camp » (décoché par
défaut) lève le filtre ; « Tout surveiller » le lève pour le Zeus et l'administrateur, tous camps confondus.

Le tri se fait à l'affichage : le serveur envoie à un abonné toutes les émissions de son camp. C'est un choix de
simplicité (un seul état par camp, pas un par abonné), pas une protection : un joueur qui lirait la variable verrait
les fréquences non veillées de son propre camp. Les émissions d'un autre camp ne lui sont jamais envoyées.

### Réglages (CBA, « ATAK · Mode local et poste TOC »)

| Réglage | Défaut | Portée |
| --- | --- | --- |
| Activité radio au poste TOC | coché | serveur |
| Réseaux surveillés par défaut (exemple : `CMD NET=31.000*, AIR=40.500, 152.250`) | vide | serveur |
| Le TOC voit tous les réseaux du camp | décoché | serveur |
| Silence d'un réseau (min) | 10 | serveur |
| Encadré « Qui parle » à l'écran | décoché | joueur |
| Encadré « Qui parle » au Zeus | coché | joueur |

## Points de branchement dans les fichiers des autres branches

Ajouts seulement, aucune ligne existante réécrite hors des listes d'affichages.

| Fichier | Ajout |
| --- | --- |
| `toc/fn_tocScreen.sqf` (PR #1556) | `RADIO` dans `_views`, `_boards`, la table des titres et la liste des affichages redessinés chaque seconde. |
| `toc/fn_tocBoard.sqf` | `case "RADIO"` : une ligne, qui remet ses outils de dessin à `fn_tocRadioUi "wall"`. |
| `toc/fn_tocTheme.sqf` | Régions `RADIO` dans `_walls`, règle sous FLASH, colonnes `RADIO` dans `_cols`. |
| `toc/fn_tocMenu.sqf` | Vue « Activité radio » dans « Tableaux du poste… », entrée « Activité radio (console)… », action `radio`. |
| `toc/fn_moduleTocScreen.sqf`, `modules.hpp`, `config.cpp` | L'affichage RADIO dans les listes Zeus et Eden ; module ; trois fonctions. |
| `ui/displays.hpp` | Calque `COMSPEC_RscTitleTocRadioHud`. |
| `XEH_preInit.sqf`, `XEH_postInitClient.sqf`, `XEH_postInitServer.sqf` | Six réglages ; trois appels d'initialisation. |
| `tests/Unit/AtakTocScreenDisplaysAssetTest.php`, `AtakTocThemeTest.php` | 26 affichages au lieu de 25 ; `RADIO` dans les mises en page et tableaux vérifiés. |

Le tableau des écrans (`fn_tocManager`) propose RADIO sans y toucher : il lit la liste des affichages. Il n'a pas de
catégorie de sources ; dans le menu ACE, RADIO est rangé avec les tableaux du poste.

Depuis la fusion de main (4.12.0) : la console du poste (`fn_tocLaptop.sqf`, onglet RADIO de la rubrique SALLE) monte le
panneau à la place de la carte par `["dock", …]` (deux ajouts dans « layout ») ; les indicatifs viennent de
`fn_tocDisplayName` (jamais le pseudo) ; la liste de veille ne se change qu'avec le droit « régler la salle » de
`fn_tocAccess` (chef de poste, administrateur Zeus ; demande et refus au journal du TOC) ; le filtre « seulement les
entités équipées d'un ATAK » (`fn_tocHasAtak`) est appliqué dès qu'il est chargé. Montage dans une autre fenêtre :

```sqf
// display de la console, rectangle [x, y, l, h], camp, tout voir, groupe parent (ou controlNull)
private _ui = ["mount", [_display, [_x, _y, _w, _h], "WEST", false, _groupe]] call comspec_atak_native_fnc_tocRadioUi;
```

Il se rafraîchit seul tant que son display existe. La même console s'ouvre aussi en fenêtre par le menu ACE d'un écran
TOC et par le module Zeus.

## Ce qui n'a pas été vu en jeu

- Signature de `OnTangent` et fonctions de fréquence de TFAR 1.x ; TFAR 0.9.x non prévu (comme `fn_mpuVoice`).
- Émission ACRE2 sur une radio de véhicule (rack) : genre déduit du nom de classe `acre_vrc`.
- Touches d'alternat du jeu (`addUserActionEventHandler`) quand le canal vient d'un basculement « voix sur IP ».
- Rendu de l'écran RADIO à la vraie proportion d'une dalle, largeur réelle des textes (estimée par le test, mesurée
  par `fn_tocBoard` au dessin), console en 4:3 et en ultra-large, encadré dans l'interface du Zeus.
- Charge : 40 joueurs sur un même réseau n'ont pas été simulés ; le serveur envoie au plus quatre fois par seconde.

## Suite possible

- Garder la liste de veille d'une partie à l'autre (`fn_tocPersist`).
- Tri côté serveur par abonné si la discrétion entre équipes d'un même camp devient un enjeu.
