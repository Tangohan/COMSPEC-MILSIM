# Audit fonctionnel — ATAK Overwatch Beta

Date : 2026-09-14
Périmètre : **uniquement** les routes `/-ATAK-OVERWATCH-Beta`,
`/atak-overwatch-beta` et `/ATAK-OVERWATCH-Beta`, leur vue et les API déjà
consommées par le client ATAK partagé.

## Résumé exécutif

La bêta disposait de deux implémentations contradictoires : une maquette autonome
dans `views/atak-overwatch-beta.php`, composée de contacts et d'événements fictifs,
alors que `views/atak.php` et `public/assets/js/atak-overwatch-beta.js` contenaient
déjà l'intégration prévue au client opérationnel. Le contrôleur construit bien le
contexte ATAK complet (JWT, tenant, capacités serveur, cartes et préférences), mais
la maquette ne le consommait pas. C'est le manque P0 : l'écran semblait connecté
alors qu'aucune position COMSPEC, permission métier ni reconnexion ne l'alimentait.

Le backend partagé est sensiblement plus complet que la bêta visible. Ce rapport
distingue donc **API/client ATAK existant** et **exposition réelle dans la bêta**.
La première livraison doit reconnecter la composition Overwatch au client ATAK,
sans nouvelle route et sans modification des mécanismes d'authentification/RBAC.

## Arborescence et flux audités

| Couche | Fichiers / routes | Constat |
|---|---|---|
| Entrée web | `routes/web.php` (`/-ATAK-OVERWATCH-Beta` et alias) | Protégée par la pile `$mwAtakWeb`, identique à `/atak`. |
| Composition | `app/Controllers/Web/AtakController.php::overwatchBeta/index` | Construit token, tenant, capacités, carte, utilisateur et préférences ; choisit la vue bêta. |
| Vue bêta | `views/atak-overwatch-beta.php` | Maquette plein écran autonome au moment de l'audit ; données codées en dur, aucune API. |
| Client opérationnel partagé | `views/atak.php`, `public/assets/js/atak-*.js` | Carte Leaflet, BFT, chat, dessin, photos, replay, 3D, météo, modules C2 et dégradations réseau. |
| Habillage bêta prévu | `public/assets/css/atak-overwatch-beta.css`, `public/assets/js/atak-overwatch-beta.js` | Code partiellement divergent de la vue : il attend le DOM du client ATAK réel. |
| API tactique | `/api/units`, `/api/markers`, `/api/map-shapes`, `/api/chat`, `/api/recon/images`, `/api/atak/*` | Contrôleurs partagés et filtrage tenant/capacités existants ; non consommés par la maquette. |
| COMSPEC | `AtakApiController::position`, `operatorSync`, `clientInit`, unités/activité | Ingestion déjà disponible, mais l'état n'atteignait pas la maquette bêta. |

## Inventaire fonctionnel

Légende : **Existe** = présent et opérationnel dans le client partagé ; **Partiel**
= backend ou UI présent mais parcours incomplet ; **Absent** = aucune implémentation
identifiée dans le périmètre ; **Maquette** = affichage fictif non relié aux données.

