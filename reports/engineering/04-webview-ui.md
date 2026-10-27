# Audit UI / WebView — téléphone ATAK embarqué

Date : 2026-10-27. Branche visée : `release/2.7.0`. Aucune correction appliquée. Aucun commit.

Mesures mémoire : **non mesuré**. Aucun banc Node ni harness local ne couvre ces pages. Arma 3 n’a pas été lancé. Les tailles ci-dessous sont des plafonds déduits du code (nombre de tuiles, taille de fichier), pas des relevés d’occupation.

## Périmètre réellement chargé

Trois surfaces HTML distinctes. `mergewt/`, `Workshop/`, `_tmp/` et le portail `public/` ne sont pas chargés par ces WebView (les tuiles distantes pointent vers des adresses HTTPS, pas vers le PHP du dépôt).

| Surface | Fichiers | Qui la charge |
| --- | --- | --- |
| Téléphone natif 2.7 | `mod/COMSPEC_ATAK_Native/Sources/addons/main/data/web/slide.html` | `fn_slideWebShow.sqf`, contrôle `COMSPEC_RscWebBrowser` (`type=106`). Briefing et écrans de diffusion uniquement. |
| Tablette Overwatch, interface V2 | `mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/atak-v2/` (`atak-v2.html`, `app.js`, `live-map.js`, `map-*.js`, Leaflet 147 557 octets) | `fn_webBrowserOnLoad.sqf` si la version d’interface vaut `v2`. Dialogue `idd` 9974, contrôle 9401. |
| Tablette Overwatch, interface V1 | `connect/web/tablet.html` | Même dialogue si la version n’est pas `v2`. Pas de Leaflet. |
| Coque « phone.html » | `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/phone.html` plus `map-bus.js`, `map-store.js`, `map-tiles.js` | Aucun `LoadFile` vers `phone.html` dans les SQF présents. La page réclame pourtant `vendor/leaflet/leaflet.js`, `map-engine.js`, `map-symbols.js` et `live-map.js`, absents de ce dossier (listing du 2026-10-27 : `assets`, `media`, les trois `map-*.js`, `phone.html`). Le moteur complet qui existe est celui d’atak-v2. |

Le téléphone natif (carte, drone, galerie, ouverture / fermeture) est dessiné en contrôles Arma. `fn_pageClear.sqf` arrête la caméra en direct (`livecamStop`) et supprime les contrôles de page au frame suivant, navigateur compris. La vue drone `fn_pageView3d.sqf` est une texture de rendu (`r2t(comspec_livecam)`), pas une page HTML. La galerie du `phone.html` (panneau photos) n’affiche que du texte, aucun `<img>` de cliché. Il n’y a pas de `<video>` dans `phone.html`.

## Conditions du navigateur embarqué citées par le code

- `CT_WEBBROWSER`, Arma 3 2.14 ou plus (`controls.hpp` lignes 118–121, `fn_webBrowserOnLoad.sqf`).
- Contrôle natif : `allowExternalURL=0`, contenu local seulement. Les images hors PBO sont refusées en texture ; le navigateur est le repli (`controls.hpp`, `fn_slideWebData.sqf`).
- `LoadFile` est tenté en premier. Si `PageLoaded` n’arrive pas, repli `OpenDataAsURL` (diapositive : 4 s, `fn_slideWebShow.sqf` ; tablette : 2,2 s, `fn_webBrowserOnLoad.sqf`).
- Une image `data:` peut être refusée : `slide.html` bascule alors vers une adresse `blob:` (commentaire lignes 11–12).
- Image trop lourde pour ce navigateur : plafond annoncé 1,4 Mo (`fn_slideWebData.sqf`, erreur `too_large`).
- Morceaux JavaScript de 100 000 caractères : une longue chaîne d’un bloc « passe mal » (`fn_slideWebShow.sqf`).
- Pont vers le jeu : `A3API.SendAlert` ou, à défaut, `alert` (`phone.html` lignes 174–188). Un seul transport, pour éviter un ordre en double.
- `IndexedDB` et `fetch` sont testés ; à défaut, mémoire vive ou abandon (`map-store.js`, `map-tiles.js`).
- Fermeture de la tablette : `onUnload` du dialogue 9974 invalide le jeton de boucle (`display_webbrowser.hpp` ligne 60). Le script planifié de `fn_webBrowserOnLoad.sqf` (lignes 123–141) s’arrête. Les écouteurs du contrôle meurent avec le dialogue.

