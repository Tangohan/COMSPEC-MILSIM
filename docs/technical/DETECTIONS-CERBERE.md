# Détections Cerbère (propositions automatiques sur les remontées)

## Existant (avant le lexique 2026-11-16)

**Ce qui est lu.** `IntelIntakeAdvisor::proposals()` (app/Services/Intel) lisait, pour une remontée trouvée par
`IntelIntakeService::find()`, les colonnes `title`, `body`, `summary`, `details`, `remarks`, `place_label`,
`location_description` : titre et corps d'une fiche de renseignement (`sse_field_notes`), résumé, détails et remarques d'un
compte rendu (`atak_tactical_reports`). Le texte était replié (minuscules, quelques accents) par `IntelIntakeAdvisor::fold()`.

**Lexiques.** Trois tableaux en dur dans la classe :
- `KIND_WORDS` : type de fiche FRT, FRC, FRA (au moins deux mots trouvés) ;
- `THEME_WORDS` : mots par thème (TERROR, CBRNE, ARMEMENT, PERSON, LOGIST, COMMS, INFRA, MOUV, SECUR, CIVIL) ;
- `URGENT_WORDS` : mots « critical » et « high » qui faisaient proposer l'urgence Critique ou Urgent.

**Règle unique : sous-chaîne.** `str_contains($texte, $mot)` : un mot était trouvé n'importe où, même au milieu d'un autre
mot. « otage » (TERROR et urgence critique) était donc trouvé dans « sabotage », « arme » dans « armée », « pont » dans
« pontage », « civil » dans « civilisation ». Aucune négation (« pas d'otage » proposait l'urgence Critique), aucune
exclusion, aucun poids, aucun identifiant de règle. L'urgence venait des mêmes mots que les thèmes.

**Où sont les propositions.** Nulle part : elles étaient recalculées à chaque affichage et jamais stockées. Une proposition
écartée revenait à chaque visite ; aucune statistique n'existait.

**Explication.** Une seule ligne : « Le texte parle de : otage. » (les trois premiers mots trouvés).

**Application.** Le bouton « Appliquer » envoie la même action que la saisie à la main (`type`, `themes`, `edit` de
`IntelIntakeDesk::perform`), journalisée dans le fil de la remontée. Pas de rejet possible.

**Qui les voit.** Les traitants des remontées (droit `treat`) : back-office « Remontées » (`views/admin/intel/intake_show.php`,
toutes propositions) et onglet « Suivi » d'une fiche au bureau SSE (`views/atak/sse/_intake_suivi.php`, sans l'urgence, qui
demande le droit « modifier les données »).

**Autres traitements « Cerbère » sans lecture de texte libre.** Cerbère-Liaison, -Validation, -Corrélation, -Détection et
-Création (`App\Support\SseCerbere`) relient des identifiants (papiers, clés biométriques, codes de dossier) : pas de mots-clés.
Cerbère-Tri (`SseTriageRules`, étape 4740) filtre « À trier » avec un mot du titre choisi par la communauté (`mb_stripos`,
sous-chaîne voulue : c'est un filtre que l'utilisateur écrit lui-même, pas une détection).

## Maintenant : Cerbère-Lecture (lexique 2026-11-16.1)

**Moteur.** `App\Services\Sse\Detection\SseDetectionEngine`, lexique en données dans `config/sse_detection_lexicon.php`
(règles `R-xx` thèmes, `U-xx` urgence, `K-xx` type de fiche : poids, explication, termes, exclusions, contexte requis, types
de fiche attendus). Textes lus : titre (résumé d'un compte rendu), corps, remarques, lieu (`SseDetectionService::segments`).

- **Mots entiers** (`SseTextNormalizer`) : minuscules, accents et ligatures repliés, ponctuation, apostrophes droites ou
  typographiques et espaces insécables séparent les mots. « otage » n'est jamais trouvé dans « sabotage ».
- **Formes déclarées** : pluriel simple -s/-x automatique, suffixes `blessé(e|es)`, racine `surveill*` (jamais un faux ami
  de la liste `false_friends`), expressions (« prise d’otage »). Un passage couvert par une exclusion de la règle est écarté.
- **Contexte** dans la même phrase : négation (« pas de », « aucun »…, ×0,4), hypothèse ou exercice (×0,45), formule prudente
  (« pouvant », « possible »…, ×0,8). Négations et hypothèses ne comptent jamais pour l'urgence.
- **Confiance** : poids du meilleur terme × contexte, + termes distincts, répétitions, titre, type de fiche attendu, autres
  règles du même thème. ≥ 55 % : proposition montrée ; 30–54 % : repliée « À vérifier » ; < 30 % : rien.
- **Urgence** : seules les règles U-xx comptent. « Critique » exige un signal immédiat (imminent, en cours, sous le feu…) et un
  deuxième signal distinct ; sinon « Urgent ». Appliquer une urgence Critique demande de cocher « J’ai vérifié le texte ».

**Explication.** Carte « Détecté : « passage » (contexte) · règle R-14 · confiance 58 % » avec l'extrait surligné ; fenêtre
« Pourquoi ? » : règle, termes trouvés, exclusions vérifiées, faux amis écartés, calcul, lien vers le réglage
(`views/partials/cerbere_proposals.php`, back-office et onglet Suivi de la fiche au bureau SSE).

**Retour d'expérience.** Appliquer (action habituelle), Ignorer, Mauvaise détection (motif obligatoire) : action `cerbere` de
`IntelIntakeDesk` (droit `treat`, jeton vérifié par les contrôleurs). Gardés dans `sse_detection_decisions` (une ligne par
remontée, cible, valeur ; une proposition appliquée n'est jamais modifiée) et journalisés dans `sse_audit_log`
(`cerbere.lecture.applied|ignored|rejected|withdrawn`). Une proposition ignorée ou rejetée ne revient pas.

**Gestion.** Bureau SSE › Analyse › « Détections Cerbère » (`atak/sse/detections`, droits de « Gestion des fiches » :
lecture pour voir et tester, « gérer » pour régler) : règles par thème, état, poids, termes, exclusions, contexte, déclenchements
sur 30 jours (moteur rejoué sur les remontées récentes), décisions, taux de rejet (règle signalée dès 40 % avec 3 rejets),
réglage en fenêtre (`sse_detection_rule_settings`), « Rétablir le lexique livré », historique, « Testez un texte ».
Aucun apprentissage automatique : seules des statistiques, et les réglages faits par une personne.

**Base.** Étape `sse_detection_rules` (`database/steps/4890_sse_detection_rules.php`, retour arrière
`migrations/rollback/sse_detection_rules_down.sql`). Sans elle : lexique livré, propositions applicables, retours non gardés.

**Re-détection.** `php tools/sse-redetect.php` (simulation) puis `--apply` : sur les remontées ouvertes, les propositions de
l'ancienne lecture que le nouveau moteur ne justifie plus sont notées « withdrawn » avec leur motif et journalisées. Aucune
remontée modifiée, aucune décision humaine touchée, idempotent (`--tenant=`, `--limit=`).
