# Couvertures, infiltrés et « Ma couverture »

Version anglaise : [ma_couverture.en.md](ma_couverture.en.md). Sommaire : [index.md](index.md).

## À quoi ça sert

Un joueur (ou une IA) **infiltré** vit sous une identité de couverture : des papiers de nature « couverture » et une **légende** (adresse, employeur, métier, famille, phrases types). Tant que la couverture tient, les ennemis ne le prennent pas pour cible et les checkpoints IA le contrôlent comme un civil. Elle **saute** (« brûlée ») quand il se trahit. L'app **Ma couverture** du téléphone montre au joueur sa fiche, sa cohérence et ce qui le trahit ; le Zeus y voit tous les infiltrés.

## Où le trouver

| Besoin | Chemin |
|---|---|
| Infiltrer un joueur (Zeus) | ZEN › **COMSPEC · Identité › Donner une couverture** sur la personne |
| Infiltrer dans Eden | Attributs de l'unité › COMSPEC SSE — Papiers › **Infiltré (couverture)** (+ Nature des papiers : Couverture préparée) |
| Par Athena | Brief de couverture (Préparation de mission › briefs) ou papier « couverture » dans le kit : l'infiltration s'ouvre à l'arrivée |
| Par l'éditeur SSE | Kit **Infiltration** (identité + papiers de couverture + légende), « Sur quelqu'un » |
| Sa fiche | Téléphone ATAK › Renseignement › **Ma couverture** |
| Répondre à un garde | App Ma couverture, ou menu ACE personnel › **Mes papiers › Répondre** |
| Zone interdite | ZEN › **COMSPEC · Identité › Poser une zone interdite** (Rayon 0-500 m, Nom ; 0 retire la plus proche) ou marqueurs nommés `comspec_id_interdit…` |

## Le module « Donner une couverture »

| Champ | Valeurs | Exemple |
|---|---|---|
| **Action** | Infiltrer · Brûler la couverture · Lever l'infiltration | Infiltrer |
| **Captif tant que la couverture tient** | Selon le réglage de mission · Oui · Non | Oui : les IA ennemies ne tirent pas tant qu'il n'est pas brûlé |
| **Lui donner une carte de couverture** | case (non) | Cochée : carte d'identité de couverture (qualité 90) au nom saisi ou tiré |
| **Nom de la couverture**, **Prénom de la couverture** | texte | « STAVROU », « Nikos » |

## L'app « Ma couverture »

Visible de vous seul (et du Zeus) : **état** (tient / brûlée), **cohérence** (0 à 100) et **ce qui vous trahit**, identité, adresse, employeur, profession, téléphone, contacts, documents complémentaires, historique, biographie, famille, langues, phrases types, sujets sensibles, secrets, brief et consignes d'Athena. Onglet **ZEUS** (Zeus seulement) : tous les infiltrés, leur cohérence, leur fiche avec le vrai nom.

Cohérence = papiers (une pièce de couverture, un seul nom, sa préparation) + légende (réponses justes) + équipement (rien de militaire en vue) + comportement (soupçons). Elle pèse sur l'examen visuel d'un contrôleur et sur la suspicion des checkpoints.

## Les questions de légende

Après la lecture des papiers, un garde pose 2 questions (3 au poste strict) : « D'où venez-vous ? », « Pour qui travaillez-vous ? », « Raison du déplacement ? », « Quel est votre métier ? », « Où êtes-vous né ? », « Qui peut confirmer ce que vous dites ? ». Trois réponses au choix (la vraie légende et deux leurres), dans Ma couverture ou **Mes papiers › Répondre**. Délai : 20 s (réglage), +10 s par question. Mauvaise réponse ou silence : soupçons.

## Pas à pas : traverser un checkpoint ennemi

1. Avant la mission, le Zeus : **Donner une couverture › Infiltrer**, carte cochée, « STAVROU Nikos ». Vous recevez la carte et votre légende dans **Ma couverture** : lisez-la (métier, employeur, lieu de naissance).
2. Déposez tout ce qui est militaire (arme longue dans le dos, gilet, casque, JVN) : l'app liste ce qui vous trahit ; visez une cohérence au-dessus de 70.
3. Au checkpoint : « Papiers, s'il vous plaît » sur l'ATAK. À 3 m du garde, **Mes papiers › Présenter mes papiers › Carte d'identité › Présenter** (dans les 45 s).
4. Deux questions : répondez dans **Ma couverture** (la réponse qui colle à votre légende) avant la fin du délai.
5. Laissé passer : la couverture tient. Retenu : soupçons ; alerte : brûlée, les ennemis à 400 m passent en combat.

## Pièges fréquents

- **Ce qui brûle la couverture tout de suite** : coup de feu près d'un ennemi, menottes, alerte d'un checkpoint, arme trouvée à la fouille, arme à la main vue par un ennemi (distance des témoins : 50 m, réglage).
- **Ce qui accumule les soupçons** (limite 100, réglage) : arme longue dans le dos, tenue militaire, gilet, casque, JVN vus de près ; zone interdite ; coup de feu entendu ; contrôle raté ; mauvaises réponses ; être retenu.
- Brûlée : vous êtes prévenu **discrètement** (vibreur), le Zeus et le compte rendu AAR aussi ; les ennemis connaissent votre position.
- La couverture passe N0 à N3 mais **pas la biométrie** : un N4 ou un SEEK vous identifie si vous êtes fiché.
- Plusieurs papiers à des **noms différents** sur vous font chuter la cohérence (un seul nom).
- Un **joueur sans papier Athena** voit dans Ma couverture une identité tirée par le mod, avec la raison (paquet non chargé, aucun papier pour ce compte Steam…).
- Réglages : Infiltré captif tant que sa couverture tient (oui) · Soupçons avant que la couverture saute (100) · Distance des témoins (50 m) · Questions de légende (oui) · Délai pour répondre (20 s).

## Athena

- Le **brief de couverture** préparé sur Athena (Préparation de mission › briefs) arrive dans Ma couverture (brief et consignes) et ouvre l'infiltration à l'arrivée.
- La carte de couverture du kit Athena est **celle du membre** : au contrôle, c'est l'identité Athena de couverture qui s'affiche.
- Couverture brûlée et contrôles subis remontent sur Athena (compte rendu, Contrôles subis).
