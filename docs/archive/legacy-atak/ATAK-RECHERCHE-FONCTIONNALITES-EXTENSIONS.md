# ATAK — étude fonctionnelle, extensions et propositions de modules

**État de la recherche :** 1er août 2026  
**Périmètre :** ATAK officiel et écosystème TAK ; les fonctions propres à COMSPEC sont explicitement séparées  
**Public :** produit, architectes, développeurs, administrateurs et responsables opérationnels

> **Point de vocabulaire.** Le nom public actuel est **Android Team Awareness Kit
> (ATAK)**. « Android Tactical Assault Kit » est son nom historique. ATAK appartient à
> la famille TAK, aux côtés de WinTAK, iTAK, WebTAK et TAK Server.[^tak-products]

---

## 1. Synthèse

ATAK est un client Android de **connaissance de la situation partagée** : il réunit
cartographie, positionnement, objets tactiques, échanges entre équipiers et médias sur
une même vue. Il est issu de travaux du gouvernement américain ; le dépôt public
ATAK-CIV est publié par le Department of Defense américain.[^atak-repository]

Il faut distinguer trois couches :

1. **le client ATAK**, qui fournit l'interface cartographique et les outils terrain ;
2. **le réseau TAK**, qui transporte notamment des événements Cursor on Target (CoT),
   des messages, fichiers et flux ;
3. **TAK Server**, qui assure l'interconnexion, la distribution des données et la
   gestion des accès entre clients autorisés.[^tak-server]

ATAK n'est donc ni une simple application GPS, ni à lui seul un système complet de
commandement. La valeur opérationnelle vient de la combinaison du client, des données
cartographiques, d'une connectivité adaptée, d'un serveur correctement administré et
de procédures humaines.

---

## 2. Éditions et composants à ne pas confondre

| Élément | Rôle | Remarque de cadrage |
|---|---|---|
| **ATAK-CIV** | Distribution civile/publique Android | Base pertinente pour des usages sécurité civile, recherche et sauvetage, événementiel ou expérimentation autorisée. |
| **ATAK-GOV / ATAK-MIL** | Distributions destinées à des communautés gouvernementales ou militaires autorisées | Disponibilité, fonctions et plugins dépendent des droits d'accès ; ne pas supposer qu'une capacité observée dans une édition existe dans une autre. |
| **TAK Server** | Hub de données et d'identités | Relie les clients, applique les contrôles d'accès et peut fédérer plusieurs environnements. |
| **WinTAK / iTAK / WebTAK** | Autres clients de l'écosystème | L'interopérabilité réelle doit être testée fonction par fonction, pas déduite du seul nom TAK. |
| **Plugins ATAK** | Capacités additionnelles installées dans le client | Ils suivent le cycle de version et le modèle de confiance du client hôte. |

La page de téléchargements officielle et son processus d'inscription restent la source
de référence pour la disponibilité d'une édition, d'un SDK ou d'un composant à une date
donnée.[^tak-downloads]

---

## 3. Catalogue des fonctionnalités natives

### 3.1 Cartographie et géolocalisation

- affichage de cartes en ligne ou préparées pour un usage hors connexion ;
- position GNSS de l'opérateur, orientation, centrage et suivi de carte ;
- points d'intérêt, marqueurs, formes, lignes, zones et annotations ;
- mesure de distances et d'azimuts, routes et aide à la navigation ;
- exploitation de données d'altitude lorsque les jeux de données nécessaires sont
  disponibles ;
- import et superposition de données géospatiales prises en charge par la version et
  ses extensions.

**Valeur :** conserver une image géographique commune, même sur un terrain mal couvert.
**Limite :** la précision dépend du terminal, de la source cartographique, du référentiel
de coordonnées et de la fraîcheur des données.

### 3.2 Situation partagée

