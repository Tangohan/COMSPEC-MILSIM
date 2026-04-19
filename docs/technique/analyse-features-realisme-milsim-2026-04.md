# Analyse du projet COMSPEC MILSIM et propositions de features orientées détail & réalisme

_Date : 19 avril 2026_

## 1) Lecture rapide du socle actuel

Le projet dispose déjà d’une base très solide pour une plateforme MILSIM complète :

- backend PHP monolithique structuré (contrôleurs/services/repositories/middlewares) ;
- périmètre fonctionnel large (ORBAT, personnel, courrier, forum, LMS, documents, recrutement, modules ATAK/C2) ;
- logique multi-tenant, utile pour séparer des communautés/unitées ;
- API tactiques existantes (markers, pings, CAS/Nine-Line, santé, replay, IFF, zones, etc.).

En synthèse : la plateforme est déjà « riche en modules », et le gain principal se trouve désormais dans la **profondeur simulation** (réalisme métier), pas seulement dans l’ajout de pages.

---

## 2) Opportunités de réalisme MILSIM (vision produit)

Pour renforcer la crédibilité et l’immersion, les améliorations les plus efficaces sont celles qui :

1. relient les modules entre eux (RH ↔ formation ↔ opérations ↔ logistique ↔ RETEX),
2. imposent des contraintes proches du terrain (chaîne de commandement, disponibilité, délais, friction),
3. produisent des traces exploitables (journal, doctrine, métriques, progression objective).

---

## 3) Features recommandées (priorisées)

## P0 — Impact fort / complexité maîtrisée (4 à 8 semaines)

### P0.1 — Readiness opérationnelle individuelle et collective

**Objectif** : éviter les opérations « irréalistes » avec effectifs indisponibles/non qualifiés.

**Proposition**
- Ajouter un score de readiness par opérateur (0–100) calculé via :
  - validité formations/certifications,
  - présence récente (pointage/activité),
  - aptitude médicale simulée (si activée),
  - disponibilité déclarée.
- Agréger ce score au niveau équipe/groupe/unité ORBAT.

**Bénéfice réalisme**
- Le commandement planifie en fonction de la capacité réelle, pas d’un effectif théorique.

---

### P0.2 — Cycle MISSION complet (OPORD → EXORD → SITREP → AAR)

**Objectif** : formaliser la doctrine de conduite des opérations.

**Proposition**
- Ajouter un workflow type mission avec statuts :
  1) brouillon OPORD,
  2) validation commandement,
  3) exécution (SITREP horodatés),
  4) clôture + AAR (After Action Review),
  5) génération des actions correctives.
- Lier mission, unités, ressources, événements carte, pertes simulées, enseignements.

**Bénéfice réalisme**
- Introduit discipline d’état-major + boucle d’apprentissage opérationnelle.

---

### P0.3 — Gestion des rôles critiques (JTAC, Medic, RTO, SL, PL)

**Objectif** : renforcer la spécialisation fonctionnelle en jeu.

**Proposition**
- Créer des profils de rôle avec prérequis (formations, validations, heures de pratique).
- Restreindre certaines actions applicatives selon qualification active (ex: demandes CAS, validations médicales, canaux radio spécifiques).

**Bénéfice réalisme**
- Les capacités tactiques dépendent des compétences certifiées, pas seulement d’un grade affiché.

---

### P0.4 — Journal tactique horodaté unifié

**Objectif** : consolider la « vérité terrain » multi-source.

**Proposition**
- Unifier dans un fil chronologique : pings, marqueurs majeurs, changements statut unité, événements santé, ordres publiés, messages critiques.
- Ajouter filtres (mission, unité, type d’événement, fenêtre temporelle).

**Bénéfice réalisme**
- Permet un RETEX fiable, la reconstitution de l’action, et le commandement en temps réel.

---

## P1 — Réalisme avancé (2 à 4 mois)

### P1.1 — Modèle logistique simplifié mais crédible

**Objectif** : intégrer la friction logistique sans surcharger les joueurs.

**Proposition**
- Inventaires par unité (munitions, médical, carburant, pièces).
- Consommation liée aux opérations (règles simples paramétrables).
- Ravitaillement via demandes validées (priorité, délai, disponibilité).

**Bénéfice réalisme**
- Les choix tactiques deviennent dépendants du soutien.

---

### P1.2 — Système médical MILSIM structuré (TCCC-lite)

**Objectif** : rendre la chaîne médicale cohérente et mesurable.

**Proposition**
- États blessure normalisés (léger, modéré, critique, KIA simulé).
- Timers clés : traitement initial, stabilisation, évacuation, retour à l’unité.
- Rôles autorisés à modifier les états (Medic/commandement selon doctrine).

**Bénéfice réalisme**
- Ajoute de la tension opérationnelle et valorise les spécialisations médicales.

---

### P1.3 — Communications et discipline radio

**Objectif** : mimer la structure C2 radio sans complexité excessive.

**Proposition**
- Canaux radio virtuels par échelon (command, platoon, support, medevac).
- Journalisation des messages critiques au format standard (ex: contact report court).
- Option d’accusé de réception obligatoire pour ordres critiques.

