# Prérequis manuels (attestation par un instructeur)

Tout ne se mesure pas dans Arma 3. Pour ce que le jeu ne sait pas compter, la règle d’obtention d’une qualification accepte des **conditions attestées** : un instructeur les valide membre par membre, avec une note qui renvoie à la preuve. Le reste de la règle (heures, sauts, cours Academy…) continue d’être vérifié automatiquement.

## 1. Les types d’attestation

| Type | Nature | Exemple de libellé | Pourquoi ce n’est pas mesuré |
|---|---|---|---|
| Sauts en chute libre | Nombre, propre au membre | « 50 sauts en chute libre » | Le mod compte les sauts sans savoir si la voile a été ouverte à la main ou par sangle. |
| Sauts à ouverture automatique | Nombre, propre au membre | « 5 sauts à ouverture automatique, dont 1 de nuit » | Même raison. |
| Heures de plongée | Nombre, propre au membre | « 10 h de plongée, dont 2 h de nuit » | Le mod ne relève pas le temps sous l’eau. |
| Qualification de tir | Épreuve propre à la qualification | « Stand : 8 impacts sur 10 à 600 m » | Le mod ne relève ni les tirs ni les impacts. |
| Épreuves physiques | Épreuve propre à la qualification | « Marche de 12 miles en moins de 3 h », « 500 m de nage » | Pas de mesure de charge, de chrono d’épreuve ni de nage. |
| Autre condition (« Condition attestée par un instructeur ») | Épreuve propre à la qualification | « 6 guidages 9-LINE complets », « Vol de contrôle réussi », « Plan de mission présenté » | Jugement d’un instructeur. |

**Nombre propre au membre** : une attestation de sauts ou d’heures vaut pour **toutes** les qualifications qui la demandent. Un membre attesté « 60 sauts en chute libre » remplit d’un coup « 50 sauts » du chef largueur et « 20 sauts » du chuteur. Une **épreuve** (tir, test physique, autre) est au contraire propre à la qualification et à son libellé.

## 2. Comment attester

1. Ajoutez la condition dans la règle de la qualification (Référentiel › Qualifications › la qualification › Modifier, rubrique « Comment l’obtenir »), en choisissant le type et, pour une épreuve, un libellé précis.
2. Sur la fiche de la qualification, l’encart **« Conditions attestées par un instructeur »** liste chaque condition, les membres déjà attestés et ceux qui peuvent l’être.
3. Choisissez le membre, saisissez la valeur (nombre de sauts, d’heures) si la condition en porte une, et une **note** : date de la séance, lieu (carte, serveur), nom de l’instructeur, lien vers la preuve.
4. L’attestation peut être corrigée ou retirée depuis le même encart.

Dès que l’attestation est enregistrée, le membre progresse dans la règle ; si tout est rempli, la qualification est délivrée (ou proposée) à la vérification suivante, toutes les heures.

## 3. Bonnes pratiques

- **Un libellé = un standard** : « 8/10 à 600 m, de jour, couché, fusil de dotation » plutôt que « tir validé ».
- **Deux personnes** pour les qualifications de haut niveau : l’instructeur qui conduit la séance et un second qui contrôle la preuve.
- **Attestez juste après la séance**, quand la preuve est fraîche.
- **Ne remplacez pas une mesure par une attestation** : si le mod compte la donnée (sauts, heures de vol), mettez le seuil mesuré et gardez l’attestation pour ce qu’il ne distingue pas.
- **Expliquez la règle** dans la description de la qualification, pour que les membres sachent ce qui sera attesté et comment s’y préparer.
- **Fixez une validité** aux épreuves qui se dégradent (tir, condition physique) : la qualification passe « à renouveler » à l’échéance.

## 4. Preuves recevables

| Preuve | Pour quoi | Où la garder |
|---|---|---|
| Captures d’écran ou vidéo | Groupement au stand, altimètre à l’ouverture, tableau de score | Dossier partagé de la communauté ; lien dans la note |
| Compte rendu de fin d’opération (AAR) | Déroulé de la séance, participants, résultats | Comptes rendus du site (validé par un responsable) |
| Messages de l’app JTAC | 9-LINE, 5-LINE, MEDEVAC transmis, statut jusqu’au BDA | Données de l’ATAK, consultées par l’instructeur |
| Carnet de vol contresigné | Vols de nuit, aux instruments, en patrouille | Carnet de vol du pilote (cursus pilote) |
| Fiche de séance (FRM, fiche d’évaluation) | Critères notés un par un, « GO / NO GO » | Document de la communauté ; lien dans la note |

## 5. Ce que le mod pourrait mesurer à l’avenir

Ces données ne sont pas relevées aujourd’hui ; elles permettraient de remplacer certaines attestations par des seuils automatiques :

- **Chute libre / ouverture automatique** : distinguer l’ouverture commandée de l’ouverture par sangle, et l’altitude d’ouverture.
- **Temps sous l’eau** : durée en plongée, profondeur, recycleur ou bouteille.
- **Tirs et impacts** : coups tirés, touches, distance de la cible, par arme.
- **Marche chargée** : distance à pied avec le poids porté et le dénivelé.
- **Messages JTAC comptés** : nombre de 9-LINE / 5-LINE complets menés jusqu’au BDA, par membre.

En attendant, attestez ces conditions avec un libellé précis : le jour où une mesure existera, la règle pourra basculer sans changer la qualification.