- publication de la position et de l'identité opérationnelle du terminal ;
- visualisation des membres, équipes et objets partagés ;
- mise à jour des objets au moyen du format d'échange **Cursor on Target (CoT)** ;
- groupes, canaux ou périmètres de diffusion selon le serveur et la configuration ;
- historique ou persistance variables selon le type de donnée et les règles serveur.

Le schéma CoT public documente une enveloppe XML centrée sur un événement géolocalisé ;
une intégration doit préserver les identifiants, types, horodatages `time`, `start` et
`stale`, ainsi que la sémantique des détails.[^cot-schema]

### 3.3 Collaboration

- messagerie individuelle ou collective ;
- partage de points, itinéraires, dessins et autres objets de carte ;
- envoi de photos, documents ou paquets de mission ;
- notifications et coordination autour d'une même situation.

Un chiffrement de transport ne signifie pas que tout contenu est automatiquement
chiffré de bout en bout. La confidentialité dépend du mode de connexion, des certificats,
du serveur, du plugin, du stockage local et de la politique de l'organisation.

### 3.4 Vidéo et capteurs

- consultation de flux vidéo compatibles lorsqu'une source ou une extension les expose ;
- association d'un flux ou d'un capteur à une position ;
- réception de métadonnées et d'observations provenant de systèmes connectés.

L'expression « vidéo de drone » décrit un **cas d'usage**, pas une garantie universelle :
codec, protocole, latence, métadonnées, droits et réseau doivent être validés avec la
source vidéo choisie.

### 3.5 Données et travail déconnecté

- préparation de cartes, couches et ressources avant mission ;
- regroupement de contenus dans des paquets distribuables ;
- reprise des échanges après une interruption selon les capacités du client, du plugin
  et du serveur.

Le mode hors ligne doit être testé comme un mode à part entière : espace disque,
expiration des données, conflits, journalisation et révocation des contenus sont des
sujets de conception, pas seulement de connectivité.

### 3.6 Administration et sécurité

- authentification et certificats lorsqu'ATAK est raccordé à TAK Server ;
- séparation des groupes et maîtrise des flux par la configuration serveur ;
- distribution contrôlée de configurations, paquets et plugins ;
- journaux techniques utiles au diagnostic.

Le guide de configuration de TAK Server livré avec chaque version doit primer sur tout
tutoriel générique : ports, mécanismes d'enrôlement et options changent avec les versions.
Le dépôt public du serveur fournit les artefacts et la documentation communautaire de
référence.[^tak-server-repository]

---

## 4. Extensions : modèle et cycle de vie

### 4.1 Ce qu'est un plugin ATAK

Un plugin est une application Android complémentaire chargée par ATAK. Il peut ajouter
des outils, panneaux, couches, traitements de données ou connecteurs matériels. Le dépôt
ATAK-CIV contient un exemple de plugin et des informations de construction ; il constitue
la référence technique publique plutôt qu'une API réinventée côté projet.[^atak-repository]

Un **connecteur externe** n'est pas nécessairement un plugin. Une passerelle serveur
peut traduire une source métier vers CoT ou vers une API autorisée sans modifier le
terminal. Ce choix réduit souvent les contraintes Android et centralise l'audit.

### 4.2 Choisir le bon point d'extension

| Besoin | Option recommandée | Pourquoi |
|---|---|---|
| Nouvelle interaction dans la carte Android | Plugin ATAK | Accès direct au contexte et à l'UX terrain. |
| Conversion d'un système tiers | Passerelle serveur | Secrets, quotas, journalisation et conversion centralisés. |
| Analyse lourde ou multi-sources | Service métier séparé | Cycle de déploiement indépendant et charge hors terminal. |
| Couche purement géographique | Format/couche standard pris en charge | Moins de code et meilleure portabilité. |
| Fonction commune à plusieurs clients TAK | Service + contrat d'échange | Évite de limiter la capacité à Android. |

### 4.3 Contraintes de compatibilité

