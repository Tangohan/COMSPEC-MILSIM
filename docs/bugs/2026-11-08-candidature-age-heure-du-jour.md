# Candidature : âge faux d'un an le jour de l'anniversaire, selon l'heure

## Contexte
`CandidateAccountIdentity::ageFrom`, arrivé avec #1420. `CandidateAccountIdentityTest` rouge sur main dès que l'horloge dépasse 12 h UTC.

## Symptôme
Le jour de son anniversaire, un candidat a un an de moins tant que l'heure courante n'a pas dépassé l'heure d'exécution du calcul. Le test échoue ou passe selon l'heure de la CI.

## Cause
`createFromFormat('Y-m-d', ...)` sans `!` complète la date avec l'heure courante, au lieu de minuit.

## Correctif
Format `!Y-m-d` : la naissance est à minuit, le résultat ne dépend plus de l'heure.

## Fichiers touchés
`app/Services/Recruitment/CandidateAccountIdentity.php`

## Vérification
`CandidateAccountIdentityTest`, attente de 26 ans rétablie (un commit précédent l'avait changée à tort en 25).

## Statut
corrigé
