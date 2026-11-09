<?php
declare(strict_types=1);

/**
 * Administration du site › Messages aux communautés : création et modification, avec aperçu rendu par le serveur.
 *
 * @var ?array<string, mixed> $pbmRow
 * @var list<array<string, mixed>> $pbmTenants
 * @var array<string, string> $pbmPlans
 * @var array<string, string> $pbmTypes
 * @var string $pbmFormAction
 */
$row = is_array($pbmRow ?? null) ? $pbmRow : [];
$isEdit = $row !== [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$val = static fn (string $k): string => $h((string) ($row[$k] ?? ''));
$dt = static function (mixed $raw): string {
    $t = ($raw === null || $raw === '') ? false : strtotime((string) $raw);

    return $t ? date('Y-m-d\TH:i', $t) : '';
};
$mode = (string) ($row['target_mode'] ?? 'all');
$picked = \App\Support\PlatformBoMessage::decodeValues($row['target_json'] ?? null);
$pickedStr = array_map('strval', $picked);
$level = (string) ($row['level'] ?? 'info');
$active = !$isEdit || !empty($row['is_active']);
$err = \App\Core\Session::getFlash('error');
?>
<div class="pmad">
    <div class="pmad__bar pa-head">
        <div>
            <h1 class="pmad__h1"><?= $isEdit ? 'Modifier le message' : 'Nouveau message aux communautés' ?></h1>
            <p class="pmad__lead">Texte brut, sans mise en forme. Les adresses https:// et les e-mails deviennent cliquables. Renseignez les deux langues : à défaut, l’autre langue sert de repli.</p>
        </div>
        <a class="pm-btn" href="<?= $h(url('admin/system/messages-communautes')) ?>">Retour à la liste</a>
    </div>
    <?php if ($err): ?><p class="pmad-error" role="alert"><?= $h((string) $err) ?></p><?php endif; ?>

    <form method="post" action="<?= $h($pbmFormAction) ?>" class="pmad-form" id="pmad-form" data-preview-url="<?= $h(url('admin/system/messages-communautes/apercu')) ?>">
        <?= \App\Core\Csrf::field() ?>

        <section class="pmad-card">
            <h2>Message</h2>
            <div class="pmad-field">
                <label for="pmad-level">Niveau</label>
                <select id="pmad-level" name="level">
                    <option value="info"<?= $level === 'info' ? ' selected' : '' ?>>Information</option>
                    <option value="important"<?= $level === 'important' ? ' selected' : '' ?>>Important</option>
                    <option value="urgent"<?= $level === 'urgent' ? ' selected' : '' ?>>Urgent (ne se ferme pas, un gestionnaire l’acquitte)</option>
                </select>
            </div>
            <div class="pmad-grid">
                <div class="pmad-field">
                    <label for="pmad-title-fr">Titre (français)</label>
                    <input type="text" id="pmad-title-fr" name="title_fr" maxlength="<?= \App\Support\PlatformBoMessage::TITLE_MAX ?>" value="<?= $val('title_fr') ?>">
                </div>
                <div class="pmad-field">
                    <label for="pmad-title-en">Titre (anglais)</label>
                    <input type="text" id="pmad-title-en" name="title_en" maxlength="<?= \App\Support\PlatformBoMessage::TITLE_MAX ?>" value="<?= $val('title_en') ?>">
                </div>
                <div class="pmad-field">
                    <label for="pmad-body-fr">Texte (français)</label>
                    <textarea id="pmad-body-fr" name="body_fr" maxlength="<?= \App\Support\PlatformBoMessage::BODY_MAX ?>"><?= $val('body_fr') ?></textarea>
                </div>
                <div class="pmad-field">
                    <label for="pmad-body-en">Texte (anglais)</label>
                    <textarea id="pmad-body-en" name="body_en" maxlength="<?= \App\Support\PlatformBoMessage::BODY_MAX ?>"><?= $val('body_en') ?></textarea>
                </div>
            </div>
        </section>

        <section class="pmad-card">
            <h2>Bouton (facultatif)</h2>
            <div class="pmad-grid">
                <div class="pmad-field">
                    <label for="pmad-label-fr">Libellé (français)</label>
                    <input type="text" id="pmad-label-fr" name="cta_label_fr" maxlength="<?= \App\Support\PlatformBoMessage::CTA_LABEL_MAX ?>" value="<?= $val('cta_label_fr') ?>" placeholder="Rejoindre notre Discord">
                </div>
                <div class="pmad-field">
                    <label for="pmad-label-en">Libellé (anglais)</label>
                    <input type="text" id="pmad-label-en" name="cta_label_en" maxlength="<?= \App\Support\PlatformBoMessage::CTA_LABEL_MAX ?>" value="<?= $val('cta_label_en') ?>" placeholder="Join our Discord">
                </div>
            </div>
            <div class="pmad-field">
                <label for="pmad-url">Adresse du bouton</label>
                <input type="url" id="pmad-url" name="cta_url" maxlength="<?= \App\Support\PlatformBoMessage::URL_MAX ?>" value="<?= $val('cta_url') ?>" placeholder="https://discord.gg/…">
                <p class="pmad-help">Adresse complète en https:// (ou http://). Les clics sont comptés par communauté.</p>
            </div>
        </section>

        <section class="pmad-card">
            <h2>Destinataires</h2>
            <div class="pmad-field">
                <label for="pmad-mode">Qui voit ce message</label>
                <select id="pmad-mode" name="target_mode">
                    <option value="all"<?= $mode === 'all' ? ' selected' : '' ?>>Toutes les communautés</option>
                    <option value="tenants"<?= $mode === 'tenants' ? ' selected' : '' ?>>Communautés choisies</option>
                    <option value="plans"<?= $mode === 'plans' ? ' selected' : '' ?>>Par formule</option>
                    <option value="types"<?= $mode === 'types' ? ' selected' : '' ?>>Par type de communauté</option>
                </select>
            </div>
            <div class="pmad-pick" data-pick="tenants"<?= $mode === 'tenants' ? '' : ' hidden' ?>>
                <?php foreach ($pbmTenants as $t): ?>
                    <label class="pmad-check"><input type="checkbox" name="target_tenants[]" value="<?= (int) $t['id'] ?>"<?= in_array((string) $t['id'], $pickedStr, true) && $mode === 'tenants' ? ' checked' : '' ?>> <span><?= $h((string) $t['name']) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="pmad-pick" data-pick="plans"<?= $mode === 'plans' ? '' : ' hidden' ?>>
                <?php foreach ($pbmPlans as $slug => $name): ?>
                    <label class="pmad-check"><input type="checkbox" name="target_plans[]" value="<?= $h((string) $slug) ?>"<?= in_array((string) $slug, $pickedStr, true) && $mode === 'plans' ? ' checked' : '' ?>> <span><?= $h($name) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="pmad-pick" data-pick="types"<?= $mode === 'types' ? '' : ' hidden' ?>>
                <?php foreach ($pbmTypes as $key => $name): ?>
                    <label class="pmad-check"><input type="checkbox" name="target_types[]" value="<?= $h((string) $key) ?>"<?= in_array((string) $key, $pickedStr, true) && $mode === 'types' ? ' checked' : '' ?>> <span><?= $h($name) ?></span></label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="pmad-card">
            <h2>Programmation</h2>
            <div class="pmad-grid">
                <div class="pmad-field">
                    <label for="pmad-start">Début (facultatif)</label>
                    <input type="datetime-local" id="pmad-start" name="starts_at" value="<?= $h($dt($row['starts_at'] ?? null)) ?>">
                </div>
                <div class="pmad-field">
                    <label for="pmad-end">Fin (facultatif)</label>
                    <input type="datetime-local" id="pmad-end" name="ends_at" value="<?= $h($dt($row['ends_at'] ?? null)) ?>">
                </div>
            </div>
            <label class="pmad-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
                <span>Message activé (décoché : enregistré mais invisible)</span>
            </label>
            <p class="pmad-help"><?= (int) \App\Support\PlatformBoMessage::MAX_ACTIVE ?> messages actifs au plus.</p>
        </section>

        <section class="pmad-card">
            <h2>Aperçu avant envoi</h2>
            <p class="pmad-help">Rendu par le serveur, tel que les gestionnaires le verront.</p>
            <div class="pmad-grid">
                <div><p class="pmad-help">Français</p><div class="pmad-preview" id="pmad-prev-fr" aria-live="polite"></div></div>
                <div><p class="pmad-help">Anglais</p><div class="pmad-preview" id="pmad-prev-en" aria-live="polite"></div></div>
            </div>
        </section>

        <div class="pmad-actions">
            <button type="submit" class="pm-btn pm-btn--primary"><?= $isEdit ? 'Enregistrer' : 'Publier le message' ?></button>
            <a class="pm-btn" href="<?= $h(url('admin/system/messages-communautes')) ?>">Annuler</a>
        </div>
    </form>
</div>
<script>
(function () {
  var form = document.getElementById('pmad-form');
  if (!form) { return; }
  var mode = document.getElementById('pmad-mode');
  function picks() {
    form.querySelectorAll('[data-pick]').forEach(function (el) { el.hidden = el.getAttribute('data-pick') !== mode.value; });
  }
  mode.addEventListener('change', picks);
  var timer = null;
  function preview() {
    var body = new FormData(form);
    fetch(form.getAttribute('data-preview-url'), { method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        document.getElementById('pmad-prev-fr').innerHTML = j.fr || '';
        document.getElementById('pmad-prev-en').innerHTML = j.en || '';
      })
      .catch(function () {});
  }
  form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(preview, 400); });
  form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(preview, 150); });
  picks();
  preview();
})();
</script>
