<?php
declare(strict_types=1);

use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * « Mon cursus pilote » (Mes qualifications) : où en est le membre sur chaque palier commencé.
 *
 * @var list<array<string, mixed>> $pilotProgress
 */
if (empty($pilotProgress) || !is_array($pilotProgress)) {
    return;
}
$ph = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$stateLabel = [
    Eng::ELIGIBLE => ['Heures atteintes', 'is-ok'],
    Eng::PENDING => ['En attente d’un instructeur', 'is-warn'],
    Eng::PROGRESS => ['En cours', ''],
    Eng::HELD => ['Obtenue', 'is-ok'],
    Eng::BLOCKED => ['Ajournée', 'is-warn'],
];
?>
<section class="pcs pcs-me ath-card" aria-labelledby="pcs-me-title">
    <header class="pcs-sec__head">
        <h2 id="pcs-me-title">Mon cursus pilote</h2>
        <p>Vos heures aux commandes sont relevées en jeu. Chaque palier atteint est attribué, ou proposé à un instructeur qui le valide après un vol avec vous.</p>
    </header>
    <ul class="pcs-me__list">
        <?php foreach ($pilotProgress as $r):
            [$label, $tone] = $stateLabel[$r['state']] ?? ['', ''];
            ?>
        <li class="pcs-me__row">
            <span class="pcs-step__short"><?= $ph((string) (Cat::tier((string) $r['tier'])['short'] ?? '')) ?></span>
            <span class="pcs-me__main">
                <b><?= $ph((string) $r['definition_name']) ?></b>
                <span class="pcs-bar" role="img" aria-label="<?= (int) $r['pct'] ?> %"><span style="width:<?= (int) $r['pct'] ?>%"></span></span>
                <small><?= $ph(Eng::formatHours((float) $r['scope_hours'])) ?> sur <?= $ph(Eng::formatHours((float) $r['target_scope'])) ?><?= (float) $r['target_track'] > 0 ? ' · ' . $ph(Eng::formatHours((float) $r['track_hours'])) . ' sur ' . $ph(Eng::formatHours((float) $r['target_track'])) . ' en ' . $ph(mb_strtolower(Cat::trackLabel((string) $r['track']))) : '' ?><?= !$r['prereq_ok'] && $r['state'] === Eng::PROGRESS ? ' · d’abord : ' . $ph((string) ($r['prereq_name'] ?? 'palier précédent')) : '' ?></small>
            </span>
            <span class="pcs-me__state <?= $ph($tone) ?>"><?= $ph($label) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
