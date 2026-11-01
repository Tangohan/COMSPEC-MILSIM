# JTAC — Licence ATH-ACADEMY

Le premier module Academy est un cursus publié, entièrement rédigé en français et en anglais, livré dans `AcademyBuiltInCourses`. Sa clé stable est `jtac-licence-ath-academy`. Depuis la version `2.0` (durée indicative : 180 minutes), il enseigne la procédure JTAC de la communauté pour nos missions Arma 3 : le joueur au sol qui coordonne un appui aérien rapproché piloté par d’autres joueurs. Les termes OTAN restent en anglais. Il s’agit d’une formation de jeu : la licence est une attestation interne, sans valeur de qualification réelle. La version `1.0` (observation, communication et débriefing, désignation d’objectifs hors cursus) reste dans la copie des stagiaires qui l’avaient commencée.

L’accueil connecté affiche ce module avant les formations du LMS natif. Le catalogue, le tableau de bord, les simulateurs et la bibliothèque Academy le proposent également. Son lecteur est `/academy/classroom?course=jtac-licence-ath-academy`. L’accès conserve la connexion, la communauté effective, la formule payante et le module formation autorisé ; aucune nouvelle formule ni intégration de paiement n’est ajoutée.

| Chapitre | Contenu | Questions |
| --- | --- | --- |
| Phase 1 — Contact initial | Prise en charge de la patrouille, menaces immédiates, trafic aérien, check-in (armement, code laser, senseurs, pods, rover), code abort. | 6 |
| Phase 2 — Situation update | Image commune : amis, ennemi, menaces, changements ; faits séparés des suppositions. La source de la communauté ne fixe pas de format : le contenu est présenté comme un guide de jeu. | 6 |
| Phase 3 — Game plan | Types de contrôle 1, 2 et 3, bomb on target / bomb on coordinate, JDAM = type 2, munition, phrases radio « Voodoo 1 ». | 7 |
| Phase 4 — CAS brief (9-line) | Les neuf lignes (IP à au moins 4 km), remarques (danger close, code abort unique), collationnement puis « Continue ». | 8 |
| Phase 5 — Attaque terminale et BDA | Abort, Continue, Continue dry, Clear hot, Cleared to engage ; quatre issues du BDA. | 8 |
| Brevity | Visual / Blind, Tally / No Joy, Contact, Spot, Captured, Winchester, Paveway ; Medevac « Dust Off ». | 8 |

Chaque chapitre comporte les sept étapes : cours théorique, démonstration (échange radio commenté), exercice guidé (avec aide), simulation autonome (cas distinct, sans aide), évaluation (questions à trois choix, dix minutes, seuil de 80 %), débriefing (consigne et critères) et validation des acquis par un instructeur habilité distinct du stagiaire. Quatre supports FR/EN sont joints à chaque chapitre : fiche mémo des cinq phases, carte 9-Line, fiche brevity et grille instructeur. Leur texte est échappé à l’affichage. Les étapes déjà lues peuvent être revues sans modifier la progression ; les aides de relecture sont masquées pendant une tentative d’examen active.

Le cours n’est pas un seed de migration. Sa publication ne nécessite aucune nouvelle table : les tables existantes `academy_courses` et `academy_learners` sont réutilisées. Une inscription explicite stocke le contenu et sa version dans un instantané privé ; les changements ultérieurs du catalogue ne remplacent pas les preuves du stagiaire. Les cours de communauté ne peuvent pas remplacer la clé du module intégré. L’étape `academy_jtac_v2_refresh` (3280) de la file active remplace la copie des seuls inscrits qui n’ont rien commencé ; les parcours entamés, terminés et les licences restent sur leur version. Si le stockage Academy manque, les inscriptions sont désactivées et le LMS natif reste accessible.

Les étapes sont contrôlées séquentiellement sur le serveur. Le navigateur ne choisit ni score, ni échéance, ni étape suivante. Un jeton de tentative et un verrou de ligne empêchent les envois rejoués. Les corrigés ne sont pas inclus dans le formulaire d’examen ; ils deviennent consultables après réussite pour le débriefing et la revue instructeur.

La validation du dernier chapitre émet un identifiant `ATH-ACADEMY-JTAC-…`, la date, l’instructeur et la version du cours, avec un périmètre `simulation`. Ces informations restent dans le dossier du stagiaire et sont imprimables. Ni une simple inscription, ni une réussite au questionnaire, ni une autosignature ne délivre la licence.

Vérifications : `AcademyJtacCourseTest` contrôle les cinq phases et le brevity, les sept étapes FR/EN, les questions, le parcours complet jusqu’à la licence et l’étape de mise à jour ; `AcademyRichCurriculumTest` contrôle les sept étapes, les exercices distincts, le seuil, les expirations et les licences ; `AcademyRichPlayerViewsTest` contrôle les vues et l’absence de corrigé avant l’examen. `AcademyRepositoryTest` réalise un parcours complet avec le vrai contenu sur une base jetable `academy_test` et contrôle les instantanés et l’isolation des comptes/communautés. Les contrôles du disclaimer et des droits payants existants restent applicables.

La remise à vide du lanceur de migrations est indépendante du contenu du cours ; elle est décrite dans [migrations-active-queue.md](migrations-active-queue.md). La version 2.0 ne crée aucune table ; seule l’étape 3280 met à jour, à la demande de l’administrateur, la copie des inscrits non commencés.
