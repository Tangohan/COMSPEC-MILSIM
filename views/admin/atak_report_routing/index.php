<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $routingRules */
/** @var array<string,string> $routingReportTypes */
/** @var array<string,string> $routingPriorities */
/** @var string $csrfToken */

$decode = static function (mixed $raw): array {
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $v = json_decode($raw, true);

    return is_array($v) ? $v : [];
};
?>
<div class="page-heading">
    <div>
        <h1>Diffusion des rapports tactiques</h1>
        <p>
            Ces règles désignent qui doit lire un rapport dès sa réception. Elles
            s’appliquent dans l’ordre de priorité, et un rapport peut en déclencher
            plusieurs.
        </p>
    </div>
</div>

<?php if ($routingRules === []): ?>
    <div class="alert alert--info">
        <strong>Aucune règle : la diffusion dirigée ne fait rien.</strong>
        <p>
            C’est l’état normal après installation, pas une panne. Le moteur est en
            place et attend vos règles ; tant qu’il n’y en a aucune, les rapports
            arrivent sans destinataire désigné, comme avant.
        </p>
    </div>
<?php endif; ?>

<div class="alert alert--warning">
    <strong>Les notifications ne sont pas encore émises.</strong>
    <p>
        La diffusion enregistre qui doit lire le rapport et l’affiche sur sa fiche.
        L’envoi effectif — en jeu, par courriel ou vers Discord — n’est pas branché :
        cocher « prévenir les destinataires » enregistre l’intention, rien de plus.
        C’est écrit ici plutôt que découvert en opération.
    </p>
</div>

<section class="panel">
    <div class="panel-header">
        <h2>Règles en place</h2>
        <span class="panel-meta"><?= count($routingRules) ?></span>
    </div>

    <?php if ($routingRules !== []): ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Ordre</th>
                <th>Règle</th>
                <th>S’applique quand</th>
                <th>Destinataires</th>
                <th>Escalade</th>
                <th>État</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($routingRules as $rule): ?>
                <?php
                $cond = $decode($rule['trigger_conditions'] ?? null);
                $roles = $decode($rule['auto_assign_to_roles'] ?? null);
                $units = $decode($rule['auto_assign_to_units'] ?? null);
                $active = !empty($rule['is_active']);

                $when = [];
                if (!empty($cond['report_types'])) {
                    $when[] = 'type : ' . implode(', ', array_map(
                        static fn (string $t): string => $routingReportTypes[$t] ?? $t,
                        $cond['report_types']
                    ));
                }
                if (!empty($cond['priorities'])) {
                    $when[] = 'priorité : ' . implode(', ', array_map(
                        static fn (string $p): string => $routingPriorities[$p] ?? $p,
                        $cond['priorities']
                    ));
                }
                if (!empty($cond['keywords'])) {
                    $when[] = 'mots-clés : ' . implode(', ', $cond['keywords']);
                }
                ?>
                <tr class="<?= $active ? '' : 'is-muted' ?>">
                    <td><?= (int) ($rule['priority_order'] ?? 100) ?></td>
                    <td><strong><?= $h($rule['rule_name'] ?? '') ?></strong></td>
                    <td>
                        <?= $when === []
                            ? '<em>tous les rapports</em>'
                            : $h(implode(' · ', $when)) ?>
                    </td>
                    <td>
                        <?php if ($roles !== []): ?>
                            <div>Fonctions : <?= $h(implode(', ', $roles)) ?></div>
                        <?php endif; ?>
                        <?php if ($units !== []): ?>
                            <div>Unités : <?= $h(implode(', ', $units)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= !empty($rule['escalate_after_minutes'])
                            ? $h((int) $rule['escalate_after_minutes']) . ' min'
                            : '—' ?>
                    </td>
                    <td><?= $active ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <form method="post"
                              action="<?= $h(url('admin/atak-diffusion-rapports/' . (int) $rule['id'] . '/etat')) ?>"
                              style="display:inline">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                            <button type="submit"><?= $active ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                        <form method="post"
                              action="<?= $h(url('admin/atak-diffusion-rapports/' . (int) $rule['id'] . '/supprimer')) ?>"
                              style="display:inline"
                              onsubmit="return confirm('Supprimer cette règle ? Les diffusions déjà effectuées restent au journal.');">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <button type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Ajouter une règle</h2>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('admin/atak-diffusion-rapports')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

            <label for="rule_name">Nom de la règle</label>
            <input id="rule_name" name="rule_name" type="text" required maxlength="200"
                   placeholder="Contact ennemi vers le PC">

            <label for="priority_order">Ordre d’application</label>
            <input id="priority_order" name="priority_order" type="number" value="100" min="1" max="9999">
            <p class="form-hint">Le plus petit s’applique en premier.</p>

            <fieldset>
                <legend>S’applique aux types de rapport</legend>
                <?php foreach ($routingReportTypes as $key => $label): ?>
                    <label>
                        <input type="checkbox" name="report_types[]" value="<?= $h($key) ?>">
                        <?= $h($label) ?>
                    </label>
                <?php endforeach; ?>
                <p class="form-hint">Aucune case cochée = tous les types.</p>
            </fieldset>

            <fieldset>
                <legend>Et aux priorités</legend>
                <?php foreach ($routingPriorities as $key => $label): ?>
                    <label>
                        <input type="checkbox" name="priorities[]" value="<?= $h($key) ?>">
                        <?= $h($label) ?>
                    </label>
                <?php endforeach; ?>
                <p class="form-hint">Aucune case cochée = toutes les priorités.</p>
            </fieldset>

            <label for="keywords">Mots-clés dans le texte (facultatif)</label>
            <textarea id="keywords" name="keywords" rows="2"
                      placeholder="embuscade, IED, blindé"></textarea>
            <p class="form-hint">
                Un par ligne ou séparés par des virgules. La règle ne s’applique que si
                l’un d’eux figure dans le résumé ou le détail.
            </p>

            <label for="roles">Fonctions destinataires</label>
            <textarea id="roles" name="roles" rows="2" placeholder="Chef de section, Officier renseignement"></textarea>

            <label for="units">Unités destinataires</label>
            <textarea id="units" name="units" rows="2" placeholder="EAGLE, TOC"></textarea>
            <p class="form-hint">
                Au moins une fonction ou une unité est requise : sans destinataire, la
                règle ne diffuse rien.
            </p>

            <label for="escalate_after_minutes">Escalader si non traité après (minutes)</label>
            <input id="escalate_after_minutes" name="escalate_after_minutes" type="number" min="0" value="0">
            <p class="form-hint">0 = pas d’escalade.</p>

            <label>
                <input type="checkbox" name="send_notification" value="1">
                Prévenir les destinataires (intention enregistrée, envoi non branché)
            </label>

            <label>
                <input type="checkbox" name="is_active" value="1" checked>
                Règle active
            </label>

            <button type="submit">Enregistrer la règle</button>
        </form>
    </div>
</section>