- épingler la version ATAK et la version d'API/SDK ciblées ;
- compiler et signer le plugin conformément à la distribution visée ;
- vérifier le chargement, les permissions Android et le comportement en arrière-plan ;
- tester chaque mise à niveau ATAK sur un canal pilote avant diffusion générale ;
- prévoir désinstallation, retour arrière et migration des données locales ;
- ne jamais embarquer durablement un secret serveur dans l'APK.

### 4.4 Contrat minimal d'une extension

Chaque extension proposée devrait documenter :

1. propriétaire fonctionnel et mainteneur ;
2. éditions et versions ATAK compatibles ;
3. permissions Android et données collectées ;
4. formats entrants/sortants, comportement `stale` et stratégie hors ligne ;
5. authentification, autorisation, chiffrement en transit et au repos ;
6. débit nominal, limites, batterie, stockage et mode dégradé ;
7. observabilité sans fuite de positions ou d'identifiants sensibles ;
8. procédure de test, signature, publication, révocation et retour arrière.

---

## 5. Propositions de modules optionnels

Ces propositions sont des **créations possibles**, et non des fonctionnalités officielles
annoncées par TAK. Elles privilégient la sûreté, l'interopérabilité et un opérateur humain
dans la boucle.

### 5.1 Priorité P0 — forte valeur, risque maîtrisable

#### A. Module « Préparation hors ligne »

Assistant qui vérifie avant départ : emprise cartographique, volume disponible, date des
couches, contacts d'urgence, certificats, batterie et paquet de mission.

- **Implémentation :** plugin léger pour la checklist + service de catalogue de paquets.
- **Mode dégradé :** checklist locale exportable, sans connexion.
- **Critère de succès :** 100 % des ressources obligatoires présentes avant validation.

#### B. Module « Qualité de position »

Affiche la fraîcheur, l'incertitude et la provenance d'un contact au lieu de présenter
tous les points avec la même confiance.

- **Implémentation :** passerelle qui enrichit les événements, couche visuelle côté plugin.
- **Garde-fou :** ne jamais inventer une précision absente ; conserver la donnée source.
- **Critère de succès :** un opérateur distingue immédiatement position récente, ancienne,
  estimée ou inconnue.

#### C. Module « Recherche et sauvetage »

Gestion de secteurs, équipes, derniers points connus, indices, progression de recherche
et formulaires de compte rendu standardisés.

- **Implémentation :** objets géographiques versionnés + formulaires dans un plugin ;
- **interopérabilité :** export GeoJSON/KML et résumé PDF non sensible ;
- **garde-fou :** validation humaine avant fermeture ou réaffectation d'un secteur.

#### D. Module « Relève et journal opérationnel »

Produit une chronologie lisible des événements importants, décisions, accusés de lecture
et tâches ouvertes pour le changement d'équipe.

- **Implémentation :** service séparé alimenté par événements autorisés ;
- **sécurité :** listes d'autorisation, rétention et caviardage par rôle ;
- **critère de succès :** relève compréhensible sans exporter l'intégralité du trafic.

### 5.2 Priorité P1 — après validation de l'architecture

#### E. Passerelle IoT et environnement

Intègre météo locale, niveau d'eau, balises ou capteurs logistiques signés, avec unité,
horodatage, qualité et durée de validité explicites.

#### F. Gestion d'incident multi-organisations

Espaces de partage temporaires avec catalogue des données publiées, approbation d'un
responsable et expiration automatique. La fédération doit partager le strict nécessaire,
pas fusionner tous les annuaires.

#### G. Logistique et ressources

État agrégé des véhicules, stocks médicaux, eau, énergie et points de ravitaillement.
Les valeurs sont déclaratives ou issues de systèmes autorisés ; elles doivent afficher
leur provenance et leur dernière mise à jour.

#### H. Accessibilité et procédures guidées

Profils à contraste élevé, tailles tactiles, retour haptique, modèles de messages et
checklists contextualisées. Les alertes restent utilisables sans son et sans couleur seule.

