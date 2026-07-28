# Terminal SSE & renseignement interpersonnel

Vision produit pour un module **Sensitive Site Exploitation / renseignement interpersonnel** dans COMSPEC Overwatch.

**Statut : livré 1.4.0** — fiche personne (photo visage, identité, armement, circonstances) + API + TOC. Sites / watchlist / chaîne de possession = versions suivantes.

Contrat technique détaillé : [contrat-api-sse.md](contrat-api-sse.md).

---

## Pourquoi ce module

Le pack couvre déjà :

- Photos reconnaissance
- Points d’intérêt dont **personnes prioritaires (HVT)**
- Rapports SALUTE / CONTACT
- Fusion renseignement sur Tacmap

Il manque le **fil conducteur SSE** :

- Enregistrer une **personne** au point de capture (identité, photo, statut)
- Exploiter un **site** (pièces, saisies, preuves)
- Tracer la **chaîne de possession** des informations
- Croiser avec **listes de surveillance** communauté

Références réelles (doctrine, pas copie matériel) :

| Pays | Idée retenue pour le mod |
|---|---|
| **États-Unis** | Enrôlement biométrique type SEEK (empreinte, iris, photo), exploitation site sensible, fiche capture |
| **France** | Renseignement interpersonnel, exploitation site, PV de saisie, transmission nominative |

---

## Principe de communication (Arma ↔ portail)

```text
Opérateur Arma → Terminal SSE → COMSPECExtension
  → REST JSON / multipart (/api/sse/…)
  → Fiches + preuves en base
  → TOC Athena « Personnes identifiées » (polling)
```

- **Écriture** : REST multipart (photo) + JSON (fiche) — même modèle que recon / rapports.
- **Lecture TOC** : polling Tacmap existant.
- **Hors couverture** : file locale SQF puis flush à la reconnexion (pipeline réalisme 1.3.0).
- **Séparation stricte** : fiches SSE = **identités de scénario / cibles RP** ; jamais fusion automatique avec le dossier RH des membres.

Libellés **100 % français métier** à l’écran — jamais de codes techniques, slugs ou valeurs brutes BDD.

---

## Catalogue — Personne (enrôlement)

### Photo du visage (priorité 1 — 1.4.0)

| Élément | Source Arma | Transmission |
|---|---|---|
| Photo visage (mugshot) | Capture dédiée face à la tête (screenshot / pont Iceman) | Multipart → preuve liée à la fiche |
| Angle | Face / profil / trois-quarts (choix joueur) | Champ métier |
| Horodatage + position | `getPosASL` + heure serveur | Métadonnées possession |

Pas de reconnaissance faciale automatique : la photo est une **preuve visuelle** ; le matching watchlist reste textuel / manuel TOC (ou score simple nom + alias).

### Identité (priorité 1 — 1.4.0)

| Élément | Saisie | Pré-remplissage Arma |
|---|---|---|
| Nom, prénom, alias | Formulaire terminal | Variables Eden / `setVariable` NPC |
| Sexe apparent, âge estimé | Liste + nombre | Optionnel Eden |
| Date / lieu de naissance | Texte | Eden |
| Nationalité / langue | Listes métier | Eden + bonus interprète à proximité |
| Pièce d’identité (présent / type / n°) | Liste + texte | Inventaire ACE si item mission |
| Statut | Civil / Combattant / Détenu / Personne prioritaire | Armes + ACE restrain + override |

### Autres éléments réalistes et faisables Arma

| Élément | Réalisme milsim | Faisabilité Arma | Version |
|---|---|---|---|
| Empreintes / iris | SEEK-like | Simulation barre + son (`biometrie_simulee`) | 1.4.0 (événement) |
| Signature / consentement | PV capture | Case + identité opérateur (Steam / ATAK) | 1.4.0 |
| Armement porté | Fouille | `weapons` / `magazines` / inventaire ACE | 1.4.0 |
| Équipement notable | Radio, téléphone, docs | Inventaire + tags scénario | 1.4.0 |
| Signes distinctifs | Cicatrices, tatouages | Texte libre | 1.4.0 |
| Affiliation estimée | Fac / cellule | Texte + lien POI HVT optionnel | 1.4.0 |
| Lieu de capture | Site / rue | Position + marker / bâtiment | 1.4.0 |
| Circonstances | Contrôle, perquisition, reddition | Liste métier | 1.4.0 |
| Déclarations | Interrogatoire court | Texte + niveau de confiance | 1.4.0 |
| Liens connus | Autres fiches | IDs fiches SSE | 1.4.2+ |
| Résultat listes | Watchlist | Match API au save | 1.4.2 |

### Hors scope volontaire

- Reconnaissance faciale, lecture iris réelle, OCR pièce d’identité, RCON, fusion RH membre.

---

## Catalogue — Site (exploitation — 1.4.1)

