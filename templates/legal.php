<div class="card">
    <h1><?= h($heading ?? '') ?></h1>
    <div style="margin-top:1rem;line-height:1.75;">
        <?= $content ?? '' /* stored as HTML in site_settings — admin input */ ?>
    </div>
</div>
