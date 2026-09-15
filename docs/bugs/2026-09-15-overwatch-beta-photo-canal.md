# Overwatch Beta — Envoyer une photo échouait

## Contexte
Renseignement, bouton Envoyer sur une photo reçue.

## Symptôme
La transmission échouait. Un message générique partait parfois sur le canal, sans le lien de la photo.

## Cause
Envoyer appelait une action photo qui n’existe pas. Le poste se rattrapait en n’envoyant qu’un texte.

## Correctif
Envoyer pose directement le libellé et le lien de la photo sur le canal ouvert.

## Fichiers touchés
- `public/assets/js/atak-overwatch-beta.js`

## Vérification
Renseignement → Envoyer : le canal actif reçoit le texte et le lien de l’image. Plus d’échec silencieux.

## Statut
corrigé
