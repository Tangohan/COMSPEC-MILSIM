# Plan — Niveaux d'information et chaîne de diffusion

Document de conception. **Aucun code n'est écrit avant validation de ce plan.**

Objectif énoncé : cesser de considérer que tous les joueurs et Athena connaissent
la même chose. Un élément trouvé par EAGLE-21 ne doit pas apparaître immédiatement
partout ; il doit suivre `OBSERVE → REPORT → TRANSMIT → RECEIVE → FUSE → DISSEMINATE`.

---

## 1. Ce qui existe déjà — et qui change l'estimation

Avant de concevoir, inventaire de l'existant réel.

### 1.1 Un moteur de routage complet, jamais branché

`migrations/2026_07_24_007_atak_intelligence_enhancements.sql` crée
`atak_report_routing_rules` et `atak_report_routing_history` :

| Colonne | Rôle |
|---|---|
| `trigger_conditions` (JSON) | type de rapport, priorité, mots-clés, zone |
| `auto_assign_to_roles` / `_users` / `_units` (JSON) | destinataires |
| `notification_channels` (JSON) | in-game, e-mail, webhook, Discord |
| `priority_order`, `is_active` | ordre et activation |

`app/Repositories/AtakReportRoutingRepository.php` implémente `applyRoutingRules()`,
`routeReport()`, `processEscalations()`, `listForRecipient()`, `acknowledgeRouting()`.

**Rien n'appelle cette classe.** Aucun contrôleur, aucun service. Le chantier a été
commencé puis abandonné avant branchement.

C'est la nouvelle la plus importante de cet inventaire : une partie du travail est
déjà faite, et il serait absurde de construire un second système à côté.

### 1.2 Aucune donnée ne porte de portée

| Table | Notion de portée |
|---|---|
| `atak_markers`, `atak_intel`, `atak_chat_messages`, `atak_map_shapes` | aucune |
| `atak_orders`, `sse_persons`, `sse_cases` | aucune |
| `sse_sites` | `team_label` — libellé d'affichage, pas un filtre |
| `atak_poi` | `visibility_level`, défaut `'PUBLIC'` — seule trace existante |

Tout est donc visible de tous, et `visibility_level` sur les POI n'est appliqué
nulle part à la lecture.

---

## 2. L'erreur de conception à éviter

L'énoncé mélange trois choses distinctes. Les confondre produirait un système
incompréhensible à l'usage.

| Axe | Question à laquelle il répond | Nature |
|---|---|---|
| **Diffusion** | Qui a le **droit** de voir cette donnée ? | Attribut de la donnée |
| **État** | Où en est-elle dans la chaîne ? | Cycle de vie |
| **Routage** | À qui l'**envoie**-t-on activement ? | Règle, déjà construite (§ 1.1) |

Exemple concret : une observation d'EAGLE-21 peut être **diffusable au TOC**
(diffusion), **encore à l'état « observé »** parce que personne ne l'a transmise
(état), et **non routée** parce qu'aucune règle ne la concerne (routage).

Ces trois valeurs sont indépendantes. Un seul champ ne peut pas les porter.

---

## 3. Modèle proposé

### 3.1 Diffusion — six niveaux

```
TEAM        binôme / trinôme
SQUAD       groupe
PLATOON     section
TOC         poste de commandement
INTEL_CELL  cellule renseignement
ALL         tous
```

Ce n'est **pas une échelle**, contrairement aux classifications SSE. `TOC` n'est
pas « plus haut » que `SQUAD` : ce sont des destinataires différents. Le modèle est
donc un **ensemble**, pas un rang — une donnée peut être diffusée à `SQUAD` **et**
`TOC` sans l'être à `PLATOON`.

> C'est le point où une implémentation naïve se trompe. Traiter la diffusion comme
> un niveau croissant, par symétrie avec les habilitations SSE, rendrait impossible
> le cas le plus courant : le groupe qui a trouvé et le PC, sans les sections
> voisines.

### 3.2 État — six étapes