| Domaine | Fonction cible | État avant lot P0 | Preuves / routes concernées | Décision V1 |
|---|---|---:|---|---|
| Carte | Carte temps réel, zoom, recentrage, géolocalisation | **Maquette** en bêta / **Existe** dans ATAK | `views/atak-overwatch-beta.php`; `public/assets/js/atak-map.js`, `atak-units.js`; `GET /api/units` | P0 : réutiliser le client réel. |
| Carte | Marqueurs ami/hostile/inconnu, labels, fiche unité | **Maquette** / **Existe** | `atak-markers.js`, `atak-unit-popup.js`, `atak-symbol-picker.js`; `/api/markers`, `/api/units` | P0 via client partagé, P1 pour presets Overwatch. |
| Carte | 2D / 3D, relief, bâtiments, altitude | **Absent** dans la maquette / **Partiel** dans ATAK | `atak-scene-3d.js`, `atak-terrain-3d.js`; `/api/atak/scene`, `/api/atak/terrain/*` | P1, selon couverture terrain disponible. |
| Carte | Calques géoréférencés personnalisés | **Partiel** | Cartes tenant dans `AtakController`; création de carte existante, pas d'overlay libre dans la bêta | P1 ; validation API/RBAC requise avant ajout d'upload. |
| Carte | PiP vidéo/image/sous-carte | **Partiel** | `atak-cams.js`; `GET/POST /api/atak/video-feeds` | P2 : vidéo existante, sous-carte absente. |
| Carte | Liens entre squads en direct | **Partiel** | `public/assets/js/atak-overwatch-beta.js` calcule les liens depuis `ATAKUnits` | P1 : stabiliser groupes et préférences. |
| Carte | Historique et playback de trajectoire | **Existe** dans ATAK, invisible dans la maquette | `atak-motion.js`, `atak-replay.js`; historique `atak_unit_motion` | P0 via client partagé. |
| Dessin | Points, lignes, polygones, mesure | **Existe** dans ATAK, boutons factices en bêta | `atak-map-tools.js`, `atak-map-shapes.js`; `/api/map-shapes`, `/api/markers` | P0 via client partagé. |
| Dessin | Découpage de polygones | **Absent** | Aucun outil de scission identifié | P2, algorithme et UX coûteux. |
| Dessin | AOI persistantes | **Partiel** | `/api/atak/zones`, `/api/map-shapes`; pas de workflow AOI unifié Overwatch | P1 ; conserver les contrôles serveur. |
| OSINT | Notes/liens/captures géolocalisés | **Partiel** | `/api/sse/notes`, `/api/sse/v1/*`, `atak-intel-view.js` | P1 ; droits SSE à valider avant exposition. |
| OSINT | Tags et recherche | **Partiel** | recherche SSE disponible côté API ; pas de panneau Overwatch dédié | P1/P2. |
| Temps réel | SSE positions/alertes/chat | **Absent** | Le client utilise du polling (`views/atak.php`, `atak-units.js`, `atak-chat.js`), aucun `EventSource` tactique | P1 après contrat d'événements et revue infra. |
| Temps réel | Reconnexion et fallback polling | **Partiel** | polling et backoff existants (`atak-socket.js`, `AtakPollBackoffAssetTest`) | P0 via client partagé ; SSE ensuite. |
| Contacts | Présence live et filtres unité/rôle | **Maquette** / **Partiel** dans ATAK | `atak-units.js`, `AtakApiController::unitsIndex`; filtre texte présent | P0/P1. |
| Contacts | Visibilité strictement RBAC | **Non appliquée par la maquette** / **Serveur existant** | capacités injectées par `AtakController`; garde/filtrage dans `AtakApiController` | P0 : ne jamais produire de contacts côté front. |
| Imagerie | Upload lié position/unité | **Absent** en bêta / **Existe** dans ATAK | `atak-cams.js`; `/api/recon/images`, `/api/intel/photos` | P0 via client partagé. |
| Imagerie | Liste, métadonnées, SEND/ADD | **Partiel** | opérations `/api/recon/images/{id}/ops`, interface photos existante | P1 : clarifier destinataires et états. |
| Comms | Canaux, historique persistant | **Maquette** / **Existe** dans ATAK | `atak-chat.js`; `/api/chat`, `/api/chat/channels`; migrations `atak_chat_*` | P0 via client partagé. |
| Comms | Normal / alerte / système | **Partiel** | sources et styles chat présents ; homogénéisation visuelle à vérifier | P1. |
| Comms | Canal Support distinct | **Absent** | aucun type de canal support identifié | P1 ; nouvelle convention API/table à faire valider. |
| Sync | Connecté, latence, dernier ping | **Faux indicateur** en bêta / **Partiel** dans ATAK | libellé `LINKED` statique ; `atak-status`, `atak-ops-status.js`, présence et statistiques API | P0 : dériver du statut réel ; P1 : exposer dernier ping précis. |
| Sync | Fréquence configurable | **Absent** en bêta / **Partiel** | intervalles multiples codés dans `views/atak.php` et modules JS | P1 : centraliser la politique de rafraîchissement. |
| Personnalisation | Thème, panneaux, marqueurs, préférences | **Partiel** | `atak-theme.js`, `atak-pin-dock.js`, `UserUiPreferencesRepository`; watchlist locale | P1 ; préférences tactiques serveur à étendre après validation. |
| Données | Import/export mission | **Partiel** | export AAR et mission-plan PDF ; pas de paquet mission complet | P2. |
| Données | Journal d'audit consultable | **Partiel** | activité/web logs/AAR existent ; vue unifiée absente | P2. |
| Calcul | ETA pied/véhicule, recalcul dynamique | **Partiel** | planification de route et GPS existent ; pas d'ETA multimodal Overwatch unifié | P2. |
| Satellite | Catalogue, recherche, passages | **Absent** | aucune source orbitale identifiée | Hors V1 : dépendance à une source TLE et calcul orbital. |
| TAK | Enveloppe proche CoT | **Partiel** | plusieurs payloads positions/événements existent mais pas de contrat CoT unifié | P1 conception, migration progressive uniquement. |
| TAK | Geofencing entrée/sortie AOI | **Partiel** | `/api/atak/zones/check-position`, `/api/atak/zones/alerts` | P1 ; brancher alertes live. |
| TAK | 9-line / CASEVAC | **Existe** | `/api/nine-line`, `/api/cas`, `/api/atak/medevac` | P1 : exposer clairement depuis Overwatch. |
| TAK | Profil d'élévation | **Existe** côté API / **Partiel** UI | `/api/atak/terrain/profile`, outils terrain | P2 selon données terrain. |
| TAK | Overlay météo mission | **Existe** dans ATAK | `tacmap-weather.js`, `/api/atak/weather` | P1 via client partagé. |
| TAK | Cache hors-ligne des tuiles | **Absent** | aucun service worker de cache tactique identifié | Hors V1 : volume, droits des tuiles et invalidation. |
| TAK | Impression/PDF annoté | **Partiel** | PDF mission/AAR existants, pas de rendu carte complet | P2 : rendu cartographique serveur/navigateur à cadrer. |

