# JTAC — Licence ATH-ACADEMY

Le premier module Academy est un cursus publié, entièrement rédigé en français et en anglais, livré dans `AcademyBuiltInCourses`. Sa clé stable est `jtac-licence-ath-academy`, sa version initiale `1.0` et sa durée indicative de 90 minutes. Il porte sur l’observation, la communication, les facteurs humains, l’incertitude et le débriefing dans des scènes MILSIM fictives. La licence est une attestation interne de pratique en simulation, sans qualification officielle.

L’accueil connecté affiche ce module avant les formations du LMS natif. Le catalogue, le tableau de bord, les simulateurs et la bibliothèque Academy le proposent également. Son lecteur est `/academy/classroom?course=jtac-licence-ath-academy`. L’accès conserve la connexion, la communauté effective, la formule payante et le module formation autorisé ; aucune nouvelle formule ni intégration de paiement n’est ajoutée.

| Étape | Contenu et preuve attendue |
| --- | --- |
| Cours théorique | Dix leçons : cadre de simulation, faits et hypothèses, incertitude, facteurs humains, messages clairs, reformulation, respect des personnes, incident technique, décision et traçabilité, débriefing. |
| Démonstration | Cas Aster commenté, reliant les faits, limites, demandes de clarification et conditions de reprise. |
| Exercice guidé | Cas Boréal : versions contradictoires d’une consigne, démarche expliquée, aide et retour sur la réponse. |
| Simulation autonome | Cas Canopée distinct : capture ancienne, sources dupliquées et surcharge ; choix autonome de la décision et de la trace. |
| Évaluation | Dix situations, quinze minutes, seuil de 80 %. Note, réponses, expiration et tentatives conservées côté serveur. |
| Débriefing | Réflexion personnelle et six critères : faits, raisonnement, difficultés et amélioration vérifiable. |
| Validation des acquis | Revue du résultat, du débriefing et de six critères par un instructeur habilité distinct du stagiaire. |

Six supports FR/EN sont intégrés et consultables sans URL externe : fiche de communication, glossaire, schéma de raisonnement, diapositives de synthèse, modèle de journal et grille instructeur. Leur texte est échappé à l’affichage. Les étapes déjà lues peuvent être revues sans modifier la progression ; les aides de relecture sont masquées pendant une tentative d’examen active.

Le cours n’est pas un seed de migration. Sa publication ne nécessite aucune nouvelle table : les tables existantes `academy_courses` et `academy_learners` sont réutilisées. Une inscription explicite stocke le contenu et sa version dans un instantané privé ; les changements ultérieurs du catalogue ne remplacent pas les preuves du stagiaire. Les cours de communauté ne peuvent pas remplacer la clé du module intégré. Si le stockage Academy manque, les inscriptions sont désactivées et le LMS natif reste accessible.

Les étapes sont contrôlées séquentiellement sur le serveur. Le navigateur ne choisit ni score, ni échéance, ni étape suivante. Un jeton de tentative et un verrou de ligne empêchent les envois rejoués. Les corrigés ne sont pas inclus dans le formulaire d’examen ; ils deviennent consultables après réussite pour le débriefing et la revue instructeur.

La validation finale émet un identifiant `ATH-ACADEMY-JTAC-…`, la date, l’instructeur et la version du cours, avec un périmètre `simulation`. Ces informations restent dans le dossier du stagiaire et sont imprimables. Ni une simple inscription, ni une réussite au questionnaire, ni une autosignature ne délivre la licence.

Vérifications : `AcademyRichCurriculumTest` contrôle les sept étapes, les exercices distincts, le seuil, les expirations et les licences ; `AcademyRichPlayerViewsTest` contrôle les vues et l’absence de corrigé avant l’examen. `AcademyRepositoryTest` réalise un parcours complet avec le vrai contenu sur une base jetable `academy_test` et contrôle les instantanés et l’isolation des comptes/communautés. Les contrôles du disclaimer et des droits payants existants restent applicables.

La remise à vide du lanceur de migrations est indépendante du contenu du cours ; elle est décrite dans [migrations-active-queue.md](migrations-active-queue.md). Aucun déploiement ni changement de données de production n’est effectué par cette PR.
