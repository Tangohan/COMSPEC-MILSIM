# Documents — aperçu PDF vide (type de fichier incorrect)

## Contexte

Page publique d’un PDF publié dans la bibliothèque (exemple : Doctrine d’emploi ATAK / Overwatch).

## Symptôme

L’aperçu reste vide. La console indique qu’un script module est refusé, puis parfois une ressource « hors ligne ».

## Cause

Le lecteur PDF est un module JavaScript (fichiers `.mjs`). Le serveur de production les livrait comme un fichier brut, que le navigateur refuse d’exécuter. L’assistant d’arrière-plan du site transformait ensuite un échec de chargement en page « hors ligne ».

## Correctif

Le lecteur et son assistant sont aussi servis comme des scripts JavaScript classiques, que le serveur connaît déjà. Le type des modules `.mjs` est déclaré côté Apache, routeur PHP et configuration Nginx. Un échec d’affichage propose le téléchargement, sans masquer toute la page.

## Fichiers touchés

- `views/documents/show.php`
- `public/assets/vendor/pdfjs/pdf.js`
- `public/assets/vendor/pdfjs/pdf.worker.min.js`
- `public/.htaccess`
- `public/index.php`
- `public/sw.js`
- `docs/nginx.example.conf`

## Vérification

Ouvrir un PDF publié : les pages s’affichent, page suivante / zoom. Console sans refus de module pour le lecteur, sans page « hors ligne ». Si l’aperçu échoue, le message propose de télécharger.

Après déploiement Nginx : `curl -sI https://athena.ttrd.fr/assets/vendor/pdfjs/pdf.mjs` doit indiquer un type JavaScript, pas un fichier brut.

## Statut

corrigé