## Incohérences et dette technique

| Priorité | Constat | Risque | Remédiation |
|---|---|---|---|
| P0 | La vue bêta affiche unités, réseau, latence, imagerie et événements codés en dur. | Fausse situation tactique et contournement visuel du RBAC. | Faire de la vue un simple wrapper de `views/atak.php`. |
| P0 | Le contrôleur calcule le contexte de sécurité et de mission, ensuite ignoré par la maquette. | Désynchronisation COMSPEC/front et code mort. | Réutiliser intégralement le bootstrap ATAK. |
| P0 | Le JS/CSS Overwatch existant cible le DOM de `views/atak.php`, pas celui de la maquette. | Deux implémentations divergentes, tests et production incohérents. | Une seule source de vérité : client ATAK + composition Overwatch. |
| P1 | Polling dispersé entre la vue et plusieurs modules. | Rafales de requêtes, backoff incohérent, difficulté d'ajouter SSE. | Créer ultérieurement un coordinateur de transport, polling fallback compris. |
| P1 | Watchlist et style de fond sont en `localStorage`. | Préférences non portables ; acceptable hors permissions. | Migrer vers préférences serveur sans en faire une source d'autorisation. |
| P1 | Routes API sans middleware déclaré dans la table de routes. | Audit difficile, même si les contrôleurs appliquent leurs gardes JWT/session/tenant. | Ne pas modifier maintenant ; documenter puis tester la matrice de gardes par famille. |
| P2 | Nombreux alias (`/api/units` et `/api/atak/units`, etc.). | Contrats redondants et maintenance accrue. | Déprécier progressivement, sans rupture COMSPEC. |

