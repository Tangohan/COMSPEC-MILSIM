<?php
declare(strict_types=1);

/**
 * Bandeau violet site-wide : mode édition avancée de fiche (grant 24 h).
 */
if (!\App\Core\Session::get('user_id')) {
    return;
}
$advancedGrant = user_advanced_fiche_edit_grant();
if ($advancedGrant === null) {
    return;
}

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$endsAt = (string) ($advancedGrant['ends_at'] ?? '');
$endsLabel = $endsAt !== '' ? date('d/m/Y à H:i', strtotime($endsAt)) : '—';
$granter = trim((string) ($advancedGrant['granter_display_name'] ?? ''));
$editUrl = url('personnel/me/edit');
$hours = \App\Repositories\UserAdvancedEditGrantRepository::durationHours();
?>
<div id="advanced-fiche-edit-banner" class="advanced-fiche-edit-banner relative z-[96]" role="region" aria-label="Mode édition avancée">
  <button type="button" id="advanced-fiche-edit-banner-btn" class="advanced-fiche-edit-banner__bar" aria-haspopup="dialog" aria-controls="advanced-fiche-edit-modal">
    <span class="advanced-fiche-edit-banner__dot" aria-hidden="true"></span>
    <span class="advanced-fiche-edit-banner__text">Un administrateur a activé le mode avancée de modification de fiche</span>
    <span class="advanced-fiche-edit-banner__hint">En savoir plus</span>
  </button>
</div>

<div id="advanced-fiche-edit-modal" class="advanced-fiche-edit-modal hidden" role="dialog" aria-modal="true" aria-labelledby="advanced-fiche-edit-title" hidden>
  <div class="advanced-fiche-edit-modal__backdrop" data-afe-close></div>
  <div class="advanced-fiche-edit-modal__panel">
    <div class="advanced-fiche-edit-modal__head">
      <h2 id="advanced-fiche-edit-title">Mode édition avancée de fiche</h2>
      <button type="button" class="advanced-fiche-edit-modal__close" data-afe-close aria-label="Fermer">×</button>
    </div>
    <div class="advanced-fiche-edit-modal__body">
      <p>Un administrateur vous a temporairement autorisé à <strong>modifier l’ensemble des champs de votre fiche personnel</strong>, à l’exception de l’identifiant Athena (non modifiable).</p>
      <ul>
        <li><strong>Durée :</strong> <?= (int) $hours ?> heures à compter de l’activation<?= $endsAt !== '' ? ' (jusqu’au ' . $h($endsLabel) . ')' : '' ?>.</li>
        <li><strong>Accès :</strong> ouvrez votre fiche en édition pour utiliser les champs déverrouillés (matricule, niveau d’habilitation, etc.).</li>
        <?php if ($granter !== ''): ?>
        <li><strong>Activé par :</strong> <?= $h($granter) ?>.</li>
        <?php endif; ?>
      </ul>
      <p class="advanced-fiche-edit-modal__note">Passé le délai, ou si l’autorisation est révoquée, les champs sensibles redeviennent en lecture seule. Les modifications déjà enregistrées sont conservées.</p>
    </div>
    <div class="advanced-fiche-edit-modal__actions">
      <button type="button" class="advanced-fiche-edit-modal__btn advanced-fiche-edit-modal__btn--ghost" data-afe-close>Fermer</button>
      <a href="<?= $h($editUrl) ?>" class="advanced-fiche-edit-modal__btn advanced-fiche-edit-modal__btn--primary">Ouvrir l’édition de ma fiche</a>
    </div>
  </div>
</div>

<style>
.advanced-fiche-edit-banner__bar {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  flex-wrap: wrap;
  border: 0;
  margin: 0;
  padding: 0.65rem 1rem;
  cursor: pointer;
  background: linear-gradient(90deg, #5b21b6 0%, #7c3aed 45%, #6d28d9 100%);
  color: #f5f3ff;
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-align: center;
}
.advanced-fiche-edit-banner__bar:hover { filter: brightness(1.06); }
.advanced-fiche-edit-banner__dot {
  width: 0.55rem; height: 0.55rem; border-radius: 999px;
  background: #e9d5ff; box-shadow: 0 0 0 3px rgba(233, 213, 255, 0.25);
  flex-shrink: 0;
}
.advanced-fiche-edit-banner__hint {
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
  opacity: 0.85; border-bottom: 1px solid rgba(245, 243, 255, 0.45);
}
.advanced-fiche-edit-modal {
  position: fixed; inset: 0; z-index: 520;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
}
.advanced-fiche-edit-modal.hidden { display: none !important; }
.advanced-fiche-edit-modal__backdrop {
  position: absolute; inset: 0; background: rgba(15, 8, 30, 0.62); backdrop-filter: blur(2px);
}
.advanced-fiche-edit-modal__panel {
  position: relative; z-index: 1; width: 100%; max-width: 32rem;
  border-radius: 1rem; border: 1px solid rgba(167, 139, 250, 0.35);
  background: #fff; box-shadow: 0 25px 50px rgba(76, 29, 149, 0.35);
  overflow: hidden;
}
.advanced-fiche-edit-modal__head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
  padding: 1rem 1.15rem; background: linear-gradient(90deg, #ede9fe, #f5f3ff);
  border-bottom: 1px solid #ddd6fe;
}
.advanced-fiche-edit-modal__head h2 {
  margin: 0; font-size: 0.95rem; font-weight: 900; color: #4c1d95;
  text-transform: uppercase; letter-spacing: 0.04em;
}
.advanced-fiche-edit-modal__close {
  border: 0; background: transparent; color: #6d28d9; font-size: 1.4rem; line-height: 1; cursor: pointer;
}
.advanced-fiche-edit-modal__body { padding: 1rem 1.15rem; color: #334155; font-size: 0.875rem; line-height: 1.55; }
.advanced-fiche-edit-modal__body ul { margin: 0.75rem 0; padding-left: 1.1rem; }
.advanced-fiche-edit-modal__body li { margin: 0.35rem 0; }
.advanced-fiche-edit-modal__note { margin: 0.75rem 0 0; font-size: 0.75rem; color: #64748b; }
.advanced-fiche-edit-modal__actions {
  display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.5rem;
  padding: 0.85rem 1.15rem 1.1rem; border-top: 1px solid #f1f5f9;
}
.advanced-fiche-edit-modal__btn {
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 0.75rem; padding: 0.55rem 0.9rem;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em;
  text-decoration: none; border: 1px solid transparent; cursor: pointer;
}
.advanced-fiche-edit-modal__btn--ghost { background: #fff; border-color: #e2e8f0; color: #475569; }
.advanced-fiche-edit-modal__btn--primary { background: #7c3aed; color: #fff; }
.advanced-fiche-edit-modal__btn--primary:hover { background: #6d28d9; }
</style>
<script>
(function () {
  var btn = document.getElementById('advanced-fiche-edit-banner-btn');
  var modal = document.getElementById('advanced-fiche-edit-modal');
  if (!btn || !modal) return;
  function open() {
    modal.classList.remove('hidden');
    modal.removeAttribute('hidden');
  }
  function close() {
    modal.classList.add('hidden');
    modal.setAttribute('hidden', 'hidden');
  }
  btn.addEventListener('click', open);
  modal.querySelectorAll('[data-afe-close]').forEach(function (el) {
    el.addEventListener('click', close);
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
  });
})();
</script>
