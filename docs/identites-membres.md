# Identités des membres : qui s'affiche sous quel nom

Un membre porte plusieurs noms (prénom et nom, indicatif, nom du compte, trigramme, surnom, matricules). Depuis cette
règle, **un seul point d'affichage** décide lequel est montré : `App\Support\MemberIdentity`
(`member_identity()`, `member_name()`, `member_label()`). Les écrans n'ont plus le droit de fabriquer un nom à la main
(`display_name ?: callsign ?: ...`).

## Ordre de priorité du nom affiché

| Rang | Source | Colonne | Exemple |
|---|---|---|---|
| 1 | Prénom et nom d'état civil du personnage, au format de la communauté (« Prénom N. » en liste, « Prénom NOM » en fiche) | `user_profiles.first_name`, `user_profiles.last_name` | Damian S. / Damian STONES |
| 2 | Indicatif | `users.callsign` (`user_community_profiles.callsign` s'il est renseigné pour la communauté) | YB1 |
| 3 | Nom du compte (jamais une adresse e-mail) | `users.display_name` (`user_community_profiles.display_name` s'il existe) | dami#4242 |
| 4 | Libellé de repli | `MemberName::FALLBACK` | Membre |

Le **nom du personnage** (`personnel_profiles.character_name`, ancien champ) ne compte plus nulle part dans cette règle.
La colonne reste en base (aucune donnée supprimée) et continue d'être tenue égale à « Prénom Nom » à l'enregistrement du
dossier, pour les recherches et les doublons ; plus aucun écran ne l'affiche comme nom.

L'identité légale du joueur (`user_legal_identities`) n'est **jamais** un nom d'affichage : le prénom et le nom viennent
uniquement de `user_profiles`. Les listes SQL qui joignent `user_legal_identities` voient leurs prénom et nom retirés
avant l'affichage (`MemberNameService::withIdentities()`, `MemberIdentity::displayForUser()`).

## Contextes

| Contexte | Où | Nom principal |
|---|---|---|
| `list` | listes, tableurs, annuaire, espaces serrés | ordre ci-dessus, format « liste » de la communauté |
| `card` | fiche, carte de membre | ordre ci-dessus, format « fiche » de la communauté |
| `c2` | carte tactique, ATAK, ordres, données du jeu | **indicatif d'abord**, puis nom abrégé d'état civil, puis nom du compte, puis « Membre » |

Les contrats `/api/atak` et `/api/game` ne passent pas par cette règle et sont inchangés. Les identités SSE, les
couvertures et les papiers de jeu de rôle ne sont pas concernés.

## Valeur renvoyée

`MemberIdentity::display(array $row, string $context, ?int $tenantId, bool $allowGrade, ?array $settings)` renvoie :

- `primary` : le nom principal ;
- `secondary` : la ligne secondaire, une seule fonction pour tous les écrans : grade (s'il n'est pas déjà dans le nom),
  indicatif, trigramme et, en fiche, nom du compte. Jamais une valeur déjà écrite dans le nom ni l'e-mail ;
- `callsign`, `trigram` : valeurs seules (trigramme vide si la communauté ne les utilise pas) ;
- `source` : `civil`, `callsign`, `account`, `fallback` ou `masked`.

Tout est du **texte brut** : l'échappement HTML reste à la charge de la vue.

**Anonymisation** : une ligne `anonymized` (membre masqué pour le lecteur, voir `ConfidentialDisplay`) ne renvoie que le
libellé de remplacement ; ni indicatif, ni trigramme, ni ligne secondaire.

## Champs, stockage et affichage

| Champ (libellé joueur) | Stockage | Modifié dans | S'affiche |
|---|---|---|---|
| Prénom, Nom (« État civil du personnage ») | `user_profiles.first_name`, `last_name` | fiche membre > État civil du personnage ; Ma fiche > Modifier ; enrôlement | partout : fiche, annuaire, effectifs, JNET, messagerie, notifications, e-mails |
| Indicatif | `users.callsign`, `personnel_profiles.callsign` | fiche, Ma fiche, enrôlement | carte tactique, ATAK, jeu ; remplace le nom si prénom et nom manquent |
| Indicatifs supplémentaires | `personnel_profiles.extra_callsigns_json` | Ma fiche | alias radio, jamais comme nom |
| Nom du compte | `users.display_name` | compte, inscription | dernier recours avant « Membre » ; en fiche, ligne secondaire |
| Trigramme | `member_trigrams` (par communauté) | fiche, Ma fiche, Organisation > Noms et trigrammes | paraphes, organigramme, format « trigramme » si la communauté l'a choisi |
| Surnom | `personnel_profiles.nickname_primary` | fiche, Ma fiche | fiche seulement ; ne remplace jamais le nom |
| Nom du personnage (ancien champ) | `personnel_profiles.character_name` | plus de formulaire | plus affiché ; conservé en base |
| Identité légale | `user_legal_identities` | compte | jamais comme nom ; données personnelles |
| Numéro de membre (ATH-xxxxx) | `users.athena_identifier` | automatique | fiche, papiers |
| Matricule d'unité, matricule interne | code d'unité, `personnel_profiles.matricule_internal` | gestion | fiche, tableurs |

## Contrôle de la qualité des données

`php tools/audit-report-identites.php [--tenant=ID] [--format=text|csv|json]` (lecture seule) liste les membres actifs
sans prénom ni nom, ceux qui n'ont qu'un des deux, les noms de compte qui sont des e-mails et les écarts avec l'identité
légale (sans jamais l'afficher). Les corrections se font depuis la fiche du membre.

## Identité officielle (SSE) : elle suit l'état civil

L'identité civile la plus ancienne d'un membre dans le registre SSE (`sse_identities`, `kind = 'civil'`, `user_id`) est son identité
officielle. Son prénom et son nom sont ceux de `user_profiles` ; sa date de naissance est celle du personnage
(`user_profiles.character_birth_date`, jamais `birth_date`, qui est la date réelle du joueur) ; son lieu de naissance est celui du
dossier du personnage. Elle est alignée à chaque changement de prénom ou de nom, par le bouton « Aligner sur l'état civil » ou par
`php tools/sse-sync-member-identities.php` (simulation par défaut, `--apply`, `--tenant`, `--limit`, `--create-missing`). Les
couvertures, alias et faux, les sujets non membres et les identités marquées « nom volontairement différent » ne sont jamais touchés ;
les anciennes valeurs restent dans l'historique des identités (restaurables).

## Écrans qui ne passent pas (encore) par la règle

Voir le corps de la PR : ils sont listés avec la raison (C2, SSE, contrats du mod, noms sans identifiant de membre).
