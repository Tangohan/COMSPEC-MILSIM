# Fiches de renseignement simplifiées (`ATH-SSE-FICHES`)

## Pourquoi

Entre « je n'ai rien à signaler » et « j'ouvre un dossier d'intérêt », il manquait
la marche la plus basse : noter tout de suite ce qu'on vient de voir, sans
formulaire à remplir et sans rien conclure. Une plaque relevée, une attitude
inhabituelle, un axe soudain désert, une conversation avec un habitant : autant
d'éléments qui se perdaient parce que les consigner coûtait plus cher que de les
oublier.

La fiche de renseignement simplifiée est cette marche. Elle demande cinq choses,
et rien d'autre :

- un **texte libre** (1000 caractères) ;
- une **date** et une **heure** ;
- un **lieu** et, si disponible, un **repère de carroyage** ;
- un ou plusieurs **thèmes**, qui orientent la fiche vers le bon analyste ;
- jusqu'à **quatre pièces jointes** (photographies, captures, documents).

## Ce qu'une fiche n'est pas

Une fiche **n'identifie personne** et **ne vaut pas preuve**. Elle consigne un
constat daté et situé. Toute conclusion passe par un dossier, un rapprochement
et une validation humaine — la règle fondamentale du module SSE s'applique sans
exception aux fiches.

## Deux surfaces de saisie, une seule mécanique

| | Portail SSE | ATAK (in-game) |
|---|---|---|
| Accès | *Pilotage → Fiches de renseignement*, ou le bouton « Rédiger une fiche » de la barre supérieure | Menu **RENS** du tiroir ATAK, icône « Fiche RENS » de l'écran d'accueil, ou action ACE « Rédiger une fiche de renseignement… » |
| Surface | Rédacteur plein écran (`/atak/sse/fiches/nouvelle`) | Rédacteur plein cadre, enfant de `cTab_Android_dlg` |
| Pièces jointes | Fichiers de l'ordinateur : photo, capture, PDF, texte | Capture de la scène, bibliothèque photo ATAK, relevé de position |
| Hors liaison | Sans objet | Fiche conservée et transmise au rétablissement |

Les deux rédacteurs partagent la même disposition — date à gauche, lieu à
droite, étiquettes, cadre de rédaction avec compteur, volet des pièces jointes,
barre d'action basse — et le **même référentiel de libellés**. Un opérateur qui
passe du jeu au portail ne réapprend rien.

Côté serveur, les deux passent par `App\Services\Sse\SseFieldNoteService` :
mêmes règles de validation, même journalisation, mêmes limites.

## Types de fiche

Le sigle apparaît en pastille bleue à côté des thèmes.

| Sigle | Type | Quand l'utiliser |
|---|---|---|
| `FRM` | Fiche de renseignement de mission | Ce que vous avez constaté pendant la mission en cours |
| `FRO` | Fiche d'observation | Un fait observé, sans lien direct avec la mission du jour |
| `FRC` | Fiche de contact | Un échange avec une personne, un groupe, une autorité locale |
| `FRA` | Fiche d'ambiance | Le climat d'un secteur : attitude de la population, tensions |
| `FRT` | Fiche technique | Matériel, véhicule, installation, marquage ou signe distinctif |

## Thèmes

Quatre thèmes au maximum. La couleur de la pastille suit la gravité : rouge pour
`Sécurité publique`, `Menace armée` et `Engins explosifs` ; orange pour
`Ordre public`, `Trafics` et `Mouvements et flux` ; bleu pour `Population et
attitude`, `Infrastructures`, `Communications` et `Logistique adverse` ; gris
pour `Environnement et terrain` et `Divers`.

L'ordre du référentiel est contractuel : chaque bascule du rédacteur ATAK est
câblée sur un **rang**, pas sur un code. Réordonner les thèmes côté serveur sans
mettre à jour `fn_intelNoteCatalog.sqf` décalerait toutes les bascules — un test
verrouille cette correspondance.

## Degré d'urgence

- **Courant** — à exploiter dans le cours normal du travail.
- **Prioritaire** — à regarder dans la journée.
- **Immédiat** — doit remonter tout de suite au poste de commandement.

## Suivi côté bureau

Une fiche reçue est **Transmise**. L'analyste habilité la fait ensuite passer en
**Prise en compte**, **Exploitée** ou **Classée sans suite**, avec un
commentaire de suivi. Il peut la rattacher à un dossier validé : la fiche
apparaît alors dans le dossier concerné, sans rien conclure sur les personnes
qui y sont citées.

Chaque fiche alimente également le **journal des transmissions terrain**
(`/atak/sse/transmissions`) sous la forme d'un événement `REPORT_RECEIVED`, et le
journal d'activité ATAK sous le type `SSE_FIELD_NOTE`.

## Habilitations

| Geste | Permission |
|---|---|
| Lire la file et une fiche | `atak.sse.access` (habilitation SSE active) |
| Rédiger une fiche, joindre ou retirer une pièce | `atak.sse.access` |
| Suivre une fiche, la rattacher à un dossier | `atak.sse.case.manage` |

Les sessions **invitées** (entrées par code d'accès) ne peuvent pas rédiger : le
rédacteur leur est fermé, la lecture reste possible dans les limites de leur
habilitation.

## Documents liés

- [Référence API et pont ATAK](api.md)
- [Dictionnaire de données](dictionnaire-donnees.md)
