<?php
/** @var list<array{name:string,kind:string,status:string,note:string}> $sources */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sources = is_array($sources ?? null) ? $sources : [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Sources & veille</h2>
    </div>
    <div class="jnet-panel__body">
        <table class="jnet-table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Nature</th>
                    <th>État</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $s): ?>
                    <tr>
                        <td><?= $h((string) ($s['name'] ?? '')) ?></td>
                        <td><?= $h((string) ($s['kind'] ?? '')) ?></td>
                        <td><span class="jnet-badge jnet-badge--ok"><?= $h((string) ($s['status'] ?? '')) ?></span></td>
                        <td><?= $h((string) ($s['note'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