**Bénéfice réalisme**
- Réduit le bruit, améliore coordination et clarté de commandement.

---

### P1.4 — Météo / heure / visibilité comme paramètres opérationnels

**Objectif** : intégrer les facteurs environnementaux dans la planification.

**Proposition**
- Ajouter par mission : fenêtre horaire, météo simulée, visibilité, contraintes terrain.
- Impacter automatiquement la difficulté estimée et la préparation requise.

**Bénéfice réalisme**
- Les plans évoluent selon les conditions, comme en conduite réelle.

---

## P2 — Différenciation premium / long terme

### P2.1 — Doctrine et SOP versionnées

- Dossier doctrine par communauté : SOP officielles, checklists, formats de rapport.
- Versionnement + date d’effet + accusé de lecture.

### P2.2 — Profil d’expérience opérateur (XP réaliste)

- Historique missionnel pondéré (type mission, rôle, performance, discipline).
- Progression plafonnée par qualification validée (pas de « level-up arcade »).

### P2.3 — Simulateur de préparation mission (wargaming léger)

- Avant mission : estimation de risque, disponibilité, logistique, couverture rôle.
- Score de préparation avec recommandations automatiques.

### P2.4 — AAR semi-assisté par IA

- Génération d’un brouillon RETEX à partir du journal tactique.
- Détection d’écarts doctrine vs exécution (avec validation humaine obligatoire).

---

## 4) Améliorations de détail UI/UX qui augmentent fortement l’immersion

### 4.1 Micro-détails « commandement »

- Horodatage UTC + local partout sur les événements critiques.
- Affichage systématique de la chaîne hiérarchique sur fiches mission.
- Statuts unitaires homogènes (READY / PARTIAL / NMC).

### 4.2 Cohérence terminologique

- Uniformiser les termes militaires (mission, ordre, SITREP, CASEVAC/MEDEVAC, ROE).
- Ajouter un glossaire interne visible in-app pour onboarding rapide.

### 4.3 Densité informationnelle contrôlée

- Vues compactes pour commandement (tableaux filtrables + raccourcis clavier).
- Vues guidées pour novices (mode simplifié activable).

### 4.4 Modes d’affichage tactique

- Thème “briefing nuit” à contraste adapté.
- Couleurs d’état standardisées et accessibles (daltonisme).

---

## 5) Propositions techniques concrètes (implémentation)

## 5.1 Modèle de données (itératif)

Ajouter progressivement des tables dédiées :
- `missions`, `mission_orders`, `mission_sitreps`, `mission_aar` ;
- `readiness_snapshots`, `qualifications`, `qualification_validations` ;
- `logistics_stocks`, `logistics_requests`, `logistics_transfers` ;
- `medical_events`, `medical_status_history` ;
- `tactical_event_log` (journal unifié).

Principe : migrations incrémentales + index sur `tenant_id`, `mission_id`, `unit_id`, `created_at`.

## 5.2 Permissions et gouvernance

Étendre le catalogue de permissions avec familles cohérentes :
- `operations.missions.*`, `operations.sitrep.*`, `operations.aar.*`
- `operations.readiness.*`, `operations.medical.*`, `operations.logistics.*`
- `operations.comms.*`, `operations.doctrine.*`

## 5.3 API et contrats

- Exposer les nouveaux domaines via `/api/operations/*`.
- Standardiser les réponses d’erreur (code métier + message + contexte).
- Ajouter endpoints dédiés export RETEX (JSON/PDF).

## 5.4 Qualité / sécurité

- Tests priorisés : isolation multi-tenant, contrôle d’accès rôle/qualification, workflows mission.
- Journal d’audit immuable pour actions critiques.
- Rate-limit renforcé sur endpoints temps réel sensibles.

---

## 6) Roadmap recommandée (90 jours)

### Mois 1
- Livrer le **journal tactique unifié** + socle missions (OPORD/SITREP/AAR minimal).
- Introduire permissions `operations.*` et premiers tests d’accès.

### Mois 2
- Livrer **readiness individuelle/collective** + rôle/qualification active.
- Connecter readiness aux écrans de planification mission.

### Mois 3
- Livrer **logistique simplifiée** + **médical TCCC-lite**.
- Ajouter tableau RETEX (indicateurs mission, délais, incidents, enseignements).

---

## 7) Indicateurs de succès (réalisme mesurable)

- % de missions préparées avec OPORD complet.
- % de missions clôturées avec AAR validé.
- Taux de couverture des rôles critiques qualifiés avant départ.
- Écart readiness planifiée vs readiness réelle au départ.
- Délai moyen de production d’un RETEX après mission.
- Taux d’actions correctives fermées sous 30 jours.

---

## 8) Conclusion

Le projet est déjà mature côté couverture fonctionnelle. Pour passer un cap « MILSIM réaliste », il faut prioriser la **profondeur opérationnelle** : readiness, chaîne mission complète, spécialisation de rôle, logistique, médical, discipline C2 et RETEX. Ces ajouts augmentent la crédibilité, l’immersion et la valeur d’entraînement sans nécessité de rupture architecturale.