## Cycles

**Ouverture / fermeture du téléphone natif.** Quitter une page appelle `pageClear` : les contrôles, dont le navigateur de diapositive, sont masqués puis `ctrlDelete` au frame suivant. Rouvrir le briefing recrée un navigateur et rappelle `LoadFile`. Le magasin base64 `COMSPEC_ATAK_SlideWeb` survit dans `uiNamespace`, vidé d’un coup au-delà de 12 images (`fn_pageBriefing.sqf` lignes 347–349, même seuil pour Google et pour les écrans). Ce n’est pas une croissance sans borne.

**Ouverture / fermeture de la tablette.** Chaque ouverture charge `atak-v2.html` ou `tablet.html`. La fermeture détruit le dialogue. Les `setInterval` du document partent avec lui. Ce que le code écrit dans `IndexedDB` (`comspec_atak_v2`) est conçu pour rester (« extraits déjà ouverts », `phone.html` ligne 3903). Que le profil du navigateur Arma conserve réellement IndexedDB après `ctrlDelete` : **non mesuré** (hypothèse).

**Carte.** `live-map.js` `ensure` (lignes 547–615) ne reconstruit la carte que si le monde ou la taille change. Sinon la même instance Leaflet reste. `applyState` (lignes 968–1001) ne détruit rien quand la liaison tombe. Déconnexion puis reconnexion : tuiles, objets et magasins continuent de grossir.

**Caméra drone.** Hors WebView (texture de rendu, arrêt dans `pageClear`). Le panneau drone de `phone.html` (vers 3200 et 5092) réécrit une liste de texte à chaque état. Pas d’image, pas de listener ajouté dans cette boucle.

**Galerie.** Même constat : `compatPhotoList` est vidé puis reconstruit (`phone.html` ligne 5090). La taille suit le tableau reçu, le JavaScript ne l’allonge pas. L’appareil photo HTML est un PNG statique (`media/camera-overlay.png`) et des ordres `camera:open|shot|close`.

**Écrans de diffusion.** Un navigateur par display de texture, réutilisé (`fn_screenCast.sqf` lignes 307–316). `displayUpdate` toutes les 10 s tant que l’écran est vu (lignes 271–276). Consommation de cette recopie : **non mesuré**. Les displays nuls sont retirés du dictionnaire (ligne 296). Aucun `closeDisplay` dans ce fichier : rétention d’un display dont l’objet a disparu mais qui n’est pas nul — **hypothèse**.

**Diapositive.** Chaque image non vide rappelle `LoadFile` puis, sur `PageLoaded`, injecte le base64. Le gestionnaire `PageLoaded` n’est posé qu’une fois par contrôle (drapeau `COMSPEC_ATAK_SlideWebOk` / `SlideWebEh`, lignes 26–28).

## Problèmes

### UI-001 — Adresses blob des tuiles jamais libérées

- **Statut :** confirmé
- **Fichier :** `mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/atak-v2/map-tiles.js:47-53`, `:68-80`, `:216-220`. Copie plus courte, même trou : `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/map-tiles.js:39-41`, `:55-64`, `:151-155`.
- **Symptôme :** en parcourant la carte, le nombre d’adresses `blob:` augmente à chaque tuile servie depuis le magasin, y compris en repassant sur les mêmes dalles.
- **Cause :** `fromCache` fait `URL.createObjectURL` à chaque lecture. Aucun `URL.revokeObjectURL` dans `atak-v2/` ni dans le dossier `web/` de `phone.html` (recherche exhaustive). Si la tuile réseau a déjà réussi, l’adresse créée est abandonnée sans être révoquée. Leaflet retire l’`<img>` au panoramique ; l’adresse blob vit jusqu’à la fin du document.
- **Preuve :** `blobUrl` lignes 47–53 ; `fromCache` retourne cette adresse ; le `then` de `paintTile` (216–220) ne révoque pas l’ancienne `img.src`. Zéro occurrence de `revokeObjectURL`.
- **Gravité :** haute
- **Impact :** une session carte qui zoome et se déplace réalloue une adresse et un décodage par peinture, pas par dalle unique. Plafond théorique du monde : zoom max 7, tuile 256, monde 30 720 (`layer`, lignes 228–248) soit de l’ordre de 120 × 120 dalles si tout le théâtre est visité. Le répétition sur les mêmes dalles n’est pas plafonnée. Octets : **non mesuré**.
- **Correction minimale :** révoquer l’adresse précédente de l’image avant d’en poser une nouvelle, et révoquer dans l’événement `tileunload` de la couche. Ne pas créer d’adresse si `paintedOk` est déjà vrai.
- **Test de répétition :** ouvrir atak-v2 avec `?preview=map`, zoomer et panser deux minutes sur la même zone, compter `performance` / les `blob:` encore accessibles (script console : pas de compteur dans le dépôt). Puis changer de monde pour passer dans `destroy()` et vérifier que les adresses de l’ancienne carte sont révoquées.

