# COMSPEC ATAK Native — architecture

## Périmètre

`comspec_overwatch_atak_native` est le client ATAK principal. Son affichage est composé exclusivement de `RscDisplay`, `RscControls`, `RscMapControl` et SQF. Il n'ouvre ni page distante, ni CEF/WebView, et ne contient aucun HTML, JavaScript, CSS ou Leaflet. Athena est une **source de données facultative** : la carte moteur, le joueur, le groupe, les outils et les marqueurs locaux restent utilisables hors ligne.

Le PBO utilise le préfixe `z\comspec_overwatch\addons\atak_native`, dépend seulement de l'UI Arma, CBA/XEH et du connecteur COMSPEC. ACE, ACRE, BCE et cTab ne sont pas des dépendances. L'ancien addon `atak_athena` détecte ce patch et interrompt ses deux bootstraps afin d'empêcher deux terminaux concurrents.

## Cycle de vie

1. `XEH_preInit.sqf` initialise génération, version, store et canary.
2. `XEH_postInitClient.sqf` est protégé par `hasInterface`, enregistre la touche CBA et un unique `ExtensionCallback`.
3. `open` réutilise/ferme l'instance existante puis crée `COMSPEC_RscDisplayATAK` depuis le display jeu.
4. `displayLoad` applique le layout safezone, crée le rail avec `ctrlCreate`, initialise la carte et démarre un PFH central.
5. `displayUnload` arrête le PFH, persiste la page et invalide les références de controls.

## Display et design system

Les classes réutilisables sont définies dans `ui/controls.hpp` : texte, structured text, boutons, edit, combo, listbox, tree, checkbox, progress, controls group, picture, map, panel, card et divider. La palette est centralisée dans `ui/colors.hpp`, les dimensions dans `ui/defines.hpp`, et tous les IDC dans `ui/ui_ids.hpp` (88500–88999).

La grille calcule barre haute/basse, rail, centre et inspecteur à partir de `safeZoneX/Y/W/H`. `layoutGet` expose les dimensions déterministes ; `layoutApply` les applique lors de l'ouverture. Le routeur conserve un seul display et alterne la carte ou une page dynamique : HOME, MAP, C2, BFT, CHAT, TASK, SSE, INTEL, BDA, BRIEFING, PHOTOS, SETTINGS et STATUS.

## Store et flux de données

`uiNamespace/COMSPEC_ATAK_Data` sépare `units`, `markers`, `tasks`, `messages`, `intel`, `photos`, `zones`, `routes`, `events`, `briefing` et leurs revisions. Le flux est :

```
COMSPECExtension / moteur → normalisation → store → dirty/revision → UI / onDraw
```

`storeSet` incrémente les revisions et marque un domaine sale. Les fonctions réseau ne détiennent aucun control. `importLegacyData` adapte les stores fonctionnels établis (`COMSPEC_Orders`, `COMSPEC_Athena_AlertInbox`, `COMSPEC_IntelStore`) plutôt que de dupliquer les contrats.

## Scheduler et performances

Un seul CBA PFH à 0,2 s orchestre : données locales à 0,25 s, statut à 1 s, synchronisation distante selon le réglage BFT (3 s par défaut), import secondaire à 10 s. `onDraw` ne fait aucun appel réseau et ne crée aucun control. La fermeture retire le PFH. Les listes/pages ne sont reconstruites qu'à la navigation ; les données disposent de revisions pour un diff incrémental futur.

## Extension et callbacks

`extensionCall` est l'unique adaptateur direct du nouvel addon vers `COMSPECExtension`; il ne journalise jamais les arguments. `remoteSync` réutilise les producteurs éprouvés `pollAthenaMarkers`, `pollOrders` et `pollChatMessages`. Le dispatcher traite notamment Connected, Error, NetworkHiccup, AccessDenied, RateLimited/Clear, BftIdentity et les callbacks Google Slides. Le dispatcher historique reste installé pour préserver l'authentification et les services globaux du connecteur.

## Carte et interactions

`COMSPEC_RscMap` hérite de `RscMapControl`. `mapOnDraw` lit uniquement le cache et emploie `drawIcon`, `drawLine`, `drawRectangle`, `drawEllipse` et `drawPolygon`. La symbologie choisit cadre/couleur par affiliation et icône par plateforme, avec labels dépendants du zoom et opacité liée à la fraîcheur. La machine d'état accepte SELECT, PAN, MARKER, PING, MEASURE, ROUTE, ZONE, DRAW, COORD et SITREP. Clic sélectionne, Ctrl-clic ping, Shift-clic crée un marqueur local, Alt-clic active le chemin mesure.

## Intégrations et offline

CBA fournit XEH, keybind, événements et PFH. Les informations ACE/ACRE peuvent être lues uniquement après détection runtime. BCE/cTab n'est jamais utilisé pour afficher le terminal. Sans Athena, `remoteSync` sort immédiatement : aucune boucle d'erreur n'est créée et les données moteur continuent d'être rafraîchies.

## Debug

`debugDump` écrit FPS, volumes de store, nombre de controls, PFH, réseau, page, outil, sélection, queues et versions selon la convention `[COMSPEC ATAK NATIVE][LEVEL][MODULE]`. Les canaries attendus sont `native_client_v1_0_0_loaded`, `display_created` et `map_control_ready`. Aucun secret ni payload d'authentification n'est journalisé.

## Construction

`build_mod.bat` traite `atak_native.pbo` comme obligatoire après `connect.pbo`. `workshop-pack.ps1` refuse la publication si ce PBO est absent, puis l'ajoute au paquet. Le bridge historique cTab/BCE reste optionnel mais s'auto-supprime au boot lorsque le client natif est chargé.

