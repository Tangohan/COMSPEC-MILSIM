# Croisement — pas de détection Jawadi / Khalil

## Contexte

Liste active : « Jawadi Khalil ». Fiche `IDN-00001` : « Khalil Jawadi ».
Écran 03.02 : aucune correspondance.

## Symptôme

La surveillance ne remonte pas la fiche pourtant clairement liée.

## Cause

1. La fiche terrain avait souvent **alias = « Khalil Jawadi »** et **nom/prénom vides**
   (remontée SEEK mal mappée). Le score ne regardait que `last_name` / `first_name`.
2. L’inversion seule ne suffisait pas sans champs structurés.
3. Affichage liste : prénom+nom (ordre anglo) masquait l’ordre réel saisi.

## Correctif

- Découpe de l’alias / libellé en prénom+nom quand les champs sont vides.
- Matching par **jetons** (ordre indifférent) + identité nominale complète.
- Libellé liste : Nom puis Prénom.
- Aide formulaire mise à jour.

## Fichiers touchés

- `app/Services/Sse/SseCrossMatchService.php`
- `app/Repositories/SseWatchlistRepository.php`
- `views/atak/sse/cross.php`

## Vérification

1. Déployer PHP.
2. Recharger `/atak/sse/croisements` → correspondance Khalil/Jawadi ≥ 60 %.
3. Motif attendu : combinaison de noms ou ordre inversé.

## Statut

corrigé (à déployer)
