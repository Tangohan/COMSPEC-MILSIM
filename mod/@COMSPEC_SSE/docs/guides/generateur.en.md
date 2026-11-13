# SSE generator

French version: [generateur.md](generateur.md). Guides index: [index.en.md](index.en.md).

## What it is for

The generator builds in one click the **intelligence content** of a person, an item or a full site: identity, phone (contacts, SMS, calls), documents, biometrics, links between targets, digital media. Three levels of control: a **profile** (« make me a credible financier »), a **template** (ready-made recipe, 24 built-in + your own), a **dataset** (fixed scenario: FALCON, same names and links for everyone).

## Where to find it

Eden and Zeus modules (category **COMSPEC · Renseignement (SSE)** (COMSPEC · Intelligence (SSE)), and **COMSPEC · Mission** for the brief and the scenario); Zeus: **BRIEF / GÉNÉRER** (BRIEF / GENERATE) button of the Contrôle Zeus SSE (SSE Zeus Control) screen.

| Module | Target | Fields |
|---|---|---|
| **Générer un profil** (Generate a profile) | Person or item | Profil (Profile) (Insurgé (Insurgent), Civil (Civilian), Militaire (Military), Chef / HVT (Leader / HVT), Courrier (Courier), Financier, Technicien / artificier (Technician / bomb-maker), Renseignement (Intelligence), Logistique (Logistics), Aléatoire (Random)) · Richesse (Richness) (Légère — quelques indices (Light — a few clues) / Standard / Détaillée (Detailed) / Haute valeur — dossier riche (High value — rich file)) · checkboxes Identité réelle (Arma / Eden) (Real identity (Arma / Eden)), Téléphone (Phone), Documents, Biométrie (Biometrics), Lier les cibles entre elles (Link the targets together) · Données inutiles (Useless data) (0 / 10 / 25 / 50 / 75 %) |
| **Créer un site complet** (Create a full site) | Position | Rayon (Radius) (15-150, 35) · Type de site (Site type) (Cellule insurgée (Insurgent cell), Position militaire (Military position), Site civil (Civilian site), Poste de commandement (Command post), Nœud logistique (Logistics node), Réseau financier (Financial network), Atelier technique / IED (Technical / IED workshop), Aléatoire) · Richesse · Objets exploitables (max) (Exploitable items (max)) (0-30, 8) · Supports numériques (Digital media), Documents, Liens réseau (Network links) |
| **Rendre exploitable** (Make exploitable) | Item | Nature de l'objet (Item nature) (Automatique (Automatic), Objet divers (Miscellaneous item), Document, Téléphone, Ordinateur (Computer), Radio, Conteneur / caisse (Container / crate), Arme (Weapon), Véhicule (Vehicle)) · Profil · Richesse |
| **Poser un objet de renseignement** (Place an intelligence item) | Position | Objet (Item) (Ordinateur portable (Laptop), Smartphone, Téléphone basique (Basic phone), Téléphone satellite (Satellite phone), Radio portable (Handheld radio), Dossier papier (Paper file), Photos / dossier (Photos / file), Valise (Suitcase), Caisse d'armes (Weapons crate)) · Profil · Richesse · Générer le contenu au lancement (Generate content at start) |
| **Appliquer un modèle** (Apply a template) | Person or item | Modèle intégré (Built-in template) (« Choisir au Zeus (liste à la pose) » (Choose in Zeus (list on placement)) or a `builtin_*`) · Autre modèle (identifiant) (Other template (identifier)) |
| **Enregistrer un modèle** (Save a template) (Zeus) | Generated target | Nom du modèle (Template name) (« Mon modèle SSE » (My SSE template)): the target becomes a reusable recipe |
| **Lister les modèles** (List templates) (Zeus) | — | Shows « n modèle(s) SSE disponibles : • nom [source] — id » (n SSE template(s) available: • name [source] — id) |
| **Générer depuis un brief** (Generate from a brief) | Position | Brief narratif (Narrative brief) (« cellule de 3 financiers » (cell of 3 financiers), « atelier IED » (IED workshop), « chef et courrier » (leader and courier)) · Pack scénario (Scenario pack) (Aucun — utiliser le brief (None — use the brief), Cellule insurgée, Réseau de contrebande (Smuggling network), Dépôt d'armes (Weapons depot), Poste de commandement, Planque (Safehouse), Atelier IED, Nœud financier (Financial node), Cellule renseignement (Intelligence cell), Dataset FALCON (Irak 2012)) · Rayon (20-200, 40) |
| **Diriger le scénario** (Direct the scenario) | Position | Action (Appliquer le dataset (Apply the dataset) / Changer le niveau seulement (Change the level only) / Lister les datasets (List datasets)) · Dataset (« FALCON — cellule Irak 2012 (6 rôles) » (FALCON — Iraq 2012 cell (6 roles))) · Niveau de révélation (Disclosure level) (0 — Surface, 1 — Tactique (Tactical), 2 — Terrain (Field), 3 — Vérité complète (Full truth)) · Rayon (20-300, 50: the nearest person becomes the leader) |
| **Créer un site d'entraînement** (Create a training site) | Position | Scénario (Scenario) (Aléatoire + 8 packs) · Rayon (20-150, 60) |
| **Poser un site** (Place a site) / **Peupler un bâtiment** (Populate a building) / **Semer des preuves** (Scatter evidence) / **Relier plusieurs sites** (Link several sites) | Position | Type de site (Planque, Labo d'IED (IED lab), Atelier de faux papiers (Forgery workshop), Cache d'armes (Weapons cache), PC de cellule (Cell CP), Boîte aux lettres morte (Dead drop), Nœud hawala / changeur (Hawala node / money changer)), Occupants, Camp des occupants (Occupants' side), Occupants armés (Armed occupants), Richesse, Pièges (Traps), Dans un bâtiment proche (In a nearby building); Semer: Rayon 30, Nombre de pièces (Number of exhibits) 6; Relier: Nombre de sites (Number of sites) 4, Rayon 3 km |
| **Lier des entités** (Link entities) | Selection | Relation (Associé (Associate), Contact, Propriétaire de (Owner of), Fait référence à (Refers to)) · Confiance (Confidence) (0.3 / 0.5 / 0.7 / 0.9) · Disposition (Layout) (Chaîne (Chain) / Étoile (Star)) |

Equivalent Eden attributes on every person or item: **COMSPEC SSE — Renseignement** (COMSPEC SSE — Intelligence) (Activer le renseignement SSE (Enable SSE intelligence), Nature de l'objet, Profil, Moment de la génération (Generation time), Richesse, Identité (Identity), Ce que la base doit répondre (What the database should answer), Nom (Last name), Prénom (First name), Alias connu (Known alias), Graine (Seed), Rôle déclaré (Declared role), Nationalité (Nationality), Langue (Language), Biométrie, Notes Zeus, Modèle SSE (SSE template), Dataset mission (Mission dataset), Rôle dans le dataset (Role in the dataset), Région / théâtre (Region / theatre)), **— Documents** (Pièce 1 à 3 (Exhibit 1 to 3): intitulé (title), contenu (content), grille (grid), mot de code (code word)), **— Téléphone** (Numéro (Number), Modèle (Model), Contacts, Messages, Notes, Lieux (Places)), **— Ordinateur** (Nom (Name), Compte (Account), Fichiers (Files), Courrier (Mail), Réseau (Network), Indice d'accès (Access hint)).

## Step by step: a cell in five minutes

Situation: a village, five civilian AI placed by Zeus, to be turned into a logistics cell.

1. Zeus › **Générer depuis un brief** on the village square: Brief « cellule logistique de 5 personnes » (logistics cell of 5 people), Pack **Aucun — utiliser le brief**, Rayon 40. Notification « Réseau SSE généré (…) — rayon 40 m » (SSE network generated (…) — radius 40 m). The five AI have an identity, phone, documents, biometrics and links.
2. **Générer un profil** on the leader: Profil **Chef / HVT**, Richesse **Haute valeur — dossier riche**.
3. **Poser un objet de renseignement** in the house: Objet **Ordinateur portable**, Profil **Logistique**.
4. **Enregistrer un modèle** on the leader: « Chef logistique village » (Village logistics leader); to reuse via **Appliquer un modèle › Autre modèle (identifiant)**.
5. Check with **Comparer connu et vérité** (Compare known and truth) (Zeus screen): everything is in « vérité » (truth), nothing in « connu des joueurs » (known to the players).

## Built-in templates

Cellule insurgée — Irak (`builtin_cellule_insurgee_irak`) (Insurgent cell — Iraq), Chef HVT (HVT leader), Réseau courriers (Courier network), Financier, Technicien IED (IED technician), Safehouse urbain (Urban safehouse), Contrebande frontière (Border smuggling), Civil non pertinent (bruit) (Irrelevant civilian (noise)), Cellule drone / ISR (Drone / ISR cell), Propagande / média (Propaganda / media), Irak 2010-2020 (Cache d'armes (Weapons cache), Cellule IED (IED cell), Chef de secteur (Sector leader), Courrier frontière (Border courier), Relais financier (Financial relay), Planque urbaine (Urban safehouse)), Russie 2020-2024 (Reconnaissance, Logistique, Poste de commandement (Command post), Cellule drone (Drone cell), Radio / EW, Info ops, Courrier civil (Civilian courier), Civil couverture (Cover civilian)). The `builtin_` ones cannot be deleted.

**FALCON** dataset (Iraq 2012, 6 roles): Chef de secteur ABU KARIM (HVT) (Sector leader), Technicien IED « L'INGÉNIEUR » (THE ENGINEER), Courrier frontière « LE CHAUFFEUR » (THE DRIVER), Relais financier « LE CHANGEUR » (THE MONEY CHANGER), Gardien de planque « LE LOCATAIRE » (Safehouse keeper, THE TENANT), Civil (bruit de fond) (Civilian (background noise)). Eden attribute **Rôle dans le dataset** to pin a role on a person.

## Common pitfalls

- The generator **never acts on players**.
- **Same seed, same results**: Graine (0 = automatic) on attributes and modules; a dataset is fixed for everyone.
- The brief only understands a few keywords (headcount 1-10, « trois » (three), « cinq » (five); financ, ied / engin / explosi, courrier, command / chef); for the rest, use a pack.
- **Données inutiles** (25 % by default) and the Génération (Generation) settings (Part d'infos sans intérêt (Share of uninteresting info) 0.25, Part de fausses pistes (Share of red herrings) 0.05) add deliberate noise: that is intended.
- A generated item only has its SSE menu if it is **SSE-marked** (the generator does it; a hand-placed item needs **Rendre exploitable**).
- **Réinitialiser un site** (Reset a site) (Zeus, Rayon 50) and **Effacer des données** (Erase data) (confirmation) clean up before replaying.

## Athena

- **Nom de code de la mission** (Mission code name) and **Référence du dossier SSE** (SSE case reference) (Réseau (Network) settings) accompany everything that is transmitted.
- Mission datasets and templates can be consulted in Bureau SSE › **Modèles de mission** (SSE Desk › Mission templates); the SSE Desk AI profiles are chosen on the [Area AI](ia_de_la_zone.en.md) page.