| État | Signification | Qui le fait avancer |
|---|---|---|
| `observe` | Constaté sur le terrain, pas encore rapporté | l'opérateur |
| `rapporte` | Rédigé, en attente de transmission | l'opérateur |
| `transmis` | Parti vers Athena | le mod |
| `recu` | Arrivé et horodaté côté portail | le serveur |
| `fusionne` | Recoupé avec d'autres éléments | l'analyste |
| `diffuse` | Rendu visible à sa diffusion | l'analyste ou une règle |

Le **tampon hors ligne** livré récemment occupe déjà la transition
`rapporte → transmis` : une fiche saisie hors liaison reste « rapportée » jusqu'au
rétablissement. La chaîne s'appuie donc sur du réel, pas sur une abstraction.

### 3.3 Portée du chantier

| Donnée | Diffusion | État | Priorité |
|---|---|---|---|
| `atak_intel` (SALUTE, SPOTREP) | oui | oui | **1 — c'est le cœur** |
| `atak_markers`, `atak_map_shapes` | oui | non | 2 |
| `atak_poi` (+ `visibility_level` existant à reprendre) | oui | oui | 2 |
| `atak_chat_messages` | oui | non | 3 |
| `sse_persons`, `sse_cases` | **non** | non | — |

**Le SSE reste hors périmètre.** Il a déjà son propre modèle — classification,
habilitation, caviardage — construit et documenté. Lui superposer un second système
de diffusion créerait deux vérités concurrentes sur la même fiche, et la question
« pourquoi ne vois-je pas ce dossier ? » n'aurait plus de réponse unique.

---

## 4. Migration sans casser le portail vivant

Contrainte : déploiement FTP manuel, communauté en activité, aucune fenêtre de
maintenance. La même méthode que le verrou de classification s'applique.

**Étape 1 — colonnes ajoutées, valeur par défaut = comportement actuel.**
`diffusion = 'ALL'`, `etat = 'diffuse'` sur tout l'existant. Aucun changement
visible : ce qui était visible le reste.

**Étape 2 — écriture seulement.** Les nouvelles données portent une diffusion
réelle, mais la lecture ne filtre pas encore. On observe ce que le champ contient
en conditions réelles avant qu'il ne cache quoi que ce soit.

**Étape 3 — filtrage à la lecture, sous interrupteur, désarmé par défaut.**
Réglage `sse_portal_settings` (la table existe et sert déjà à cela).

**Étape 4 — écran de revue avant armement**, comme pour le verrou : « voici ce que
chaque élément deviendrait invisible à qui ».

> L'étape 2 n'est pas une précaution de confort. Armer un filtre sur un champ dont
> personne n'a vérifié le contenu, c'est reproduire exactement le problème de la
> classification SSE : des valeurs posées sans conséquence qui deviennent d'un coup
> des décisions d'exclusion que personne n'a prises.

---

## 5. Ce que je recommande de ne pas faire

**Ne pas cacher aux joueurs ce qu'ils voient en jeu.** Si EAGLE-21 voit un véhicule
de ses yeux, aucune règle de diffusion ne doit l'empêcher de le voir sur sa carte.
Le système modélise la circulation de l'information rapportée, pas la perception.
Confondre les deux produit une frustration immédiate et illégitime.

**Ne pas faire de la diffusion un outil d'administration.** Elle décrit une réalité
opérationnelle — qui a besoin de savoir — et non une sanction ou un privilège.

**Ne pas rendre l'état obligatoire.** Un opérateur qui pose un marqueur ne doit pas
avoir à déclarer « observé » puis « rapporté ». L'état doit se déduire des actions
existantes ; là où il ne se déduit pas, il vaut mieux ne pas le porter.

**Ne pas construire un second moteur de routage.** Celui du § 1.1 existe : le
brancher, l'éprouver, et seulement ensuite juger s'il manque quelque chose.

---

## 6. Phasage proposé

| Phase | Contenu | Dépendance |
|---|---|---|
| **A** | Brancher le routage existant sur `atak_intel` : appel de `applyRoutingRules()` à la réception, écran de règles, journal | aucune — code déjà écrit |
| **B** | Colonne `diffusion` sur `atak_intel` + marqueurs, écriture seule, défaut `ALL` | A |
| **C** | Filtrage à la lecture sous interrupteur + écran de revue | B, plus une période d'observation réelle |
| **D** | Colonne `etat` et affichage de la chaîne | C |
| **E** | Extension aux POI, formes de carte, messagerie | D |

