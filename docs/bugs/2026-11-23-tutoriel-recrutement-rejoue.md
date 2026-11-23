# Tutoriel du bureau recrutement rejoué à chaque visite

## Contexte
Page `/back-office/ressources/recrutement` : la modale d'accueil du module (`views/partials/lms_module_entry_modal.php`, script `public/assets/js/lms_module_entry_modal.js`) s'ouvre automatiquement. Même mécanisme pour Formations (`lmsModuleEntryAuto = 'formation'`) et les liens `data-lms-module-entry` du tableau de bord.

## Symptôme
Le tutoriel se rejoue à chaque nouvel onglet ou nouvelle session, même après avoir été fermé ou terminé.

## Cause
Dans l’ancien script (origin/main, `lms_module_entry_modal.js` lignes 69, 204, 228 et 242-255), fermer la modale (croix, Échap, fond) ou cliquer « Continuer » appelait seulement `markWelcomed()`, qui écrit dans `sessionStorage` (propre à l'onglet, perdu à sa fermeture). L'écriture durable dans `localStorage` (`markDismissed()`) n'avait lieu que si la case « Ne plus afficher pour ce module » était cochée (`skipInput.checked`). Le garde d'ouverture automatique (`isDismissed || isWelcomed`) ne retrouvait donc plus rien dans un nouvel onglet. Reproduit dans Edge avec l'ancien script : deux chargements, même profil, la modale se rouvre.

## Correctif
- Fermer ou continuer mémorise toujours « vu » dans `localStorage`, sous la forme de la version vue (l'ancien `true` vaut version 1) ; la case est supprimée.
- Version explicite : `LmsModuleEntry::TUTORIAL_VERSION` (1). L'incrémenter rejoue le tutoriel une fois pour tous.
- Bouton « Revoir le tutoriel » (`data-lms-entry-replay`) dans l'en-tête du bureau recrutement.

## Fichiers touchés
`public/assets/js/lms_module_entry_modal.js`, `app/Support/LmsModuleEntry.php`, `views/partials/lms_module_entry_modal.php`, `public/assets/css/lms_module_entry_modal.css`, `views/admin/recruitment_workspace/dashboard.php`, `tests/Unit/RecruitmentTutorialSeenTest.php`.

## Vérification
Edge réel, même profil : fermeture au 1er chargement, modale masquée au 2e ; rouverte par « Revoir le tutoriel » ; rouverte si la version passe à 2. Tests unitaires `RecruitmentTutorialSeenTest`.

## Statut
corrigé à vérifier sur le site
