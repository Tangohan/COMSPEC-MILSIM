# Droits, postes, verrou et journal de la salle TOC

ATAK natif 4.12.0. Fichiers : `fn_tocAccess.sqf` (droits et verrou), `fn_tocStation.sqf` (postes nommés),
`fn_tocHandover.sqf` (relève), `fn_tocJournal.sqf` (journal des actions), `fn_tocDiag.sqf` (diagnostic). Feuille de
route : `docs/audit/2026-11-toc-feuille-de-route.md` (n° 10 à 17, 35, Z2 à Z4). Tout est tenu par le serveur du jeu,
sans Athena ni téléphone.

## Les trois rôles

| Rôle | Niveau | Qui |
|---|---|---|
| `operator` Opérateur | 1 | Tout joueur du camp de la salle. |
| `chief` Chef de poste | 2 | Le joueur qui tient le poste « chef de quart » (`CDQ`) de la salle ; un joueur listé dans le réglage « Chefs de poste désignés » ; un joueur à qui la mission a donné le rôle (`grant`). |
| `admin` Administrateur Zeus | 3 | Zeus, hôte de la partie, administrateur connecté au serveur, gestionnaires désignés par le serveur. La mission (script du serveur) agit à ce niveau. |

Un joueur d'un autre camp que la salle n'a aucun droit (`none`, niveau 0).

## Qui peut faire quoi

Règle par défaut, validée par TTRD : **seuls le chef de poste et l'administrateur Zeus changent les écrans et les
salles.** Le réglage « Droits de la salle TOC » choisit la colonne.

| Droit | Ce qu'il couvre | Rôles (défaut) | Ouvert | Administrateurs | Gelé par le verrou |
|---|---|---|---|---|---|
| `view` | Lire la console et les écrans, identifier un écran | opérateur | opérateur | opérateur | non |
| `station` | Tenir un poste | opérateur | opérateur | opérateur | non |
| `entry` | Écrire dans les registres du poste | opérateur | opérateur | opérateur | non |
| `handover` | Proposer et signer une relève | opérateur | opérateur | opérateur | non |
| `screens` | Changer l'affichage des écrans, les allumer, appliquer une scène | chef de poste | opérateur | administrateur | oui |
| `rooms` | Créer, renommer une salle, lier des écrans, enregistrer une scène, régler un poste | chef de poste | opérateur | administrateur | oui |
| `presets` | Enregistrer et poser un préréglage de salle | chef de poste | opérateur | administrateur | oui |
| `lock` | Verrouiller et déverrouiller la salle, libérer un poste d'office | chef de poste | chef de poste | administrateur | non |
| `journal` | Lire le journal des actions | chef de poste | chef de poste | administrateur | non |
| `diag` | Exporter le diagnostic | chef de poste | chef de poste | administrateur | non |
| `build` | Poser ou retirer une salle complète | administrateur | opérateur | administrateur | oui |
| `grant` | Donner un rôle à un joueur | administrateur | administrateur | administrateur | non |

## Verrou de salle

Une salle verrouillée ne bouge plus : les droits « gelés » ne restent qu'à l'auteur du verrou et à l'administrateur
Zeus. Le verrou se pose et se lève par l'action ACE sur soi « Poste TOC », par la console, ou par la mission (`lock`).
Un verrou n'est jamais oublié : il est levé quand son auteur signe une relève ou se déconnecte, et tout chef de poste
ou Zeus peut le lever. Il n'est pas gardé d'une partie à l'autre.

## Postes nommés et relève

- Chaque PC de TOC est un poste. Rôles de poste : `CDQ` chef de quart, `OPS` opérations, `RENS` renseignement, `FEUX`
  feux, `LOG` logistique, `TRANS` transmissions. Un chef de poste peut imposer un rôle à un PC ; sinon celui qui
  s'assoit choisit.
- Prendre un poste : action ACE sur soi « Poste TOC », à moins de 6 m du PC. Un joueur ne tient qu'un poste ; une
  salle n'a qu'un chef de quart. Un poste tenu ne se prend pas : on demande la relève.
