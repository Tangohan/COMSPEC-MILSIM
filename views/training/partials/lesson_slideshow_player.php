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
  var slideCount = el.querySelectorAll('.swiper-slide').length;
  if (slideCount < 1) return;
  var visited = new Set();
  function note(sw) {
    if (sw && typeof sw.realIndex === 'number') {
      visited.add(sw.realIndex);
    }
    if (
      visited.size >= slideCount &&
      window.LmsLessonProgress &&
      typeof window.LmsLessonProgress.signalComplete === 'function'
    ) {
      window.LmsLessonProgress.signalComplete();
    }
  }
  new Swiper(el, {
    loop: slideCount > 1,
    pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
    navigation: {
      nextEl: el.querySelector('.swiper-button-next'),
      prevEl: el.querySelector('.swiper-button-prev'),
    },
    on: {
      init: function (sw) {
        note(sw);
      },
      slideChange: function (sw) {
        note(sw);
      },
    },
  });
});
</script>
