<?php
/** @var list<array<string,mixed>> $rosterMembers */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$members = is_array($rosterMembers ?? null) ? $rosterMembers : [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Effectifs présents</h2>
        <span class="jnet-meta"><?= count($members) ?> profil<?= count($members) > 1 ? 's' : '' ?></span>
    </div>
    <div class="jnet-panel__body">
        <?php if ($members === []): ?>
            <p class="jnet-empty">Aucun membre actif listé pour cette communauté.</p>
        <?php else: ?>
            <table class="jnet-table">
                <thead>
                    <tr>
                        <th>Indicatif / nom</th>
                        <th>Statut</th>
                        <th>Rôle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <?php
                        $label = trim((string) ($m['callsign'] ?? ''));
                        if ($label === '') {
                            $label = (string) ($m['display_name'] ?? 'Membre');
                        }
                        $status = (string) ($m['status'] ?? '');
                        $statusLabel = match ($status) {
                            'active' => 'Actif',
                            'pending_verification' => 'Vérification e-mail',
                            'inactive' => 'Inactif',
                            default => ($status !== '' ? 'Compte' : '—'),
                        };
                        ?>
                        <tr>
                            <td><?= $h($label) ?></td>
                            <td><span class="jnet-badge jnet-badge--ok"><?= $h($statusLabel) ?></span></td>
                            <td><?= $h((string) ($m['role_name'] ?? $m['role_label'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
