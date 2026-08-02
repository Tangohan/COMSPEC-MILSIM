# Plan #25 — niveaux de diffusion ATAK

**Statut :** phase A engagée, sans activation de filtrage
**Périmètre recommandé pour le premier lot :** routage des informations ATAK  
**Hors périmètre :** dossiers SSE, perception directe en jeu

## 1. Décision recherchée

Le besoin de « niveaux de diffusion » recouvre en réalité trois axes indépendants. Ils ne doivent pas être condensés dans un champ unique.

| Axe | Question | Nature |
|---|---|---|
| Diffusion | Qui a le droit de consulter l'information ? | Attribut de la donnée |
| État | Où en est-elle dans la chaîne de traitement ? | Cycle de vie |
| Routage | À qui l'information est-elle envoyée activement ? | Règle de distribution |

Une observation d'EAGLE-21 peut ainsi être visible par son groupe et le TOC, rester à l'état `OBSERVED` tant qu'elle n'a pas été transmise, et n'être routée à personne si aucune règle ne correspond. Ces valeurs ne se déduisent pas les unes des autres.

La diffusion n'est surtout **pas une classification ordinale**. `TOC` n'est pas « supérieur » à `SQUAD` au sens où une habilitation SECRET est supérieure à une habilitation CONFIDENTIEL. Le cas courant « groupe producteur + TOC, mais pas les groupes voisins » impose un ensemble de destinataires, et non un rang minimal.

## 2. Inventaire vérifié dans le dépôt

### 2.1 Un moteur de routage existe déjà

La migration `migrations/2026_07_24_007_atak_intelligence_enhancements.sql` crée :

- `atak_report_routing_rules`, avec ordre de priorité, conditions JSON, destinataires par rôle, utilisateur ou unité, canaux de notification et paramètres d'escalade ;
- `atak_report_routing_history`, avec destinataire, notification, accusé de réception et horodatages.

`app/Repositories/AtakReportRoutingRepository.php` fournit déjà les opérations structurantes :

- `applyRoutingRules()` ;
- `listForRecipient()` ;
- `acknowledgeRouting()` ;
- `processEscalations()`.

La recherche des quatre méthodes hors de ce repository ne trouve que de la documentation et le changelog : **le moteur n'a aucun appelant applicatif**. Il s'agit donc d'une amorce fonctionnelle non branchée, à reprendre plutôt que de créer un second système.

### 2.2 Le moteur cible les rapports tactiques, pas `atak_intel`

Le raccordement n'est pas un simple appel de méthode : le repository charge `atak_tactical_reports`, et l'historique possède une clé étrangère `report_id` vers cette table. À l'inverse, `AtakIntelRepository::store()` écrit dans `atak_intel`, une table globale sans `tenant_id`, `context_id`, état ou destinataires.

Cette incompatibilité doit être résolue explicitement en phase A. Deux solutions sont possibles :

1. **Recommandée :** transformer l'intel routable en `atak_tactical_reports`, puis appeler le moteur existant avec l'identifiant du rapport ;
2. généraliser le moteur autour d'un type et d'un identifiant de ressource, ce qui nécessite une migration plus large et augmente le risque du premier lot.

Brancher directement l'identifiant d'`atak_intel` dans `report_id` serait incorrect et violerait la clé étrangère.

### 2.3 Les champs de visibilité existants ne forment pas une politique commune

Plusieurs objets ATAK possèdent déjà un `visibility_level` (`atak_pois`, `atak_tactical_zones`, `atak_waypoints`). Ces vocabulaires diffèrent (`PUBLIC`, `UNIT`, `COMMAND`, `ALL`, `COMMAND_ONLY`, `RESTRICTED`) et les écritures observées ne constituent pas, à elles seules, un contrôle d'accès transversal.

Ils ne doivent donc pas être réinterprétés brutalement comme des décisions d'exclusion. Une période d'écriture et d'observation est nécessaire avant tout filtrage.

### 2.4 Interrupteur de déploiement

Le dépôt dispose déjà d'un mécanisme de modules ATAK par tenant dans `AtakBridgeModulesService`, stocké sous `storage/cache/atak-modules/`. Le futur filtrage doit être protégé par un interrupteur par tenant. Aucun schéma nommé `sse_portal_settings` n'est présent dans la branche auditée ; il ne faut donc pas fonder l'implémentation sur cette table sans migration ou branche complémentaire.

## 3. Principes non négociables proposés

### 3.1 Le SSE reste hors périmètre

Le SSE possède sa propre classification, ses habilitations et son caviardage. Ajouter une diffusion ATAK aux mêmes fiches créerait deux sources de vérité et rendrait les refus d'accès impossibles à expliquer. Les objets SSE conservent exclusivement leur politique actuelle.

