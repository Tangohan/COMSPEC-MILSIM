# Indicatif de la liste des effectifs différent de la fiche

## Contexte
Back-office, Effectifs, liste des membres (vue Par unité, mode bureau) et fiche membre.

## Symptôme
Deux membres d'unités différentes affichaient le même indicatif « YA1 » dans la colonne « Indicatif » du tableur, alors que la fiche du second affichait un autre indicatif (« YB1 »).

## Cause
La liste lit `UserRepository::listForTenant` (requête `SELECT u.*`, donc `users.callsign`, valeur du compte d'origine). La fiche lit `findById`, qui applique `overlayCommunityProfile` : l'indicatif de la communauté (`user_community_profiles.callsign`) l'emporte. Quand les deux valeurs diffèrent, la liste et la fiche se contredisent. Le matricule de communauté avait déjà ce traitement (`withTenantMemberNumbers`), pas l'indicatif ni le nom. La recherche et le comptage ne regardaient aussi que `users.callsign`.

## Correctif
`listForTenant` applique l'indicatif et le nom de la fiche communauté (`UserIdentityMergeRules::overlayCommunityIdentity`, même règle `shouldOverlayCommunityField` que la fiche). La recherche de la liste cherche aussi dans l'indicatif et le nom de la fiche communauté. Aucun changement de droits ni de route. Les exports lisent la même liste.

## Fichiers touchés
- app/Repositories/UserRepository.php
- app/Services/Identity/UserIdentityMergeRules.php
- tests/Unit/RosterCommunityIdentityOverlayTest.php

## Vérification
Test unitaire avec données fictives (deux membres de deux unités, un seul avec l'indicatif de l'autre en valeur de compte). À contrôler en production : la ligne du Capitaine « B SQN, TRP A » doit afficher YB1. Si elle affiche encore YA1, la donnée est identique dans la fiche communauté et le compte : c'est alors un doublon de données à corriger à la main.

## Statut
corrigé, à vérifier en production
