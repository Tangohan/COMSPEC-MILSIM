# API du TOC pour les créateurs de mission

Version de l'API : **1.0** (ATAK natif 4.12.0). Fichier : `fn_tocApi.sqf`. Feuille de route : `docs/audit/2026-11-toc-feuille-de-route.md` (Z4).

Tout se passe dans le jeu, sur le serveur de la partie : ni Athena, ni site, ni téléphone. Une mission sans aucun
téléphone peut créer une salle, poser des écrans, changer leur affichage, verrouiller la salle et suivre ce qui s'y
passe.

## Appeler l'API

```sqf
[verbe, [arguments]] call comspec_atak_native_fnc_tocApi
```

- Les verbes marqués **serveur** s'appellent sur le serveur (`initServer.sqf`, déclencheur « serveur seulement »,
  `if (isServer) then { … }`). La mission y agit avec les droits de l'administrateur (voir `docs/TOC-ACCESS.md`).
  Ailleurs, le verbe ne fait rien et rend sa valeur d'échec.
- Un appel envoyé au serveur par un joueur (`remoteExec` vers le serveur) est refusé. Les joueurs passent par les
  consoles et les actions du TOC, qui vérifient leurs droits.
- Arguments faux : rien n'est fait, la valeur d'échec est rendue, la raison se lit avec
  `["lastError"] call comspec_atak_native_fnc_tocApi` et dans le journal COMSPEC. Jamais d'erreur de script, jamais de
  fenêtre du jeu.
- Attendre que le TOC soit prêt : appeler après le lancement de la mission (`time > 1`), par exemple dans
  `initServer.sqf` derrière un `[{ … }, [], 5] call CBA_fnc_waitAndExecute`.

Types : S texte, N nombre, B booléen, O objet, A tableau, C code, X tout. Les camps s'écrivent `"WEST"`, `"EAST"`,
`"GUER"`, `"CIV"`. Les affichages : `SPLIT`, `MAP`, `FEED`, `NET`, `ORBAT`, `TOC`, `CAM`, `SAT`, `CLOCK`, `SQUAD`,
`LOG`, `EXCHECK`, `KILLS`, `REQ`, `STRIP`, `PLATE`, `MOSAIC`, `TEXT`, `COLA`, `COLB`.

## Contrat de stabilité

- Un verbe publié ne change ni de nom ni d'ordre d'arguments.
- Un nouvel argument s'ajoute à la fin et reste facultatif.
- La version passe à 1.1 pour un ajout, à 2.0 pour une rupture. `["version"] call comspec_atak_native_fnc_tocApi` rend
  la version ; `["verbs"]` rend la liste des verbes ; `["events"]` celle des événements.
- `["check", [verbe, [arguments]]] call comspec_atak_native_fnc_tocApi` rend `""` si les arguments sont justes, sinon
  la raison : utile pour vérifier une mission sans rien changer.

## Exemple complet

```sqf
// initServer.sqf
[{
    private _salle = ["roomCreate", ["PC AVANT", "WEST"]] call comspec_atak_native_fnc_tocApi;
    private _mur = ["screenSpawn", [getPosATL poste_1, getDir poste_1, "", "MUR", "WEST", "SPLIT", _salle]] call comspec_atak_native_fnc_tocApi;
    ["screenAdd", [ecran_eden_1, "HORLOGE", "WEST", "CLOCK", _salle]] call comspec_atak_native_fnc_tocApi;
    ["timerAdd", ["WEST", "H-HOUR", "AT", 22 * 60]] call comspec_atak_native_fnc_tocApi;
    ["exAdd", ["WEST", "Franchissement de la ligne de départ", "ROUGE", "Alpha", "22:10"]] call comspec_atak_native_fnc_tocApi;
    ["lock", [_salle, true, "briefing"]] call comspec_atak_native_fnc_tocApi;
    ["on", ["comspec_atak_toc_locked", { params ["_salle", "_verrou", "_auteur"]; diag_log format ["Salle %1 : %2 par %3", _salle, _verrou, _auteur]; }]] call comspec_atak_native_fnc_tocApi;
}, [], 5] call CBA_fnc_waitAndExecute;
```

