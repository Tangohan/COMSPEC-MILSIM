<?php
declare(strict_types=1);
/**
 * Demandes d'accès aux images confidentielles des membres : celles des membres dont le lecteur est un supérieur hiérarchique. Une
 * décision accorde une dérogation datée avec motif (30 jours au plus) ou refuse ; l'une et l'autre sont inscrites au journal d'audit SSE.
 *
 * @var list<array<string,mixed>> $requests
 * @var array<int,string> $names
 * @var string $minEnd
 * @var string $maxEnd
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
?>
<div class="fx">
    <div class="fx-th">
        <div><h1>Accès aux images confidentielles</h1><p>Les images des membres (portrait, identité, couverture, papiers) sont classées TRÈS SECRET (TS). Voici les demandes concernant les membres dont vous êtes un supérieur hiérarchique. Une dérogation est datée, avec un motif, et inscrite au journal d’audit.</p></div>
    </div>
    <section class="fx-card">
        <?php if ($requests === []): ?>
            <div class="fx-nr"><b>AUCUNE DEMANDE</b><p>Aucune demande en attente pour les membres de votre chaîne de commandement.</p></div>
        <?php else: ?>
            <div class="fx-tbw"><table class="fx-tb">
                <thead><tr><th>Demandeur</th><th>Membre concerné</th><th>Motif</th><th>Décision</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td translate="no"><?= $h($names[(int) $r['grantee_user_id']] ?? '—') ?></td>
                        <td translate="no"><?= $h($names[(int) $r['owner_user_id']] ?? '—') ?></td>
                        <td style="white-space:normal"><?= $h($r['reason']) ?></td>
                        <td>
                            <form method="post" action="<?= $h(url('atak/sse/fichiers/acces-images/' . (int) $r['id'])) ?>" style="display:flex;flex-direction:column;gap:6px;min-width:240px"><?= $csrf ?>
                                <label class="fx-f"><span>Fin de la dérogation</span><input class="fx-in mono" type="date" name="ends_at" min="<?= $h($minEnd) ?>" max="<?= $h($maxEnd) ?>" value="<?= $h($maxEnd) ?>"></label>
                                <label class="fx-f"><span>Motif de la décision</span><input class="fx-in" name="reason" maxlength="300" placeholder="ex. enquête interne en cours"></label>
                                <span style="display:flex;gap:6px"><button class="btn pri sm" type="submit" name="decision" value="grant">Accorder</button><button class="btn sm" type="submit" name="decision" value="deny">Refuser</button></span>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