### 5.3 Priorité P2 — expérimentation encadrée

#### I. Détection d'anomalies de données

Signale doublons, sauts de position impossibles, horloges incohérentes ou capteurs
silencieux. Le système étiquette une anomalie ; il ne décide pas qu'un contact est hostile.

#### J. Résumé assisté

Prépare un brouillon de synthèse à partir des événements auxquels l'utilisateur a accès.
Toute sortie indique ses sources, son heure de génération et exige une validation humaine.
Aucune donnée sensible ne doit être envoyée à un service externe sans base contractuelle.

#### K. Simulateur et relecture d'exercice

Environnement séparé qui rejoue des données synthétiques ou désensibilisées pour la
formation et l'analyse après action. Il doit être impossible d'injecter accidentellement
le rejeu sur le réseau opérationnel.

---

## 6. Architecture cible recommandée

```text
Sources autorisées ──> Passerelles d'adaptation ──> TAK Server ──> Clients TAK
                            │                         │              └─ plugin UX
                            ├─ validation             ├─ identité/groupes
                            ├─ normalisation CoT       ├─ distribution
                            └─ métriques/audit         └─ fédération contrôlée

Services métier <──────── contrat versionné / file d'événements ─────────────┘
```

Principes :

- **offline-first** : une coupure doit produire un état visible et prévisible ;
- **zero trust pragmatique** : identité, moindre privilège et expiration à chaque échange ;
- **minimisation** : ne collecter ni conserver une position sans finalité documentée ;
- **interopérabilité testée** : fixtures CoT, tests croisés entre versions et validation des
  dates `stale` ;
- **séparation** : développement, exercice et production ont des clés, serveurs et données
  distincts ;
- **humain dans la boucle** : aucune classification, alerte critique ou action terrain ne
  repose uniquement sur une automatisation.

---

## 7. Roadmap proposée

| Étape | Durée indicative | Livrable de décision |
|---|---:|---|
| **0. Cadrage** | 2 semaines | Cas d'usage, édition ATAK, propriétaires de données, analyse de risques. |
| **1. Socle de test** | 2–4 semaines | TAK Server isolé, terminaux pilotes, PKI, jeux CoT de référence, matrice de compatibilité. |
| **2. Prototype P0** | 4–6 semaines | Un seul module, télémétrie minimale, tests hors ligne et retour arrière. |
| **3. Pilote terrain** | 2–4 semaines | Mesures batterie/réseau/UX, incidents, validation sécurité et accessibilité. |
| **4. Industrialisation** | 4 semaines | Signature, catalogue, support, supervision, sauvegarde et procédure de révocation. |
| **5. Extension** | continue | P1/P2 uniquement sur résultats mesurés et revue de gouvernance. |

### Indicateurs

- délai de propagation et taux d'événements expirés avant réception ;
- autonomie et consommation réseau par scénario ;
- taux de livraison des messages et conflits après reconnexion ;
- temps de préparation hors ligne et taux d'erreur opérateur ;
- vulnérabilités ouvertes, délai de rotation/révocation et couverture des versions ;
- nombre de données sans propriétaire, classification ou durée de rétention.

---

## 8. Checklist de validation d'un module

- [ ] Le besoin ne peut pas être couvert par une fonction native ou une simple couche.
- [ ] L'édition, la version ATAK et la version Android sont explicitement supportées.
- [ ] Les données personnelles et de localisation ont une finalité et une rétention.
- [ ] Le serveur refuse un utilisateur, groupe ou certificat non autorisé.
- [ ] Les secrets ne figurent ni dans l'APK, ni dans les journaux, ni dans un paquet partagé.
- [ ] Les objets anciens expirent et les doublons restent idempotents.
- [ ] La coupure, la reconnexion, l'horloge fausse et le stockage plein sont testés.
- [ ] Batterie, bande passante et lisibilité au soleil ont été mesurées sur terminal réel.
- [ ] L'upgrade ATAK, le downgrade du plugin et la révocation ont une procédure testée.
- [ ] Une automatisation critique est explicable et confirmée par un humain.

