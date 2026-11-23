<?php
declare(strict_types=1);
/**
 * Choix du niveau de diffusion, en cartes. Les niveaux au-dessus de l'habilitation de la session sont proposés grisés.
 * @var array<string,string> $classifications code => libellé
 * @var string $maxLevel plafond de la session
 * @var string $classDefault niveau coché par défaut (déjà ramené au plafond)
 * @var string $classWhen condition d'affichage (optionnel)
 * @var string $classLegend
 */
use App\Services\Sse\SseRedactionService;
use App\Support\SseCreationBrief;
use App\Support\SseWizardUi;

$classOptions = [];
foreach ($classifications as $code => $label) {
    $above = SseRedactionService::levelRank((string) $code) > SseRedactionService::levelRank($maxLevel);
    $classOptions[$code] = [
        'label' => $label,
        'desc' => SseCreationBrief::CLASSIFICATION_HELP[$code] ?? '',
        'disabled' => $above,
        'note' => $above ? 'Au-dessus de votre habilitation' : '',
    ];
}
?>
<p class="wz-clear">Votre habilitation actuelle : <b><?= htmlspecialchars($classifications[$maxLevel] ?? $maxLevel, ENT_QUOTES, 'UTF-8') ?></b>. Vous ne pouvez pas diffuser à un niveau supérieur.</p>
<?= SseWizardUi::cards('classification', $classLegend ?? 'Niveau de diffusion', $classOptions, $classDefault, [
    'required' => true,
    'when' => $classWhen ?? '',
    'help' => 'En cas de doute, gardez le niveau proposé : vous pourrez le modifier plus tard depuis la fiche.',
    'error' => 'Choisissez un niveau de diffusion.',
]) ?>
