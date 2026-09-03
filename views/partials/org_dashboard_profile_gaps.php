<?php
declare(strict_types=1);

$profileGaps = is_array($orgProfileGaps ?? null) ? $orgProfileGaps : [];
$profileGapRows = is_array($profileGaps['rows'] ?? null) ? $profileGaps['rows'] : [];
$profileGapTotal = (int) ($profileGaps['total'] ?? 0);
$profileGapShown = (int) ($profileGaps['shown'] ?? count($profileGapRows));
$profileGapTruncated = !empty($profileGaps['truncated']);
$profileGapError = isset($profileGaps['error']) ? (string) $profileGaps['error'] : '';
$profileGapCounts = is_array($profileGaps['counts'] ?? null) ? $profileGaps['counts'] : [];
$profileGapCell = static function (bool $missing, string $okLabel, string $missingLabel): array {
    if ($missing) {
        return [$missingLabel, 'bg-amber-50 text-amber-950 ring-amber-200'];
    }
    $label = trim($okLabel);
    if ($label === '') {
        $label = 'Renseigné';
    }

    return [$label, 'bg-emerald-50 text-emerald-900 ring-emerald-200'];
};
$profileGapInitials = static function (string $name): string {
    if (function_exists('user_display_initials')) {
        return user_display_initials($name, 2);
    }
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        $letters .= mb_strtoupper(mb_substr((string) $part, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : '?';
};
?>
<section class="org-dash__section" aria-labelledby="org-profile-gaps-heading">
    <div class="org-dash__section-head">
        <div>
            <p class="org-dash__kicker">À traiter</p>
            <h2 id="org-profile-gaps-heading" class="org-dash__section-title">Profils à compléter</h2>
            <p class="org-dash__section-lead">Membres actifs auxquels il manque une fonction, un grade, un rôle, une image opérateur, ou dont l’absence n’est pas indiquée.</p>
        </div>
        <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="org-dash__section-link">Effectifs →</a>
    </div>
    <div class="bo-sheet-panel" x-data="{ filter: 'all' }">
        <div class="bo-sheet-toolbar">
            <div>
                <h3 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Dossiers incomplets</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    <?php if ($profileGapTotal > 0): ?>
                        <?= (int) $profileGapShown ?> affiché<?= $profileGapShown > 1 ? 's' : '' ?>
                        sur <?= (int) $profileGapTotal ?> membre<?= $profileGapTotal > 1 ? 's' : '' ?> concerné<?= $profileGapTotal > 1 ? 's' : '' ?>
                        <?php if ($profileGapTruncated): ?>
                            · aperçu limité aux premiers dossiers
                        <?php endif; ?>
                    <?php else: ?>
                        Aucun trou de dossier sur les membres actifs.
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <?php
                $gapFilters = [
                    'all' => 'Tout',
                    'function' => 'Fonction',
                    'rank' => 'Grade',
                    'role' => 'Rôle',
                    'operator_image' => 'Image',
                    'absence' => 'Absence',
                ];
                foreach ($gapFilters as $fkey => $flabel):
                    $fcount = $fkey === 'all' ? $profileGapTotal : (int) ($profileGapCounts[$fkey] ?? 0);
                ?>
                <button
                    type="button"
                    @click="filter = '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>'"
                    :class="filter === '<?= htmlspecialchars($fkey, ENT_QUOTES, 'UTF-8') ?>' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                    class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                ><?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?><?php if ($fcount > 0): ?> · <?= (int) $fcount ?><?php endif; ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($profileGapError !== ''): ?>
            <div class="border border-t-0 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"><?= htmlspecialchars($profileGapError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="bo-sheet-wrap" style="max-height:min(50vh,28rem)">
            <table class="bo-sheet min-w-[64rem]">
                <thead>
                    <tr>
                        <th style="width:2.5rem">#</th>
                        <th>Membre</th>
                        <th>Fonction</th>
                        <th>Grade</th>
                        <th>Rôle</th>
                        <th>Image opérateur</th>
                        <th>Absence</th>
                        <th class="num">Fiche</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($profileGapError !== '' && $profileGapRows === []): ?>
                    <tr><td colspan="8" class="!bg-white px-4 py-8 text-center text-sm text-slate-500">Liste temporairement indisponible.</td></tr>
                <?php elseif ($profileGapRows === []): ?>
                    <tr><td colspan="8" class="!bg-white px-4 py-12 text-center text-sm text-slate-500">Tous les profils actifs ont une fonction, un grade, un rôle, une image opérateur et une absence indiquée.</td></tr>
                <?php else: ?>
                    <?php foreach ($profileGapRows as $i => $grow):
                        if (!is_array($grow)) {
                            continue;
                        }
                        $issueKeys = is_array($grow['issue_keys'] ?? null) ? $grow['issue_keys'] : [];
                        $filterAttr = htmlspecialchars(implode(',', array_map('strval', $issueKeys)), ENT_QUOTES, 'UTF-8');
                        $name = trim((string) ($grow['display_name'] ?? 'Membre'));
                        $callsign = trim((string) ($grow['callsign'] ?? ''));
                        $thumb = trim((string) ($grow['portrait_url'] ?? ''));
                        if ($thumb === '') {
                            $thumb = trim((string) ($grow['avatar_url'] ?? ''));
                        }
                        [$fnLab, $fnClass] = $profileGapCell(!empty($grow['missing_function']), (string) ($grow['function_label'] ?? ''), 'Manquante');
                        [$rkLab, $rkClass] = $profileGapCell(!empty($grow['missing_rank']), (string) ($grow['rank_label'] ?? ''), 'Manquant');
                        [$rlLab, $rlClass] = $profileGapCell(!empty($grow['missing_role']), (string) ($grow['role_label'] ?? ''), 'Manquant');
                        [$imLab, $imClass] = $profileGapCell(!empty($grow['missing_operator_image']), 'Présente', 'Manquante');
                        $absenceOk = !empty($grow['has_active_absence']) ? 'Déclarée' : 'Indiquée';
                        [$abLab, $abClass] = $profileGapCell(!empty($grow['missing_absence']), $absenceOk, 'Non indiquée');
                        $ficheUrl = trim((string) ($grow['fiche_url'] ?? ''));
                        ?>
                        <tr x-show="filter === 'all' || '<?= $filterAttr ?>'.split(',').includes(filter)">
                            <td class="num text-slate-400"><?= (int) ($i + 1) ?></td>
                            <td>
                                <div class="bo-gap-id">
                                    <div class="bo-gap-id__avatar" aria-hidden="true">
                                        <?php if ($thumb !== ''): ?>
                                            <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($profileGapInitials($name), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="bo-gap-id__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($callsign !== '' && strcasecmp($callsign, $name) !== 0): ?>
                                            <span class="bo-gap-id__meta"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="inline-flex max-w-[12rem] truncate rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($fnClass, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fnLab, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fnLab, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <span class="inline-flex max-w-[10rem] truncate rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($rkClass, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($rkLab, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rkLab, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <span class="inline-flex max-w-[10rem] truncate rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($rlClass, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($rlLab, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rlLab, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($imClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($imLab, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($abClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($abLab, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="num">
                                <?php if ($ficheUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">Ouvrir</a>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
