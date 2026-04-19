# Plan d’implémentation P2 — Doctrine versionnée, XP opérateur, wargaming léger et AAR IA

_Date : 19 avril 2026_

## Objectif

Transformer les blocs **P2.1 à P2.4** en plan de livraison exécutable, avec :
- modèle de données,
- API,
- règles métier,
- UI/UX,
- garde-fous (RBAC, audit, validation humaine),
- critères d’acceptation.

---

## P2.1 — Doctrine et SOP versionnées

### Portée fonctionnelle

Chaque communauté dispose d’un **dossier doctrine** structuré :
- SOP officielles,
- checklists opérationnelles,
- formats de rapport (SITREP, MEDEVAC/CASEVAC, AAR).

Chaque document doctrine possède :
- `version` (ex: `1.3.0`),
- `effective_at` (date d’effet),
- statut (`draft`, `approved`, `active`, `superseded`, `archived`),
- accusés de lecture par utilisateur ciblé.

### Modèle de données (proposé)

- `doctrine_documents` : identité, type, statut, version courante, dates,
- `doctrine_document_versions` : contenu versionné, hash d’intégrité, auteur,
- `doctrine_acknowledgements` : utilisateur, version, date lecture, preuve (IP/user-agent optionnel),
- `doctrine_applicability_rules` : cibles (rôle, unité, qualification).

### Règles métier clés

1. Une version ne peut passer à `active` que si elle est `approved`.
2. `effective_at` future = publication planifiée (activation automatique).
3. Si une version active est remplacée, les accusés de lecture sont réinitialisés pour les populations concernées.
4. Les ordres/missions peuvent référencer une version doctrine figée pour audit historique.

### API (exemples)

- `GET /api/operations/doctrine/documents`
- `POST /api/operations/doctrine/documents`
- `POST /api/operations/doctrine/documents/{id}/versions`
- `POST /api/operations/doctrine/versions/{versionId}/approve`
- `POST /api/operations/doctrine/versions/{versionId}/activate`
- `POST /api/operations/doctrine/versions/{versionId}/ack`

### UI/UX

- Vue “Bibliothèque doctrine” avec filtres (type, statut, version active).
- Badge de conformité lecture (`ACK 78%`) visible côté commandement.
- Bannière de rappel pour opérateurs non conformes sur version active.

### DoD / Critères d’acceptation

- Historique complet des versions consultable.
- Activation planifiée testée (date d’effet).
- Export CSV des accusés de lecture par document/version.
- Journal d’audit pour approbation/activation.

---

## P2.2 — Profil d’expérience opérateur (XP réaliste)

### Portée fonctionnelle

Construire un profil d’expérience basé sur l’historique missionnel, sans mécanique arcade.

Score d’expérience pondéré par :
- type de mission,
- rôle tenu,
- performance de mission,
- discipline (ponctualité, conformité doctrine, incidents).

Progression plafonnée par qualifications validées :
- une qualification absente bloque certains paliers,
- l’XP seule n’accorde pas d’autorisations critiques.

### Modèle de données (proposé)

- `operator_experience_profiles` : score agrégé, indice discipline, niveau de confiance,
- `operator_experience_events` : événements source (mission, rôle, incident, distinction),
- `operator_progression_caps` : plafonds par filière/rôle selon qualifications,
- `operator_role_readiness` : statut d’éligibilité opérationnelle par rôle.

### Règles métier clés

1. XP calculée par lot nocturne + recalcul manuel possible (admin).
2. Poids mission paramétrables par communauté (tenant settings).
3. Les incidents disciplinaires majeurs appliquent un malus borné dans le temps.
4. Toute recommandation de promotion reste “assistée” (validation humaine obligatoire).

### UI/UX

- Fiche opérateur : timeline missionnelle + synthèse discipline.
- Affichage explicite des plafonds (“Cap atteint : qualification JTAC requise”).
- Segment “preuve” cliquable vers mission/événement source (traçabilité).

### DoD / Critères d’acceptation

- Recalcul idempotent d’un même intervalle produit le même score.
- Aucun déverrouillage de rôle critique sans qualification active.
- Historique des corrections manuelles journalisé et justifié.

---

## P2.3 — Simulateur de préparation mission (wargaming léger)

### Portée fonctionnelle

Avant mission, fournir un score de préparation et des recommandations automatiques à partir de :
- risque mission,
- disponibilité réelle,
- logistique,
- couverture des rôles critiques.

### Entrées de calcul

- Paramètres mission : type, zone, fenêtre horaire, météo/visibilité,
- Ressources humaines : disponibilité + readiness + qualifications,
- Logistique : état des stocks et délais de ravitaillement,
- Contraintes doctrine : exigences minimales par type mission.

### Sorties

- `prep_score` 0–100,
- sous-scores : RH, logistique, commandement/C2, médical,
- recommandations priorisées (impact × effort),
- indicateur `go/no-go` assisté.

### Règles métier clés

1. Calcul transparent : chaque recommandation expose les causes.
2. Pas de blocage dur par défaut : décision finale conservée au commandement.
3. Option tenant : imposer blocage “no-go” sous seuil configurable.

