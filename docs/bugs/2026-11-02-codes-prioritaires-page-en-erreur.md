# Codes prioritaires : la page tombait en erreur

## Contexte
Ajout des liens d'invitation partageables (Recrutement › Liens d'invitation), qui étendent la table des codes
prioritaires. En vérifiant le câblage des contrôleurs du recrutement, le contrôleur des codes prioritaires s'est révélé
absent du Container.

## Symptôme
Ouvrir **Recrutement › Codes prioritaires** (`/back-office/recruitments/codes-invitation`, ses fiches et son formulaire)
affichait une page d'erreur.

## Cause
`RecruitmentInviteCodesController` exige trois dépôts dans son constructeur mais n'était pas déclaré dans
`app/Core/Container.php` : le routeur retombait sur `new RecruitmentInviteCodesController()` sans argument. Le test
`RoutedControllersContainerWiringTest` ne le voyait pas, car il ne lisait que les contrôleurs écrits avec leur nom
complet dans `routes/web.php`, pas ceux importés par `use` et cités par leur nom court.

## Correctif
- Contrôleur déclaré dans le Container avec ses dépendances.
- Le test lit aussi les `use App\Controllers\…;` des fichiers de routes.

## Fichiers touchés
- `app/Core/Container.php`
- `tests/Unit/RoutedControllersContainerWiringTest.php`

## Vérification
Back-office › Recrutement › Codes prioritaires : la liste s'ouvre, « Créer un code prioritaire » aussi.

## Statut
corrigé