**La phase A a le meilleur rapport valeur / risque** : elle rend utilisable un
moteur déjà écrit et payé, sans toucher au modèle de données ni à ce que voient les
joueurs. Elle donne aussi une matière réelle pour décider si les phases suivantes
valent la peine.

---

## 7. Ce que ça change pour l'AAR (#24)

L'AAR consommera ces niveaux : « qui savait quoi, à quel moment » n'a de sens que
si la donnée porte sa diffusion et son horodatage d'état. Construire l'écran AAR
**avant** la phase B revient à le refaire ensuite.

D'où l'ordre recommandé : phase A, puis décision sur B/C, puis AAR.

---

## 8. Décisions attendues

1. **Phase A seule, ou engagement sur le modèle complet ?**
2. Le SSE reste-t-il bien hors périmètre ?
3. La liste des six diffusions correspond-elle à votre organisation réelle
   (binôme / groupe / section / PC / cellule renseignement) ?
4. Confirmez-vous que rien de ce qui est **perçu en jeu** ne doit être masqué ?

---

## 9. Phase A — livrée

### Ce qui a été fait

Le moteur de règles est branché : `AtakReportRoutingService::onReportSubmitted()`
est appelé à la soumission d'un rapport tactique, et l'historique de diffusion est
exposé sur la consultation du rapport (`routing`) et sur la réponse de soumission
(`routed_to`).

**Aucun interrupteur.** Sans règle enregistrée, le moteur ne route vers personne et
n'écrit rien : une table de règles vide *est* l'état désactivé. Ajouter un réglage
par-dessus donnerait deux façons de désactiver la même chose, donc deux endroits à
vérifier quand quelqu'un demande pourquoi son rapport n'est pas arrivé.

Un échec de routage n'échoue jamais la soumission : le rapport est enregistré
d'abord, la diffusion est tentée ensuite et tracée si elle échoue. Perdre un compte
rendu de contact parce qu'une règle est mal formée serait un échange calamiteux.

### La cible a changé, et pourquoi

La demande visait `atak_intel`. L'inventaire l'a écartée :

| | `atak_intel` | `atak_tactical_reports` |
|---|---|---|
| `report_type`, `priority`, `summary`, `details` | absents | **présents** |
| `tenant_id` / `context_id` | **absents** | présents |
| Clé étrangère de `atak_report_routing_history` | non | **pointe dessus** |
| `visibility`, `distributed_to` | non | **déjà présents** |

Brancher sur `atak_intel` aurait imposé d'altérer une clé étrangère en base
vivante, d'ajouter un cloisonnement par communauté à une table qui n'en a pas, et
d'inventer une priorité. Le moteur a été écrit pour `atak_tactical_reports`, cette
table est vivante et routée, et elle porte déjà `visibility` — l'axe diffusion du
§ 3.1 y est prévu.

**Conséquence pour `atak_intel`** : le router reste possible, mais ce n'est pas un
branchement, c'est une phase de schéma. Elle relève de la phase B.

### Défauts d'isolation corrigés au passage

Deux failles pré-existantes, dans le périmètre touché :

- `GET /api/atak/reports/{id}` appelait `findById($id)` **sans filtre de
  communauté** : un identifiant deviné suffisait à lire le rapport d'une autre
  communauté.
- `POST /api/atak/reports/{id}/acknowledge` de même — et acquitter est un acte,
  pas une lecture.

`atak_report_routing_history` ne porte pas de `tenant_id` ; le cloisonnement de la
lecture de diffusion repose donc sur celui du rapport, ce qui est désormais vrai et
documenté dans `listForReport()`.

**Même classe de défaut relevée ailleurs, non corrigée** : `AtakPoiRepository` et
`AtakMedevacRepository` exposent aussi un `findById()` sans communauté. Hors du
périmètre de cette phase — à traiter séparément.

### Ce qu'il reste pour rendre la phase utilisable

Aucune règle n'existe en base : le moteur tourne à vide tant que personne n'en
crée. Il manque donc un écran de gestion des règles, ou un jeu de règles initial.
C'est le prochain incrément, et il ne demande aucune décision d'architecture.

