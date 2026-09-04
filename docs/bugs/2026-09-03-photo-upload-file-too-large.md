# Photos terrain — `file_too_large` sur une capture PNG

## Symptôme

Après `NotifyNewPhoto OK`, l’extension retrouve bien le fichier
`COMSPEC_….png`, mais `POST /public/api/recon/images` répond `400` et le journal
termine par `PhotoUpload — http_400 — file_too_large`.

Le `file_not_found` éventuel d’un JPEG horodaté est un signal Photo Library
obsolète distinct : la capture PNG créée ensuite est bien celle à transmettre.

## Cause

La configuration web plafonnait chaque fichier à 16 Mo et le corps POST à
20 Mo. Une capture PNG native Arma peut dépasser 70 Mo. PHP rejetait donc le
multipart avant que le contrôleur puisse lire `$_FILES`.

## Correctif

- `upload_max_filesize` passe à 96 Mo ;
- `post_max_size` passe à 100 Mo pour conserver la marge du multipart ;
- le traitement reste compatible avec la limite mémoire : le contrôleur utilise
  le fichier temporaire PHP et ne relit pas tout le corps multipart en mémoire.

La modification de `.user.ini` peut demander quelques minutes avant d’être
rechargée par PHP-FPM / CGI. Le frontal HTTP doit également autoriser les corps
d’au moins 100 Mo.

## Vérification

Après déploiement du portail et rechargement de la configuration PHP, prendre
une photo depuis le téléphone. Le journal doit afficher `PhotoUpload OK — uploaded`
et le cliché doit apparaître dans Photos au poste.