## Salles

### `roomCreate`

Serveur. `[nom S, camp S, PC O (facultatif), identifiant S (facultatif)]` → identifiant de la salle, `""` en cas d'échec.

```sqf
private _salle = ["roomCreate", ["PC AVANT", "WEST"]] call comspec_atak_native_fnc_tocApi;
```

### `roomBuild`

Serveur. Pose une salle complète (écrans, bureaux, postes). `[posATL A, cap N, taille S "S" | "M" | "L", nom S, camp S]`
→ identifiant de la salle.

```sqf
["roomBuild", [getPosATL repere_toc, getDir repere_toc, "M", "TOC BRAVO", "WEST"]] call comspec_atak_native_fnc_tocApi;
```

### `roomRename`

Serveur. `[salle S, nom S]` → vrai ou faux.

```sqf
["roomRename", [_salle, "PC ARRIÈRE"]] call comspec_atak_native_fnc_tocApi;
```

### `roomRemove`

Serveur. Retire une salle complète et ce qu'elle a posé. `[salle S]` → vrai ou faux.

```sqf
["roomRemove", [_salle]] call comspec_atak_native_fnc_tocApi;
```

### `rooms`

Partout. `[camp S (facultatif)]` → `[[identifiant, nom, camp, nombre d'écrans, verrouillée]...]`.

```sqf
{ diag_log format ["%1 : %2 écran(s)", _x select 1, _x select 3]; } forEach (["rooms", ["WEST"]] call comspec_atak_native_fnc_tocApi);
```

## Écrans

### `screenAdd`

Serveur. Fait d'un objet déjà posé un écran TOC. `[objet O, nom S, camp S, affichage S, salle S]` (tout facultatif
sauf l'objet) → vrai si l'objet est devenu un écran.

```sqf
["screenAdd", [ecran_eden_1, "HORLOGE", "WEST", "CLOCK", _salle]] call comspec_atak_native_fnc_tocApi;
```

### `screenSpawn`

Serveur. Crée un écran. `[posATL A, cap N, classe S ("" : premier écran de la liste), nom S, camp S, affichage S,
salle S]` → l'écran, `objNull` en cas d'échec.

```sqf
private _mur = ["screenSpawn", [[1520, 4310, 0], 90, "", "MUR", "WEST", "SPLIT", _salle]] call comspec_atak_native_fnc_tocApi;
```

### `screenView`

Serveur. Change l'affichage. `[écran O, affichage S]` → vrai si l'affichage est en place (faux si le format de la dalle
le refuse, par exemple une carte sur un bandeau).

```sqf
["screenView", [_mur, "EXCHECK"]] call comspec_atak_native_fnc_tocApi;
```

### `screenSource`

Serveur. Règle la source d'un affichage. `[écran O, genre S, valeur X]`. Genres : `cam` (objet source ou son netId,
`objNull` pour retirer), `sat` (0 ou 1), `squad` (nom du groupe), `mosaic` (réglage de mosaïque), `zoom`
(demi-largeur de carte en m), `center` (`[x, y]`), `auto` (cadrage automatique).

```sqf
["screenView", [_mur, "CAM"]] call comspec_atak_native_fnc_tocApi;
["screenSource", [_mur, "cam", drone_1]] call comspec_atak_native_fnc_tocApi;
["screenSource", [_mur, "squad", "Alpha 1-1"]] call comspec_atak_native_fnc_tocApi;
```

### `screenText`

Serveur. Texte de l'affichage TEXTE. `[écran O, texte S ou réglage A]`. Un texte s'écrit comme dans le module :
`message ; couleur ; durée ; clignotant | message suivant`.

```sqf
["screenView", [_bandeau, "TEXT"]] call comspec_atak_native_fnc_tocApi;
["screenText", [_bandeau, "BRIEFING À {zulu} ; ambre ; 10"]] call comspec_atak_native_fnc_tocApi;
```

### `screenPower`

Serveur. `[écran O, allumé B]` → vrai ou faux.

```sqf
["screenPower", [_mur, false]] call comspec_atak_native_fnc_tocApi;
```

### `screenLink`