- Déconnexion : le poste est libéré et gardé 5 minutes à son tenant.
- Relève : le sortant propose la relève (avec une consigne), le serveur y joint le résumé de la salle (demandes
  ouvertes, pertes, prochaines lignes de la liste d'exécution, chronos en marche, FLASH, verrou), l'entrant signe près
  du PC et prend le poste. Les relèves se lisent et s'exportent en texte.

## Réglages (Options des addons, COMSPEC, « ATAK · Salle TOC : postes, droits et journal »)

| Réglage | Valeurs | Exemple |
|---|---|---|
| Droits de la salle TOC | Rôles (défaut), Ouvert, Administrateurs seulement | En « Rôles », un joueur de passage ouvre le PC et lit la carte ; son changement d'affichage est refusé et écrit au journal. |
| Grade minimal du chef de quart | Soldat (défaut) à Colonel | Avec « Sergent », un caporal tient le poste Opérations, pas celui de chef de quart. |
| Chefs de poste désignés | Identifiants Steam séparés par des virgules | `76561198000000001, 76561198000000002` : ces deux joueurs règlent toutes les salles de leur camp. |
| Lignes gardées au journal du TOC | 50 à 2000 (défaut 500) | 500 lignes couvrent une soirée d'opération ; les 200 dernières sont gardées avec la salle. |
| Journal du TOC dans le journal COMSPEC | coché (défaut) | Une ligne « Journal du TOC n° 12 · … Salle verrouillée » par action. |
| Thème des écrans TOC | Automatique (défaut), Jour, Nuit | Réglage de chaque joueur ; « Automatique » passe en nuit quand le soleil est couché. |

## Le serveur ne croit pas le client

- L'auteur d'une demande est retrouvé par `remoteExecutedOwner` ; son camp (`side group`) et son identifiant
  (`getPlayerUID`) sont lus sur le serveur. Rien de ce que le client annonce (camp, nom, UID) n'est utilisé.
- L'état qui fait foi (verrous, rôles donnés, postes, journal, relèves) vit dans `localNamespace` du serveur : un
  client ne peut pas l'écraser. Les variables publiques (`COMSPEC_ATAK_TocAccess`, `COMSPEC_ATAK_TocStations`,
  `COMSPEC_ATAK_TocHandoverPending`) n'en sont que des copies pour l'affichage.
- Le journal et les relèves ne sont envoyés qu'à la demande, à qui en a le droit, pour son camp.
- Un client dont le mod est d'avant la 4.12.0 envoie encore l'ancien événement : son émetteur est inconnu, il n'a donc
  que les droits d'un simple opérateur, et rien dans une salle verrouillée.

## Points d'accroche pour les autres fichiers du TOC

À appeler sur le serveur, dans le gestionnaire d'une action, avant de l'appliquer.

```sqf
// Vérifie le droit, écrit le journal (accordé ou refusé) et dit le refus au joueur. _unite : l'unité annoncée par le
// client ; dans un appel distant, c'est l'émetteur réel qui est contrôlé, jamais l'unité annoncée.
if !(["check", [_unite, "screens", _salle, "screen.view", "MUR · mode"]] call comspec_atak_native_fnc_tocAccess) exitWith { false };

// Retrouver l'émetteur réel d'une demande : [unité, de confiance].
(["actor", _unite] call comspec_atak_native_fnc_tocAccess) params ["_u", "_confiance"];

// Écrire une ligne au journal sans contrôle de droit.
["add", [_camp, _salle, _u, "hall.rename", "PC AVANT", true]] call comspec_atak_native_fnc_tocJournal;

// Dire quelque chose au joueur (console, jamais une alerte du jeu). Dans un gestionnaire appelé à distance, déclarer
// d'abord « private _tocTrusted = true; » : sans cette variable, « feedback » et les modes internes ne font rien, et
// « add » du journal écrit l'émetteur réel à la place de l'auteur annoncé.
private _tocTrusted = true;
["feedback", [_u, true, "Scène appliquée", []]] call comspec_atak_native_fnc_tocAccess;
```

