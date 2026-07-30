# Plan — Architecture COMSPEC SSE (identity activities)

Reprise de la proposition d'architecture en trois temps : collecte terrain, enregistrement
et corrélation Athena, exploitation par la cellule renseignement. Ce document arbitre entre
ce qui existe déjà, ce qui doit être repris, et ce qui reste à construire.

**État au 30/07/2026.** Version mod : 1.4.12. Branche : `claude/sse-medical-ace-integration-cbf1yt`.

---

## 1. Ce que la proposition change vraiment

Le modèle en trois temps est juste et il n'est pas celui qui a été codé jusqu'ici. La
différence de fond n'est pas l'UI : c'est **le dossier comme unité de travail**.

Aujourd'hui la fiche personne est l'objet central, et le dossier (`sse_cases`) n'est qu'une
étiquette de classement saisie après coup. Dans le modèle proposé, l'équipe **ouvre un
dossier en arrivant sur objectif**, et tout ce qui suit — sujets, matériel, photos — s'y
rattache automatiquement. Le contexte (quoi, où, quand, par qui, dans quel dossier) est
préservé sans que le joueur le ressaisisse.

C'est un renversement, pas un ajout. Il conditionne le reste.

Deuxième apport majeur : **la préparation Eden avec génération déterministe**. Sans elle,
un chef de mission qui veut quinze civils exploitables doit écrire quinze identités à la
main, et personne ne le fera. C'est le point qui décide si le module est joué ou non.

---

## 2. Décision d'architecture : pas de `@COMSPEC_SSE` séparé

La proposition retient un mod séparé `@COMSPEC_SSE` avec neuf addons. Je recommande de
**garder un seul mod `@COMSPECOverwatch`** et d'y ajouter des PBO.

| Argument | Constat |
|---|---|
| Dépendance | `@COMSPEC_SSE` dépendrait de `COMSPEC Overwatch` pour la couche réseau, l'identité opérateur et le terminal. Il ne serait jamais utilisable seul. |
| DLL | `COMSPECExtension_x64.dll` est livrée dans `@COMSPECOverwatch`. Un second dossier `@` ne l'emporte pas, et il ne faut surtout pas une seconde DLL. |
| Coût réel | Second item Workshop, second cycle de version, second changelog, second point de désynchronisation — pour du code qui vit dans le même pack. |
| Ce qu'on cherche vraiment | La séparation **logique** et la possibilité de retirer la couche. Un PBO donne exactement cela : le retirer du dossier `addons` suffit. |

**Neuf addons est également trop.** Un PBO n'est pas un dossier : chacun coûte une entrée
de build, un `CfgPatches`, une chaîne de dépendances et un risque d'ordre de chargement.
Quatre suffisent, alignés sur les trois temps :

```text
@COMSPECOverwatch/addons/
├── connect.pbo          existant — liaison, terminal SEEK, identité (déjà livré)
├── sse_ace.pbo          existant — interactions ACE sur autrui (déjà livré)
├── sse_site.pbo         nouveau  — dossier, site, matériel, evidence, photographie
└── sse_zeus.pbo         nouveau  — modules Zeus + attributs Eden + génération
```

`sse_main`, `sse_seek`, `sse_identity`, `sse_camera`, `sse_network` de la proposition
correspondent à du code déjà présent dans `connect` : les y laisser évite un éclatement
sans bénéfice. `sse_interactions` est `sse_ace`.

---

## 3. Ce qui est déjà livré

À ne pas reconstruire.

| Élément proposé | État |
|---|---|
| Objet SEEK II en inventaire | **Livré** — `COMSPEC_Item_SeekTerminal`, requis pour ouvrir une fiche, réglage CBA `comspec_sse_require_item` |
| UI Arma dédiée (`RscDisplay`) | **Livré** — dialog 9991, châssis, barre d'état, platine, touches physiques A1/A2/−/+/::/SIGN |
| Interaction ACE sur un personnage | **Livré** — nœud « Renseignement SSE » sur `CAManBase` |
| Acquisitions avec durée ACE | **Livré** — empreintes 8 s, iris 10 s, ADN 20 s, barre ACE, interruption si la cible s'éloigne |
| Qualité d'acquisition variable | **Livré** — indice par modalité, jamais 100 %, jauge et référence de laboratoire |
| Données post-mortem | **Livré** — le terminal préremplit une personne décédée, le constat porte « Décédée » |
| Préremplissage automatique | **Livré partiellement** — joueur, position, grille, heure, inventaire cible, statut ACE restrain, constat ACE Medical |
| Couche réseau partagée | **Livré** — `SubmitSsePerson` passe le JSON tel quel ; pas de seconde DLL |
| Chaîne de possession | **Livré** — `sse_custody_events` alimentée et affichée en frise sur la fiche |
| Watchlist / croisement | **Livré** — croisement à l'enregistrement, résultat au journal du poste de commandement |
| Dossier site, pièces, saisies | **Livré côté plateforme** — dépôt, API, portail. **Aucune commande d'extension** : le terrain ne peut pas encore ouvrir un site |
| Mode hors-ligne / file locale | **Non livré** |
| Eden / génération déterministe | **Non livré** |
| Réseau de relations | **Non livré** |

---

## 4. Lots à construire

### L4 — Le dossier comme contexte actif (fondation)

Sans ce lot, tout le reste reste une collection de fiches isolées.

