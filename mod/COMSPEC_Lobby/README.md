# COMSPEC Lobby

Écran de sélection des rôles refait pour Arma 3 : le **dossier de l'opérateur** (portrait, nom, prénom, grade, unité, fonction, qualifications). Aucune dépendance autre que le jeu. Version 0.4.0 (0.3.0 essayée en jeu sur serveur dédié : voir « État »).

Le système de postes d'Arma est gardé tel quel : liste des rôles, camps, pool de joueurs, paramètres de mission, verrou, expulsion, Workshop, chat. Le mod restyle l'écran (fond noir, filets fins, un accent ambre), remonte le pool de joueurs sous le dossier, et ajoute le dossier. Rien n'est remplacé ni piloté par script.

Il n'y a pas de code de poste : la protection des postes par code a été écrite puis retirée de la version. Elle est gardée à part dans la branche `archive/lobby-avec-pin` et n'est pas en service.

## Poser une étiquette sur un poste

Dans Eden, sur chaque unité jouable, champ **Description du rôle** : `[A-1] Chef de groupe`. L'étiquette sert à retrouver l'identité du poste.

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
        };
    };
};
```

3. votre carte Athena (voir « Athena ») ;
4. sinon, le texte du rôle sert de nom.

Tout le reste de la mission se règle comme d'habitude, dans Arma : l'écran garde le bouton **Paramètres** (paramètres de mission), le verrou, l'expulsion et les camps.

## Athena : votre dossier et votre portrait

Avec **COMSPEC ATAK** chargé et un compte Athena relié, le dossier montre **votre** identité Athena sur le poste que vous choisissez, quand la mission ne donne pas d'identité à ce poste : indicatif, nom, grade, unité, fonction, qualifications et portrait opérateur (celui de Mon compte > Portrait). La **fonction** est la vôtre dans votre communauté (fiche Effectifs d'Athena), jamais le texte du poste : le poste reste dans la liste des rôles. Une ligne sans valeur (pas de grade, pas de fonction) est masquée, et une longue liste de qualifications est coupée à la largeur de la ligne (« A · B +3 »).

Comment ça marche : à chaque partie, COMSPEC ATAK garde votre carte dans le profil Arma (`profileNamespace COMSPEC_LOBBY_Self`) et le portrait téléchargé par COMSPEC Link. L'écran des rôles, qui s'ouvre avant toute mission et sans liaison, relit cette carte. Conséquences :

- la toute première fois (aucune partie jouée avec ATAK 4.14.0 ou plus), le dossier montre seulement le texte du rôle ;
- un changement de portrait ou d'indicatif sur le site apparaît à l'écran des rôles après la partie suivante ;
- le portrait est rangé par COMSPEC Link (2.0.128 ou plus) dans `comspec_cache` du dossier du jeu, seul dossier hors PBO qu'Arma lit en texture. De 2.0.124 à 2.0.127 il était rangé dans Documents : le cadre restait vide (0.3.0). Si le dossier du jeu n'est pas inscriptible, le portrait passe par le navigateur intégré du jeu (aucune adresse externe, copie JPEG lue sur le disque par COMSPEC Link) après un clic sur **Synchroniser** ;
- sans portrait affiché, le cadre dit « Sans photo » et la ligne à côté du bouton dit pourquoi (aucun portrait sur Athena, ou image à resynchroniser).

Le bouton **Synchroniser**, en haut du dossier, redemande votre carte à Athena tout de suite, sans attendre la prochaine partie. Il marche quand COMSPEC Link a déjà une session Athena dans ce lancement du jeu (une partie jouée, puis retour à l'écran des rôles) ; sinon un message dit quoi faire. Le clic fige le jeu le temps de la réponse du site (quelques secondes au plus).

Sans COMSPEC ATAK, rien ne change : le mod n'en dépend pas. Un autre module peut toujours poser des cartes par poste dans `uiNamespace COMSPEC_LOBBY_Cards` (HashMap étiquette vers HashMap aux clés `surname`, `given`, `rank`, `unit`, `role`, `quals`, `portrait`, `source`), prioritaires sur tout le reste.

## Installer

Charger `@COMSPEC_Lobby` sur **les clients** (l'écran des rôles est affiché par le client). Sur un serveur qui vérifie les signatures, copier `keys\comspec_lobby.bikey` dans le dossier `keys` du serveur.

## État

Essayé en jeu (2026-11-14, serveur dédié + client local) : l'écran s'ouvre, les étiquettes `[HQ-1]` sont lues dans les lignes de la liste, le dossier se remplit à chaque poste choisi, sans recouvrement. La config est aussi vérifiée hors du jeu (HEMTT, mêmes classes et identifiants que ui_f, pas de recouvrement à 16:9, 21:9, 16:10 et 5:4 : `tools\check_layout.py`) et les fichiers par `tests/Unit/LobbyModAssetTest.php`.


Si un contrôle attendu manque, le script de l'écran ne touche à rien et le jeu reste maître de l'écran. Le journal (RPT) le dit : `contrôles manquants`. Il écrit aussi la liste des rôles telle que le moteur la fournit (lignes `[COMSPEC LOBBY]`).

## Essayer en jeu

`tools\ingame_lobby_test.ps1` (Start, Shot, Click, Report, Stop) lance un serveur dédié local avec la mission `missions\COMSPEC_Lobby_Test.Stratis` et un client, prend des captures de l'écran des rôles, puis rend tout. Ne publie rien.

## Construire

```
cmd /c "<chemin>\mod\COMSPEC_Lobby\build_mod.bat"
```

HEMTT (`winget install BrettMayson.HEMTT`), Arma 3 Tools. La clé de signature du mod (`comspec_lobby`) est créée une seule fois hors du dépôt, dans `%APPDATA%\COMSPEC\signing\lobby` : la garder, la même doit servir à toutes les versions.
