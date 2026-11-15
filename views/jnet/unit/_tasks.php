<?php
declare(strict_types=1);
/**
 * Rubrique « Tâches » : trois colonnes (à faire, en cours, fait). Les changements d'état et de responsable sont de
 * petits formulaires (marche sans JavaScript) ; la lecture seule n'affiche aucun formulaire.
 *
 * @var array<string, mixed> $usw
 * @var string $base
 * @var string $label
 */
use App\Services\Jnet\JnetUnitWorkspaceService as WS;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tasks = WS::tasksByStatus(is_array($usw['tasks'] ?? null) ? $usw['tasks'] : []);
$assignees = is_array($usw['assignees'] ?? null) ? $usw['assignees'] : [];
$canPost = !empty($usw['canPost']);
$chief = !empty($usw['canModerate']);
$me = (int) ($usw['viewerId'] ?? 0);
$action = static fn (int $id): string => $h($base . '/taches/' . $id);
$next = ['todo' => ['doing', 'Démarrer'], 'doing' => ['done', 'Terminer'], 'done' => ['todo', 'Rouvrir']];
$emptyText = ['todo' => 'Rien à faire pour l’instant.', 'doing' => 'Rien en cours.', 'done' => 'Rien de terminé.'];
?>
<header class="us-page">
    <h1 class="ja-disp ja-disp--md">Qui fait quoi.</h1>
    <p>Les tâches de <?= $h($label) ?>. Chaque membre peut en ajouter, les prendre en charge et les faire avancer.</p>
</header>
<?php if (!$canPost): ?><p class="us-note">Vous consultez cet espace sans en avoir l’écriture : les tâches sont en lecture seule.</p><?php else: ?>
<p class="uw-note uw-js-only" hidden>Glissez une carte d’une colonne à l’autre, ou utilisez les boutons de la carte.</p>
<?php endif; ?>
<div class="uw-kanban" id="taches"<?= $canPost ? ' data-uw-kanban data-uw-csrf="' . $h(\App\Core\Csrf::token()) . '"' : '' ?>>
    <?php foreach (WS::TASK_LABELS as $status => $title): ?>
        <section class="uw-col" aria-labelledby="uw-col-<?= $h($status) ?>" data-uw-col="<?= $h($status) ?>">
            <header class="uw-col__head">
                <h2 id="uw-col-<?= $h($status) ?>"><?= $h($title) ?></h2>
                <span class="ja-mono"><?= count($tasks[$status]) ?></span>
            </header>
            <?php if ($tasks[$status] === []): ?>
                <p class="uw-col__empty"><?= $h($emptyText[$status]) ?></p>
            <?php endif; ?>
            <?php foreach ($tasks[$status] as $t): ?>
                <article class="uw-task<?= (int) ($t['assignee_user_id'] ?? 0) === $me ? ' is-mine' : '' ?>" data-uw-task="<?= (int) $t['id'] ?>" data-uw-url="<?= $action((int) $t['id']) ?>"<?= $canPost ? ' draggable="true"' : '' ?>>
                    <p class="uw-task__title"><?= $h((string) $t['title']) ?></p>
                    <p class="uw-task__who">
                        <?php if ((string) ($t['assignee_label'] ?? '') !== ''): ?>
                            <span class="ja-mono"><?= $h((string) $t['assignee_label']) ?></span>
                        <?php else: ?>
                            <span class="uw-task__none">Sans responsable</span>
                        <?php endif; ?>
                    </p>
                    <?php
                    // « Liés à » : ordre, opération, page… (liens contrôlés côté serveur pour ce lecteur).
                    $taskItems = is_array($usw['links']['tasks'][(string) (int) $t['id']] ?? null) ? $usw['links']['tasks'][(string) (int) $t['id']] : [];
                    if (isset($linkBlockFor) && (!empty($usw['links']['ready']) && ($canPost || $taskItems !== []))) {
                        $linkBlock = $linkBlockFor('tache', (int) $t['id'], $taskItems, 'echange,operation,page,cible,document,unite,membre,sse');
                        $linkMode = 'inline';
                        $linkUid = 't' . (int) $t['id'];
                        require base_path('views/jnet/_linked.php');
                    }
                    ?>
                    <?php if ($canPost): ?>
                        <div class="uw-task__acts">
                            <form method="post" action="<?= $action((int) $t['id']) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="act" value="status">
                                <button class="uw-link" type="submit" name="status" value="<?= $h($next[$status][0]) ?>"><?= $h($next[$status][1]) ?></button>
                            </form>
                            <form method="post" action="<?= $action((int) $t['id']) ?>" class="uw-task__assign">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="act" value="assign">
                                <label class="ja-sr" for="uw-as-<?= (int) $t['id'] ?>">Responsable</label>
                                <select id="uw-as-<?= (int) $t['id'] ?>" name="assignee" data-uw-autosubmit>
                                    <option value="0">Sans responsable</option>
                                    <?php foreach ($assignees as $uid => $name): ?>
                                        <option value="<?= (int) $uid ?>"<?= (int) ($t['assignee_user_id'] ?? 0) === (int) $uid ? ' selected' : '' ?>><?= $h($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button class="uw-link" type="submit">Appliquer</button></noscript>
                            </form>
                            <?php if (WS::canEditTask($t, $me, $chief)): ?>
                                <form method="post" action="<?= $action((int) $t['id']) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="act" value="delete">
                                    <button class="uw-link uw-link--bad" type="submit">Retirer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($canPost): ?>
                <form class="uw-add" method="post" action="<?= $h($base . '/taches') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="<?= $h($status) ?>">
                    <label class="ja-sr" for="uw-new-<?= $h($status) ?>">Nouvelle tâche, colonne « <?= $h($title) ?> »</label>
                    <input id="uw-new-<?= $h($status) ?>" name="title" maxlength="<?= WS::TASK_MAX ?>" required placeholder="+ Ajouter une tâche, puis Entrée" autocomplete="off">
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
