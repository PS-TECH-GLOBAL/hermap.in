<?php
declare(strict_types=1);

function ps_escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ps_url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

function ps_asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT_PATH . '/assets/' . $path;
    return ASSETS_URL . '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function ps_icon(string $name): string
{
    return '<i class="fa-solid fa-' . ps_escape($name) . '" aria-hidden="true"></i>';
}

function ps_campaign(): string
{
    return '<span class="campaign-primary">Every Report,</span> <span class="campaign-secondary">Saves a Life.</span>';
}

function ps_logo(): string
{
    // Prefer the supplied artwork, preserving its embedded tagline and proportions.
    if (is_file(ROOT_PATH . '/assets/images/site/logo-website.png')) {
        return '<img class="brand-logo" src="' . ps_escape(ps_asset('images/site/logo-website.png')) . '" width="500" height="163" alt="HerMap.in — Know Before You Go.">';
    }
    foreach (['logo.svg', 'logo.webp', 'logo.png'] as $file) {
        if (is_file(ROOT_PATH . '/assets/images/' . $file)) {
            return '<img class="brand-logo" src="' . ps_escape(ps_asset('images/' . $file)) . '" alt="HerMap.in">';
        }
    }
    return '<span class="wordmark">Her<span>Map</span><small>.in</small></span>';
}