### UI-002 — Magasins tuiles, événements et file sans plafond

- **Statut :** confirmé
- **Fichier :** `atak-v2/map-store.js:8`, `:133-151` ; `atak-v2/map-tiles.js:82-87` et `:121-125` ; `atak-v2/map-bus.js:67-80`, `:91-99`, `:105-111` ; `atak-v2/map-engine.js:116-134`. Copie `phone.html` : `map-bus.js:73-86` et `:148-155` (pas de fonction `ack`).
- **Symptôme :** chaque dalle visitée et chaque tracé restent en mémoire vive, dans `localStorage` (sauf les tuiles) et dans IndexedDB, y compris après accusés ou suppressions à l’écran.
- **Cause :** `put` copie toujours dans `mem[store]` et n’efface jamais les tuiles. `cacheLater` / `toCache` enregistrent le blob avec un horodatage, sans quota ni TTL. `emitEvent` empile `events` puis `queue`. `ack` ne retire que la file, et le commentaire ligne 110 dit que l’historique `events` est conservé. Aucun appel à `COMSPEC_MapBus.ack` ni `.fail` dans les JS et SQF hors de la définition. `map-engine.js` borne le journal écran à 800 (`MAX_EVENTS`, lignes 5 et 129–131) mais le bus, lui, persiste chaque événement en entier.
- **Preuve :** `mem.tiles[key] = value` sans `delete` correspondant ; `store.del` n’est atteint que par `ack` ; recherche `COMSPEC_MapBus.ack` : une seule définition. `kept` (`map-tiles.js:121-125`) retient aussi une entrée par dalle pour toute la vie de la page.
- **Gravité :** haute
- **Impact :** la carte et les outils de dessin grossissent pour toute la vie du document, et au-delà si IndexedDB survit à la fermeture (hypothèse, non mesuré). `localStorage` finit par lever, l’exception est avalée (`lsWrite`) : la copie mémoire continue. La file « pending » du centre de liaison ne redescend pas, puisqu’aucun accusé n’est branché.
- **Correction minimale :** plafond ou éviction LRU sur `tiles` (mémoire et IndexedDB). Après envoi, appeler `ack`. Purger `events` au même plafond que `MAX_EVENTS`, ou ne pas y recopier le payload déjà gardé par la scène. Vider `kept` dans `destroy`.
- **Test de répétition :** en aperçu, poser puis effacer 50 points, appeler `COMSPEC_MapStore.all("queue")` et `all("events")` : les deux longueurs doivent redescendre. Panser jusqu’à une cinquantaine de dalles, rouvrir la tablette, vérifier que le magasin tuiles ne dépasse pas le plafond choisi.

### UI-003 — Objets de scène « supprimés » conservés

