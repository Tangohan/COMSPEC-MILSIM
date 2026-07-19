<?php
/**
 * Champs enrichissement créneau (création ou édition).
 *
 * @var array<string, mixed>|null $eventDetailsSource
 * @var bool $eventDetailsRequireAlpine
 */
use App\Support\CommunityEventDetails;

$src = is_array($eventDetailsSource ?? null) ? $eventDetailsSource : [];
$tagsSelected = CommunityEventDetails::decodeTags($src['tags_json'] ?? null);
$scheduleRows = CommunityEventDetails::decodeSchedule($src['schedule_json'] ?? null);
if ($scheduleRows === []) {
    $scheduleRows = [
        ['type' => 'phase', 'tone' => 'red', 'label' => '', 'time' => ''],
    ];
}
$coverUrl = CommunityEventDetails::publicCoverUrl(isset($src['cover_image_path']) ? (string) $src['cover_image_path'] : null);
$cg = (string) ($src['conditions_general'] ?? '');
$cs = (string) ($src['conditions_special'] ?? '');
$toneOpts = CommunityEventDetails::scheduleToneOptions();
$tagOpts = CommunityEventDetails::tagOptions();
$scheduleInit = [];
foreach ($scheduleRows as $row) {
    $scheduleInit[] = [
        'type' => $row['type'],
        'tone' => $row['tone'] ?? 'gray',
        'label' => $row['label'],
        'time' => $row['time'] ?? '',
    ];
}
?>
<div class="bo-events__field--full" x-data='{ phases: <?= htmlspecialchars(json_encode($scheduleInit, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?> }'>
    <p class="bo-events__label" style="margin-bottom:0.65rem">Étiquettes d’activité</p>
    <div class="bo-events__tag-grid">
        <?php foreach ($tagOpts as $code => $label): ?>
            <label class="bo-events__tag-check">
                <input type="checkbox" name="event_tags[]" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($code, $tagsSelected, true) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($label) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="bo-events__form-grid" style="margin-top:1rem">
        <div class="bo-events__field--full">
            <label class="bo-events__label" for="ev-cover">Image d’illustration <span>(optionnel)</span></label>
            <input id="ev-cover" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="bo-events__input">
            <p class="bo-events__hint">JPG, PNG ou WebP — max. 8 Mo. Affichée dans le détail du créneau.</p>
            <?php if ($coverUrl): ?>
                <div class="bo-events__cover-preview">
                    <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <label class="bo-events__tag-check">
                        <input type="checkbox" name="remove_cover" value="1">
                        <span>Retirer l’image actuelle</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <div class="bo-events__field--full">
            <label class="bo-events__label" for="ev-cg">Conditions générales <span>(optionnel)</span></label>
            <textarea id="ev-cg" name="conditions_general" rows="3" class="bo-events__textarea" placeholder="Tenue, règles de session, consignes permanentes…"><?= htmlspecialchars($cg) ?></textarea>
        </div>
        <div class="bo-events__field--full">
            <label class="bo-events__label" for="ev-cs">Conditions particulières <span>(optionnel)</span></label>
            <textarea id="ev-cs" name="conditions_special" rows="3" class="bo-events__textarea" placeholder="Exceptions pour ce créneau, matériel imposé, carte…"><?= htmlspecialchars($cs) ?></textarea>
        </div>
    </div>

    <div style="margin-top:1rem">
        <div class="bo-events__schedule-head">
            <p class="bo-events__label" style="margin:0">Déroulement horaire</p>
            <button type="button" class="bo-events__action bo-events__action--ghost" @click="phases.push({ type: 'phase', tone: 'green', label: '', time: '' })">+ Étape</button>
            <button type="button" class="bo-events__action bo-events__action--ghost" @click="phases.push({ type: 'section', tone: 'gray', label: '', time: '' })">+ Sous-titre</button>
        </div>
        <p class="bo-events__hint">Ex. regroupement, briefing, équipement, top action — comme sur Discord.</p>
        <template x-for="(phase, idx) in phases" :key="idx">
            <div class="bo-events__schedule-row">
                <input type="hidden" :name="'schedule_type[' + idx + ']'" :value="phase.type">
                <template x-if="phase.type === 'section'">
                    <div class="bo-events__field--full">
                        <label class="bo-events__label">Sous-titre de section</label>
                        <input type="text" class="bo-events__input" :name="'schedule_label[' + idx + ']'" x-model="phase.label" placeholder="Ex. Top action différé (non présent à 21H00)">
                    </div>
                </template>
                <template x-if="phase.type !== 'section'">
                    <div class="bo-events__schedule-phase">
                        <div>
                            <label class="bo-events__label">Couleur</label>
                            <select class="bo-events__select" :name="'schedule_tone[' + idx + ']'" x-model="phase.tone">
                                <?php foreach ($toneOpts as $tone => $tLab): ?>
                                    <option value="<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tLab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="bo-events__label">Horaire</label>
                            <input type="text" class="bo-events__input" :name="'schedule_time[' + idx + ']'" x-model="phase.time" placeholder="21H00 - 21H15">
                        </div>
                        <div class="bo-events__field--grow">
                            <label class="bo-events__label">Libellé</label>
                            <input type="text" class="bo-events__input" :name="'schedule_label[' + idx + ']'" x-model="phase.label" placeholder="Briefing">
                        </div>
                    </div>
                </template>
                <button type="button" class="bo-events__link-btn bo-events__link-btn--danger" @click="phases.splice(idx, 1)" x-show="phases.length > 1">Retirer</button>
            </div>
        </template>
    </div>
</div>