---

## 9. Ajouts proposés pour notre mod COMSPEC Overwatch

COMSPEC possède déjà une Tacmap web, une liaison Arma 3, des positions, marqueurs,
messages, photos, rapports, JTAC, MEDEVAC, QRF, véhicules, météo et fonctions de
commandement. Il ne faut ni recréer ces fonctions, ni les présenter comme des fonctions
natives de l'ATAK officiel.

Les propositions ci-dessous ciblent le **mod actif** dans
`mod/UptoDate/Sources/comspec-overwatch-addons/`. Elles complètent les quatre addons
existants : `connect` pour le socle Athena, `atak_athena` pour le pont cTab/BCE,
`mavik_compat` pour les drones compatibles et `sse_ace` pour l'intégration médicale ACE.

### 9.1 Terminer les chaînes déjà amorcées — priorité immédiate

| Ajout dans le mod | Expérience joueur | Réutilisation du portail | Addon cible | Critère d'acceptation |
|---|---|---|---|---|
| **Routes et waypoints synchronisés** | Le chef reçoit une route numérotée sur sa tablette ; le prochain point est mis en avant et peut être déclaré atteint. | API de routes et waypoints déjà disponible. | `connect`, avec rendu optionnel dans `atak_athena` | Création d'une route web de cinq points, réception en jeu, progression puis reprise correcte après reconnexion. |
| **Timeline enrichie côté jeu** | Les actions importantes produisent des événements cohérents : départ, contact, rapport, blessé, QRF, objectif et fin de mission. | API Replay existante, actuellement seulement partiellement alimentée/restituée. | `connect` | Aucun doublon après reconnexion ; chaque événement possède mission, auteur, position et heure serveur. |
| **Mission de reconnaissance UAV** | Le chef assigne une route à un drone ; l'opérateur accepte, met en pause ou annule depuis le terminal. | Routes, air assets et flux vidéo existent déjà. | `mavik_compat` + `connect` | Aucun mouvement automatique sans acceptation locale ; perte de liaison = maintien ou retour selon réglage mission. |
| **File de photos robuste** | Une photo prise hors réseau reste en attente, affiche son état et repart après retour de la liaison. | Dépôt de photos et portail Cams existants. | `connect` / DLL | Une même photo n'est jamais publiée deux fois ; limites de taille, quantité et rétention sont configurables. |

Ces quatre ajouts offrent le meilleur rapport valeur/effort : ils branchent des capacités
serveur déjà présentes au lieu d'ouvrir de nouveaux domaines fonctionnels.

### 9.2 Nouveaux modules optionnels — P0

#### A. État de liaison compréhensible

Remplacer le simple état connecté/déconnecté par un widget compact : qualité estimée,
dernier échange réussi, éléments en attente et mode dégradé actif.

- **CBA :** activation, fréquence de test et seuils configurables par mission ;
- **réseau :** aucun ping supplémentaire si un échange métier récent fournit déjà la mesure ;
- **UX :** vert/orange/rouge accompagné d'un texte ou pictogramme, jamais la couleur seule ;
- **acceptation :** une coupure simulée explique immédiatement ce qui reste local et ce
  qui sera resynchronisé.

#### B. Boîte d'envoi hors ligne commune

Créer un gestionnaire unique pour rapports, marqueurs, QRF, MEDEVAC et photos, au lieu de
laisser chaque fonction gérer différemment les échecs réseau.

- file persistante bornée, priorité (`urgence`, `normal`, `volumineux`) et backoff ;
- identifiant d'idempotence conservé jusqu'à l'accusé serveur ;
- bouton « réessayer » et possibilité de supprimer un élément non critique ;
- télémétrie agrégée, sans contenu tactique dans les journaux ;
- **acceptation :** scénario connexion → coupure → cinq actions → reconnexion sans perte
  ni doublon.

