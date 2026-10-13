# Photos terrain trop lourdes (captures 4K)

## Constat

Une capture Arma prise sur un écran 4K arrive en PNG 3840×2160 d’environ 10 Mo. Le stock de
photos (reconnaissance, fiches SEEK, renseignement) atteignait 1,2 Go.

## Correction

- `App\Support\StoredPhotoCompressor` : côté long ramené à 1920 px, réencodage JPEG qualité 80.
  Le fichier garde son nom, donc la ligne en base, l’identifiant et l’URL ne changent pas
  (un ancien `.png` peut contenir du JPEG ; navigateurs, GD et Discord lisent le contenu).
- Sans perte possible : écriture dans un fichier temporaire du même dossier, relu et vérifié,
  puis renommé par-dessus l’original seulement si le gain dépasse 10 %. Un JPEG déjà à la bonne
  taille et léger n’est pas réencodé : un deuxième passage ne touche à rien.
- À l’envoi : `reconImagesStore` (ATAK) et l’ajout de photo SEEK compressent la capture avant de
  la ranger, en `.jpg`. Aucun changement du mod.
- Stock existant : tâche automatique `photo_compress` (une fois par heure, au plus 90 s par
  passage, fichiers de moins de 10 minutes laissés pour le passage suivant). Dossiers :
  `public/uploads/recon`, `storage/uploads/recon`, `storage/private/recon` (+ `originals`),
  `public/uploads/sse`, `public/uploads/sse/evidence`, `public/uploads/intel`.
- La route authentifiée des clichés confidentiels annonce le type réel du fichier.

## Exploitation (VPS)

La tâche passe par le cron Athena déjà en place (toutes les 5 minutes) :

```
*/5 * * * * flock -n /tmp/athena-cron.lock php /var/www/athena.ttrd.fr/scripts/cron-run.php >> /var/www/athena.ttrd.fr/storage/logs/cron.log 2>&1
```

S’il manque : `bash scripts/install-system-cron.sh`, ou Administration › Tâches automatiques ›
« Installer le cron sur le VPS ».

Tout le stock d’un coup (facultatif) :

```
php scripts/compress-photos.php --dry-run   # place gagnée, rien n’est modifié
php scripts/compress-photos.php
```
