# Guide fonctionnel de référence — structure communautaire type « unité »

Ce document traduit et structure en français un modèle fonctionnel inspiré des plateformes de gestion d’unités milsim (souvent présentées avec des écrans de type « rangs / distinctions / qualifications groupés, ORBAT visuel, calendrier, campagnes, analytics »).  

**Il ne constitue pas à lui seul le manuel utilisateur du déploiement Athena actuel.** Il sert de **cahier des charges de référence** pour prioriser l’évolution produit. Pour l’état réel du code, voir [INVENTAIRE-FONCTIONNALITES.md](INVENTAIRE-FONCTIONNALITES.md).

Chaque grande section inclut un encadré **« Dans Athena aujourd’hui »** : *Absent*, *Partiel* ou *Équivalent* (avec nuance).

---

## 1. Rangs (grades)

### 1.1 Concept

Les rangs permettent d’afficher la hiérarchie des membres. Le modèle de référence prévoit pour chaque rang : libellé, abréviation, **image PNG** dédiée (upload), et un court descriptif administratif.

> **Dans Athena aujourd’hui — *Partiel***  
> Un **référentiel de grades** existe (systèmes FR/US, catégories, libellés, ordre) dans l’admin organisationnel. Il ne repose pas sur le même paradigme que des « patches » PNG par rang personnalisables par communauté comme décrit ici. L’affichage profil peut combiner grades référentiels et champs texte selon les vues.

### 1.2 Groupes de rangs

Les groupes classent les rangs (ex. sous-officiers, officiers) et permettent de les organiser dans l’interface d’administration.

> **Dans Athena aujourd’hui — *Partiel***  
> Les **catégories** et **systèmes** de grades du référentiel jouent un rôle proche, sans duplication exacte du flux « groupe + image PNG par rang » du présent guide.

### 1.3 Créer un groupe de rangs

Parcours type : menu **Rangs** → **Créer un groupe** → formulaire (nom du groupe) → validation ; aperçu avec actions modifier / supprimer.

> **Dans Athena aujourd’hui — *Partiel***  
> Gestion des catégories et systèmes via `/admin/organization/referentiels/grades` et données associées — l’intitulé exact des écrans peut différer.

### 1.4 Modifier l’ordre d’affichage des groupes

Glisser-déposer des groupes, enregistrer.

> **Dans Athena aujourd’hui — *Partiel***  
> Ordre géré via champs `sort_order` côté référentiel ; pas nécessairement d’UI dédiée identique.

### 1.5 Supprimer un groupe de rangs

Confirmation en modale.

> **Dans Athena aujourd’hui — *Partiel***  
> Désactivation / suppression logique selon implémentation.

### 1.6 Rangs individuels

Création : nom, abréviation, image, description ; liste dans un groupe ; prévisualisation.

> **Dans Athena aujourd’hui — *Partiel***  
> Création de lignes de référentiel (libellés, codes) ; **pas** d’upload d’image PNG par rang au sens de ce guide.

### 1.7 Recommandations image (référence)

Carré recommandé, PNG fond transparent.

> **Dans Athena aujourd’hui — *Absent*** pour les grades du référentiel actuel (hors besoins futurs).

---

## 2. Distinctions (récompenses / badges)

### 2.1 Concept

Même logique que les rangs : image PNG, libellé, synopsis ; affichage sur le profil.

> **Dans Athena aujourd’hui — *Absent*** comme module dédié « distinctions avec médias ». L’**historique de service** ou champs libres peuvent documenter des récompenses sans équivalent graphique générique.

### 2.2 Groupes de distinctions

Catégories (ex. décorations d’unité, rubans de campagne).

> **Dans Athena aujourd’hui — *Absent***.

### 2.3 Création / édition / ordre / suppression

Identique en principe aux groupes de rangs.

> **Dans Athena aujourd’hui — *Absent***.

---

## 3. Qualifications

### 3.1 Concept

Qualifications avec image, description, organisation par groupes (ex. qualifications d’unité vs ateliers état-major).

> **Dans Athena aujourd’hui — *Partiel***  
> Table `personnel_qualifications` : entrées **textuelles** (nom, niveau, dates, statut) — pas de groupement ni médias comme dans ce guide.

### 3.2 Groupes et qualifications individuelles

Création, ordre, suppression ; liaison profil.

> **Dans Athena aujourd’hui — *Partiel***  
> Saisie des qualifications côté dossier personnel sans le parcours « groupe + PNG » décrit ici.

---

## 4. Sous-système « unités » (affichage profil)

### 4.1 Concept

Unités affichées sur le profil (ex. peloton, section), avec images et textes, groupées par catégories.

> **Dans Athena aujourd’hui — *Équivalent / partiel***  
> **ORBAT** et `units` + affectations (`personnel_assignments`, `primary_unit_id`) couvrent l’organisation réelle. Les « unités graphiques » à la carte du présent guide ne sont pas requises telles quelles.

---

## 5. Postes (fonctions)

### 5.1 Concept

Postes avec image, description, groupes (ex. postes de manoeuvre vs état-major).

> **Dans Athena aujourd’hui — *Partiel***  
> Champs de rôle (`primary_role`, `secondary_role`, rôles dans affectations) — pas de bibliothèque de postes illustrés identique.

---

## 6. Structure communautaire — ORBAT

### 6.1 Concept

ORBAT : structure hiérarchique (S-shops, chaîne de commandement, rôles de combat).

