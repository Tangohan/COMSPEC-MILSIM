# Écrans du poste : menus ACE en double et écran noir

## Contexte
ATAK natif 2.9.24, pack de test FN (main.pbo) avec le mod du Workshop, moniteur désigné Écran COMSPEC et écrans TOC
dans la mission. Capture et journal du joueur du 01/11.

## Symptôme
- Menu ACE d'un écran : « Écran COMSPEC » deux fois, « Écran TOC » superposé, sous-menus en surimpression ; les
  actions ne font rien.
- Diapositive diffusée sur l'écran : écran noir, alors que la même diapositive s'affiche dans l'app Briefing.

## Cause
- Jusqu'à trois racines ACE sur un même écran :
  - la racine de classe (`fn_tocScreen aceInit`), posée avec héritage : une classe dérivée d'une classe listée en
    recevait deux ;
  - la racine « Écran COMSPEC » ajoutée sur l'objet par `fn_screenCast actions` ;
  - la racine « Écran TOC » ajoutée sur l'objet par `fn_tocScreen actions`.
- Écran noir, en deux temps :
  - l'écran retéléchargeait l'image dans le fichier de cache déjà ouvert par Arma pour l'app Briefing ; l'écriture
    était refusée, d'où « fichier illisible » ;
  - le repli par navigateur intégré refusait ensuite l'adresse athena-comspec.eu, parce que le joueur était connecté
    par athena.ttrd.fr (`host_not_allowed`).
- À la fin d'une diffusion sur un écran TOC, la texture d'origine recouvrait la carte du TOC, que `fn_tocScreen` croyait
  encore posée.

## Correctif
- Menus ACE :
  - une racine unique, posée sur la classe exacte (sans héritage) ou sur l'objet, une seule fois ;
  - sous-menus Diffusion, Affichage TOC et Carte ;
  - racine posée dès l'arrivée du joueur.
- Garde contre deux copies du mod : initialisations exécutées une seule fois, avertissement dans le journal et sur le
  téléphone (`fn_dupCheck`).
- COMSPEC Link 2.0.114 :
  - `ImageDataChunk` accepte les portails officiels ;
  - `DownloadBriefingSlideImage` renvoie le fichier déjà présent quand il est verrouillé.
- `fn_screenCast` reprend l'image de l'app Briefing ; la carte du TOC est reposée après une diffusion.

## Fichiers touchés
- `fn_tocScreen.sqf`, `fn_screenCast.sqf`, `fn_tocCam.sqf`
- `XEH_postInitClient.sqf`, `XEH_postInitServer.sqf`
- `fn_dupCheck.sqf`
- `Extension.cs`

## Vérification
- `hemtt check` (erreurs connues de `ctrlWebBrowserAction` seulement), `dotnet build -c Release`, PHPUnit.
- À faire en jeu, avec ACE, pour chacune de ces sources :
  - un moniteur désigné par le module ;
  - un écran TOC ;
  - une variante noire du grand écran sur trépied ;
  - l'app Briefing ouverte sur la même diapositive, puis diffusion.

## Statut
Corrigé, à vérifier en jeu (2.9.25).