- **Statut :** confirmé
- **Fichier :** `atak-v2/map-engine.js:415-417`, `:625-637`, `:48-61`, `:63-90`
- **Symptôme :** après de nombreux tracés effacés, le rechargement de la carte restaure tout l’historique des objets, y compris ceux marqués supprimés.
- **Cause :** `removeObject` pose `deleted = true` et retire la couche Leaflet, mais laisse l’entrée dans `state.objects`. `save` sérialise le dictionnaire entier vers `localStorage` (`comspec_atak_scene_v1`) et vers la clé IndexedDB `scene`. `load` réinjecte ce dictionnaire sans filtre. Le journal d’événements est coupé à 800 ; les objets ne le sont pas. Le croquis libre, lui, est coupé à 600 points (`map-engine.js` vers 708).
- **Preuve :** `delete leafletById[id]` existe (`removeLeaflet`, lignes 273–280) ; aucun `delete state.objects[id]`.
- **Gravité :** moyenne
- **Impact :** session de dessin longue, puis réouverture : parse JSON de plus en plus gros, `repaintAll` ignore les supprimés mais le snapshot reste. Quota `localStorage` ensuite silencieux.
- **Correction minimale :** retirer la clé de `state.objects` à la suppression (garder l’événement court déjà borné à 800). Au `load`, ignorer `deleted` ou les objets au-delà d’un plafond.
- **Test de répétition :** créer et supprimer 200 points, `JSON.stringify(localStorage.getItem("comspec_atak_scene_v1")).length` ne doit pas suivre le nombre de suppressions. Recharger la page : `COMSPEC_MapEngine.objects().length` vaut 0.

### UI-004 — Minuterie et téléchargements d’une tuile déjà retirée

- **Statut :** confirmé
- **Fichier :** `atak-v2/map-tiles.js:108-120` et `:199-215`
- **Symptôme :** un panoramique rapide laisse des `setInterval` et des `fetch` continuer sur des images sorties de la carte.
- **Cause :** chaque `createTile` arme un `setInterval` de 3 s qui avance dans les six adresses miroir. Il n’est arrêté que si `paintedOk`, `finished`, ou si l’index dépasse la liste (au plus environ 18 s). Rien n’écoute `tileunload`. `cacheLater` efface le drapeau `inflight` au bout de 8 s alors que la chaîne de `fetch` (délai 2,2 s par adresse, lignes 91–95) peut encore tourner : la même dalle peut être demandée deux fois. Les `AbortController` ne sont pas annulés quand la tuile quitte la carte.
- **Preuve :** `failoverTimer` n’est pas rangé sur l’image ; `clearInterval` n’est que dans le rappel. `setTimeout(..., 8000)` supprime `inflight[id]` sans `abort`.
- **Gravité :** moyenne
- **Impact :** borné dans le temps par tuile (environ 18 s), pas une minuterie permanente. Sous panoramique continu, le nombre de téléchargements simultanés suit les tuiles créées pendant cette fenêtre. La copie `map-tiles.js` de `phone.html` n’a pas cet intervalle.
- **Correction minimale :** stocker l’id de minuterie sur l’image et l’arrêter dans `tileunload`, en révoquant aussi le blob (UI-001). Ne pas remettre `inflight` à faux tant que la promesse n’est pas réglée, ou annuler le `AbortController`.
- **Test de répétition :** en aperçu, zoomer et panser sans attendre le chargement. Après arrêt, attendre 20 s : plus aucun intervalle de tuile (compteur posé dans `paintTile` le temps du test). Les requêtes vers les miroirs cessent.

### UI-005 — Diapositive : blob et base64 retenus

- **Statut :** confirmé pour la vie du document ; accumulation d’une diapositive à l’autre : **hypothèse**
- **Fichier :** `mod/COMSPEC_ATAK_Native/Sources/addons/main/data/web/slide.html:8-13` ; `fn_slideWebShow.sqf:13-21` et `:30-41`
- **Symptôme :** une diapositive dont l’adresse `data:` est refusée reste décodée en blob jusqu’à la navigation suivante. Le base64 complet reste aussi sur le contrôle.
- **Cause :** `cs.show` pose `img.src` en `data:`. Le commentaire dit que cette forme est refusée et que le repli est `URL.createObjectURL`. `onerror` ne révoque pas l’adresse précédente. `cs.p` est bien vidé. Le SQF garde le base64 dans `COMSPEC_ATAK_SlideWeb` sur le contrôle, et `uiNamespace` en garde jusqu’à 12 (voir cycles). Chaque appel avec image rappelle `LoadFile` : un nouveau document devrait lâcher les blob de l’ancien. Si `LoadFile` ne détruit pas le contexte (le code dit seulement que `PageLoaded` peut ne jamais venir), les blob s’empilent. **Non mesuré.**
- **Preuve :** `slide.html` lignes 11–13, aucun `revokeObjectURL`. Injection par morceaux de 100 000 caractères puis `cs.show`.
- **Gravité :** moyenne (pic d’une image, plafond produit 1,4 Mo) ; haute seulement si le contexte survit au `LoadFile` (hypothèse).
- **Impact :** pic = chaîne base64 + image `data:` + blob de repli, sur le téléphone et sur chaque écran de diffusion qui réutilise son navigateur. Le magasin uiNamespace est borné par une remise à zéro au-delà de 12.
- **Correction minimale :** dans `show`, révoquer `img.dataset.blob` avant d’affecter la nouvelle adresse, et ne garder qu’une des deux formes (blob ou data). Côté SQF, vider la variable du contrôle une fois `cs.show` parti.
- **Test de répétition :** enchaîner 15 diapositives Google ou Athena (chemin `WEB:`). Après chaque `PageLoaded`, une seule adresse blob doit rester. Le magasin `COMSPEC_ATAK_SlideWeb` ne dépasse pas 13 entrées (comportement actuel du seuil `> 12`).