### 3.2 Écrire et mesurer avant de filtrer

Le premier déploiement enregistre les valeurs, les routes appliquées, les destinataires et les accusés de réception, mais ne retire aucune donnée aux lecteurs actuels. Le filtrage ne peut être activé qu'après audit des valeurs réelles, définition d'une valeur de repli et validation métier.

### 3.3 La perception directe ne peut pas être masquée

La diffusion décrit la circulation d'une **information rapportée**, pas la perception du joueur. Si EAGLE-21 voit un véhicule dans le monde de jeu, aucune règle serveur ne doit supprimer cet objet de sa carte ou de son interface locale. Le filtrage futur ne pourra porter que sur les rapports, messages et artefacts issus de leur transmission.

### 3.4 Refus par défaut explicable, jamais silencieux

À terme, chaque exclusion devra produire une raison testable : absence dans l'audience, contexte incorrect, expiration ou règle inactive. Les contrôles doivent rester bornés au tenant et au contexte de mission ; les accusés de réception doivent vérifier que l'utilisateur représente réellement le destinataire ciblé.

## 4. Plan d'exécution

### Phase A — rendre le routage existant utilisable (recommandée maintenant)

**But :** obtenir des usages réels sans masquer quoi que ce soit.

Le routage est désormais branché à la création des rapports tactiques : le module `report_routing` applique les règles actives, renvoie son bilan dans la réponse de création et empêche les distributions identiques. Une boîte de réception calcule les identités réellement détenues par l'appelant (`USER`, rôles et unités), l'acquittement porte sur une distribution précise, et le job planifié traite les escalades idempotentes. Les notifications restent volontairement « en attente » tant qu'un transport n'a pas confirmé leur envoi.

1. Définir quels événements `PING`, `CHAT` et `PHOTO` deviennent des rapports tactiques routables ; conserver les autres dans `atak_intel` si nécessaire.
2. À l'ingestion, créer le rapport dans le contexte et le tenant authentifiés, puis appeler `applyRoutingRules()` après la transaction d'écriture.
3. Rendre l'application idempotente : une même règle et un même destinataire ne doivent pas créer plusieurs lignes d'historique lors d'une nouvelle tentative.
4. Respecter réellement `notification_channels` ; l'implémentation actuelle enregistre seulement `in-game` et marque la notification envoyée sans preuve d'envoi.
5. ~~Exposer une boîte de réception routée, limitée aux identités `USER`, `ROLE` et `UNIT` effectivement détenues par l'appelant.~~
6. ~~Exposer l'accusé de réception avec contrôle tenant/contexte/destinataire et journal d'audit.~~
7. ~~Exécuter `processEscalations()` dans un job planifié idempotent et empêcher les escalades répétées vers le même rôle.~~
8. Ajouter métriques et tests : règles évaluées, rapports sans règle, distributions, délais d'acquittement, escalades et doublons évités.

**Critères de sortie :** routage observable de bout en bout, zéro modification des listes/cartes existantes, aucun accès élargi par le nouvel endpoint, et possibilité de désactiver le module par tenant.

### Phase B — écrire la diffusion sans l'appliquer

**But :** valider le vocabulaire sur des données réelles.

- Introduire une audience explicite et non ordinale sur les rapports : producteur, utilisateurs, rôles et unités autorisés.
- Conserver séparément l'état du rapport et l'historique de routage.
- Prévoir une valeur héritée documentée pour les données antérieures ; ne pas convertir automatiquement les anciens `visibility_level` hétérogènes.
- Ajouter l'édition, l'audit des changements et un mode « expliquer l'audience ».
- Mesurer pendant plusieurs missions qui aurait été inclus ou exclu, sans modifier les réponses API.

**Critère de sortie :** les responsables opérationnels valident le vocabulaire et un échantillon d'audiences produites.

### Phase C — filtrage en lecture, sous interrupteur

**But :** faire respecter une audience validée sans casser la perception en jeu.

- Centraliser la décision dans un service unique, utilisé par les listes, détails, exports, notifications et flux temps réel.
- Activer d'abord en mode simulation, puis pour un tenant pilote.
- Ne filtrer que les informations rapportées ; exclure les objets issus de la perception locale.
- Définir le comportement des données historiques et des clients incapables d'envoyer une audience.
- Journaliser les refus sans révéler le contenu protégé.
- Prévoir un retour arrière instantané par tenant.

### Phase D — administration et doctrine

**But :** rendre le dispositif gouvernable.

- Éditeur et simulateur de règles (« ce rapport serait envoyé à… »).
- Modèles par type de mission, versionnement, auteur, dates d'effet et restauration.
- Tableau des rapports non routés, non acquittés et escaladés.
- Documentation des rôles responsables et procédure de secours.