#### C. Ordres avec accusé de réception

Un ordre web ou in-game devient un objet suivi : `reçu`, `lu`, `accepté`, `refusé avec
motif`, `terminé`. Une expiration empêche qu'un ordre ancien réapparaisse comme actuel.

- destinataire individuel, groupe ou rôle ;
- accusé manuel pour les ordres critiques, automatique uniquement pour « reçu » ;
- historique visible au commandement et à l'équipe concernée ;
- **acceptation :** l'émetteur distingue sans ambiguïté transport réussi et acceptation
  humaine.

#### D. Checklist pré-mission locale

Avant la mise en liaison : vérification URL, Mission ID, clé communautaire, terminal
équipé, dépendances optionnelles, carte, espace disque et heure du poste.

- résultat exportable dans le diagnostic existant ;
- aucun secret affiché ou copié dans le rapport ;
- profil `joueur`, `chef`, `JTAC`, `médical` ou `UAV` ;
- **acceptation :** un mauvais Mission ID ou une dépendance absente est détecté avant le
  premier envoi.

### 9.3 Nouveaux modules optionnels — P1

#### E. Qualité et fraîcheur des contacts

Les icônes indiquent `direct`, `relayé`, `estimé` ou `obsolète`, avec âge et incertitude.
Après expiration, un contact est grisé puis masqué selon la politique de mission ; il
n'est jamais déplacé artificiellement pour donner une impression de précision.

#### F. Passation de commandement

Le chef sortant sélectionne situation, tâches ouvertes, derniers rapports et points
d'attention. Le chef entrant accuse réception d'un résumé borné. Les données brutes ne
sont pas recopiées : le résumé référence leurs identifiants.

#### G. Couches et profils par rôle

Profils d'affichage `commandement`, `médical`, `logistique`, `UAV` et `renseignement`.
Chaque profil choisit les couches visibles et le niveau de notifications sans modifier
les droits serveur. Un filtre d'interface ne doit jamais être traité comme une autorisation.

#### H. Balises logistiques

Ajout rapide d'un point carburant, munitions, réparation, eau ou médical avec quantité
approximative, état, heure et auteur. Les entrées expirent ou demandent une confirmation
périodique afin d'éviter les stocks fantômes.

#### I. Journal technique partageable

Générer depuis le hub un diagnostic court : versions des PBO et de la DLL, dépendances
détectées, état de liaison, dernier code d'erreur et taille des files. Le joueur contrôle
l'envoi et voit exactement les champs transmis ; clé, chat, coordonnées et identité Steam
sont exclus par défaut.

### 9.4 Modules expérimentaux — P2

#### J. Relecture d'exercice in-game

Mode séparé de la mission active qui rejoue positions et événements désensibilisés sur
une carte de débriefing. Un bandeau permanent « REPLAY » et un namespace distinct rendent
impossible la confusion avec le direct.

#### K. Détection d'incohérences

Signaler localement les sauts impossibles, les horloges décalées, les doublons et une
position trop ancienne. Le module diagnostique la **donnée** ; il ne qualifie jamais un
joueur d'ennemi ou de tricheur.

#### L. Interopérabilité CoT expérimentale

Une passerelle distincte traduit d'abord uniquement positions et marqueurs d'exercice.
Elle ne doit pas être intégrée directement à la DLL tant que le mapping, l'idempotence,
`stale`, les groupes et les certificats n'ont pas été validés sur un TAK Server isolé.

### 9.5 Ce que nous déconseillons

- **contrôle caméra invisible ou capture périodique imposée** : coût performance et risque
  de confidentialité ; préférer une capture explicite avec témoin visuel ;
- **pilotage autonome d'un drone depuis le portail** : conserver une acceptation et une
  reprise en main côté joueur ;
- **actions de tir automatiques** : le portail peut préparer une demande simulée, mais un
  joueur ou Zeus doit la valider et déclencher l'effet en jeu ;