### UI-006 — Course IndexedDB à 400 ms

- **Statut :** confirmé
- **Fichier :** `atak-v2/map-store.js:10-44`. Même schéma : `web/map-store.js` du dossier `phone.html`, lignes 12–40, base `comspec_atak_v1` au lieu de `comspec_atak_v2`.
- **Symptôme :** si l’ouverture IndexedDB dépasse 400 ms, tous les accès suivants ignorent la base, tout en laissant une connexion ouverte. La copie mémoire devient le seul magasin, sans plafond (UI-002).
- **Cause :** `setTimeout(..., 400)` résout la promesse avec `db` encore nul. `ready` n’est plus recalculé. `onsuccess` peut ensuite remplir `db`, mais `tx` fait `if (!d) return fn(null)` avec la valeur déjà résolue. Aucun `db.close()`.
- **Preuve :** lignes 21 et 41–43 ; `openDb` retourne `ready` dès le second appel (lignes 11–12).
- **Gravité :** moyenne
- **Impact :** navigateur embarqué lent ou sans IndexedDB : croissance uniquement en RAM pour la session. Navigateur qui répond après 400 ms : connexion orpheline + RAM. Comportement réel dans Arma : **non mesuré**.
- **Correction minimale :** ne résoudre qu’à `onsuccess` / `onerror`, avec un délai qui bascule en mémoire sans garder la requête. Si le délai gagne, `abort` ou ignorer `onsuccess` et fermer.
- **Test de répétition :** retarder `indexedDB.open` de 500 ms dans un jsdom ou un Chromium, puis `put` / `get` : la valeur doit rester lisible, et `indexedDB.databases` ne doit pas montrer une connexion inutilisée. Aucun harness de ce type dans le dépôt.

### UI-007 — Le centre de liaison relit toutes les tuiles

- **Statut :** confirmé
- **Fichier :** `atak-v2/map-bus.js:140-166` ; appel depuis `phone.html:3906-3934` (`refreshSyncCenter`, déclenché à l’ouverture du panneau liaisons, ligne 3959). atak-v2 n’a pas ce panneau ; la fonction `summary` y est tout de même publique.
- **Symptôme :** ouvrir le centre de liaison reconstruit en mémoire un tableau de toutes les tuiles, blobs compris, uniquement pour afficher un nombre.
- **Cause :** `store.all("tiles")` puis `tiles.length`. `getAll` sur un magasin de blobs ne compte pas sans charger.
- **Preuve :** `pair[1]` n’est utilisé que pour `.length` (ligne 150 de `map-bus.js` atak-v2, équivalent `phone` `map-bus.js` vers 132).
- **Gravité :** moyenne, amplificateur de UI-002
- **Impact :** un aller-retour sur ce panneau après une longue carte duplique l’ensemble des blobs le temps du `then`. Octets : **non mesuré**.
- **Correction minimale :** compter par un index ou `count()` IndexedDB, sans `getAll` des blobs.
- **Test de répétition :** après 30 tuiles en cache, ouvrir le panneau liaisons et vérifier qu’aucun tableau de blobs n’est alloué (point d’arrêt sur `getAll`, ou compteur temporaire).

