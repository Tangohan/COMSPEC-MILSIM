# Test automatique en jeu (ATAK et SSE)

`tools/ingame_test.ps1` lance Arma 3 sur la mission `missions/COMSPEC_Autotest.Stratis`, attend sa fin, ferme le jeu et résume le RPT. Aucun clic n'est demandé. Compter 20 à 30 minutes. La souris doit rester immobile pendant `carte_appui_long`.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mod\COMSPEC_ATAK_Native\tools\ingame_test.ps1
```

Contrôle des fonctions seul (chaque fonction de `CfgFunctions` des mods COMSPEC existe et compile) :

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File mod\COMSPEC_ATAK_Native\tools\ingame_test.ps1 -Groupes fonctions
```

Faire le build d'abord (`build_mod.bat`) : le test charge les PBO de `@COMSPEC_ATAK_Native` et de `mod\@COMSPEC_SSE`, pas les sources. Le script prévient si les sources sont plus récentes.

## Paramètres

| Paramètre | Rôle |
|---|---|
| `-AtakDir`, `-SseDir`, `-AcreDir` | Dossiers des mods. ACRE par défaut : Workshop `751965892`. |
| `-Mods` | Liste complète, remplace CBA, ACE, ACRE, ATAK, SSE. |
| `-SansAcre`, `-SansSse` | Retire ce mod. |
| `-Groupes` | `fonctions telephone carte antennes gps explo sse zeus acre reseau` (vide : tous). |
| `-DureeMaxMinutes` | Défaut 45. Au-delà, le jeu est fermé. |
| `-DemarrageMaxSecondes` | Défaut 300. Sans première ligne `AUTOTEST`, on passe au plan suivant. |
| `-Lancement` | `Auto` (défaut), `Init`, `Autotest`. |
| `-PleinEcran`, `-AvecSon` | Par défaut : fenêtré, sans son. |
| `-SansProxy` | Vérification de la licence : voir Sûretés. |
| `-LargeurCapture` | Défaut 1280. |
| `-Sortie` | Dossier du passage. Défaut : `%TEMP%\comspec_autotest\<date>`. |

Le pack `3026491672` est refusé pour ACRE : il contient aussi des PBO COMSPEC, qui masqueraient le build testé.

## Lancement de la mission

1. **`-init=playMission['','COMSPEC_Autotest.Stratis']`** : syntaxe de la documentation BI, en guillemets simples du SQF pour éviter l'échappement. La mission doit être dans le dossier `Missions` du jeu : le script y copie la mission, puis la retire.
2. **`-autotest=<fichier>`**, si rien ne démarre : `autotest.cfg` avec `class TestMissions` et un chemin absolu. Le format vient de mémoire, le wiki BI n'étant pas lisible depuis l'outil : **à essayer**.
3. **À la main**, si les deux échouent : ouvrir la mission dans Eden sous le profil `COMSPECTest`, puis Jouer. Le script ne lit alors pas le résultat : relire le RPT.

Sans BattlEye (`arma3_x64.exe` direct). `-filePatching` n'est pas utilisé. Si Arma est déjà lancé, le script refuse de démarrer (code 3) et n'y touche pas. Un autre Arma ne doit pas être lancé pendant le test.

## Résultat

`rapport.txt`, le RPT, `lancement.txt`, `fonctions_manquantes.txt`, `captures\`, `captures.txt` et `planches\` (planches contact de 12 vignettes) sont dans le dossier du passage.

Les captures viennent de la fenêtre réelle : la commande `screenshot` d'Arma n'enregistre que la scène 3D, sans aucune interface, en PNG de 17 Mo avec un plafond de 250 Mo par dossier. La mission écrit `AUTOTEST|SHOT|nom` et attend ; le script voit la ligne au RPT, met la fenêtre au premier plan et l'enregistre en JPEG de 1280 pixels de large. **Pendant le test, Arma passe au premier plan à chaque capture** : ne pas s'en servir pour autre chose. Si la fenêtre refuse le premier plan, le script essaie `PrintWindow` et le note dans `captures.txt`. Le rapport donne les étapes KO et SKIP, les erreurs de script dont un fichier COMSPEC est cause avec l'étape en cours, `[WARN]`, `[FRAME]`, `[PERF]`, les captures, les connexions observées et le code de sortie : 0 bon, 1 KO ou erreur COMSPEC, 2 mission non terminée, 3 Arma déjà lancé, 4 environnement.

Lignes au RPT : `AUTOTEST|OK|nom`, `AUTOTEST|KO|nom|motif`, `AUTOTEST|SKIP|nom|motif`, `AUTOTEST|INFO`, `AUTOTEST|VISUEL` (à contrôler sur la capture), `AUTOTEST|SHOT`, `AUTOTEST|FCT`, `AUTOTEST|PERF`, encadrées par `BEGIN` et `END`. Un échec n'arrête pas la suite. Une fonction d'une branche non fusionnée donne SKIP.

## Sûretés

- **Profil** : `-name=COMSPECTest`, séparé du profil du joueur. La mission refuse de tourner sous un autre profil, car elle change des préférences.
- **Fichiers partagés** : `Documents\Arma 3\Arma3.cfg` et `COMSPEC_Local\config.json` sont sauvegardés, puis restaurés s'ils ont changé.
- **Réseau** : `cba_settings.sqf` force le mode local ; le processus reçoit un proxy mort (`HTTPS_PROXY`), car la DLL fait une requête `HEAD` vers Athena au chargement, même en mode local (`KickBackgroundWarmup`, corrigé par #1461). Pas de jeton, rien de publié. Le rapport liste les connexions vues, avec leur nom d'après le cache DNS de Windows.
- **Licence** : `-SansProxy` retire le proxy et rend le test KO si le jeu joint Athena (`LICENCE OK` ou `LICENCE KO` au rapport). C'est le seul passage qui vérifie « ne transmet rien si le joueur répond Non » ; à lancer une fois #1461 fusionnée. Sans ce paramètre, le test ne prouve rien sur ce point. Une connexion courte entre deux relevés de 3 s peut échapper au relevé d'adresses, pas au relevé DNS, sauf si le nom était déjà en cache.
- **ACRE** : lectures et fonction pure seulement. La voix par le maillage reste coupée, y compris au banc.
- **Publication** : `missions/` et `tools/` ne sont dans aucun PBO. `build_mod.bat` n'empaquette que `Sources\addons\*`. Les réglages du mode développeur ne sont forcés que dans la mission.

## Non couvert

Son et voix, multijoueur et serveur dédié, clics et saisies réels (les fonctions sont appelées directement), menus ACE (la barre de progression est testée pour l'antenne satellite et les papiers), connexion à Athena, exécution des modules Zeus. Le placement des cercles et le rendu sont à contrôler sur les captures.
