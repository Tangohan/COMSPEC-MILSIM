# Brief — Parcours de formation SSE (LMS Athena)

Document à donner tel quel à un agent de code / design. Il décrit **ce qu'il faut produire**,
**où**, et **selon quelles conventions du dépôt**. Le contenu pédagogique est à rédiger par
l'agent à partir des sources listées.

---

## Prompt à copier

> Tu travailles dans le dépôt `COMSPEC-MILSIM` (Athena — SaaS RH tactique MILSIM Arma 3,
> PHP 8.4 sans framework, vues PHP natives, pas de build front). Ta tâche : créer le
> **parcours de formation « Renseignement interpersonnel (SSE) »** dans le LMS existant.
>
> **Ne pars pas d'une page blanche : reproduis le parcours ATAK déjà livré.**
> Le modèle de référence est `bootstrap/training_atak_course_seed.php` (slug
> `parcours-atak-web-jeu`). Lis-le en entier avant d'écrire une ligne : il définit la
> structure exacte attendue (fonctions `*_course_exists`, `*_sync_course_cover`,
> `*_module_specs`, `*_module_objectives_json`, `*_seed_quiz_questions_for_module`,
> decks par module), l'idempotence par `tenant_id` + `slug`, et le ton éditorial.
>
> **Livrable 1 — le seed.** `bootstrap/training_sse_course_seed.php`, slug
> `parcours-sse-renseignement`. Même signature, même idempotence, même style de
> `module_specs` (`title`, `subtitle`, `minutes`, `module_description`,
> `module_learning_objectives`, `deck`, `lesson_summary`, `recap_html`). Enregistre-le
> dans `run-migrations.php` au même endroit que le seed ATAK.
>
> **Livrable 2 — le contenu.** Six modules, ~25-35 min chacun :
> 1. *Cadre SSE* — ce qu'est l'exploitation de site et le renseignement interpersonnel,
>    qui fait quoi (opérateur, chef d'élément, TOC), et la règle de séparation stricte :
>    les fiches SSE sont des **identités de scénario**, jamais le dossier RH d'un membre.
> 2. *Le terminal SEEK* — objet à emporter, ouverture depuis le menu ATAK ou l'interaction
>    ACE sur une personne, les six sections du terminal, ce qui est prérempli
>    automatiquement et ce qui reste à saisir.
> 3. *Relever une identité* — nom / prénom / alias, statut (civil, combattant, détenu,
>    personne prioritaire), circonstances, signes distinctifs, déclarations et niveau de
>    confiance. Insister sur : un alias vaut mieux qu'un nom inventé.
> 4. *Constat de terrain et biométrie* — ce que le terminal reprend d'ACE Medical, ce que
>    la biométrie simule (empreintes, iris, ADN : qualité, référence de laboratoire), et
>    ce qu'elle ne fait pas. **Point pédagogique central : aucune reconnaissance réelle,
>    aucun verdict d'identification sur le terrain.**
> 5. *Classement et procès-verbal* — code dossier SSE fourni par le commandement,
>    signature par l'ATAK, ce que vaut une fiche non signée et non classée.
> 6. *Exploitation par le TOC* — le portail `/atak/sse` : dossiers, registre des personnes,
>    croisement listes de surveillance, codes d'accès, export PDF. Un score de similarité
>    n'est pas une identification.
>
> **Livrable 3 — les quiz.** 4 à 6 questions par module, sur le modèle de
> `training_atak_seed_quiz_questions_for_module`. Au moins une question par module doit
> porter sur une limite ou une règle, pas seulement sur une manipulation.
>
> **Sources à lire pour le contenu** (ne rien inventer qui les contredise) :
> - `mod/UptoDate/docs/terminal-sse-renseignement.md` — vision produit, catalogue, hors-scope
> - `mod/UptoDate/docs/contrat-api-sse.md` — champs, libellés métier, tables
> - `mod/UptoDate/docs/plan-sse-ace-medical.md` — arbitrages et limites assumées
> - `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp` — les
>   sections réelles du terminal, dans l'ordre
> - `views/atak/sse/*.php` — les écrans réels du portail
>
> **Contraintes rédactionnelles, non négociables :**
> - **Français métier à 100 %.** Jamais un code technique, un slug ou une valeur brute de
>   base de données à l'écran. On écrit « Détenu », pas `detenu`.
> - **Aucune clé, aucun réglage sensible, aucun nom d'endpoint** dans le contenu apprenant.
>   Le parcours ATAK existant montre où placer la limite.
> - Le ton est celui d'une instruction militaire : phrases courtes, impératif, pas de
>   marketing, pas d'emphase décorative.
> - Chaque module se termine par un `recap_html` « À retenir » de 2-4 phrases.
>
> **Design.** N'invente aucun composant : les gabarits de leçon, deck et quiz existent
> (`views/training/lesson.php`, `views/training/partials/`, `views/training/quiz.php`) et
> le parcours ATAK les utilise déjà. Si un visuel manque, réutilise les jetons CSS de
> `public/assets/css/sse_portal.css` (`--green`, `--amber`, `--mono`, `.sse-gauge`,
> `.sse-record-block`) pour rester cohérent avec le portail SSE.
>
> **Vérifications avant de rendre :** `php -l` sur chaque fichier touché ; le seed doit
> pouvoir être joué deux fois de suite sans créer de doublon ; aucune chaîne technique
> visible dans les libellés apprenants.

---

## Pourquoi ce cadrage

Le LMS a déjà un parcours ATAK complet et abouti. Le risque, en confiant « fais un parcours
SSE » à un agent, est qu'il réinvente une structure parallèle : autre format de modules,
autre gabarit de leçon, autre ton. Le brief impose donc la reprise du seed existant comme
gabarit, et liste les sources de contenu pour éviter qu'il invente des fonctionnalités que
le mod n'a pas — en particulier une reconnaissance biométrique réelle, qui est un hors-scope
explicite du produit.
