<?php

declare(strict_types=1);

/*
 * Central default configuration of the recipe site generator (versioned).
 *
 * Private or installation-specific values (domain …) do NOT belong
 * here but in config/site.php. That file is excluded via .gitignore and only
 * needs the values that differ; nested arrays are merged, e.g.:
 *
 *   <?php
 *   return ['base_url' => 'https://your-domain.example'];
 *
 * Relative paths are resolved against the project directory.
 */
return [
    // Name and description are website content and therefore German.
    'name' => 'Rezeptsammlung',
    'description' => 'Selbst erprobte Rezepte für Brot, Kuchen und herzhafte Gerichte.',
    'language' => 'de',
    'locale' => 'de_DE',

    // Absolute base URL without a trailing slash. Its path part
    // (e.g. "/rezepte/public") is prepended to all internal links.
    // Usually set in config/site.php; can additionally be overridden
    // with --base-url when building.
    'base_url' => 'https://rezepte.example.org',

    // Who may crawl the website (robots.txt, noindex):
    //   'all'            – all crawlers (default)
    //   'search_engines' – only Google, Bing, DuckDuckGo and Apple;
    //                      everything else, including AI crawlers, is excluded
    //   'none'           – no crawling and no indexing
    // Note: robots.txt is a request, not an access restriction.
    'robots' => 'all',

    'paths' => [
        'recipes' => 'recipes',
        'output' => 'public',
        'templates' => 'templates',
        'assets' => 'assets',
    ],

    // Used as the Open Graph image for pages without an image of their own
    // (relative to the assets directory).
    'default_image' => 'static/default.jpg',

    'pagination' => [
        'per_page' => 12,
    ],

    'home' => [
        'newest_count' => 3,
    ],

    // Texts and IDs that end up in the HTML generated from Markdown.
    'markdown' => [
        'heading_id_prefix' => 'abschnitt',
        'permalink_title' => 'Link zu diesem Abschnitt',
    ],

    // Front-end libraries: downloaded from the CDN once, verified against
    // the official SRI hash and cached in assets/vendor/.
    // Only Bootstrap's CSS is used; the few interactions (mobile navigation,
    // search, checklists) are implemented with jQuery in assets/js/.
    'vendor' => [
        'bootstrap.min.css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css',
            'integrity' => 'sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX',
        ],
        'jquery.min.js' => [
            'url' => 'https://code.jquery.com/jquery-4.1.0.min.js',
            'integrity' => 'sha256-hjRvPkFLO1JGZFMXGrzdpG6TlsHDyjp4PQeqLWRcMzk=',
        ],
    ],
];