Serveur. Lie un écran à une salle, `""` pour le délier. `[écran O, salle S]` → vrai ou faux.

```sqf
["screenLink", [_mur, _salle]] call comspec_atak_native_fnc_tocApi;
```

### `screens`

Partout. `[salle ou camp S (facultatif)]` → `[[écran, nom, camp, affichage, salle, éteint]...]`.

```sqf
private _eteints = (["screens", [_salle]] call comspec_atak_native_fnc_tocApi) select { _x select 5 };
```

## Verrou, droits et postes

### `lock`

Serveur. Verrouille ou déverrouille une salle : plus personne ne change ses écrans, sauf l'auteur du verrou et
l'administrateur Zeus. `[salle S, verrouiller B, motif S (facultatif)]` → vrai si la salle est dans l'état demandé.

```sqf
["lock", [_salle, true, "briefing"]] call comspec_atak_native_fnc_tocApi;
```

### `locked`

Partout. `[salle S]` → `[verrouillée, auteur, heure ZULU, motif, UID]`.

```sqf
if ((["locked", [_salle]] call comspec_atak_native_fnc_tocApi) select 0) then { diag_log "Salle verrouillée"; };
```

### `grant`

Serveur. Donne un rôle à un joueur pour toute la partie. `[joueur O ou UID S, rôle S]`, rôle `"chief"` (chef de poste),
`"admin"`, `"none"` (aucun droit) ou `""` (retire le rôle donné) → vrai ou faux.

```sqf
["grant", [chef_de_section, "chief"]] call comspec_atak_native_fnc_tocApi;
```

### `roleOf`

Partout (le serveur fait foi). `[unité O, salle S (facultatif)]` → `"none"`, `"operator"`, `"chief"` ou `"admin"`.

```sqf
private _role = ["roleOf", [player, _salle]] call comspec_atak_native_fnc_tocApi;
```

### `can`

Partout (le serveur fait foi). `[unité O, droit S, salle S (facultatif)]` → vrai ou faux. Droits : voir
`docs/TOC-ACCESS.md`.

```sqf
if (["can", [player, "screens", _salle]] call comspec_atak_native_fnc_tocApi) then { /* montrer le bouton */ };
```

### `stationSet`

Serveur. Donne un rôle de poste à un PC de TOC. `[PC O, rôle du poste S, nom S, imposé B]`. Rôles : `CDQ` (chef de
quart), `OPS`, `RENS`, `FEUX`, `LOG`, `TRANS`.

```sqf
["stationSet", [pc_toc_1, "CDQ", "CHEF DE QUART"]] call comspec_atak_native_fnc_tocApi;
```

### `stations`

Partout. `[camp S (facultatif)]` → postes : `[[netId du PC, salle, camp, rôle du poste, nom, tenant, UID, heure ZULU,
serverTime, gardé pour (UID), jusqu'à]...]`.

```sqf
private _tenus = (["stations", ["WEST"]] call comspec_atak_native_fnc_tocApi) select { (_x select 5) isNotEqualTo "" };
```

## Registres du poste

### `state`

Partout. `[camp S]` → table (HashMap) : `api`, `side`, `revision`, `rooms`, `screens`, `stations`, `timers`, `log`,
`excheck`, `requests`, `flash`, `casualties`, `accessMode`.

```sqf
private _etat = ["state", ["WEST"]] call comspec_atak_native_fnc_tocApi;
private _demandes = count (_etat get "requests");
```

### `logAdd`

Serveur. Ajoute une ligne à la main courante. `[camp S, texte S, genre S (facultatif, "INFO")]`.

```sqf
["logAdd", ["WEST", "Convoi parti de la base", "INFO"]] call comspec_atak_native_fnc_tocApi;
```

### `exAdd`

Serveur. Ajoute une ligne à la liste d'exécution. `[camp S, événement S, mot de code S, élément S, heure prévue S]`.

```sqf
["exAdd", ["WEST", "Objectif saisi", "VERT", "Bravo", "22:40"]] call comspec_atak_native_fnc_tocApi;
```

### `flash`

Serveur. Affiche un FLASH sur tous les écrans du camp ; `""` l'efface. `[camp S, texte S]`.

