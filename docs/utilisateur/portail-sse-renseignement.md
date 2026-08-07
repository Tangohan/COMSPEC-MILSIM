# Portail de renseignement interpersonnel (SSE)

Le portail classifié Athena regroupe les **dossiers d’affaire**, les **fiches personnes** de scénario, les **croisements** avec les listes de surveillance et l’**export PDF**. Il est distinct de la carte tactique.

## Accès

1. Ouvrez **Portail SSE** (lien depuis la carte Athena, le guide mod, ou le back-office « Renseignement »).
2. Saisissez le **code temporaire** délivré par le commandement.
3. **Membre** : compte connecté + droit d’accès renseignement + code membre.
4. **Invité** : code invité seul — consultation limitée au portail SSE (pas de carte tactique).

La session est limitée dans le temps. Toute consultation est journalisée. Un bandeau permanent rappelle la diffusion restreinte.

## Dossiers d’affaire

- Création réservée aux rôles habilités à gérer les dossiers.
- Référence lisible générée (ex. `SSE-2026-0042`).
- Classification : Diffusion interne, Encadrement, Confidentiel, Diffusion très restreinte.
- Contenu : personnes rattachées, notes, preuves, sites liés, comptes rendus, corrélations.
- **Code secret du dossier** (optionnel) : distinct du code d’accès au portail ; demandé à l’ouverture pour les lecteurs non commandement.

## Personnes

Les fiches proviennent du terminal terrain (SEEK / Overwatch). Ce sont des **identités de scénario** : elles ne sont jamais fusionnées avec les dossiers RH des membres.

## Croisements

Le commandement peut tenir une **liste de surveillance** (nom, prénom, alias, niveau). Le portail propose des correspondances **indicatives** avec les fiches terrain. Une correspondance n’est pas une identification : elle appelle une confirmation humaine.

## Codes d’accès (commandement)

Réservé aux rôles autorisés à délivrer les accès :

- type membre ou invité ;
- durée de validité du code et durée de session ;
- nombre d’usages ;
- dossier cible optionnel ;
- niveau de lecture porté par le code ;
- révocation immédiate.

Le code en clair n’est affiché **qu’une seule fois** à la génération.

## Export PDF

Les opérateurs autorisés à exporter produisent une synthèse classifiée du dossier (personnes, notes, preuves) portant le bandeau de diffusion restreinte.

## Configuration communauté

Après mise à jour, l’entrée **Portail de renseignement classifié** (`SSE_PORTAL_V1`) apparaît dans les mises à jour de configuration. Les nouvelles communautés sont déjà marquées comme prêtes. L’écran de délivrance des codes est accessible via le back-office renseignement.