### UI-008 — Anneaux de ping retenus dans un tableau

- **Statut :** confirmé
- **Fichier :** `atak-v2/live-map.js:146-175` et `:112-127`
- **Symptôme :** chaque signal sur la carte ajoute deux couches. Elles quittent la carte après 3,6 s mais restent référencées jusqu’à un changement de monde.
- **Cause :** `pingMarkers.push(marker, halo)` puis `removeLayer` dans le `setTimeout`, sans `splice`. `destroy` remplace le tableau seulement quand `ensure` reconstruit la carte.
- **Preuve :** lignes 160–174 ; aucun `pingMarkers.splice` dans le fichier.
- **Gravité :** basse
- **Impact :** le DOM des anneaux part (l’animation CSS infinie de `styles.css` ligne 32 s’arrête avec eux). Les objets JavaScript restent pour la session. Pas une fuite de nœuds si `removeLayer` réussit.
- **Correction minimale :** retirer les deux références dans le même `setTimeout`, ou ne pas garder le tableau.
- **Test de répétition :** appeler `COMSPEC_ATAK_liveMapPulse` cinquante fois, attendre 4 s, vérifier que le tableau interne est vide (l’exposer le temps du test) et qu’il ne reste pas de `.atak-live-ping` dans le DOM.

### UI-009 — Horloges non arrêtées

- **Statut :** facultatif
- **Fichier :** `atak-v2/app.js:291-292` (15 s) ; `tablet.html:2943-2949` (1 s) ; `phone.html:669`, `:4532-4535`, `:4564` (trois intervalles d’1 s).
- **Symptôme :** aucun `clearInterval`. Les rappels tiennent le document.
- **Cause :** armés une fois au chargement, jamais réarmés par le JS lui-même. La fermeture du dialogue 9974 détruit le document (`display_webbrowser.hpp`). Ils ne s’empilent pas d’une ouverture à l’autre, sauf si le même document était réévalué sans navigation (le code ne le fait pas).
- **Preuve :** une occurrence de chaque `setInterval` au premier niveau, pas dans une fonction rappelée par `ExecJS`.
- **Gravité :** basse
- **Impact :** coût fixe pendant que la tablette est ouverte. Pas une accumulation.
- **Correction minimale :** inutile pour la fuite. Si le document doit survivre masqué, garder les identifiants et les couper sur l’ordre `close`.
- **Test de répétition :** ouvrir et fermer la tablette dix fois. Un seul dialogue 9974 à la fois, jeton `COMSPEC_WebBrowser_RefreshToken` à −1 après fermeture. Pas de second `setInterval` dans un document déjà vivant.

### UI-010 — Second écouteur de double-clic si `attach` est rappelé

- **Statut :** hypothèse
- **Fichier :** `atak-v2/map-engine.js:597-623`
- **Symptôme :** plusieurs finitions de tracé pour un seul double-clic, si `attach` vise deux fois la même carte.
- **Cause :** `map.on("dblclick", ...)` sans `map.off`. Aujourd’hui `ensure` appelle `destroy` (qui fait `map.remove()`) puis `attach` une fois ( `live-map.js:562` et `:603-606`). Le chemin actuel ne double pas. Un second `attach` sur la même instance le ferait. Les `addEventListener` de `app.js` (ligne 196) et de `map-engine.js` (lignes 787 et 831) sont au chargement, une fois.
- **Preuve :** un seul site d’appel de `attach` dans atak-v2. Pas de `off`.
- **Gravité :** basse
- **Impact :** nul sur le chemin `ensure` actuel.
- **Correction minimale :** `map.off("dblclick")` avant `map.on`, ou `{ once: false }` avec une fonction nommée retirée dans `destroy`.
- **Test de répétition :** appeler `COMSPEC_MapEngine.attach` deux fois avec la même carte, double-cliquer un polygone : une seule finalisation.

### UI-011 — Animations CSS permanentes