- **synchronisation de tout vers tous** : appliquer rôles, groupes, portée et expiration ;
- **nouvel addon monolithique** : placer la logique commune dans `connect` et garder les
  intégrations cTab, drone ou ACE optionnelles et sans dépendance dure.

### 9.6 Découpage de livraison recommandé

| Sprint | Contenu | Démonstration attendue |
|---|---|---|
| **Sprint 1** | État de liaison + boîte d'envoi commune | Rapport, marqueur et MEDEVAC créés hors ligne puis synchronisés une seule fois. |
| **Sprint 2** | Routes/waypoints + checklist pré-mission | Route web suivie en jeu et diagnostic d'une configuration volontairement erronée. |
| **Sprint 3** | Ordres accusés + fraîcheur contacts | Commandement suit réception/acceptation ; un contact expiré est clairement différencié. |
| **Sprint 4** | Photos robustes + profils par rôle | Reprise d'un upload interrompu et interface adaptée sans contournement des droits. |
| **Pilote** | UAV assisté + passation | Mission drone acceptée localement et relève complète sur serveur d'exercice. |

### 9.7 Règles d'implémentation pour le mod

1. chaque module est désactivable dans CBA et par feature flag serveur ;
2. les fonctions SQF sont idempotentes et n'utilisent pas de boucle par frame pour le réseau ;
3. la DLL assure le transport, pas les décisions métier ou l'interface ;
4. chaque payload porte version de contrat, Mission ID, identifiant d'événement et date ;
5. les intégrations ACE, cTab/BCE, Mavic et ATAK Enhanced restent optionnelles ;
6. chaque ajout possède un scénario de test dédié serveur, client hébergé et client dédié ;
7. toute évolution du contrat conserve une période de compatibilité avec le portail publié.

### 9.8 Trajectoire d'interopérabilité ATAK officiel

Une future interconnexion doit commencer par une **passerelle expérimentale et isolée** :

1. inventaire des objets COMSPEC et de leurs niveaux d'accès ;
2. profil CoT minimal pour positions et marqueurs non sensibles ;
3. passerelle unidirectionnelle sur environnement d'exercice ;
4. déduplication, expiration, audit et tests de charge ;
5. seulement ensuite, étude d'un retour bidirectionnel et d'un plugin dédié.

---

## 10. Sources et limites de la recherche

Sources primaires à revalider lors de chaque choix de version :

- portail officiel TAK et catalogue des produits ;[^tak-products]
- page officielle de téléchargement ;[^tak-downloads]
- dépôt public ATAK-CIV du Department of Defense ;[^atak-repository]
- dépôt public TAK Server ;[^tak-server-repository]
- schéma public Cursor on Target.[^cot-schema]

Cette étude ne prétend pas inventorier les plugins à accès restreint ni garantir leur
disponibilité. Les pages officielles peuvent nécessiter un compte et les capacités varient
selon l'édition, la version, le terminal et les politiques de l'organisation. Les noms de
plugins tiers ne sont volontairement pas recommandés sans audit de leur provenance, de
leur maintenance, de leur signature et de leur licence.

[^tak-products]: [TAK Product Center — Products](https://tak.gov/products/)
[^tak-downloads]: [TAK Product Center — Downloads](https://tak.gov/pages/downloads/)
[^atak-repository]: [Department of Defense — AndroidTacticalAssaultKit-CIV](https://github.com/deptofdefense/AndroidTacticalAssaultKit-CIV)
[^tak-server]: [TAK Product Center — TAK Server](https://tak.gov/products/tak-server/)
[^tak-server-repository]: [TAK Product Center — TAK Server, dépôt public](https://github.com/TAK-Product-Center/Server)
[^cot-schema]: [TAK Product Center — schémas Cursor on Target](https://github.com/TAK-Product-Center/ChatBot/tree/main/src/main/resources/cot-schema)
