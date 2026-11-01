# Bandeau de don qui ne s'active pas

## Contexte
Admin > Paiements > Dons (`/admin/paiements?vue=dons`), section « Bandeau de don ».

## Symptôme
Cocher « Afficher le bandeau » puis « Enregistrer le bandeau » : la page revient, la case est décochée, l'état reste
« Désactivé » et rien ne s'affiche sur le site.

## Cause
Tant qu'aucun message n'est écrit, l'aperçu et les champs montrent le message d'exemple (texte indicatif des zones de
saisie), mais les champs sont vides. `DonationBanner::pairsFromForm` refusait alors l'activation (« Écrivez le message
du bandeau… ») sans rien enregistrer, et l'erreur s'affichait en haut de la page alors que la redirection ramène à
l'ancre `#bandeau-don`, plus bas : l'administrateur ne voyait jamais le refus.

## Correctif
- Activer sans message enregistre le message d'exemple vu dans l'aperçu (`DEFAULT_MESSAGE_FR` / `DEFAULT_MESSAGE_EN`).
- Les messages de l'enregistrement (succès ou erreur) s'affichent dans la section du bandeau (flash `dnb_notice`).

## Fichiers touchés
`app/Services/Platform/DonationBanner.php`, `app/Controllers/Admin/System/SystemSettingsController.php`,
`views/admin/system/partials/payments_donation_banner.php`.

## Vérification
`tests/Unit/ContributePageAndDonationBannerTest.php::testEnablingWithEmptyMessageSavesTheExampleShownInPreview`
(échoue avant le correctif). En ligne : cocher la case sans écrire de message, enregistrer, l'état passe à « Affiché en
ce moment » et le bandeau apparaît sur l'accueil (jusqu'à 5 minutes de cache au plus, vidé à l'enregistrement).

## Statut
corrigé
