# Rezeptsammlung – static recipe site generator

A small static site generator written in PHP 8.4. Recipes are written as Markdown + JSON;
`php build.php` turns them into a fully static website with client-side search that runs on any
Apache web server – no PHP, database or external services needed at runtime.

The generated website (templates, UI texts) is German.

## Requirements

- PHP 8.4 (CLI); `openssl` is only needed for the one-time download of Bootstrap and jQuery,
  `gd` only for the tests
- Composer 2
- Any web server for the output (an `.htaccess` for Apache is generated)

## Installation

```bash
composer install
```

Create `config/site.php` with your own values. It is ignored by Git and only needs what differs
from `config/site.dist.php` – usually just the URL:

```php
<?php
return ['base_url' => 'https://your-domain.example'];
```

Then build:

```bash
php build.php
```

The website is written to `public/`. Bootstrap and jQuery are downloaded once on the first build
and served locally afterwards.

## Commands

| Command | Effect |
|---|---|
| `php build.php` | Build the website |
| `php build.php --dry-run` | Validate all recipes without writing files |
| `php build.php -v` | Verbose output |
| `php build.php --base-url=http://localhost/rezepte/public` | Override the base URL, e.g. for local testing |
| `php build.php --config=path/site.php` | Use a different local configuration file |
| `composer check` | Coding style (PSR-12), static analysis (PHPStan) and tests |
| `composer cs:fix` | Fix the coding style |

## Adding a recipe

1. Create a directory `recipes/<category>/<recipe>/` (lowercase letters, digits and hyphens),
   e.g. `recipes/kuchen/zwetschgenkuchen/` → URL `/kuchen/zwetschgenkuchen/`.
2. Add `meta.json`, `content.md` and `image.jpg`.
3. Run `php build.php`.

New directories automatically become new categories. Directories starting with `_` are ignored,
which is handy for drafts. An invalid recipe stops the build with a list of all problems; the
previously generated website stays untouched.

A complete example that uses every field and Markdown feature is in
`recipes/_example/pancakes/`. Copy it into a category directory to publish it.

### meta.json

```json
{
    "title": "Brioche",
    "description": "Ein lockeres, leicht süßliches Brioche.",
    "category": "Brot",
    "tags": ["Hefeteig", "Frühstück"],
    "created": "2026-11-15",
    "updated": "2026-11-15",
    "times": [
        { "type": "preparation", "minutes": 30 },
        { "type": "resting", "minutes": 120, "label": "Gehzeit" },
        { "type": "cooking", "minutes": 35, "label": "Backen" }
    ],
    "yield": { "amount": 1, "unit": "Brioche" },
    "difficulty": "mittel"
}
```

| Field | Required | Description |
|---|---|---|
| `title` | yes | recipe name |
| `description` | yes | short description (cards, meta description) |
| `category` | yes | display name of the category |
| `times` | yes | time segments in order of work (see below) |
| `yield` | yes | `amount` (integer) and optional `unit` (default: "Portion"/"Portionen") |
| `difficulty` | yes | `leicht`, `mittel` or `schwer` |
| `tags` | no | list of keywords |
| `created`, `updated` | no | dates as `YYYY-MM-DD`; only recipes with `created` appear under "Neueste Rezepte" |
| `image` | no | image file name in the recipe directory (default: `image.jpg`) |

Each entry in `times` has a `type` – `preparation`, `cooking` or `resting` – the `minutes` and an
optional `label` shown on the recipe page. Use as many entries as the recipe needs, e.g. several
proofing phases. The total time is calculated automatically.

### content.md

Regular Markdown with GitHub extensions: tables, task lists, strikethrough, autolinks, code blocks,
footnotes, definition lists, `{.class}` attributes and `[TOC]` for a table of contents. Raw HTML is
escaped for security.

A leading `# Heading` is hidden, as the title comes from `meta.json`.

Further images can be placed in the recipe directory and embedded by file name, e.g.
`![Teig nach dem Kneten](schritt-1.jpg)`. External images are not allowed; the build reports them
and any missing files.

## Configuration

`config/site.dist.php` contains all defaults and is versioned. Put your own values – at least
`base_url` – into `config/site.php`, which is ignored by Git and only needs the values that differ.

| Key | Meaning |
|---|---|
| `name`, `description` | website name and description |
| `base_url` | absolute URL of the website, e.g. `https://rezepte.example.org` |
| `robots` | who may crawl the site (see below) |
| `pagination.per_page` | recipes per category page |
| `home.newest_count` | number of newest recipes on the home page |
| `default_image` | Open Graph image for pages without a recipe image |
| `paths.*` | directories for recipes, output, templates and assets |
| `markdown.*` | anchor prefix and link title of the heading permalinks |

### Search engines and AI crawlers

| `robots` | Effect |
|---|---|
| `all` (default) | all crawlers allowed |
| `search_engines` | only Google, Bing, DuckDuckGo and Apple allowed; all other crawlers, including AI crawlers, are excluded |
| `none` | no crawling and no indexing |

Note that robots.txt is a request that well-behaved crawlers follow, not an access restriction.
For a truly private site, use password protection on the server. Crawlers only read
`/robots.txt` at the root of a domain, so the setting has no effect if the site lives in a
sub-directory (e.g. `https://example.org/rezepte/`).

## Deployment

1. Set `base_url` in `config/site.php` and run `php build.php`.
2. Upload the **contents** of `public/` to your web server, including the hidden `.htaccess` file.

## License

Released under the [MIT License](LICENSE). Bootstrap and jQuery are also MIT-licensed.
