# Carte du téléphone illisible et fenêtres de l'Overwatch qui ne se ferment pas

## Contexte
Capture d'un joueur sur iPhone (/m/carte, Altis) et retours sur l'Overwatch : la carte de « Sur mon téléphone » ne
ressemblait pas à celle de l'Overwatch, et sur l'Overwatch certaines fenêtres de marqueur restaient ouvertes.
Recette faite avec un banc local (vraies vues et vrais contrôleurs /m/*, /9-line, Overwatch ; base SQLite de
démonstration ; session du téléphone ouverte par un vrai QR code racheté ; Edge sans écran à 390 et 1440 px, FR et EN).

## Symptômes → causes → correctifs
| Symptôme | Cause | Correctif |
| --- | --- | --- |
| Marqueurs en ronds jaunes identiques, sans icône, couleur ni nom | La carte du téléphone avait son propre dessin (`L.circleMarker` + 8 couleurs) au lieu du rendu des marqueurs du jeu | Carte du téléphone = module partagé `atak-overwatch-mapkit.js` (extrait de l'Overwatch) : `ArmaMapMarkers`, symboles APP-6, tracés, appuis |
| Bande noire à gauche, carte décalée | Pas de limites de carte ni de zoom minimal sur le téléphone, centrage sur le joueur près d'un bord | Mode « mobile » du module : `setMaxBounds` (viscosité 1) et zoom minimal = terrain qui couvre l'écran |
| Marqueur dessiné hors du terrain / absent | Position arrivée en texte (`"[3600.5,13100.2,0]"`) lue caractère par caractère (`pos[0]` = `[`) ; décalage du terrain ignoré par le téléphone | `markerWorld` et `ArmaMapMarkers.parsePos` lisent les nombres du texte ; même configuration de carte (`AtakOverwatchMapConfig`, décalage compris) |
| Boutons MOI / + MARQUEUR sur la carte, pas de zoom | Gros boutons ronds flottants à droite | Barre basse compacte : Moi, Calques, + Marqueur, −/+, plein écran |
| Carte du téléphone figée sur un réseau qui ne répond plus | Requête sans délai maximal : le rafraîchissement suivant attendait toujours | Abandon au bout de 10 s, signal « Hors réseau, carte de HH:MM » |
| Overwatch : fiche d'un marqueur pas refermée en touchant la carte | Le clic carte (outil curseur) partait à l'inspection de la scène 3D sans fermer la fiche | `closeArmaMarkerSheet()` au clic carte, avant l'inspection |
| Overwatch : filtres du plan de bâtiment et barre d'écoute radio visibles alors que masqués | `display:flex/grid` de leur classe l'emportait sur `hidden` (même cause que #1345 sur le téléphone) | `.ow-root [hidden]{display:none!important}` |
| Overwatch : fiche 9-LINE refermée à chaque rafraîchissement, Échap sans effet | Couche des appuis refaite à chaque relève ; Échap ne fermait pas les fenêtres Leaflet | Couche refaite seulement si les appuis changent (`jtacGeoSignature`) ; Échap → `map.closePopup()` |
| 5-LINE sans cible sur l'Overwatch | `geo_json` non décodé pour les lignes de `/api/nine-line` | `rowGeo()` décode `geo_json` |
| /9-line en anglais : titres et état du brouillon en français | Le dictionnaire JS est découpé sur « · » mais `i18n_dom.js` ne traduisait que le texte entier | Traduction morceau par morceau des textes « A · B » |
| /9-line en anglais : exemples des champs multilignes en français | `i18n_dom.js` ignorait tout `textarea`, y compris son `placeholder` | Attributs traduits aussi sur les `textarea` (le texte saisi reste tel quel) |
| En-tête /m en anglais : « CARTE » | Titre de l'app marqué `translate="no"` | Attribut retiré |

## Fichiers touchés
`public/assets/js/atak-overwatch-mapkit.js`, `public/assets/css/atak-overwatch-mapkit.css` (nouveaux, extraits de
l'Overwatch), `public/assets/js/atak-overwatch-beta.js`, `public/assets/css/atak-overwatch-beta.css`,
`views/atak-overwatch-beta.php`, `public/assets/js/atak-m.js`, `public/assets/css/atak-m.css`, `views/m/*`,
`public/assets/js/arma-map-markers.js`, `public/assets/js/i18n_dom.js`, `app/Support/AtakOverwatchMapConfig.php`,
`app/Services/Atak/AtakMobileOverwatchAccess.php`, `app/Core/Session.php`, `app/Core/Application.php`.

## Vérification
- Marqueurs aux 4 coins et au centre d'Altis (worldSize 30720) + marqueur à position texte : chaque marqueur au pixel
  attendu, dans le terrain ; vue toujours dans le terrain (pas de bande noire).
- Scénarios /m/carte, /m/briefs, /m/cr, /m/compte-rendu, /m, session terminée, hors réseau : 390 et 1440 px, FR et EN,
  sans erreur JS ni réponse inattendue.
- Overwatch : fiche de marqueur fermée par ×, Échap, clic à côté, non rouverte au rafraîchissement (échouait sur main).
- PHPUnit : AtakMobileLinkTest (lectures Overwatch accessibles avec la session du téléphone, refusées sans), tests de
  câblage Overwatch.

## Statut
Corrigé (à confirmer en jeu : marqueurs posés en jeu, 9-LINE reçu par un pilote).
