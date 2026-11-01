# Équipement — texte « Copier pour ACE » incomplet et tenues recollées vues « non transmises »

## Contexte

Page Équipement (`/equipment`) : un joueur copie une tenue Athena pour la coller dans l’arsenal ACE (bouton Importer),
ou télécharge la liste de toutes les tenues.

## Symptôme

- Certaines tenues collées dans ACE faisaient planter la liste des tenues d’ACE
  (`fnc_fillLoadoutsList` ligne 101 : « Type Tableau, Chaîne attendu / 1 éléments fournis, 6 attendus »).
- Une tenue recollée dans ACE depuis la liste téléchargée apparaissait « non transmise » dans le panneau
  « Athena · Tenues » alors qu’elle était identique.

## Cause

1. L’export ne livrait que le tableau de la tenue (sans les infos étendues ACE) et recopiait les emplacements tels
   quels : une tenue stockée avec une enveloppe de trop (`[[tenue, infos]]`) n’était pas reconnue, et un emplacement
   mal typé (casque en tableau, objets assignés à 1 élément au lieu de 6) passait jusqu’à ACE.
2. La liste exportée renommait les tenues pour l’affichage (« Kit - Nuit » devenait « Kit · Nuit »). Le mod reconnaît
   ses tenues par leur nom : la tenue recollée sous le nom modifié n’était plus rattachée à celle du site.

L’empreinte n’était pas en cause : le site calcule `payload_sha256 = sha256(payload_text)` sur le texte exact reçu de
SyncWardrobe (« str loadout » aux guillemets doublés par callExtension, guillemets extérieurs retirés par la DLL), et
le mod calcule HashText sur ce même texte. Une tenue recollée depuis l’export redonne exactement ce texte (test
`testCopiedTenueGivesBackTheTextTheModHashes`), à condition que la tenue stockée soit déjà au format actuel du mod
(tableau nu). Une tenue stockée par une ancienne version (avec infos étendues ou numéro de version dans le texte)
reste « différente » jusqu’au prochain envoi depuis le jeu.

## Correctif

- `ArsenalLoadoutItems::aceLoadout()` : déballe toutes les enveloppes connues et renvoie toujours
  `[getUnitLoadout à 10 entrées, infos étendues]`, chaque emplacement remis au bon type (6 objets assignés).
- `aceExport()` (une tenue) et `aceExportList()` (liste `[[nom, [tenue, infos]], …]`) s’en servent.
- La liste garde le nom enregistré sur Athena.

## Fichiers touchés

- `app/Support/ArsenalLoadoutItems.php`
- `app/Repositories/ArsenalWardrobeRepository.php` (`exportPayloads`)
- `tests/Unit/ArsenalLoadoutItemsTest.php`, `tests/Unit/ArsenalWardrobeManageTest.php`

## Vérification

- PHPUnit : formats d’export (enveloppes, emplacements mal typés, tenue à 9 emplacements) et empreinte identique
  après recollage.
- À tester en jeu : « Copier pour ACE » d’une tenue, Importer dans l’arsenal (écran principal), enregistrer sous le
  même nom, vérifier l’état « transmise » dans le panneau Athena · Tenues et l’absence d’erreur dans la liste ACE.

## Statut

Corrigé côté site (en attente de test en jeu).
