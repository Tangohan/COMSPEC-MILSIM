# Fabrication textile et rendu du Patch Studio

Cette extension conserve le canevas multicouche, les droits Pro+, les ressources privées, les projets/révisions et les exporteurs existants. Aucun framework graphique, service de rendu, endpoint ou table SQL supplémentaire. Les textures MultiCam déjà embarquées sont réutilisées ; les fibres et fils sont construits de manière déterministe, sans téléchargement externe.

## Utilisation

Choisir **Modèles → Laser Cut · MultiCam** pour une composition complète éditable, ou sélectionner un texte existant et changer **Texte → Fabrication**. Le mode Laser Cut demande une forme dans le même groupe : elle constitue le tissu supérieur. La couche inférieure possède son propre remplissage, sa propre matière et sa propre couleur ; « sans remplissage » laisse les caractères réellement transparents. Les changements de texte, couleur, dimensions et relief sont prévisualisés dès la saisie, sans attendre la sortie du champ.

L’inspecteur contient cinq sections accessibles par la navigation fixe :

1. **Support** : forme, rayon, dimensions, transformations, matière et tissage. Pour une découpe : support associé et bouton de sélection du tissu supérieur.
2. **Texture** : camouflage local ou importé, couleur/dégradé/motif, rotation, échelle, position, répétition, contraste et luminosité. Les deux couches du Laser Cut sont réglables séparément.
3. **Bordure** : couleur unie, camouflage identique au fond ou texture indépendante ; position intérieure/centrale/extérieure, épaisseur, finition brodée/surjetée/thermoscellée/sans couture. Largeur, densité, orientation et relief des fils sur les finitions cousues. Le style classique reste disponible.
4. **Texte** : typographie complète et choix impression/broderie/Laser Cut. Broderie : fils orientés, densité et épaisseur. Laser : profondeur visuelle, largeur/couleur de la tranche thermoscellée, textile inférieur.
5. **Rendu** : direction et élévation de la lumière diffuse, intensité, relief, ombres de contact et détail. Prévisualisation et export y sont accessibles. Les calques restent visibles sous les propriétés.

Grouper ensemble un texte découpé et son support. L’éditeur refuse de séparer leur groupe de fabrication. Dupliquer une sélection ou un groupe remappe les références de support vers les copies. Supprimer le support ou coller une découpe dans un autre projet sans son support convertit les textes associés en impression, afin de conserver leur contenu et un document enregistrable. Masquer une découpe restaure le tissu ; masquer son support masque la construction complète. Les découpes restent associées au support quel que soit leur rang visuel dans les calques : il s’agit d’une opération de fabrication, pas d’un autocollant posé au-dessus des autres éléments.

## Composition et continuité

`patch-studio-core.js` conserve la composition SVG commune au canevas et à l’export. `patch-studio-surface.js` contient les profils de matière, l’échantillonnage des contours et les effets de fabrication :

- Cordura 500D et 1000D : pas de tissage distincts, alternance chaîne/trame et filaments fins.
- Tissu camouflage, nylon et polyester : grain et réponse lumineuse spécifiques. Le matériau et l’image de camouflage restent indépendants : choisir « Tissu camouflage » ne remplace pas la texture choisie.
- Velcro : boucles individuelles disposées de façon déterministe et répétées dans une maille locale.
- Textile brodé : fils orientés, ombre fine et reflet diffus. Le matériau « sans matière » conserve un rendu plat.

Les points de bordure sont distribués à intervalles réguliers sur le périmètre réel de chaque forme. La tangente/normale locale oriente chaque fil, y compris sur les courbes ; les chemins sont regroupés pour réduire le nombre d’éléments DOM. Les finitions brodée et surjetée ont des courbures différentes. Fils sombres, couleur du fil et reflets fins donnent le relief ; aucune lueur ni effet néon. Le budget de génération est borné à 2 000 points par contour et 14 000 par composition (8 000 en mode rapide). Les profils de tissage identiques partagent leur motif SVG.

`stroke.mode = same` réutilise **le même identifiant de remplissage SVG** que le support : même origine, même image, même transformation et mêmes réglages colorimétriques. La largeur de bordure ne modifie pas le cadrage de la texture. Le mode personnalisé utilise `stroke.fill`, soumis au même schéma et aux mêmes contrôles d’accès que les autres remplissages. La collecte des ressources, le chargement privé et le ZIP incluent ces références imbriquées.

Une image sans répétition est placée une seule fois dans un champ couvrant le support, avec proportions conservées ; les bords tournés sont couverts et le cadrage reste indépendant de la couture. Un déplacement ou une réduction dépassant le tissu peut laisser une zone vide, au lieu d’inventer des répétitions. Le champ SVG reste de taille proportionnée au support, afin d’éviter une rasterisation floue par allocation d’un motif géant.

Pour le Laser Cut, une matrice relative convertit les glyphes dans le repère du support, même si le texte et la forme sont tournés ou mis à l’échelle indépendamment. Le tissu inférieur est rendu sous le support et découpé selon les glyphes. Un masque de luminance retire ces mêmes glyphes du tissu supérieur et de son grain. Une tranche fine thermoscellée et une ombre interne orientée matérialisent la profondeur. L’ombre de contact du support est calculée seulement à l’extérieur de sa silhouette : elle ne rebouche pas les trous transparents. L’export d’un texte laser sélectionné inclut son support pour conserver cette construction.