> **Dans Athena aujourd’hui — *Équivalent***  
> Page `/orbat` et tables `units` ; types d’unités configurables (`app/Config/units.php`).

### 6.2 Créer une structure, parents / enfants

Création de structures avec ordre d’affichage ; ajout d’éléments parent puis enfant (listes déroulantes d’unités existantes).

> **Dans Athena aujourd’hui — *Partiel***  
> Hiérarchie d’unités gérée côté admin (unités / groupes / équipes) ; le flux exact « structure nommée + modaux parent/enfant » peut différer.

### 6.3 Supprimer une structure

Avec confirmation.

> **Dans Athena aujourd’hui — *Partiel***  
> Suppression / réorganisation selon écrans admin actuels.

---

## 7. Roster (effectif)

Vue listant pelotons, personnel, grades et postes — utile promotions et affectations.

> **Dans Athena aujourd’hui — *Partiel***  
> L’ORBAT et les fiches personnelles permettent une vue d’ensemble ; **pas** de page « roster » dédiée identique au descriptif.

---

## 8. Permissions (granularité métier)

Liste type de permissions : groupes d’unités, unités, structures, éléments, etc.

> **Dans Athena aujourd’hui — *Équivalent***  
> Système **RBAC** : rôles, permissions par slug, `Gate` ; administration système et organisation. Les slugs exacts ne correspondent pas ligne à ligne au texte source anglais.

---

## 9. Événements individuels

Événements hors campagne, calendrier communautaire, création (nom, date/heure, carte, description, image), notification Discord optionnelle, édition, verrouillage des réponses, archivage.

> **Dans Athena aujourd’hui — *Absent*** comme module calendrier / RSVP dédié.

---

## 10. Campagnes

Regroupement d’opérations dans le temps : nom, description, visuel, sous-événements ; archivage.

> **Dans Athena aujourd’hui — *Absent***.

---

## 11. Analytique communautaire

Cartes récapitulatives (effectifs, actifs, totaux par attributs), courbes d’arrivées / départs, répartition des grades / postes / distinctions, analytics de **présence** aux événements et campagnes, comparaison par joueur.

> **Dans Athena aujourd’hui — *Absent*** pour l’ensemble « présence événements + graphiques communautaires ». Des rapports **LMS** ou listes admin existent pour les formations.

---

## 12. Documents

Documentation interne de l’unité ; liaison compte cloud (ex. Google) pour importer ; permissions par rôle ; dissociation du compte.

> **Dans Athena aujourd’hui — *Équivalent / partiel***  
> Module **documents** riche (versions, accès, arborescence). **Pas** d’intégration Google Drive décrite dans les routes comme tel ; accès basé sur rôles / permissions documentaires.

---

## 13. Profils joueurs

Compte utilisateur lié à un profil : rang, distinctions, qualifications, unités, postes, journal d’actions, portrait / bannière, modération d’images, affectation / retrait des attributs, position et unité principales, radiation, suppression profil.

> **Dans Athena aujourd’hui — *Partiel / équivalent***  
> Fiches personnelles, profils étendus, médias, historique ; mécanismes exacts (radiation, logs détaillés, modération image) à vérifier dans les vues et contrôleurs — l’esprit est couvert, le parcours UI peut différer.

---

## 14. Rôles et habilitations (abilities)

Abilities regroupées par sections ; rôles comme ensembles d’abilities ; habilitation critique « voir la communauté » ; rôle invité par défaut ; habilitation « gérer les administrateurs » à réserver.

> **Dans Athena aujourd’hui — *Équivalent***  
> Permissions et rôles par tenant ; super-admin système vs admin organisation.

---

## 15. Alertes

Annonces contextualisées (titre, contenu, variante couleur, page cible), archivage.

> **Dans Athena aujourd’hui — *Absent*** comme module générique d’alertes multi-pages.

---

## 16. Candidatures (applications)

Constructeur de formulaire (types de champs, validation, ordre), aperçu, **actions automatisées** (rôle à l’inscription, rôle / poste / rang après acceptation), file des candidatures avec statuts (en examen, accepté, refusé), notes recruteur, journaux.

> **Dans Athena aujourd’hui — *Partiel***  
> Parcours **enlistment** public + liste admin des candidatures ; pas de moteur de formulaire aussi riche que dans ce guide ni d’actions automatiques complètes identiques.

---

## Tableau récapitulatif

| Domaine (ce guide) | Dans Athena aujourd’hui |
|--------------------|-------------------------|
| Rangs + images PNG par groupe | Partiel (référentiel sans médias par rang) |
| Distinctions | Absent (module dédié) |
| Qualifications groupées + images | Partiel (données texte) |
| Unités profil « illustrées » | Partiel (ORBAT / affectations) |
| Postes illustrés | Partiel |
| ORBAT structuré | Équivalent |
| Roster dédié | Partiel |
| Événements / présence | Absent |
| Campagnes | Absent |
| Analytics communauté | Absent |
| Documents internes | Équivalent / partiel (pas Google Drive intégré tel quel) |
| Profils complets | Partiel / équivalent |
| Permissions fines | Équivalent |
| Alertes site | Absent |
| Candidatures avancées | Partiel |

---

*Ce guide peut être utilisé pour prioriser les développements et les offres premium (voir [VISION-COMMUNAUTES-PREMIUM.md](VISION-COMMUNAUTES-PREMIUM.md)).*
