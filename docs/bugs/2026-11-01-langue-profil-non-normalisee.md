# Langue du profil enregistrée sans être normalisée

## Contexte

Mon compte › Profil et préférences, champ « Langue ». Relevé en écrivant le test d'enregistrement de chaque réglage pendant la refonte de la page.

## Symptôme

Une valeur de langue inconnue envoyée par le formulaire (par exemple « xx », ou « en-GB ») était enregistrée telle quelle dans le profil. Le site affichait le français, mais le profil gardait une langue qui n'existe pas, celle qui sert aux e-mails.

## Cause

`AccountController::preferences()` validait avec `LocaleService::isSupported()`. Or `isSupported()` normalise d'abord la valeur, et `normalize()` ramène toute langue inconnue au français : le contrôle répondait donc toujours « oui », et la saisie brute était enregistrée.

## Correctif

La langue est normalisée avant d'être enregistrée (`LocaleService::normalize()`). Une valeur inconnue devient « fr », et « en-GB » devient « en ».

## Fichiers touchés

- `app/Controllers/Web/AccountController.php`
- `tests/Unit/AccountPreferencesSaveTest.php`

## Vérification

`AccountPreferencesSaveTest::testEmptySteamFieldUnlinksAndMissingNameKeepsTypedValues` : une langue « xx » est enregistrée « fr ».

## Statut

Corrigé.
