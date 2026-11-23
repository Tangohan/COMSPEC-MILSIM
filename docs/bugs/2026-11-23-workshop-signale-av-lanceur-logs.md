# Workshop : item COMSPEC signalé « item being flagged by AV »

## Contexte
L'item Workshop COMSPEC ATAK est signalé par Steam avec le motif « item being flagged by AV ». Steam ne dit pas quel fichier est en cause.

## Symptôme
L'item n'est plus disponible sur le Workshop dans l'attente d'une revue.

## Cause
Non confirmée. Candidats, du plus au moins probable :
1. `Envoyer_mes_logs.bat` : un script qui lance PowerShell avec `-ExecutionPolicy Bypass`, puis un script qui compresse des fichiers et ouvre l'Explorateur. C'est un motif classique de détection heuristique.
2. Les deux DLL d'extension (`COMSPECATAKNativeExtension_x64.dll`, `COMSPECExtension_x64.dll`, environ 10 Mo chacune) : binaires .NET compilés en natif (sections `.managed` et `hydrated`), non signés, avec des imports de processus, de presse-papiers et de chiffrement (`CreateProcess`, `ShellExecute`, `GetClipboardData`, `CryptUnprotectData`). Ces imports viennent du runtime .NET ; aucun appel suspect propre au projet n'a été trouvé (pas de `WriteProcessMemory`, d'injection, de clé de démarrage ni de webhook en clair).

## Correctif
Retrait de `Envoyer_mes_logs.bat` et `Envoyer_mes_logs.ps1` du dossier du mod. Les consignes de signalement de bug (aide intégrée, README, descriptions Steam, discussions, réglage CBA) demandent maintenant d'envoyer à la main les deux derniers `.rpt` (`%LOCALAPPDATA%\Arma 3`) après avoir retiré mots de passe et jetons.

## Fichiers touchés
Le lanceur (2 fichiers), `views/atak/first_link.php`, `XEH_preInit.sqf`, `en.txt`, `xx.txt`, README, descriptions Steam, discussion 90, deux tests.

## Vérification
`php -l` sur les fichiers PHP modifiés. Plus aucune référence à `Envoyer_mes_logs` hors CHANGELOG. Reste à reconstruire `main.pbo` (texte du réglage CBA) et à republier le lot Workshop depuis un dossier propre.

## Statut
corrigé partiellement : cause réelle non confirmée, les DLL restent à examiner (signature de code, analyse sur un service multi-moteurs, soumission en faux positif à l'éditeur d'antivirus)