Les identifiants des définitions SVG du canevas et de chaque miniature ont des préfixes distincts : les petits aperçus ne peuvent pas détourner le motif ou le masque du canevas.

## Extensions du document v1

Les propriétés suivantes sont facultatives. Les projets antérieurs restent lisibles/enregistrables sans transformation de leurs données ; ils conservent le rendu classique en l’absence de matière/finition/fabrication.

```json
{
  "render": {
    "lightAngle": 225,
    "lightElevation": 50,
    "lightIntensity": 0.65,
    "relief": 0.75,
    "contactShadow": 0.45,
    "quality": "high"
  }
}
```

Sur une forme ou un texte :

```json
{
  "material": {
    "type": "cordura500",
    "scale": 1,
    "angle": 0,
    "roughness": 0.8,
    "relief": 0.8
  }
}
```

Types : `flat`, `cordura500`, `cordura1000`, `multicam`, `nylon`, `polyester`, `velcro`, `embroidery`. Échelle 0,25–4, angle ±360°, rugosité 0–1, relief 0–2.

Le contour conserve `color`, `width`, `opacity`, `style` et `position` ; extensions facultatives : `mode` (`color|same|custom`), `fill` (remplissage validé, obligatoire en mode custom), `finish` (`legacy|embroidered|overlock|heatcut|none`), `threadWidth` (0,1–3), `density` (0,15–3), `threadAngle` (±70°), `relief` (0–2).

Le texte accepte `construction` : `mode` (`print|embroidery|laser`), `supportId`, `depth` (0–4), `sealColor` (HEX), `sealWidth` (0–2), `threadWidth` (0,1–3), `density` (0,15–3), `threadAngle` (±90°). `supportId` doit désigner une forme du même tableau de calques pour le mode laser. `text.fill` et `text.material` définissent la couche inférieure. Aucun chemin, URL, shader ou markup libre n’est accepté.

Le contrat exécutable reste `app/Services/PatchStudio/Document.php` : clés inconnues refusées, valeurs numériques finies et bornées, 160 objets, profondeur 5, document 1 Mio. Les réglages de fabrication sont enregistrés dans le JSON courant et dans l’historique existant. Aucune migration supplémentaire.

## PNG haute résolution et déploiement

Le SVG est rasterisé **directement à la résolution d’export**, en conservant le `viewBox` du projet ; il n’est plus rasterisé à 512 px avant agrandissement. Les motifs et proportions restent cohérents, avec des fils et glyphes nets. PNG natif ou préréglages jusqu’à 4096 × 4096, canevas complet ou sélection, fond du projet/transparent/clair/sombre. Les proportions sont conservées par cadrage centré. PAA, ZIP, PBO et publication conservent un budget de 2048 px par côté et l’encodeur PAA existant.

Côté serveur : PNG 32 Mio maximum, côtés 32–4096, maximum 16 777 216 pixels, signature vérifiée et réencodage GD. Le POST d’export est borné à 48 Mio pour le base64. Les imports restent limités à **8 Mio et 8 mégapixels**. Le verrou de concurrence et les 12 exports/minute restent actifs. Une estimation mémoire refuse le rendu **avant décompression** si le worker ne peut pas le traiter.

Pour tous les préréglages 4K : configurer `memory_limit=512M`, `post_max_size=48M` minimum et une limite de corps HTTP cohérente (par exemple `client_max_body_size 48m` sur Nginx). 256M suffit à certains rendus ; le serveur peut refuser un projet dense en 4K avec une erreur explicite. Déployer les trois modules `.js` et le CSS ensemble ; le cache de version existant les renouvelle. Pas de nouveau build DLL/SQF ni de migration pour cette extension graphique.

## Validation et limites précises

Voir `VALIDATION.md`. Les tests Chromium vérifient notamment : absence de décalage à la couture par comparaison des pixels, alpha réel des trous, export 4096 × 2048 et 4096 × 4096 via le contrôleur, bouton d’export avec choix de fond, réponse distincte des sept textiles à texte identique, prévisualisation immédiate et pixels identiques après réouverture à froid. Les scripts Node/PHP couvrent références, duplication/groupe/suppression, limites et droits des textures de bordure.

Le rendu est une simulation textile **2,5D** par géométrie SVG, tissages et ombres, sans solveur de lumière, maillage 3D ou scans photométriques des marques de tissu. Cordura/nylon/polyester sont des profils visuels paramétriques, pas une mesure certifiée de leurs propriétés physiques. Les variations de forme/fibres et de lumière améliorent la perception de fabrication ; les plis libres, auto-ombres 3D et déformations physiques ne sont pas simulés. Le résultat n’est pas annoncé comme indiscernable d’une photographie. Le calcul reste reproductible dans le même navigateur ; la fidélité entre moteurs de navigateur n’est pas certifiée. Les contraintes Arma/IR/multijoueur du Studio restent inchangées.

Pour revenir à une version de l’application antérieure à ces extensions JSON, conserver une sauvegarde des projets et de leurs révisions : l’ancien validateur ne reconnaît pas ces nouveaux champs. Restaurer une révision antérieure à la fabrication pour les projets concernés avant de réutiliser l’ancien éditeur, ou conserver ce lecteur/validateur. Ne pas supprimer des propriétés du JSON à l’aveugle : cela détruirait la construction laser. Aucun rollback de base nécessaire.