- `COMSPEC_SSE_ActiveCase` en variable de mission : dossier courant de l'élément.
- Ouverture depuis le terminal : « Ouvrir un dossier SSE » → référence, objectif, unité,
  équipe, DTG, grille — tout prérempli sauf le nom de l'objectif.
- Tout enregistrement ultérieur (sujet, saisie, photo) hérite du dossier actif sans saisie.
- Le champ « code dossier » actuel devient un repli manuel, plus la voie normale.
- Extension : `SubmitSseCase`, `SubmitSseSite`, `SubmitSseSeizure` — **recompilation C#**.

### L5 — Matériel et evidence (`sse_site.pbo`)

- Interactions ACE sur objets, par famille de classes plutôt que par classname.
- Registre `CfgCOMSPEC_SSE` : `classname → profil` (`PHONE`, `COMPUTER`, `DOCUMENT`,
  `STORAGE`, `WEAPON`, `RADIO`, `IDENTITY_DOCUMENT`), avec détection par héritage de config
  en repli, et surcharge Eden possible. Pas de dépendance dure aux mods tiers.
- États : `DISCOVERED` → `COLLECTED` → `TRANSFERRED` → `EXPLOITED`.
- Provenance conservée : `RECOVERED FROM SUBJECT P03` ou `FOUND AT ROOM 02`.

### L6 — Préparation Eden et génération déterministe (`sse_zeus.pbo`)

Le lot qui rend le module jouable.

- Attributs Eden sur unités et objets : activation, type SSE, mode manuel ou généré,
  profil de jeu de données, valeur de renseignement, association de dossier.
- **Seed stable par entité** : `SSE SEED // 482731`. Toutes les valeurs dérivées — identité,
  numéro biométrique, contenu de téléphone, documents, relations — sont reconstruites à
  l'identique à chaque requête et après sauvegarde. **Jamais de `random` à la requête.**
- Profils : `RANDOM CIVILIAN`, `LOCAL CIVILIAN`, `INSURGENT`, `HVT`, `MILITARY`,
  `TECHNICIAN`, `COURIER`, `FINANCIER`, `UNKNOWN`, `CUSTOM`.
- Modules Zeus : créer un site, forcer un résultat d'identité, poser une entrée de
  watchlist, réinitialiser un site.

Note technique : les valeurs d'attributs Eden ne sont pas lisibles au runtime. Leur
`expression` doit recopier la configuration vers des variables d'objet exploitables.

### L7 — Identité à plusieurs niveaux de fiabilité

- `SOURCE`, `RELIABILITY`, `CONFIDENCE` sur chaque donnée.
- Identité déclarée, identité documentaire et identité biométrique **coexistent** au lieu
  de s'écraser. `UNVERIFIED` / `CORROBORATED` / `CONFIRMED` / `CONFLICTING`.
- C'est ce qui rend l'exploitation intéressante : un sujet qui ment produit une
  contradiction visible, pas une simple correction.

### L8 — Portrait biométrique

Trois niveaux possibles ; le niveau 1 est un piège.

| Niveau | Moyen | Verdict |
|---|---|---|
| 1 | Texture `.paa` du visage via `face _unit` → `CfgFaces` | Donne l'UV dépliée, pas un portrait. Utile seulement comme **identifiant stable** |
| 2 | Capture d'écran cadrée sur la tête | Réaliste, réutilise le pipeline photo existant. **Retenu** |
| 3 | Caméra dédiée + `renderTarget`, cadrage tête/épaules | Meilleur rendu, coût supérieur. À viser ensuite |

`face _unit` reste précieux : il fonctionne avec les visages moddés sans liste blanche, et
sert de clé biométrique fictive stable.

### L9 — Mode hors-ligne et comptes rendus

- File locale d'acquisitions, `ATHENA LINK // LOST`, puis `7 RECORDS PENDING → SYNC`.
- Comptes rendus par échelon : `SSE FLASH`, `INITIAL SSE REPORT`, `IDENTITY REPORT`,
  `MATERIAL EXPLOITATION REPORT`, `FINAL SSE REPORT`.

Le compte rendu ne doit pas ressembler à un inventaire. Le générateur cinq lignes actuel
(`SseSiteRepository::buildFiveLineReport`) est un PV de saisie : il faut le remplacer par
le format SITUATION / SITE EXPLOITATION / PERSONNEL / MATERIAL / KEY FINDINGS / ASSESSMENT
/ FOLLOW-ON, alimenté par les événements déjà enregistrés.

---

## 5. Ordre recommandé

1. **L4** — le dossier actif. Fondation, débloque le reste.
2. **L6** — Eden et génération. Sans données préparées, rien à exploiter.
3. **L5** — matériel et evidence.
4. **L7** — fiabilité et contradictions.
5. **L8** — portrait niveau 2.
6. **L9** — hors-ligne et comptes rendus.

L4 et L5 imposent une recompilation de `COMSPECExtension` : les regrouper en une seule
passe C#, avec `LookupSsePersonByUnit` qui attend déjà.

---

## 6. Ce qui reste hors périmètre

Inchangé, et à défendre : reconnaissance faciale réelle, lecture d'iris réelle, OCR de
pièce d'identité, fusion avec le dossier RH des membres. Tout est simulation de scénario.

La règle roleplay 1.4.8 tient également : aucune donnée KAT sur le terminal SSE.