```sqf
["flash", ["WEST", "CONTACT AU NORD DE LA ZONE"]] call comspec_atak_native_fnc_tocApi;
```

### `timerAdd`

Serveur. `[camp S, nom S, genre S "UP" | "DOWN" | "AT", valeur N]` (durée en secondes pour `DOWN`, minute du jour ZULU
pour `AT`).

```sqf
["timerAdd", ["WEST", "H-HOUR", "AT", 22 * 60]] call comspec_atak_native_fnc_tocApi;
```

## Journal des actions

### `journalAdd`

Serveur. Écrit une ligne de la mission au journal des actions. `[camp S, texte S, salle S (facultatif)]`.

```sqf
["journalAdd", ["WEST", "Phase 2 lancée par le scénario"]] call comspec_atak_native_fnc_tocApi;
```

### `journal`

Serveur. `[camp S ("" : tous), nombre N]` → lignes `[n°, heure ZULU, serverTime, camp, salle, auteur, rôle, action,
détail, accordée]`, les plus récentes à la fin.

```sqf
private _refus = (["journal", ["WEST", 100]] call comspec_atak_native_fnc_tocApi) select { !(_x select 9) };
```

## Préréglages de salle

Bibliothèques : `"mission"` (mémoire du serveur pour cette partie, défaut de l'API) et `"profile"` (profil du serveur,
gardé pour toutes les missions).

### `presetSave`

Serveur. Enregistre la disposition d'une salle. `[salle S, nom S, bibliothèque S]` → vrai ou faux.

```sqf
["presetSave", [_salle, "SALLE OPS", "profile"]] call comspec_atak_native_fnc_tocApi;
```

### `presetApply`

Serveur. `[nom S, salle S, manière S, posATL A, cap N, bibliothèque S]` → `[salle, écrans posés, écrans remplacés]`.
Manière `"layout"` : remet les affichages sur les écrans de la salle (même modèle, même ordre). Manière `"spawn"` :
crée les écrans autour du point, dans la salle donnée ou une nouvelle. Un modèle d'écran absent de la mission est
remplacé par le premier écran disponible, et compté.

```sqf
["presetApply", ["SALLE OPS", "", "spawn", getPosATL repere_toc, getDir repere_toc]] call comspec_atak_native_fnc_tocApi;
```

### `presetImport`

Serveur. Range un préréglage reçu en texte. `[texte S, bibliothèque S]` → nom du préréglage, `""` si le texte est
illisible.

```sqf
["presetImport", ['COMSPEC-TOC-PRESET:1:[1,"SALLE OPS","2026-11-11","WEST",[["Land_TripodScreen_01_large_F",[-1.5,0,0],0,"MUR GAUCHE","SPLIT",0,"",false,[],[],[]],["Land_TripodScreen_01_large_F",[1.5,0,0],0,"MUR DROIT","LOG",0,"",false,[],[],[]]],[]]']] call comspec_atak_native_fnc_tocApi;
```

### `presetExport`

Serveur. `[nom S, bibliothèque S]` → texte d'échange (une ligne), `""` si le préréglage est inconnu.

```sqf
diag_log (["presetExport", ["SALLE OPS"]] call comspec_atak_native_fnc_tocApi);
```

### `presets`

Serveur. `[bibliothèque S]` → `[[nom, camp, nombre d'écrans, date]...]`.

```sqf
private _noms = (["presets", ["profile"]] call comspec_atak_native_fnc_tocApi) apply { _x select 0 };
```

### Format du texte d'échange

Une ligne : `COMSPEC-TOC-PRESET:1:` suivi du tableau `[1, nom, date, camp, écrans, scènes]`. Écran :
`[classe, [dx, dy, dz], cap relatif, nom, affichage, satellite, escouade, éteint, texte, options, mosaïque]`. Les écarts
sont en mètres dans le repère de la salle (centre des écrans, cap du premier écran). Exemple :

```
COMSPEC-TOC-PRESET:1:[1,"SALLE OPS","2026-11-11","WEST",[["Land_TripodScreen_01_large_F",[-1.5,0,0],0,"MUR GAUCHE","SPLIT",0,"",false,[],[],[]],["Land_TripodScreen_01_large_F",[1.5,0,0],0,"MUR DROIT","LOG",0,"",false,[],[],[]]],[]]
```

