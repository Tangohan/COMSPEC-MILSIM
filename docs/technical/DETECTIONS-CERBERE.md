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
