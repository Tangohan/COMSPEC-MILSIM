<?php
declare(strict_types=1);
?>
        </div>
    </div>
</div>
<?php if (is_file(base_path('public/assets/js/account-search.js'))): ?>
<script src="<?= htmlspecialchars(asset_url('assets/js/account-search.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
