<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $inbox */
/** @var callable $toneClass */
/** @var callable $h */
/** @var bool $canManage */
/** @var string $csrfToken */
/** @var int $selectedCaseId */
use App\Support\SseWorkspaceUi;
?>
<header class="iw-intel-col-head">
    <h2>Inbox</h2>
    <a class="link" href="<?= $h(url('atak/sse/rapprochements')) ?>">Moteur</a>
</header>
<?php if ($inbox === []): ?>
    <p class="iw-intel-empty">Aucune piste ni suggestion en attente.</p>
<?php else: ?>
    <ul class="iw-intel-list iw-intel-list--cards">
        <?php foreach ($inbox as $item): ?>
            <?php if (!is_array($item) || !empty($item['placeholder'])) {
                if (!empty($item['placeholder'])) {
                    echo '<li class="iw-intel-empty"><span class="iw-intel-kicker">' . $h((string) ($item['kind_label'] ?? '')) . '</span><em>' . $h((string) ($item['title'] ?? '')) . '</em></li>';
                }
                continue;
            }
            $iconName = (string) ($item['icon'] ?? SseWorkspaceUi::iconForInboxKind(
                (string) ($item['kind'] ?? ''),
                (string) ($item['title'] ?? '')
            ));
            ?>
            <li class="iw-feed-item <?= $h($toneClass((string) ($item['tone'] ?? ''))) ?>">
                <a class="iw-feed-link" href="<?= $h((string) ($item['href'] ?? '#')) ?>">
                    <span class="iw-feed-ico" aria-hidden="true"><?= SseWorkspaceUi::icon($iconName) ?></span>
                    <span class="iw-feed-copy">
                        <span class="iw-intel-kicker"><?= $h((string) ($item['kind_label'] ?? '')) ?></span>
                        <strong><?= $h((string) ($item['title'] ?? '')) ?></strong>
                        <?php if (!empty($item['detail'])): ?>
                            <em><?= $h((string) $item['detail']) ?></em>
                        <?php endif; ?>
                    </span>
                </a>
                <?php if ($canManage && !empty($item['actions']) && is_array($item['actions'])): ?>
                    <div class="iw-inbox-actions">
                        <?php if (in_array('accept', $item['actions'], true)): ?>
                            <form method="post" action="<?= $h(url('atak/sse/workspace/inbox/decide')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="kind" value="<?= $h((string) ($item['kind'] ?? '')) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                <input type="hidden" name="decision" value="accept">
                                <input type="hidden" name="case" value="<?= (int) $selectedCaseId ?>">
                                <button type="submit" class="iw-btn iw-btn--tiny">Valider</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('reject', $item['actions'], true)): ?>
                            <form method="post" action="<?= $h(url('atak/sse/workspace/inbox/decide')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="kind" value="<?= $h((string) ($item['kind'] ?? '')) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                <input type="hidden" name="decision" value="reject">
                                <input type="hidden" name="case" value="<?= (int) $selectedCaseId ?>">
                                <button type="submit" class="iw-btn iw-btn--tiny iw-btn--ghost">Rejeter</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