### API (exemples)

- `POST /api/operations/missions/{id}/prep-simulate`
- `GET /api/operations/missions/{id}/prep-history`
- `POST /api/operations/missions/{id}/prep-override` (motif obligatoire)

### UI/UX

- Carte de score pré-mission (jauge + sous-scores).
- Liste de recommandations triée (priorité haute/moyenne/faible).
- Diff “avant/après” après correction des points bloquants.

### DoD / Critères d’acceptation

- Simulations reproductibles à paramètres identiques.
- Affichage clair des hypothèses de calcul.
- Journal d’override avec motif, auteur, horodatage UTC + local.

---

## P2.4 — AAR semi-assisté par IA

### Portée fonctionnelle

Générer un brouillon RETEX à partir du journal tactique et détecter les écarts doctrine vs exécution, avec validation humaine obligatoire avant publication.

### Pipeline proposé

1. Ingestion des événements mission (`tactical_event_log`, SITREP, incidents),
2. Structuration chronologique (timeline),
3. Génération d’un brouillon AAR (faits, décisions, résultats, enseignements),
4. Contrôle d’écarts doctrine (règles déclaratives + heuristiques),
5. Validation/édition humaine,
6. Publication et suivi des actions correctives.

### Garde-fous IA

- L’IA ne publie jamais automatiquement.
- Chaque écart signalé référence les preuves (événement source + SOP).
- Marquage explicite “hypothèse IA” vs “fait observé”.
- Journal complet des modifications humaines post-génération.

### API (exemples)

- `POST /api/operations/missions/{id}/aar/draft`
- `GET /api/operations/missions/{id}/aar/draft`
- `POST /api/operations/missions/{id}/aar/validate`
- `POST /api/operations/missions/{id}/aar/publish`

### UI/UX

- Éditeur AAR en 2 colonnes : “brouillon IA” / “version validée”.
- Cartouche “écarts doctrine” avec niveau de confiance.
- Workflow de validation en 2 étapes (rédacteur + approbateur).

### DoD / Critères d’acceptation

- Aucun AAR publié sans signature humaine explicite.
- Traçabilité de chaque section vers événement source.
- Mesure du temps moyen de production RETEX avant/après assistance IA.

---

## 4) Détails UI/UX immersion — implémentation ciblée

### 4.1 Micro-détails « commandement »

- Horodatage double (`UTC` + fuseau local utilisateur) sur événements critiques.
- Bloc chaîne hiérarchique systématique sur fiches mission.
- Statuts unitaires harmonisés : `READY`, `PARTIAL`, `NMC`.

**Technique**
- Introduire un composant UI unique `CriticalTimestamp`.
- Introduire enum partagé des statuts unitaires côté backend/frontend.

### 4.2 Cohérence terminologique

- Dictionnaire de termes normalisés (mission, ordre, SITREP, CASEVAC/MEDEVAC, ROE).
- Glossaire in-app contextuel (help inline + page dédiée onboarding).

**Technique**
- Fichier de terminologie versionné par locale.
- Vérification semi-automatique des libellés via tests snapshot UI.

### 4.3 Densité informationnelle contrôlée

- Mode “Compact Command” : tables denses, filtres persistants, raccourcis clavier.
- Mode “Guidé Novice” : parcours simplifié, infobulles progressives.

**Technique**
- Feature flags par utilisateur.
- Préférences UI persistées par profil.

### 4.4 Modes d’affichage tactique

- Thème “briefing nuit” (contraste adapté projection/ops room).
- Couleurs d’état accessibles daltonisme.

**Technique**
- Palette de tokens sémantiques (`status-success`, `status-warning`, `status-danger`).
- Vérification contraste WCAG AA sur composants critiques.

---

## Roadmap d’exécution recommandée (12 semaines)

### Lot 1 (semaines 1–3)
- P2.1 socle doctrine versionnée + accusés de lecture + audit.
- UI bibliothèque doctrine + conformité lecture.

### Lot 2 (semaines 4–6)
- P2.2 profil XP réaliste + plafonds qualification.
- Timeline opérateur + traçabilité vers missions.

### Lot 3 (semaines 7–9)
- P2.3 simulateur préparation mission + recommandations.
- Vue score pré-mission + override journalisé.

### Lot 4 (semaines 10–12)
- P2.4 AAR IA semi-assisté + workflow validation humaine.
- Mesures de qualité RETEX (délais, conformité doctrine).

---

## KPI de suivi

- Taux d’accusé de lecture doctrine active (J+7 / J+30).
- % d’opérateurs avec rôle critique éligible (qualification + readiness).
- Écart moyen prep-score prévisionnel vs incident réel mission.
- Délai médian de publication AAR.
- % d’écarts doctrine confirmés après validation humaine.

---

## Risques & mitigations

- **Sur-automatisation IA** → validation humaine obligatoire + audit.
- **Complexité perçue opérateur** → mode guidé novice + progressive disclosure.
- **Biais de scoring XP** → poids configurables et revues périodiques par commandement.
- **Dette UX multi-modes** → design tokens et composants partagés dès le lot 1.
