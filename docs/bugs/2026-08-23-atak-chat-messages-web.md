# Journal radio ATAK — messages du poste indistincts

## Contexte

Onglet Communications d’Athena ATAK (`/public/atak`). Les messages émis depuis
le poste (champ « Émettre ») étaient présentés comme les transmissions terrain.

## Symptôme

Un message tel que « N-10 Merci de transmettre ID atak pour recevoir ordre »,
envoyé depuis Athena, apparaissait avec la même carte verte « Message de groupe »
et la pastille « Transmis » que les messages venus du jeu.

## Cause

L’émission web réutilise le format groupe du terrain (`GROUPE|…`). Le journal
ne conservait pas l’origine, et l’interface déduisait seulement « transmis »
si l’indicatif correspondait à l’opérateur connecté.

## Correctif

- Colonne d’origine sur les messages radio : terrain ou poste de commandement.
- À l’écriture : session Athena = poste ; liaison jeu = terrain.
- Carte distincte (bandeau bleu, pastille « Poste de commandement », mention
  « Poste » dans l’en-tête).

Les messages déjà enregistrés restent au look terrain (origine inconnue). Les
nouveaux envois depuis Athena prennent le nouveau rendu.

## Fichiers touchés

- `bootstrap/atak_chat_source_migration.php`
- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-chat.js`
- `public/assets/js/tacmap-tactical-alerts.js`
- `public/assets/css/atak.css`

## Vérification

- Envoyer un message depuis Communications : carte bleue « Poste de commandement ».
- Un message venu du jeu conserve la carte verte / « Message de groupe ».

## Statut

Corrigé (à déployer + migration).
