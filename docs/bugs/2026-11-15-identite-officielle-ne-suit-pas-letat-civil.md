# L'identité officielle d'un membre ne suit pas son état civil

## Contexte

Registre des papiers du portail SSE (`/atak/sse/papiers`) : le papier officiel émis pour un membre affichait un titulaire
(« GEORGIOU Yannis », USA, « Délivré officiellement ») et une naissance qui n'étaient pas ceux de son état civil de personnage
(prénom et nom de `user_profiles`, règle unifiée `App\Support\MemberIdentity`).

## Symptôme

Le titulaire et la naissance du papier officiel restent ceux d'une autre personne. La PR #1692 a seulement ajouté l'indicateur
« Nom différent de l'état civil du membre » : le défaut restait.

## Cause

L'identité civile d'un membre (`sse_identities`, `kind = 'civil'`, `user_id`) est une COPIE saisie une fois (bouton « Enregistrer »
de la fiche papiers, kit, générateur, reprise) et rien ne la relie ensuite au profil : changer le prénom ou le nom du membre (fiche,
dialogue « État civil du personnage », fiabilisation) ne la touchait pas, et aucun outil ne la remettait d'accord. Le titulaire d'un
papier est lu sur l'identité, donc le papier officiel suivait cette copie périmée. Les noms « Georgiou » et « Yannis » figurent dans
le réservoir du générateur (`SseIdentityGeneratorService`, pays Altis) : l'identité a très probablement été tirée au hasard puis
rattachée au compte. La date de naissance d'un personnage n'existait nulle part (`user_profiles.birth_date` est la date réelle du
joueur, jamais à imprimer sur un papier de jeu).

## Correctif

- Règle : l'identité civile la plus ancienne du membre est SA identité officielle ; nom et prénom = profil, naissance = date du
  personnage (`user_profiles.character_birth_date`, tirée une fois de façon déterministe si elle manque), lieu = dossier du personnage.
- Alignement automatique à chaque changement de prénom ou de nom (`UserProfileRepository::upsert`), bouton « Aligner sur l'état
  civil » avec avant / après (registre, fiche papiers, « Mes papiers »), outil `tools/sse-sync-member-identities.php` (simulation
  par défaut).
- Anciennes valeurs dans `sse_identity_history` (décision `civil`, restaurables) ; papiers officiels annotés « mis à jour le » ;
  couvertures, alias, faux, sujets non membres jamais touchés ; « nom volontairement différent » respecté.
- Étape de base 4690 (3 colonnes, aucune donnée modifiée) ; mode dégradé sans elle.

## Fichiers touchés

`app/Services/Sse/SseMemberCivilIdentityService.php`, `SseMemberCivilIdentityBatch.php`, `tools/sse-sync-member-identities.php`,
`app/Repositories/UserProfileRepository.php`, `app/Controllers/Admin/MemberIdentityPapersController.php`,
`app/Services/Sse/SseIdentityHolderService.php`, `views/partials/sse_civil_align.php`, `database/steps/4690_sse_member_civil_identity.php`.

## Vérification

`tests/Unit/SseMemberCivilIdentityTest.php`. Rien à reconstruire côté mod : le paquet d'identité est recalculé à partir de l'identité.

## Statut

corrigé (l'alignement des identités existantes se lance en production après relecture de la simulation)
