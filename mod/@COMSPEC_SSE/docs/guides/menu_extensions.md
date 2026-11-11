# Menu « SSE ROLEPLAY · Extensions »

Version anglaise : [menu_extensions.en.md](menu_extensions.en.md). Sommaire : [index.md](index.md). Créer une extension : [extensions_documents.md](extensions_documents.md).

## À quoi ça sert

Voir les **extensions de documents** chargées sur le serveur (addons tiers qui ajoutent des types de documents : carte de presse, laissez-passer…) et **activer ou désactiver** chacune pour la partie. Une extension désactivée ne propose plus ses types dans « Choisir un document », l'attribut Eden Papiers, l'éditeur SSE et la page IA de la zone ; les documents déjà remis restent lisibles.

## Où le trouver

| Contexte | Chemin |
|---|---|
| En jeu | Échap › **SSE ROLEPLAY · Extensions** (fenêtre) |
| Réglages CBA | Échap › Options › Réglages des addons › catégorie **SSE ROLEPLAY · Extensions** : extensions servies (oui / non), liste des extensions coupées au lancement, annonce au Zeus (côté serveur, synchronisé aux clients) |
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

Ligne d'état en bas de la fenêtre (deux lignes au plus, police réduite si besoin) ; aucune alerte Arma. Boutons **Activer / Désactiver** et **Fermer**, Échap ferme. La liste coupe un nom trop long avec « … » (nom entier en bulle d'aide) ; la fiche (description, modèles) défile si elle est longue.

**Documents d'exemple** (carte de presse, laissez-passer) est livrée avec le SSE et **coupée par défaut** : activez-la pour l'essayer, désactivez-la ensuite si besoin (choix gardé sur le serveur).

**Aucune extension détectée** : à la place des deux volets, la fenêtre explique que les papiers intégrés restent disponibles, ce qu'est une extension et comment la charger, avec le bouton **Ouvrir le tuto**.

## Tuto intégré « Comment ça marche »

Bouton **Aide** dans l'en-tête (à tout moment ; le même bouton, devenu **Extensions**, ramène à la liste). Étapes à gauche, lecture à droite (défilante) :

1. Qu'est-ce qu'une extension ? 2. Activer ou désactiver. 3. L'exemple livré. 4. Créer son addon (`$PBOPREFIX$`, `requiredAddons` avec `comspec_sse_identity`). 5. Le config.cpp minimal (type « Permis de port d'arme » commenté, bouton **Copier l'exemple** : presse-papiers, confirmation sur la ligne d'état). 6. Les champs d'un type. 7. Les textures (PAA, puissance de deux, `_co` / `_ca`). 8. Empaqueter et charger (`-mod=`). 9. Tester. 10. Problèmes fréquents.

Le même contenu est dans le manuel SSE (chapitre **Extensions de documents**, bouton « ? »).

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
