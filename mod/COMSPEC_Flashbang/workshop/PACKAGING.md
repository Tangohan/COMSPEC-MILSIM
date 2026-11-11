# Publication Workshop — COMSPEC Flashbang

Objet Steam Workshop : **3837805212** (`STEAM_ITEM_ID`) —
https://steamcommunity.com/sharedfiles/filedetails/?id=3837805212

Rien dans ce dossier ne publie tout seul. Chaque envoi vers Steam se lance à la main, sur décision de TTRD.

## Contenu de `workshop/`

| Fichier | Rôle |
|---|---|
| `STEAM_ITEM_ID` | identifiant de l'objet Workshop, lu par `workshop-pack.ps1` |
| `STEAM_DESCRIPTION.txt` | description BBCode, FR puis EN (6 132 octets UTF-8, limite Steam 8 000) |
| `STEAM_CHANGELOG.txt` | historique ; le premier bloc `[h1]` est la note de version envoyée |
| `preview.jpg` | image d'aperçu de l'objet, 1280 x 720, sans texte à traduire |
| `gallery/{fr,en}/fb-NN-*.jpg` | galerie 1920 x 1080 (rendus 3D et schémas, aucune capture du jeu) |
| `CAPTURES_A_FAIRE.md` | captures en jeu à prendre plus tard dans la mission de test |
| `DISCORD_ANNONCE.md` | texte de l'annonce Discord (à poster à la main) |
| `workshop-pack.ps1` | assemble le pack propre et la note de version, vérifie la signature |

Bannières en tête de description : `public/assets/workshop/flashbang/{fr,en}/banniere.png`, servies par le site
(`https://athena-comspec.eu/assets/workshop/flashbang/...`), comme celles d'ATAK et de SSE. Elles ne s'affichent sur
Steam qu'une fois la branche fusionnée et le site déployé. Avant cela, envoyer la description sans les deux lignes
`[img]` ou attendre le déploiement.

Régénérer les images : `py -3 tools\make_workshop_gallery.py` (Pillow, police Bahnschrift). Les rendus 3D sources
sont dans `tools\render\workshop_lineup_*` ; pour les refaire : `WORKSHOP=1 blender -b --factory-startup -P
tools\model_flashbang.py`, puis relancer le script de galerie.

## Clé de signature (à garder)

- `build_mod.bat` signe le PBO avec la clé `comspec_flashbang`. La clé privée est créée **une seule fois**, hors
  du dépôt : `%APPDATA%\COMSPEC\signing\flashbang\comspec_flashbang.biprivatekey` (autre dossier possible :
  variable `COMSPEC_FB_SIGNING_DIR`, ou paramètre `-SigningDir` de `tools\sign_pbos.ps1`). Elle est ensuite
  réutilisée à chaque build.
- **La même clé doit servir à toutes les versions** : sinon chaque serveur doit remplacer `comspec_flashbang.bikey`
  dans son dossier `keys`. Sauvegarder ce dossier (coffre de mots de passe ou support chiffré).
- Ne jamais committer ni envoyer la `.biprivatekey` ; seule la `.bikey` publique part dans le pack (`keys\`).
- La clé actuelle a été créée le 11/11/2026 (l'ancienne clé jetable du worktree n'existait plus). Si une version
  signée avec une autre clé a déjà été envoyée sur l'objet, les serveurs devront prendre la nouvelle `.bikey`.

## Étapes de publication

1. **Build** (PowerShell, Arma fermé) :

       cmd /c "E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\.worktrees\flashbang\mod\COMSPEC_Flashbang\build_mod.bat"

2. **Pack propre** : produit `mod\COMSPEC_Flashbang\publisher\@COMSPEC_Flashbang` (ignoré par Git) et
   `mod\COMSPEC_Flashbang\publisher\change_note.txt` :

       powershell -NoProfile -ExecutionPolicy Bypass -File "E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\.worktrees\flashbang\mod\COMSPEC_Flashbang\workshop\workshop-pack.ps1"

   Contenu : `addons\comspec_flashbang_main.pbo` + `.bisign`, `keys\comspec_flashbang.bikey`, `mod.cpp`,
   `logo.paa`. Jamais de sources, outils, mission de test, README ni clé privée. Le script échoue si le PBO est
   plus ancien que les sources, si la signature ne correspond pas à la `.bikey` (DSCheckSignatures -deep) ou si un
   fichier interdit est présent. Il affiche taille et empreinte SHA-256 de chaque fichier.

3. **Mise à jour de l'objet** (contenu + note de version) :

       & "F:\SteamLibrary\steamapps\common\Arma 3 Tools\Publisher\PublisherCmd.exe" update /id:3837805212 "/changeNoteFile:E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\.worktrees\flashbang\mod\COMSPEC_Flashbang\publisher\change_note.txt" "/path:E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\.worktrees\flashbang\mod\COMSPEC_Flashbang\publisher\@COMSPEC_Flashbang" /nologo

4. **Description** (PublisherCmd ne la met pas à jour) : outil de l'ATAK, qui accepte un autre fichier et un
   autre objet. Steam lancé et connecté au compte propriétaire ; d'abord à blanc :

       py -3 mod\COMSPEC_ATAK_Native\tools\steam_set_description.py --item 3837805212 --file mod\COMSPEC_Flashbang\workshop\STEAM_DESCRIPTION.txt --dry-run
       py -3 mod\COMSPEC_ATAK_Native\tools\steam_set_description.py --item 3837805212 --file mod\COMSPEC_Flashbang\workshop\STEAM_DESCRIPTION.txt --note "Description 1.0.0"

   Depuis la racine du dépôt (ou du worktree). La sauvegarde de l'ancienne description va dans
   `mod\COMSPEC_ATAK_Native\publisher\_backup_ws_description_3837805212_*.txt` (ignorée par Git) : normal, l'outil
   range ses sauvegardes là. Aucune modification de l'outil n'est nécessaire.

5. **Page Steam, à la main** (Gérer l'objet) :
   - image d'aperçu : `workshop\preview.jpg` ;
   - « Ajouter / modifier des images et vidéos » : `gallery\fr\fb-01` … `fb-07`, dans l'ordre (les versions EN
     servent aux annonces anglophones) ;
   - « Objets requis » : **CBA_A3** (450814997) ;
   - visibilité : **Masqué** ou **Non répertorié** tant que le mod n'a pas été testé en partie (liste de test du
     README) ; passer en **Public** ensuite.

6. **Annonce Discord** : texte prêt dans `DISCORD_ANNONCE.md`, à poster dans 🚀︱releases (FR puis EN), une fois
   l'objet public.

## Avant la première publication publique

- Noms courts des grenades (« Flash ×1 » … « Flash ×9 », « Drill ») : la description et la note de version les
  utilisent. Ils arrivent par la branche `feat/flashbang-delai` ; publier un pack construit avec ces noms, ou
  revenir aux noms longs dans les deux textes.
- Licence : la ligne « © 2026 COMSPEC, licence COMSPEC : usage personnel depuis ce Workshop ; copie, modification et
  redistribution interdites » reprend celle de l'ATAK ; le mod n'a pas de `LICENSE.txt` (SSE en a un). À confirmer.
- Captures en jeu : voir `CAPTURES_A_FAIRE.md`.