### Point sécurité à faire valider

Aucune faille de l'authentification existante n'a été corrigée dans ce lot. La
présence de routes sans middleware visible n'est **pas à elle seule** une preuve de
contournement : les contrôleurs centralisent l'authentification, le tenant et les
capacités. Une campagne de tests négatifs (tenant croisé, JWT révoqué, capacité
absente, session téléphone) est recommandée avant toute modification de ces gardes.

## Plan par lots (une PR par lot)

### P0 — session jouable

1. **PR 1 — Reconnexion de la bêta au client réel** : supprimer la maquette de la
   vue bêta, inclure `views/atak.php`, activer explicitement le mode Overwatch et
   rétablir l'adaptateur UI (navigation, couches, liens squads). Aucun changement
   API/auth/RBAC.
2. **PR 2 — Observabilité de liaison** : état connecté/dégradé/reconnexion,
   latence et dernier message réellement dérivés du transport ; tests des timeouts
   COMSPEC. Une évolution de réponse API peut être nécessaire : validation requise.

### P1 — exploitation tactique

3. **PR 3 — Transport SSE avec polling de secours** : contrat d'enveloppe proche
   CoT pour positions, alertes et chat, curseur de reprise, reconnexion exponentielle.
   **Nouvelle route stream et vérification JWT/RBAC à valider avant code.**
4. **PR 4 — Comms/imagerie opérationnelles** : distinction message/alerte/système,
   SEND/ADD et canal Support. **Schéma et permissions de canal à valider.**
5. **PR 5 — Cartographie avancée** : styles symboliques, AOI, geofencing, liens de
   squads fiables, overlays et préférences serveur. **Uploads et droits SSE/OSINT à
   valider.**
6. **PR 6 — OSINT et formulaires TAK** : panneau notes/tags/recherche, 9-line,
   CASEVAC et alertes de zones en réutilisant les routes existantes.

### P2 — capacités lourdes

7. **PR 7 — Analyse terrain/navigation** : ETA multimodal, recalcul, profil
   d'élévation et playback enrichi.
8. **PR 8 — Portabilité mission** : paquet import/export, journal unifié,
   impression/PDF annotée.
9. **Backlog hors V1** : scission topologique de polygones, sous-carte PiP,
   satellites/prédictions et cache massif hors-ligne. Ces sujets exigent des
   contrats de données, une UX dédiée et/ou une infrastructure qui dépassent une
   V1 de session fiable.

## Contrat d'intégration COMSPEC à préserver

- COMSPEC continue d'émettre par les routes existantes (`position`, `operator/sync`,
  `client-init`, activité) ; le lot P0 ne change aucun payload.
- Les unités affichées doivent toujours provenir de la réponse serveur filtrée,
  jamais d'une liste reconstruite ou autorisée localement.
- Le client partagé conserve ses intervalles et backoffs actuels dans ce lot. Un
  futur transport SSE doit conserver le polling en secours et reprendre le dernier
  état connu sans effacer carte, sélection, tracés ni brouillon de message.
- Un contact expiré doit être présenté comme dégradé/hors liaison selon le TTL
  serveur ; le navigateur ne doit pas inventer un autre seuil d'autorité.

## Vérifications manuelles du lot P0

1. Ouvrir `/-ATAK-OVERWATCH-Beta` avec un compte autorisé : la carte réelle et la
   barre Overwatch sont présentes, aucun contact d'exemple n'apparaît.
2. Couper COMSPEC : le statut passe en mode dégradé et la dernière situation reste
   visible conformément au comportement ATAK existant.
3. Restaurer COMSPEC : le polling reprend et les unités sont actualisées sans
   rechargement de page.
4. Comparer deux rôles : la bêta ne propose pas d'action que le serveur refuse et
   les unités retournées restent celles autorisées par l'API.
