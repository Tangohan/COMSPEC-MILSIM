# Ordres C2 : arrêt du jeu, NewPI, protocole dans le chat

## Contexte

19 septembre 2026. Tous les ordres donnés en jeu fermaient Arma. Dans le chat de groupe, l’émetteur était le surnom Arma (NewPI) et le texte brut `ORDER|…` s’affichait.

## Symptôme

- Arma se ferme dès qu’un ordre ACE / Athena est envoyé.
- Au poste et dans le chat de groupe : auteur NewPI, pas le nom Athena.
- Ligne illisible du type `ORDER|ORD-3.29587e+08-6607|MOVE|Alpha 2-2|…`.

## Cause

- L’identifiant utilisait un grand nombre affiché en notation scientifique.
- L’émetteur était le surnom Arma, pas le nom Athena.
- L’ordre était recopié dans la messagerie générale, donc visible dans le chat de groupe.
- La liste d’ordres (structures internes) était diffusée à tout le monde, ce qui ferme le moteur.

## Correctif

- Identifiant entier, sans notation scientifique.
- Émetteur : nom Athena, à défaut l’indicatif.
- L’ordre est enregistré comme fiche, plus comme message de chat.
- Plus de diffusion réseau des structures internes ; les autres opérateurs reçoivent une liste simple.

Pack : Overwatch **1.5.99**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_issueOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderIssuerLabel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateOrderStatus.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakDataRepository.php`

## Vérification

Quitter Arma. Recharger Overwatch 1.5.99. Donner un ordre de déplacement depuis le menu situation. Le jeu reste ouvert. Le poste affiche le nom Athena. Le chat de groupe ne montre pas de ligne `ORDER|`.

## Statut

Corrigé.