Pour que l'émetteur soit connu, la demande doit arriver par `remoteExecCall` (pas par `CBA_fnc_serverEvent`). Deux
voies toutes faites :

```sqf
// Client : demande d'écran ou de salle par la porte du serveur (droit, verrou et journal faits par fn_tocAccess).
["send", ["screen", ["view", [_ecran, "mode", "LOG"]]]] call comspec_atak_native_fnc_tocAccess;
["send", ["hall", ["rename", [_salle, "PC AVANT"]]]] call comspec_atak_native_fnc_tocAccess;

// Client : afficher ou griser un bouton (estimation ; le serveur revérifie).
private _peut = ["can", [player, "screens", _salle]] call comspec_atak_native_fnc_tocAccess;
```

Côté client, le retour d'une demande arrive par l'événement `comspec_atak_toc_feedback` `[réussi, texte, valeurs]` et
dans `missionNamespace COMSPEC_ATAK_TocFeedback` `[diag_tickTime, réussi, texte traduit]`. Un refus ouvre la fenêtre du
TOC, sauf si une console ouverte pose `COMSPEC_ATAK_TocFeedbackUi` à vrai pour l'afficher elle-même dans sa ligne
d'état.

Accroches posées dans les fichiers existants (une ligne chacune) :

| Fichier | Accroche |
|---|---|
| `fn_tocHall.sqf` « do » (client) | la demande part par `["send", ["hall", …]]` |
| `fn_tocHall.sqf` « init » | l'ancien événement passe par `["legacy", ["hall", …]]` |
| `fn_tocScreen.sqf` « view » et « remove » (client) | la demande part par `["send", ["screen", …]]` |
| `fn_tocScreen.sqf` « initServer » | l'ancien événement passe par `["legacy", ["screen", …]]` |
| `XEH_postInitServer.sqf`, `XEH_postInitClient.sqf` | `["init"]` et `["initClient"]` de `fn_tocAccess` |
| `config.cpp`, `XEH_preInit.sqf` | fonctions et réglages, dans un bloc au nom du lot |

`fn_tocRoom.sqf` (registres) n'est pas touché : son contrôle des demandes appartient au lot 1.

## Noms montrés

Journal, postes, relèves et verrou n'écrivent jamais le pseudo Steam d'un joueur. `["nameOf", unité] call
comspec_atak_native_fnc_tocAccess` rend l'indicatif donné par le résolveur commun du TOC
(`comspec_atak_native_fnc_tocDisplayName`, appelé avec `[unité]`, fourni par le lot « sans téléphone ») quand il est
chargé ; sinon le groupe et le rang du jeu (« Alpha 1-1 · 2 »).

## Journal des actions

Ligne : `[n°, heure ZULU, serverTime, camp, salle, auteur, rôle, action, détail, accordée]`. Anneau par camp, gardé
avec la salle (200 dernières lignes). Lecture : action ACE sur soi « Poste TOC », action Zeus « ATAK · TOC », ou
`["get", [camp, nombre]] call comspec_atak_native_fnc_tocJournal` puis l'événement `comspec_atak_toc_journal`.

## Diagnostic

`["run"] call comspec_atak_native_fnc_tocDiag` : action Zeus « ATAK · TOC », touche CBA « Exporter le diagnostic du
TOC » (sans touche par défaut), action ACE sur soi « Poste TOC ». Le relevé est écrit dans le journal COMSPEC, copié
dans le presse-papiers et montré dans une fenêtre. Il ne contient ni nom ni identifiant de joueur.

## Non vérifié en jeu

Rien de ce lot n'a tourné dans Arma : droits, verrou, postes, relève, journal, fenêtre de lecture et actions ACE et
Zeus sont à essayer sur un serveur dédié avec deux joueurs de camps différents.
