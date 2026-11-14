# COMSPEC Lobby

Écran de sélection des rôles refait pour Arma 3 : le **dossier de l'opérateur** (portrait, nom, prénom, grade, unité, fonction, qualifications) et le **code du poste**, vérifié par le serveur. Dépendance : **CBA_A3**. Version 0.1.0 (première version, **jamais essayée en jeu** : voir « État »).

Le système de postes d'Arma est gardé tel quel : liste des rôles, camps, pool de joueurs, paramètres de mission, verrou, expulsion, Workshop, chat. Le mod restyle l'écran (fond noir, filets fins, un accent ambre), descend le pool de joueurs pour faire place au dossier, et ajoute le dossier. Rien n'est remplacé ni piloté par script.

## Poser une étiquette sur un poste

Dans Eden, sur chaque unité jouable, champ **Description du rôle** : `[A-1] Chef de groupe` ou, pour un poste protégé, `[A-1|P] Chef de groupe`. `P` veut dire « protégé par un code » ; le code lui-même n'est jamais dans la mission.

## Identité du poste (nom, prénom, grade, portrait)

Par ordre de priorité :

1. cartes données par un autre module dans `uiNamespace COMSPEC_LOBBY_Cards` (c'est la prise prévue pour **Athena**, voir « Athena ») ;
2. la mission, dans `description.ext` :

```cpp
class ComspecLobby {
    class Slots {
        class A1 {
            tag = "A-1";                      // l'étiquette du poste
            surname = "Vasseur";
            given = "Thomas";
            rank = "Sergent-chef";
            unit = "ALPHA";
            role = "Chef de groupe";
            quals = "Chef de groupe";
            portrait = "images\a1.paa";       // image lisible par le jeu (chemin dans la mission ou un mod)
            pin = 1;                          // poste protégé (comme [A-1|P])
        };
    };
};
```

3. sinon, le texte du rôle sert de nom.

Tout le reste de la mission se règle comme d'habitude, dans Arma : l'écran garde le bouton **Paramètres** (paramètres de mission), le verrou, l'expulsion et les camps.

## Le code du poste, trois sources

Le code a quatre chiffres. Le joueur le saisit au pavé du dossier, dans l'écran des rôles ; il est **vérifié par le serveur à l'arrivée en jeu** (avant la mission, aucun message ne peut partir vers le serveur). Un joueur qui n'a pas le code sort de la mission sur un écran de bilan, revient aux rôles et peut ressaisir ; après trois essais le poste lui est fermé dix minutes. Un joueur qui n'envoie rien est écarté après 45 secondes. Les réglages (essais, délai, expulsion) sont dans les réglages CBA du serveur, catégorie COMSPEC Lobby.

| Source | Où | Pour qui |
|---|---|---|
| **Athena** | tableau `COMSPEC_LOBBY_AthenaPins` de paires `[étiquette, code]` posé par un autre module | serveurs reliés à Athena (prise prévue, voir plus bas) |
| **Fichier serveur** | `userconfig\comspec_lobby\pins.txt`, une ligne `ÉTIQUETTE=CODE`, `#` pour un commentaire, lu au démarrage du serveur | serveurs dédiés |
| **Zeus** | module Zeus Enhanced **COMSPEC Lobby > Code du poste** (posé sur une unité : l'étiquette est lue ; sinon on la saisit) ; code gardé dans le profil du serveur | missions locales, sans fichier ni Athena |

Pour une même étiquette, la source la plus prioritaire gagne : Zeus, puis fichier, puis Athena. Un poste marqué `P` sans code configuré n'enferme jamais personne : accès libre, noté au journal du serveur. L'hôte d'un serveur local n'est jamais contrôlé. Les codes ne sont jamais écrits au journal.

### Ce que cette protection est, et n'est pas

C'est une protection de **roleplay**, pas une barrière absolue : elle empêche qu'un joueur prenne un poste réservé par erreur ou par jeu. Le contrôle est fait par le serveur, mais un client modifié peut retarder l'envoi de son code ; le serveur l'écarte alors après le délai. L'expulsion utilise `#kick` ; si le serveur ne peut pas le lancer, l'unité est gelée et cachée.

## Athena

Le mod est prêt à recevoir les cartes et les codes d'Athena, mais **ne les récupère pas encore** : il lui faut une route côté site et une commande de la DLL COMSPEC Link (qui change la version de la DLL, donc une nouvelle version d'ATAK). Contrat : un module pose, avant l'ouverture de l'écran, `uiNamespace COMSPEC_LOBBY_Cards` (HashMap étiquette vers HashMap aux clés `surname`, `given`, `rank`, `unit`, `role`, `quals`, `portrait`, `source`) et, sur le serveur, `COMSPEC_LOBBY_AthenaPins`.

## Installer

Charger `@COMSPEC_Lobby` (et CBA_A3) sur **les clients et le serveur**. Sur un serveur qui vérifie les signatures, copier `keys\comspec_lobby.bikey` dans le dossier `keys` du serveur.

## État et premier essai

Le mod a été écrit et validé **hors du jeu** : la config compile (HEMTT), l'écran est vérifié contre la config d'origine d'ui_f (mêmes classes parentes, mêmes identifiants, pas de recouvrement à 16:9, 21:9 et 16:10 : `tools\check_layout.py`), les fichiers sont vérifiés par `tests/Unit/LobbyModAssetTest.php`. **Il n'a jamais tourné dans Arma.** Points à regarder au premier essai :

1. L'écran s'ouvre avec le dossier à droite et sans recouvrement. Si une zone se chevauche, le chat du moteur (en bas à gauche) est la première à vérifier.
2. Au RPT, après l'ouverture de l'écran : `[COMSPEC LOBBY] écran ouvert` puis `liste des rôles : N ligne(s)` et les premières lignes (texte, donnée, valeur, image). **Ces lignes disent ce que le moteur donne à l'écran** : si l'étiquette `[A-1]` n'apparaît pas dans le texte des lignes, il faut changer la façon de la lire.
3. Choisir un poste étiqueté : le dossier se remplit. Un poste `[..|P]` : « CODE » à droite de la ligne, pavé actif, bouton « continuer » éteint tant que le code n'a pas quatre chiffres.
4. Arriver en jeu avec le bon code : `[COMSPEC LOBBY] ... déverrouillé`. Avec un faux : écran de bilan, puis retour aux rôles.
5. `#kick` sur serveur dédié et le module ZEN : jamais essayés.

Si un contrôle attendu manque, le script de l'écran ne touche à rien et le jeu reste maître de l'écran. Le journal le dit : `contrôles manquants`.

## Construire

```
cmd /c "<chemin>\mod\COMSPEC_Lobby\build_mod.bat"
```

HEMTT (`winget install BrettMayson.HEMTT`), Arma 3 Tools. La clé de signature du mod (`comspec_lobby`) est créée une seule fois hors du dépôt, dans `%APPDATA%\COMSPEC\signing\lobby` : la garder, la même doit servir à toutes les versions.
