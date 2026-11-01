<?php
declare(strict_types=1);

/**
 * Liens « compléter son profil » (fiche du membre et onglet Invitations). Actions : eff-invites.js (sans script,
 * chaque bouton reste un formulaire classique).
 *
 * @var list<array<string, mixed>> $invRows
 * @var bool $invCanManage
 * @var list<array{id:string, label:string, preferred:bool}> $invDestinations
 * @var string $invReturnTo '' (fiche) | 'invitations'
 * @var bool $invShowMember
 * @var int $invHighlight identifiant du lien tout juste créé
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static function (string $raw): string {
    $t = strtotime($raw);

    return $t ? date('d/m/Y à H:i', $t) : '—';
};
$csrf = \App\Core\Csrf::token();
$channelLabel = ['link' => 'lien copié', 'discord' => 'publié sur Discord', 'email' => 'envoyé par e-mail'];
?>
<ul class="eff-inv-list">
<?php foreach ($invRows as $r): ?>
    <?php
    $rid = (int) $r['id'];
    $base = effectifs_workspace_url('invitations/' . $rid . '/');
    $isNew = $rid === (int) ($invHighlight ?? 0);
    $stepsDone = count(array_filter($r['steps'], static fn (array $s): bool => $s['done']));
    ?>
    <li class="eff-inv is-<?= $h($r['status']) ?><?= $isNew ? ' is-new' : '' ?>" data-inv-row="<?= $rid ?>">
        <div class="eff-inv__head">
            <span class="eff-inv__state"><i aria-hidden="true"></i><?= $h($r['status_label']) ?></span>
            <?php if (!empty($invShowMember)): ?>
                <a class="eff-inv__who" href="<?= $h(effectifs_workspace_url('membres/' . (int) $r['user_id']) . '#invitations') ?>"><?= $h($r['member']) ?></a>
            <?php endif; ?>
            <span class="eff-inv__progress" title="Étapes faites"><?= $stepsDone ?>/<?= count($r['steps']) ?></span>
        </div>
        <ol class="eff-inv__steps" aria-label="Étapes du profil">
            <?php foreach ($r['steps'] as $s): ?>
                <li class="<?= $s['done'] ? 'is-done' : '' ?>"><span class="eff-sr"><?= $s['done'] ? 'Fait : ' : 'À faire : ' ?></span><?= $h($s['label']) ?></li>
            <?php endforeach; ?>
        </ol>
        <dl class="eff-inv__meta">
            <div><dt>Créé</dt><dd><?= $h($fmt($r['created_at'])) ?> par <?= $h($r['creator']) ?></dd></div>
            <div><dt><?= $r['status'] === 'expired' ? 'Expiré' : 'Expire' ?></dt><dd><?= $h($fmt($r['expires_at'])) ?> · <?= (int) $r['duration_hours'] ?> h<?= $r['single_use'] ? ', usage unique' : '' ?></dd></div>
            <div><dt>Dernière ouverture</dt><dd><?= $r['last_opened_at'] !== '' ? $h($fmt($r['last_opened_at'])) . ($r['open_count'] > 1 ? ' (' . (int) $r['open_count'] . ' fois)' : '') : 'Jamais ouvert' ?></dd></div>
            <?php if ($r['last_sent_channel'] !== ''): ?>
            <div><dt>Dernier envoi</dt><dd><?= $h($channelLabel[$r['last_sent_channel']] ?? '') ?>, <?= $h($fmt($r['last_sent_at'])) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if (!empty($invCanManage) && !in_array($r['status'], ['revoked', 'completed'], true)): ?>
        <div class="eff-inv__acts">
            <?php if ($r['usable']): ?>
                <form method="post" action="<?= $h($base . 'lien') ?>" data-inv-form data-inv-copy>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                    <button type="submit" class="eff-inv-btn<?= $isNew ? ' eff-inv-btn--solid' : '' ?>">Copier le lien</button>
                </form>
                <?php if (($invDestinations ?? []) !== []): ?>
                <form method="post" action="<?= $h($base . 'discord') ?>" data-inv-form class="eff-inv__discord">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                    <?php if (count($invDestinations) > 1): ?>
                    <label class="eff-sr" for="inv-dest-<?= $rid ?>">Salon Discord</label>
                    <select name="destination" id="inv-dest-<?= $rid ?>">
                        <?php foreach ($invDestinations as $d): ?><option value="<?= $h($d['id']) ?>" <?= $d['preferred'] ? 'selected' : '' ?>><?= $h($d['label']) ?></option><?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="destination" value="<?= $h($invDestinations[0]['id']) ?>">
                    <?php endif; ?>
                    <button type="submit" class="eff-inv-btn">Envoyer sur Discord</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= $h($base . 'email') ?>" data-inv-form data-inv-confirm="Envoyer ce lien par e-mail ? Un seul e-mail part, à l’adresse de la fiche.">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                    <button type="submit" class="eff-inv-btn eff-inv-btn--quiet">Par e-mail</button>
                </form>
            <?php endif; ?>
            <?php if ($r['status'] !== 'used'): ?>
            <form method="post" action="<?= $h($base . 'prolonger') ?>" data-inv-form data-inv-reload class="eff-inv__extend">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                <label class="eff-sr" for="inv-ext-<?= $rid ?>">Durée</label>
                <select name="duration_hours" id="inv-ext-<?= $rid ?>"><option value="24">24 h</option><option value="48" selected>48 h</option></select>
                <button type="submit" class="eff-inv-btn eff-inv-btn--quiet">Prolonger</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= $h($base . 'regenerer') ?>" data-inv-form data-inv-reload data-inv-confirm="Créer une nouvelle adresse ? L’ancienne cessera de fonctionner.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                <button type="submit" class="eff-inv-btn eff-inv-btn--quiet">Régénérer</button>
            </form>
            <?php if ($r['status'] !== 'expired'): ?>
            <form method="post" action="<?= $h($base . 'revoquer') ?>" data-inv-form data-inv-reload data-inv-confirm="Révoquer ce lien ? Il cessera de fonctionner immédiatement.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="return_to" value="<?= $h($invReturnTo ?? '') ?>">
                <button type="submit" class="eff-inv-btn eff-inv-btn--danger">Révoquer</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
