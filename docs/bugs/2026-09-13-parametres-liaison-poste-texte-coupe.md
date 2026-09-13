# Paramètres — description « Liaison au poste » coupée

## Contexte

Écran Paramètres du téléphone ATAK, rubrique **Liaison au poste**.

## Symptôme

Le texte d’aide s’arrête à « Préférez Appairer sur le portail. Les » : illisible / tronqué juste au-dessus de **Afficher les réglages avancés**.

## Cause

Contrôle `LblLinkSection` (IDC 9856) trop bas (`h = 0.92`) pour le titre + la phrase, avec contraste insuffisant.

## Correctif

Hauteur portée à `1.55`, texte sur trois lignes, contraste relevé, boutons et champs avancés décalés vers le bas.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.107)
- `app/Support/DevDispatchCatalog.php` (UPDATE 530)

## Vérification

Rebuild PBO Athena, quitter Arma, ouvrir Paramètres → scroller jusqu’à Liaison au poste : phrase complète visible.

## Statut

corrigé