| Élément | Contenu | Arma |
|---|---|---|
| Dossier site | Nom, type, équipe, GPS | Position + type |
| Checklist pièces | Entrée, étages, cave… | Liste mission + cases |
| Photo par pièce | Preuve liée | Pipeline photo |
| Saisies | Armes, documents, radio, médical, argent RP | Inventaire + quantités |
| Documents / supports | Photos de « papiers » | Screenshot + légende |
| Compte rendu 5 lignes | Auto à la clôture | Généré serveur + éditable |
| Lien POI carte | Suggestion / création HVT | API POI existante |

Types de sites : habitation, dépôt, CP ennemi, cache, véhicule fouillé.

---

## Interface cible (in-game)

Terminal **rugged** (laptop tactique) :

- Barre titre : **« Renseignement interpersonnel »**
- Formulaire identité + statut + circonstances
- **Photo du visage** : capture cible ou galerie recon
- **Empreinte / iris** : simulation (barre + son)
- Inventaire armes / équipement notable (pré-rempli si unité ciblée)
- Boutons : **Accueil** · **Annuler** · **Enregistrer**

Ouverture : ACE ou hub → « Enregistrer une personne ».

---

## Parcours opérateur — enrôlement personne

1. Approcher une personne (joueur OPFOR, IA, otage scénarisé)
2. Ouvrir le terminal SSE
3. Remplir ou **pré-remplir** (NPC Eden)
4. Prendre la photo du visage
5. (Option) lancer la simulation biométrique
6. Enregistrer → fiche visible sur **TOC Athena**
7. (1.4.2) vérification listes de surveillance

### Statuts personne (libellés joueur)

| Statut | Signification |
|---|---|
| Civil | Non combattant |
| Combattant | Porteur d’armes |
| Détenu | Sous contrôle amie |
| Personne prioritaire | Correspondance HVT / surveillance |

### Résultat listes (1.4.2)

| Résultat | Effet |
|---|---|
| Aucune correspondance | Fiche standard |
| Correspondance surveillance | Alerte TOC + marqueur suggéré |
| Correspondance personne prioritaire | Alerte urgente + notification Discord (option) |

---

## Chaîne de possession (preuves — 1.5.0)

Chaque élément enregistré porte :

- Qui a capturé / photographié
- Quand et où
- Transferts : « Remis au responsable renseignement », « Archivé mission »

Visible sur portail en **historique lisible** (pas de log brut). Dès 1.4.0, les métadonnées auteur / position / horodatage sont déjà stockées sur la fiche et la photo.

---

## Liens avec l’existant COMSPEC

| Brique actuelle | Lien SSE |
|---|---|
| Photos recon / Iceman | Capture mugshot réutilisable |
| POI / HVT | Suggestion ou lien affiliation (1.4.1+) |
| SALUTE / CONTACT | Lien depuis dossier site |
| Transmission renseignement (web) | Export mission debrief (1.5.0) |
| Certificats terminal | Rôle « Collecteur SSE » option OP |

---

## Réalisme & contraintes

- **Zone sans couverture** : enregistrement en file d’attente, envoi à la reconnexion
- **Interprète** (option mission) : bonus langue / confiance si interprète à proximité
- **Double validation** (option OP) : personne prioritaire = confirmation 2ᵉ opérateur
- **Délai transmission** : immersion, pas blocage compétitif

---

## Écran poste de commandement (Athena)

| Vue | Version |
|---|---|
| **Personnes identifiées** — filtres statut, mission | 1.4.0 |
| **Sites exploités** — cartographie + pièces | 1.4.1 |
| **Preuves** — galerie liée | 1.4.1+ |
| **Export debrief** — PDF mission (AAR) | 1.5.0 |

Accès selon **rôles organisation** (renseignement, MP, commandement).

---

## Roadmap

| Version | Contenu |
|---|---|
| **1.4.0** | Terminal + fiche personne (photo, identité, armes, circonstances) + API + TOC |
| **1.4.1** | Exploitation site + POI |
| **1.4.2** | Listes surveillance / correspondances |
| **1.5.0** | Chaîne possession + lien debrief |
| **1.5.x** | Identités NPC Eden pré-remplies |

---

## Ce que nous ne ferons pas

- Copie d’interfaces **MORS**, **SEEK** ou logiciels propriétaires
- Biométrie réelle (empreinte / iris) — simulation gameplay uniquement
- Stockage de données personnelles réelles hors cadre milsim / RGPD communauté

---

## Assets associés

Terminal rugged, cadre mugshot, icônes saisie, badge TOC — voir [assets-visuels.md](assets-visuels.md).

---

## Voir aussi

- [Contrat API SSE](contrat-api-sse.md)
- [Guide chef de mission](guide-chef-mission.md)
- [Architecture du mod](architecture-et-addons.md)
