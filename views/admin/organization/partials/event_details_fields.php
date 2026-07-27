<?php
/**
 * Champs enrichissement créneau (création ou édition).
 *
 * @var array<string, mixed>|null $eventDetailsSource
 * @var bool $eventDetailsRequireAlpine
 * @var bool $eventDetailsAthForm
 */
use App\Support\CommunityEventDetails;

$src = is_array($eventDetailsSource ?? null) ? $eventDetailsSource : [];
$athForm = !empty($eventDetailsAthForm);
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

$labelClass = $athForm ? 'ath-users-filters__label' : 'bo-events__label';
$inputClass = $athForm ? 'bo-select' : 'bo-events__input';
$selectClass = $athForm ? 'bo-select' : 'bo-events__select';
$textareaClass = $athForm ? 'bo-select' : 'bo-events__textarea';
$inputStyle = $athForm ? ' style="height:40px;width:100%;"' : '';
$textareaStyle = $athForm ? ' style="width:100%;min-height:88px;padding:10px 12px;"' : '';
$fullClass = $athForm ? 'ath-event-show__field--full' : 'bo-events__field--full';
$gridClass = $athForm ? 'ath-event-show__form-grid' : 'bo-events__form-grid';
$hintClass = $athForm ? 'ath-event-show__hint' : 'bo-events__hint';
$tagGridClass = $athForm ? 'ath-event-show__tag-grid' : 'bo-events__tag-grid';
$tagCheckClass = $athForm ? 'ath-event-show__tag-check' : 'bo-events__tag-check';
$scheduleHeadClass = $athForm ? 'ath-event-show__schedule-head' : 'bo-events__schedule-head';
$scheduleRowClass = $athForm ? 'ath-event-show__schedule-row' : 'bo-events__schedule-row';
$schedulePhaseClass = $athForm ? 'ath-event-show__schedule-phase' : 'bo-events__schedule-phase';
$growClass = $athForm ? 'ath-event-show__field--grow' : 'bo-events__field--grow';
$ghostBtnClass = $athForm ? 'ath-btn' : 'bo-events__action bo-events__action--ghost';
$dangerBtnClass = $athForm ? 'ath-btn' : 'bo-events__link-btn bo-events__link-btn--danger';
$coverPreviewClass = $athForm ? 'ath-event-show__cover-preview' : 'bo-events__cover-preview';
$optSpan = $athForm ? '<span class="ath-event-show__opt">(optionnel)</span>' : '<span>(optionnel)</span>';
?>
<div class="<?= $fullClass ?>" x-data='{ phases: <?= htmlspecialchars(json_encode($scheduleInit, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?> }'>
    <p class="<?= $labelClass ?>" style="margin-bottom:0.65rem">Étiquettes d’activité</p>
    <div class="<?= $tagGridClass ?>">
        <?php foreach ($tagOpts as $code => $label): ?>
            <label class="<?= $tagCheckClass ?>">
                <input type="checkbox" name="event_tags[]" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($code, $tagsSelected, true) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($label) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="<?= $gridClass ?>" style="margin-top:1rem">
        <div class="<?= $fullClass ?>">
            <label class="<?= $labelClass ?>" for="ev-cover">Image d’illustration <?= $optSpan ?></label>
            <input id="ev-cover" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="<?= $inputClass ?>"<?= $athForm ? ' style="width:100%;height:auto;padding:8px 12px;"' : '' ?>>
            <p class="<?= $hintClass ?>">JPG, PNG ou WebP — max. 8 Mo. Affichée dans le détail du créneau.</p>
            <?php if ($coverUrl): ?>
                <div class="<?= $coverPreviewClass ?>">
                    <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <label class="<?= $tagCheckClass ?>">
                        <input type="checkbox" name="remove_cover" value="1">
                        <span>Retirer l’image actuelle</span>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <div class="<?= $fullClass ?>">
            <label class="<?= $labelClass ?>" for="ev-cg">Conditions générales <?= $optSpan ?></label>
            <textarea id="ev-cg" name="conditions_general" rows="3" class="<?= $textareaClass ?>"<?= $textareaStyle ?> placeholder="Tenue, règles de session, consignes permanentes…"><?= htmlspecialchars($cg) ?></textarea>
        </div>
        <div class="<?= $fullClass ?>">
            <label class="<?= $labelClass ?>" for="ev-cs">Conditions particulières <?= $optSpan ?></label>
            <textarea id="ev-cs" name="conditions_special" rows="3" class="<?= $textareaClass ?>"<?= $textareaStyle ?> placeholder="Exceptions pour ce créneau, matériel imposé, carte…"><?= htmlspecialchars($cs) ?></textarea>
        </div>
    </div>

    <div style="margin-top:1rem">
        <div class="<?= $scheduleHeadClass ?>">
            <p class="<?= $labelClass ?>" style="margin:0">Déroulement horaire</p>
            <button type="button" class="<?= $ghostBtnClass ?>" @click="phases.push({ type: 'phase', tone: 'green', label: '', time: '' })">+ Étape</button>
            <button type="button" class="<?= $ghostBtnClass ?>" @click="phases.push({ type: 'section', tone: 'gray', label: '', time: '' })">+ Sous-titre</button>
        </div>
        <p class="<?= $hintClass ?>">Ex. regroupement, briefing, équipement, top action — comme sur Discord.</p>
        <template x-for="(phase, idx) in phases" :key="idx">
            <div class="<?= $scheduleRowClass ?>">
                <input type="hidden" :name="'schedule_type[' + idx + ']'" :value="phase.type">
                <template x-if="phase.type === 'section'">
                    <div class="<?= $fullClass ?>">
                        <label class="<?= $labelClass ?>">Sous-titre de section</label>
                        <input type="text" class="<?= $inputClass ?>"<?= $inputStyle ?> :name="'schedule_label[' + idx + ']'" x-model="phase.label" placeholder="Ex. Top action différé (non présent à 21H00)">
                    </div>
                </template>
                <template x-if="phase.type !== 'section'">
                    <div class="<?= $schedulePhaseClass ?>">
                        <div>
                            <label class="<?= $labelClass ?>">Couleur</label>
                            <select class="<?= $selectClass ?>" :name="'schedule_tone[' + idx + ']'" x-model="phase.tone">
                                <?php foreach ($toneOpts as $tone => $tLab): ?>
                                    <option value="<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tLab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="<?= $labelClass ?>">Horaire</label>
                            <input type="text" class="<?= $inputClass ?>"<?= $inputStyle ?> :name="'schedule_time[' + idx + ']'" x-model="phase.time" placeholder="21H00 - 21H15">
                        </div>
                        <div class="<?= $growClass ?>">
                            <label class="<?= $labelClass ?>">Libellé</label>
                            <input type="text" class="<?= $inputClass ?>"<?= $inputStyle ?> :name="'schedule_label[' + idx + ']'" x-model="phase.label" placeholder="Briefing">
                        </div>
                    </div>
                </template>
                <button type="button" class="<?= $dangerBtnClass ?>"<?= $athForm ? ' style="color:#b42318;border-color:#fecaca;margin-top:8px;"' : '' ?> @click="phases.splice(idx, 1)" x-show="phases.length > 1">Retirer</button>
            </div>
        </template>
    </div>
</div>
