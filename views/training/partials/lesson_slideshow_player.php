<?php
declare(strict_types=1);
/** @var array<string, mixed> $deck */
/** @var string $base */
$deck = $deck ?? [];
$base = $base ?? '';
$slides = isset($deck['slides']) && is_array($deck['slides']) ? $deck['slides'] : [];
if ($slides === []) {
    echo '<p class="text-slate-500">Aucune diapositive.</p>';

    return;
}
$slideshowImageCount = 0;
foreach ($slides as $sl) {
    if (is_array($sl) && !empty($sl['imageUrl'])) {
        $slideshowImageCount++;
    }
}
if ($slideshowImageCount < 1) {
    echo '<p class="text-slate-500">Aucune diapositive.</p>';

    return;
}
$sid = 'lmsSlideshow' . bin2hex(random_bytes(3));
?>
<div id="<?= htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') ?>" class="swiper rounded-xl overflow-hidden border border-slate-200 bg-slate-900 shadow-lg">
    <div class="swiper-wrapper">
        <?php foreach ($slides as $sl): ?>
            <?php if (!is_array($sl) || empty($sl['imageUrl'])) {
                continue;
            } ?>
            <div class="swiper-slide">
                <figure class="m-0">
                    <img src="<?= htmlspecialchars((string) $sl['imageUrl']) ?>" alt="" class="w-full max-h-[min(80vh,560px)] object-contain bg-slate-950" loading="lazy">
                    <figcaption class="px-4 py-3 bg-white/95 text-center">
                        <?php if (!empty($sl['title'])): ?>
                        <p class="text-sm font-black text-slate-900"><?= htmlspecialchars((string) $sl['title']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($sl['caption'])): ?>
                        <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars((string) $sl['caption']) ?></p>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="swiper-pagination !bottom-3"></div>
    <div class="swiper-button-prev !text-white"></div>
    <div class="swiper-button-next !text-white"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById(<?= json_encode($sid, JSON_UNESCAPED_UNICODE) ?>);
  if (!el || typeof Swiper === 'undefined') return;
  var uniqueSlideCount = <?= (int) $slideshowImageCount ?>;
  if (uniqueSlideCount < 1) return;
  var cfg = window.__LMS_LESSON_PROGRESS__;
  var MIN_DWELL =
    cfg && cfg.strict && typeof cfg.strict.slideDwellMs === 'number' && cfg.strict.slideDwellMs > 0
      ? cfg.strict.slideDwellMs
      : 2600;
  var enterTime = Object.create(null);
  var confirmed = new Set();
  var lastTimer = null;
  var lastReal = null;

  function confirmIfDwelt(r) {
    if (typeof r !== 'number' || r < 0 || r >= uniqueSlideCount) return;
    var t0 = enterTime[r];
    if (t0 != null && Date.now() - t0 >= MIN_DWELL) confirmed.add(r);
  }

  function scheduleLast(r) {
    clearTimeout(lastTimer);
    if (r !== uniqueSlideCount - 1) return;
    lastTimer = setTimeout(function () {
      confirmIfDwelt(uniqueSlideCount - 1);
      tryComplete();
    }, MIN_DWELL + 120);
  }

  function tryComplete() {
    if (confirmed.size < uniqueSlideCount) return;
    for (var i = 0; i < uniqueSlideCount; i++) {
      if (!confirmed.has(i)) return;
    }
    if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
      window.LmsLessonProgress.signalComplete();
    }
  }

  function onTransitionEnd(sw) {
    var r = sw.realIndex;
    if (lastReal !== null && lastReal !== r) {
      confirmIfDwelt(lastReal);
    }
    lastReal = r;
    enterTime[r] = Date.now();
    scheduleLast(r);
    tryComplete();
  }

  new Swiper(el, {
    loop: uniqueSlideCount > 1,
    pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
    navigation: {
      nextEl: el.querySelector('.swiper-button-next'),
      prevEl: el.querySelector('.swiper-button-prev'),
    },
    on: {
      init: function (sw) {
        lastReal = sw.realIndex;
        enterTime[lastReal] = Date.now();
        scheduleLast(lastReal);
        tryComplete();
      },
      slideChangeTransitionEnd: function (sw) {
        onTransitionEnd(sw);
      },
    },
  });
});
</script>
