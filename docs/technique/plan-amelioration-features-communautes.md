# Plan d’amélioration majeur — Features Communautés (plateforme)

## 1) Périmètre analysé

Ce plan couvre l’expérience “communautés” côté plateforme : pages publiques (`/c/{slug}`), registre, adhésion/multi-appartenance, création de communauté (wizard + paiement), vitrine publique, événements et dynamiques communautaires transverses.

## 2) Cartographie produit actuelle

### 2.1 Découverte et identité publique
- Page publique par communauté (`/c/{slug}`) avec profil public configurable.
- Contact public conditionné par la configuration tenant.
- Variantes de présentation (layout public standard/showcase).

### 2.2 Entrée et appartenance
- Registre des communautés accessibles (`/communities`).
- Résolution par code de communauté (`/community/resolve-code`).
- Bascule de communauté active (`/community/switch`).
- Entrée contextualisée vers forum et enrôlement depuis la page publique.

### 2.3 Création de communauté
- Assistant de création multi-étapes (`/communities/create*`).
- Prévisualisation avant création.
- Support des offres/paiement Stripe (selon configuration).

### 2.4 Engagement communautaire
- Événements membres + RSVP + pointage.
- Flux d’activité tenant (journal communautaire technique disponible).
- Roster public opt-in et vitrine ORBAT publique (selon réglages).

## 3) Constats clés

1. **Parcours de découverte correct**, mais encore orienté “administrateurs déjà convaincus” plutôt que “nouveaux membres à convertir”.
2. **Multi-tenant puissant**, mais manque de personnalisation de l’accueil utilisateur selon son historique de communautés.
3. **Engagement communautaire présent mais morcelé** (événements, messages, forum, recrutement non orchestrés dans une boucle de rétention).
4. **Monétisation existante**, mais différenciation d’offres perfectible via fonctionnalités communautaires premium tangibles.
5. **Potentiel de vitrine publique élevé** (showcase, roster opt-in, unités publiques), sous-exploité côté croissance organique.

## 4) Plan d’amélioration (majeur)

## Axe A — Acquisition et conversion communauté

### Objectif
Augmenter la conversion visiteur → membre/candidat.

### Évolutions proposées
- Templates de pages publiques par “type de communauté” (RP, entraînement, tactique, milsim hardcore).
- Blocs CTA adaptatifs (rejoindre, candidater, contacter, découvrir événements à venir).
- SEO social prêt à l’emploi (métadonnées enrichies, preview image robuste, schema.org).
- Page “comparatif des communautés” pour le registre (filtres avancés, tags, activité récente).

### Gains attendus
- Hausse du trafic qualifié vers les communautés.
- Hausse du taux d’inscription/candidature.

## Axe B — Onboarding membre orienté rétention

### Objectif
Réduire l’attrition pendant les 14 premiers jours.

### Évolutions proposées
- Parcours d’arrivée par rôle (nouveau membre, recruteur, instructeur, cadre).
- Checklist d’intégration personnalisée (profil, forum, premier événement, validation docs).
- Nudges automatiques : rappels contextualisés selon inactivité.
- “Buddy system” (parrain interne) pour les communautés qui l’activent.

### Gains attendus
- Plus de membres actifs semaine 1 et semaine 4.
- Diminution des comptes “inscrits non engagés”.

## Axe C — Hub communautaire unifié

### Objectif
Créer une couche de pilotage social au-dessus des modules existants.

### Évolutions proposées
- Fil communautaire consolidé (événements, recrutements, formations, annonces, highlights forum).
- Notifications prioritaires (action requise, info, succès collectif).
- “Missions de communauté” hebdomadaires (objectifs souples, ex: taux de présence).
- Score d’activité communautaire (privé tenant) + tendances.

### Gains attendus
- Meilleure visibilité des activités clés.
- Augmentation de la participation événementielle.

## Axe D — Features premium différenciantes

### Objectif
Renforcer la proposition de valeur payante sans dégrader le cœur gratuit.

### Évolutions proposées
- Kit branding avancé (domain mapping, thèmes de page publique, blocs visuels premium).
- Analytics communautaires avancées (cohortes d’engagement, funnel candidature→membre actif, rétention 30/90 jours).
- Automatisations communautaires (règles de relance, scénarios onboarding, publication planifiée).
- Espace “saison/campagne” (chapitres d’événements, objectifs, bilans).

### Gains attendus
- Valeur perçue plus claire des plans payants.
- Réduction du churn sur offres pro.

## Axe E — Confiance, sécurité et modération communautaire

### Objectif
Protéger la croissance sans effet de friction excessif.

### Évolutions proposées
- Score de confiance communauté (comportements, signalements, qualité onboarding).
- Outils anti-abus publics (contact throttling, heuristiques anti-spam).
- Paramètres de confidentialité simplifiés (roster, visibilité des unités, données publiques).
- Journal de modération lisible côté responsables communautaires.

## 5) Corrections prioritaires (quick wins)

1. Clarifier le wording des CTA publics (différencier “rejoindre”, “candidater”, “contacter”).
2. Standardiser les états vides dans registre/communautés pour éviter les impasses UX.
3. Ajouter des recommandations automatiques de complétion de profil public communauté.
4. Exposer un mini tableau de bord d’engagement (7/30 jours) dès le back-office.
5. Ajouter des templates de messages de bienvenue par scénario d’entrée.

## 6) Roadmap recommandée

### Lot 1 (0–6 semaines)
- Optimisation conversion des pages publiques (CTA + métadonnées + templates légers).
- Quick wins onboarding membre.
- Mini dashboard engagement communautaire.

### Lot 2 (6–12 semaines)
- Hub communautaire unifié V1.
- Notifications prioritaires.
- Registre avancé avec filtres/tri d’activité.

### Lot 3 (12–20 semaines)
- Features premium avancées (analytics cohortes + automatisations).
- Campagnes/saisons communautaires.
- Score de confiance communauté.

## 7) KPIs de succès

- Taux visite page publique → candidature/adhésion.
- Taux d’activation des nouveaux membres à J7/J30.
- Participation moyenne aux événements par communauté active.
- Rétention mensuelle des communautés payantes.
- Temps moyen de traitement des interactions publiques (contact/candidature).

## 8) Risques et garde-fous

- **Risque**: complexifier l’offre produit.
  **Mitigation**: paliers de fonctionnalités lisibles (core vs premium).
- **Risque**: surcharge notification.
  **Mitigation**: centre de préférences et priorisation stricte.
- **Risque**: pression sur la modération en phase de croissance.
  **Mitigation**: outils anti-abus et workflow de modération assistée.