## Diagnostic

### `diag`

Serveur. `[]` → lignes du diagnostic du TOC (salles, écrans, flux, postes, sauvegarde, performances, versions), sans
nom ni identifiant de joueur.

```sqf
{ diag_log _x; } forEach (["diag", []] call comspec_atak_native_fnc_tocApi);
```

## Événements

### `on`

Partout. `[événement S, code C]` → numéro d'abonnement, `-1` si l'événement est inconnu.

```sqf
private _id = ["on", ["comspec_atak_toc_stationChanged", { params ["", "_salle", "", "_poste", "_tenant", "_action"]; diag_log format ["%1 : %2 %3", _salle, _poste, _action]; }]] call comspec_atak_native_fnc_tocApi;
```

### `off`

Partout. `[événement S, numéro N]` → vrai ou faux.

```sqf
["off", ["comspec_atak_toc_stationChanged", _id]] call comspec_atak_native_fnc_tocApi;
```

### Liste des événements

Ce sont des événements CBA : on peut aussi s'y abonner avec `CBA_fnc_addEventHandler`.

| Événement | Levé sur | Arguments |
|---|---|---|
| `comspec_atak_toc_roomChanged` | serveur | `[salle, action]` |
| `comspec_atak_toc_screenChanged` | serveur | `[écran, action, arguments]` |
| `comspec_atak_toc_locked` | toutes les machines | `[salle, verrouillée, auteur, motif]` |
| `comspec_atak_toc_stationChanged` | toutes les machines | `[netId du PC, salle, camp, rôle du poste, tenant, action]` (`take`, `leave`, `kick`, `lost`, `handover`) |
| `comspec_atak_toc_handover` | toutes les machines | `[camp, salle, rôle du poste, sortant, entrant, état]` (`offered`, `signed`, `cancelled`) |
| `comspec_atak_toc_journalAdded` | serveur | `[ligne du journal]` |
| `comspec_atak_toc_denied` | serveur | `[unité, salle, droit, action]` |
| `comspec_atak_toc_presetApplied` | serveur | `[salle, préréglage, écrans posés, écrans remplacés]` |
| `comspec_atak_toc_saved` | serveur | `[format, numéro d'écriture]` |
| `comspec_atak_toc_feedback` | joueur concerné | `[réussi, texte, valeurs]` |
| `comspec_atak_toc_journal` | joueur qui a demandé | `[camp, lignes]` |
| `comspec_atak_toc_handoverList` | joueur qui a demandé | `[camp, relèves]` |
| `comspec_atak_toc_presets` | joueur qui a demandé | `[préréglages du profil, préréglages de la mission]` |
| `comspec_atak_toc_presetText` | joueur qui a demandé | `[nom, texte d'échange]` |
| `comspec_atak_toc_diag` | joueur qui a demandé | `[texte du diagnostic]` |

## Sauvegarde de la salle

La salle est gardée d'une partie à l'autre dans le profil du serveur (réglage « Garder les écrans TOC »). Depuis la
4.12.0 la sauvegarde est au format 3 : mêmes rangs que le format 2, et à la suite le journal des actions, les postes
nommés, les relèves et une ligne de méta. Une sauvegarde plus ancienne est complétée à la lecture ; une copie de
secours est gardée avant la première écriture. Sur le serveur :

```sqf
private _texte = ["export"] call comspec_atak_native_fnc_tocPersist;    // « COMSPEC-TOC-SAVE:3: » + tableau
["import", _texte] call comspec_atak_native_fnc_tocPersist;             // reprise au prochain lancement de la mission
["rollback"] call comspec_atak_native_fnc_tocPersist;                   // remet la copie de secours
["info"] call comspec_atak_native_fnc_tocPersist;                       // [format, date, taille, copie de secours présente]
```

## Non vérifié en jeu

Cette API a été écrite et vérifiée par lecture du code et par tests statiques. Aucun essai en jeu n'a été fait à la
4.12.0.
