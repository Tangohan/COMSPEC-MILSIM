# Portail de renseignement interpersonnel (SSE)

Le portail classifié Athena regroupe les **dossiers d’affaire**, les **fiches personnes** de scénario, les **croisements** avec les listes de surveillance et l’**export PDF**. Il est distinct de la carte tactique.

## Accès

1. Ouvrez **Portail SSE** (lien depuis la carte Athena, le guide mod, ou le back-office « Accès renseignement »).
2. **Commandement et membres déjà autorisés** : entrée **sans code** — le sas reconnaît le compte et ouvre la session après l’engagement de confidentialité.
3. **Invités et autres** : saisissez le **code temporaire** délivré par le commandement.
4. **Membre avec code** : compte connecté + droit d’accès renseignement + code membre, si le commandement préfère une session limitée dans le temps.
5. **Invité** : code invité seul — consultation limitée au portail SSE (pas de carte tactique).

La session est limitée dans le temps. Toute consultation est journalisée. Un bandeau permanent rappelle la diffusion restreinte.

## Back-office — Accès renseignement

Depuis **ATAK → Accès renseignement**, le commandement :

- délivre et révoque les codes temporaires ;
- voit le niveau de lecture porté par chaque code ;
- ouvre le portail en un clic.

Les rôles et la fiche opérateur complètent ce dispositif pour fixer jusqu’où chacun lit le renseignement classifié.

## Habilitation sur la fiche opérateur

Sur la fiche effectifs (édition staff), le champ **Niveau de diffusion renseignement** fixe le plafond de lecture classifiée de l’opérateur. Ce niveau ne remplace pas l’entrée au portail : les membres passent par leurs droits d’accès, les invités par un code.

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

- type membre habilité ou invité ;
- durée de validité du code et durée de session ;
- nombre d’usages ;
- dossier cible optionnel ;
- niveau de lecture porté par le code ;
- révocation immédiate.

Le code en clair n’est affiché **qu’une seule fois** à la génération.

## Export PDF

Les opérateurs autorisés à exporter produisent une synthèse classifiée du dossier (personnes, notes, preuves) portant le bandeau de diffusion restreinte.

## Configuration communauté

Après mise à jour, l’entrée **Portail de renseignement classifié** (`SSE_PORTAL_V1`) apparaît dans les mises à jour de configuration. Les nouvelles communautés sont déjà marquées comme prêtes. L’écran de gestion des accès est accessible via le back-office renseignement.