## 5. Risques à traiter avant la phase A

- `AtakReportRoutingRepository` suppose `tenant_id` et `context_id`, absents d'`atak_intel` ; ils doivent provenir d'une authentification fiable, pas du corps client seul.
- `rules_applied` compte actuellement les destinataires créés, pas les règles correspondantes : la métrique doit être renommée ou corrigée.
- Les insertions d'historique n'ont pas de contrainte d'unicité, donc appels répétés et escalades peuvent dupliquer les distributions.
- `notification_sent` est initialisé à vrai lorsque la règle demande une notification, même si aucun transport n'a été exécuté.
- L'accusé de réception ne borne actuellement la mise à jour ni par tenant ni par contexte et ne vérifie pas l'identité du destinataire.
- L'ordre `priority DESC` sur une valeur textuelle ne garantit pas l'ordre opérationnel attendu.

Ces points ne justifient pas un nouveau moteur ; ils définissent le durcissement minimal nécessaire pour réutiliser celui qui existe.

## 6. Recommandation

Décider **la phase A seule**. Elle valorise le chantier existant et fournit des données réelles, sans changement de visibilité ni exclusion. Les phases B à D restent conditionnées par les réponses ci-dessous et par le retour des premières missions instrumentées.

## 7. Questions à trancher

1. **Organisation réelle :** quels sont exactement les six niveaux ou audiences envisagés, et correspondent-ils à des rôles, des unités, des fonctions de mission ou des groupes ad hoc ?
2. **Source routable :** `PING`, `CHAT` et `PHOTO` doivent-ils tous produire un rapport tactique, ou seulement certains événements/promotions manuelles ?
3. **Audience par défaut :** pour une information nouvelle sans audience explicite, faut-il conserver la visibilité actuelle, limiter au producteur, ou appliquer un modèle de mission ?
4. **Autorité :** qui peut modifier l'audience, acquitter pour une unité et forcer une diffusion ou une escalade — auteur, chef d'unité, TOC, administrateur de mission ?

---

## 8. Compléments apportés après la phase A

Travail mené en parallèle du § 5, et convergeant avec lui. Ce qui suit s'ajoute au
durcissement décrit ci-dessus plutôt qu'il ne le remplace.

### Traité

| Point du § 5 | État |
|---|---|
| `notification_sent` posé à vrai sans transport | **Corrigé** — le drapeau est désormais posé par `markNotified()`, après émission réelle |
| Accusé de réception non borné par tenant ni contexte | **Corrigé** (version retenue : celle de la phase A) |
| Distributions dupliquables par appels répétés | Contrainte d'unicité côté phase A, **plus** une garde d'idempotence à l'émission : une diffusion déjà notifiée ne l'est pas une seconde fois |

### Ajouté

- **Écran de gestion des règles** — `/admin/atak-diffusion-rapports`. Sans lui, le
  moteur branché tournait à vide : aucune règle n'existait et rien ne permettait
  d'en créer. Une règle sans destinataire y est refusée, car elle donnerait
  l'illusion d'une diffusion en place sans en produire aucune.
- **Émission réelle des notifications** — une diffusion écrit une notification
  portant destinataires, position et urgence reprise du rapport. Une seule par
  rapport, et non une par destinataire : des lignes identiques répétées font
  passer l'alerte pour du bruit.
- **Route de relève `GET /api/atak/notifications`** — `AtakNotificationRepository`
  disposait de `create()`, `listActive()` et `pollSince()` sans qu'aucune route ne
  l'expose. Les notifications écrites n'étaient lisibles par personne ; émettre
  revenait à écrire dans un tiroir fermé.
- **`listForReport()`** — le dépôt savait lister les rapports d'un destinataire,
  pas les destinataires d'un rapport. La diffusion restait invisible depuis la
  fiche, et une diffusion qu'on ne voit pas ne se vérifie pas.

### Reste ouvert

- **`findById()` n'est toujours pas borné par communauté** dans le dépôt des
  rapports tactiques : un identifiant deviné suffit à lire le rapport d'une autre
  communauté. Le cloisonnement est appliqué à l'appel depuis la consultation, mais
  la signature reste permissive par défaut.
- ~~Même défaut relevé sur `AtakPoiRepository` et `AtakMedevacRepository`.~~ **Traité** :
  le point d'intérêt était même modifiable — pas seulement lisible — depuis une autre
  communauté. Les autres dépôts de la famille appellent `findById()` sur une ligne
  qu'ils viennent d'écrire, sans exposition.
- **Côté mod** : relever `GET /api/atak/notifications` et afficher via
  `fn_announce`. Demande une reconstruction du PBO.