- **Statut :** facultatif
- **Fichier :** `phone.html` (ex. lignes 1357, 1542, 1753, 2453, 2492–2503) ; `atak-v2/styles.css:32` ; `tablet.html` lignes 176, 189, 810.
- **Symptôme :** pulsations, scan et point « en ligne » tournent sans fin.
- **Cause :** `animation` infinie sur des nœuds déjà dans la page. `phone.html:2048-2050` les coupe si `prefers-reduced-motion: reduce`. Aucune règle ne crée de nœuds.
- **Preuve :** les sélecteurs cités. Pas de `setInterval` associé.
- **Gravité :** basse
- **Impact :** coût d’affichage constant, pas une accumulation. Les anneaux de ping sont retirés avec la couche (UI-008).
- **Correction minimale :** hors sujet fuite. En jeu, une classe `ingame` qui pose `animation: none` alignerait le téléphone sur le mode réduit.
- **Test de répétition :** laisser le téléphone ouvert cinq minutes, compter les nœuds de `.scanline` et `.live-dot` : le nombre ne bouge pas.

### UI-012 — Repli `OpenDataAsURL` de la tablette

- **Statut :** hypothèse
- **Fichier :** `fn_webBrowserOnLoad.sqf:144-187`
- **Symptôme :** si `LoadFile` ne signale pas la page en 2,2 s, le HTML entier est réinjecté en adresse `data:`. Les scripts relatifs (`vendor/leaflet/leaflet.js`, etc.) ne se résolvent plus depuis une adresse `data:`.
- **Cause :** le repli ne sait pas si `LoadFile` finira par réussir. Deux navigations peuvent se croiser. Le code ne documente pas une rétention des deux documents.
- **Preuve :** `LoadFile` ligne 159, `OpenDataAsURL` lignes 182–187, sans annuler le premier chargement. Taille du JS Leaflet sur disque : 147 557 octets. Occupation dans Arma : **non mesuré**.
- **Gravité :** basse pour la mémoire ; le symptôme visible est une carte vide, pas une fuite prouvée.
- **Impact :** pic le temps du second chargement, **non mesuré**.
- **Correction minimale :** ne pas ouvrir le repli `data:` pour atak-v2 (les scripts relatifs casseront). Attendre `PageLoaded` ou afficher le secours déjà prévu dans le dialogue.
- **Test de répétition :** forcer un `LoadFile` lent et vérifier qu’une seule navigation aboutit, avec Leaflet défini (`typeof L`).

## Chemins vérifiés sans accumulation

- Journal local du `phone.html` : 80 lignes (`appendLocalLog`, lignes 416–428), DOM reconstruit par `replaceChildren` (lignes 491–504).
- Contacts, POI et cercles de précision : mis à jour ou `removeLayer` (`live-map.js` vers 792–921).
- Grille de carte : plafond 80 lignes (`live-map.js` vers 194–207), couche vidée avant retracé.
- Relief : `renderTerrain` retire l’ancienne couche avant `toDataURL` (lignes 427–475). Une seule image à la fois. Pic du PNG : **non mesuré**.
- Messages, ordres, listes compat : `innerHTML` remplacé, pas d’empilement. La taille suit la charge reçue du jeu.
- Écouteurs de clics de `phone.html` (`bind`, ligne 3766) et de `tablet.html` : une fois au parse.
- Gestionnaire `PageLoaded` de la diapositive : une fois par contrôle.
- Boucle SQF de rafraîchissement tablette : un jeton, invalidé à la fermeture. Pas un timer JavaScript.

## Hors WebView (pour ne pas les chercher dans ces pages)

Caméra drone, galerie photo du téléphone natif, ouverture et fermeture de l’écran ATAK natif : contrôles et textures Arma, pas les HTML ci-dessus. Seule la diapositive briefing / écran de diffusion entre dans le navigateur intégré du téléphone natif.

---

## Append — A5 WebView (27/10 soir)

A5 ([webview](0f833c3a-9d76-4b26-9801-668c0104c0d5)) : UI-001..003 confirmes en source ; residus `kept`, course IndexedDB 400 ms, `ack` non branche, `pingMarkers`. Mesures memoire : **non mesure**.

---

## Append — statut UI-001..003 (delta doc)

Le corps du rapport ci-dessus decrit l'etat **pre-correctif**. Dans `atak-v2` actuel : UI-001 et UI-003 sont corriges ; UI-002 attenue (trimMem) mais `kept` / `ack` restent. Voir `issues.json`.
