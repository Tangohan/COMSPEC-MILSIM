<?php
declare(strict_types=1);

/**
 * Bloc discret « Identifiants » d'une fiche SSE (numéro d'identification, nom de code, nom de fiche, DSE, IA reliée).
 *
 * @var array{row: array<string, mixed>, type: string, entityId: int, canManage: bool, ai: ?array<string, mixed>, profiles: list<array<string, mixed>>} $sseIdent
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = $sseIdent['row'];
$canManage = (bool) $sseIdent['canManage'];
$ai = $sseIdent['ai'];
$action = url('atak/sse/identifiants/' . $sseIdent['type'] . '/' . (int) $sseIdent['entityId']);
$copy = static fn (string $value): string => '<button type="button" class="iw-btn" data-ident-copy="' . $h($value) . '">Copier</button>';
?>
<details class="sse-ident" style="margin:8px 0">
    <summary style="cursor:pointer;font-size:.85rem;opacity:.85">Identifiants</summary>
    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:4px 12px;margin:8px 0;font-size:.85rem">
        <dt>Numéro d’identification</dt><dd><code><?= $h($r['ident_no']) ?></code> <?= $copy((string) $r['ident_no']) ?></dd>
        <dt>Nom de code</dt><dd><code><?= $h($r['codename']) ?></code> <?= $copy((string) $r['codename']) ?></dd>
        <dt>Nom de fiche</dt><dd><?= $h($r['sheet_name']) ?><?= !empty($r['sheet_name_auto']) ? ' (automatique)' : '' ?></dd>
        <dt>DSE (Dossier Synchronisé Eden)</dt><dd><code><?= $h($r['dse']) ?></code> <?= $copy((string) $r['dse']) ?></dd>
        <dt>IA reliée</dt>
        <dd><?= $ai !== null ? $h(($ai['label'] ?? '') . ' (profil n° ' . (int) $ai['id'] . ')') : 'Aucune' ?></dd>
    </dl>
    <?php if ($canManage): ?>
        <form method="post" action="<?= $h($action) ?>" style="display:grid;gap:6px;max-width:420px;font-size:.85rem">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <label>Nom de code <input type="text" name="codename" value="<?= $h($r['codename']) ?>" maxlength="40"></label>
            <label>Nom de fiche <input type="text" name="sheet_name" value="<?= $h($r['sheet_name']) ?>" maxlength="160"></label>
            <label>IA reliée
                <select name="ai_profile_id">
                    <option value="">Aucune</option>
                    <?php foreach ($sseIdent['profiles'] as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= (int) ($r['ai_profile_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>><?= $h($p['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p style="margin:0;opacity:.7">Le numéro d’identification et le DSE ne changent jamais. Chaque modification est journalisée.</p>
            <button type="submit" class="iw-btn">Enregistrer les identifiants</button>
        </form>
    <?php endif; ?>
    <script defer src="<?= $h(asset_url('assets/js/sse-identifiers.js')) ?>"></script>
</details>
