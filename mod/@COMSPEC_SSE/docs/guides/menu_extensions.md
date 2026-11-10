# Menu « SSE ROLEPLAY · Extensions »

Version anglaise : [menu_extensions.en.md](menu_extensions.en.md). Sommaire : [index.md](index.md). Créer une extension : [extensions_documents.md](extensions_documents.md).

## À quoi ça sert

Voir les **extensions de documents** chargées sur le serveur (addons tiers qui ajoutent des types de documents : carte de presse, laissez-passer…) et **activer ou désactiver** chacune pour la partie. Une extension désactivée ne propose plus ses types dans « Choisir un document », l'attribut Eden Papiers, l'éditeur SSE et la page IA de la zone ; les documents déjà remis restent lisibles.

## Où le trouver

| Contexte | Chemin |
|---|---|
| En jeu | Échap › **SSE ROLEPLAY · Extensions** (fenêtre) |
| Réglages CBA | Échap › Options › Réglages des addons › catégorie **SSE ROLEPLAY · Extensions** : un interrupteur par extension (côté serveur, synchronisé aux clients) |
| Zeus | ZEN › COMSPEC · Identité › **Extensions** |
| Eden | Barre de menus › COMSPEC SSE › **Extensions** |

## La fenêtre

Liste des extensions détectées (`CfgComspecDocumentExtensions`) ; pour chacune :

| Colonne | Exemple |
|---|---|
| **Nom** | Documents d'exemple |
| **Auteur** | COMSPEC |
| **Version** | 1.0.0 |
| **Modèles** | 2 (carte de presse, laissez-passer) |
| **Aperçu** | texture `preview` de l'extension |
| **Interrupteur** Activée / Désactivée | état serveur, appliqué à tous les clients |

Ligne d'état en bas de la fenêtre (« Extension désactivée : ses 2 modèles ne sont plus proposés ») ; aucune alerte Arma. Bouton **Fermer**, Échap ferme.

## Pas à pas : désactiver la carte de presse pour une mission militaire

1. Échap › **SSE ROLEPLAY · Extensions**.
2. Ligne « Documents d'exemple » : interrupteur sur **Désactivée** (il faut être Zeus ou administrateur ; sinon la fenêtre est en lecture seule).
3. Ligne d'état : l'extension est désactivée ; tous les clients voient le changement.
4. Zeus › **Donner des papiers** : la carte de presse n'apparaît plus dans la liste des types.

## Pièges fréquents

- L'interrupteur agit **côté serveur** : un client qui le bascule sans droits ne change rien (lecture seule).
- Une extension absente du serveur mais présente chez un client n'apparaît pas : les deux doivent charger le même addon.
- Désactiver une extension **ne supprime pas** les documents déjà remis : ils restent lisibles et contrôlables.
- Le nombre de **modèles** compte les types (`documents[]`), pas les exemplaires (`_001`…).
- Une extension mal déclarée (sans `CfgComspecDocumentExtensions`) a ses types proposés sans nom d'extension et n'apparaît pas dans la liste : ajoutez la classe.

## Athena

Aucun lien direct : Athena reconnaît les types par leur classe quand un contrôle ou un relevé lui parvient. L'état activé / désactivé n'est pas envoyé.