5. Ouvrir COMMS, INTEL, MISSION, outils de dessin et replay depuis la barre
   Overwatch : chaque commande active le module ATAK partagé correspondant.

## Suivi d'implémentation

- **Lot P0 / PR 1 livré** : la vue bêta compose désormais le client ATAK réel.
- **Lot P0 / PR 2 livré sans évolution API** : la barre Overwatch reflète l'état
  du composant réseau ATAK (`LINKED`, `DÉGRADÉ`, `RECONNEXION`), reprend la latence
  déjà mesurée par le portail et affiche l'âge de la dernière mise à jour d'unités.
  Le seuil d'affichage dégradé est de 15 secondes, soit cinq cycles du polling
  tactique actuel de 3 secondes. Il ne remplace pas le TTL serveur et n'influence
  ni la conservation des unités, ni leur visibilité, ni une permission.
- **Complément P0 livré** : l'opérateur peut choisir un polling de secours de 3,
  8, 15 ou 30 secondes. Le choix est limité à cette liste, mémorisé par tenant et
  reprogramme un unique timer partagé ; il ne change aucune fréquence d'ingestion
  COMSPEC. Le seuil visuel « dégradé » suit automatiquement l'intervalle choisi.

### Clôture P0 / P1

- **Transport P1 livré** : `/api/atak/stream` diffuse des événements SSE `units`,
  `chat` et `alerts`, uniquement après résolution du tenant, contrôle du plan et
  vérification d'une session web ou téléphone valide. Chaque connexion est courte
  (~24 s), fournit des heartbeats et laisse `EventSource` reprendre automatiquement.
- **Fallback P1 livré** : à l'ouverture SSE, le polling partagé devient un filet de
  sécurité à 30 s ; à la moindre erreur il reprend immédiatement la fréquence
  opérateur, puis le client retente SSE avec un délai exponentiel de 3 à 30 s.
- **Comms P1 livré** : les canaux persistants existants sont exposés dans Overwatch
  et un canal système `support` séparé (`Support technique`) rejoint le catalogue.
- **Cartographie/renseignement/formulaires P1 exposés** : la barre rapide donne un
  accès direct aux AOI, notes/OSINT de mission, CASEVAC/MEDEVAC et 9-Line/JTAC. Les
  fonds, liens squads, styles de marqueurs, photos, zones, météo et géofencing
  continuent de réutiliser les modules et routes ATAK existants.
- Aucun mécanisme de permission n'est ajouté au navigateur : SSE republie les
  sorties des mêmes méthodes API filtrées que le polling, et la barre ne fait que
  naviguer vers des modules déjà soumis aux capacités serveur.

### Clôture P2

- **Analyse terrain/navigation** : Overwatch ouvre les outils ATAK existants de
  route, ETA pied/véhicule, LOS et profil d'élévation, ainsi que le replay enrichi.
- **Portabilité mission** : export JSON versionné des positions observées et tracés,
  import borné à 2 Mio/50 tracés et réécriture séquentielle via
  `ATAKMapShapes.createShape`. Les unités importées restent volontairement en
  lecture seule : seule l'ingestion COMSPEC peut faire autorité sur le BFT.
- **Historique** : accès direct au journal Liaison et au replay/AAR existants.
- **Impression** : commande dédiée et feuille d'impression centrée sur la carte,
  ses annotations et l'attribution cartographique.
- Les capacités classées **hors V1** lors de l'audit (satellites/TLE, cache massif
  hors ligne, scission topologique de polygones et sous-carte PiP) restent hors des
  lots P0/P1/P2 validés : elles nécessitent des sources ou infrastructures absentes
  et ne sont donc pas présentées comme livrées.

**Conclusion de recette statique : P0, P1 et P2 du plan priorisé sont câblés.** La
recette finale en environnement authentifié avec COMSPEC, PHP-FPM et la base de la
mission reste le dernier jalon avant fusion.
