# Photos de reconnaissance : lien /public/recon/... introuvable

- **Contexte** : fil de renseignement des joueurs (`IntelPlayerFeedService`), miniatures des images de reconnaissance.
- **Symptôme** : l'adresse `/public/recon/recon_<date>_<auteur>_<id>.jpg` donne « page introuvable ». Aucune route `/recon/<fichier>` n'existe : les images publiques sont sur `/uploads/recon/`.
- **Cause** : le fil passait `recon_images.image_path` (valeur `recon/<fichier>`) à `user_media_public_url()`, qui ne sait que préfixer le chemin par la racine du site (ici `/public`). Le dossier `uploads/` manquait. Les chemins de clichés confidentiels (`recon-private/...`) étaient aussi transformés en adresse.
- **Correctif** : à la source, le fil utilise `ReconImageStorage::urlForRow()`, comme les autres écrans : `/uploads/recon/<fichier>` pour un cliché ordinaire, route authentifiée `api/recon/images/{id}/file` pour un cliché confidentiel. Les liens Discord (`publicUrl`) et les comptes rendus passaient déjà par cette fonction.
- **Fichiers touchés** : `app/Services/Intel/IntelPlayerFeedService.php`.
- **Vérification** : lecture du code, `php -l`. Test ciblé à passer en CI.
- **Statut** : corrigé, à vérifier en production.
