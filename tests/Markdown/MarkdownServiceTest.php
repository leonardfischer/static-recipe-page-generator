<?php

declare(strict_types=1);

namespace Rezepte\Tests\Markdown;

use PHPUnit\Framework\TestCase;
use Rezepte\Markdown\MarkdownService;

final class MarkdownServiceTest extends TestCase
{
    private MarkdownService $markdown;

    protected function setUp(): void
    {
        $this->markdown = new MarkdownService('abschnitt', 'Link zu diesem Abschnitt');
    }

    public function testConvertsGithubFlavoredMarkdown(): void
    {
        $html = $this->markdown->render(<<<'MD'
            | Zutat | Menge |
            |---|---:|
            | Mehl | 500 g |

            - [x] erledigt
            - [ ] offen

            ~~alt~~ https://example.org

            ```php
            echo 1;
            ```
            MD)->html;

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<td align="right">500 g</td>', $html);
        self::assertStringContainsString('<input checked disabled type="checkbox" />', $html);
        self::assertStringContainsString('<del>alt</del>', $html);
        self::assertStringContainsString('<a href="https://example.org">https://example.org</a>', $html);
        self::assertStringContainsString('<code class="language-php">', $html);
    }

    public function testSupportsConfiguredExtensions(): void
    {
        $html = $this->markdown->render(<<<'MD'
            [TOC]

            ## Zutaten {.highlight}

            Hinweis[^1] "Zitat" -- Ende

            Begriff
            : Erklärung

            [^1]: Fußnote
            MD)->html;

        self::assertStringContainsString('class="table-of-contents"', $html);
        self::assertStringContainsString('<h2 class="highlight">Zutaten<a id="abschnitt-zutaten"', $html);
        self::assertStringContainsString('class="footnote-ref"', $html);
        self::assertStringContainsString('“Zitat” – Ende', $html);
        self::assertStringContainsString('<dt>Begriff</dt>', $html);
    }

    public function testEscapesRawHtmlAndRemovesUnsafeLinks(): void
    {
        $html = $this->markdown->render(
            "Text <b onclick=\"x()\">fett</b>\n\n<script>alert(1)</script>\n\n"
            . "[Klick](javascript:alert(1)) ![Bild](data:image/png;base64,AAAA)\n\n[Link](https://example.org){onclick=alert(1)}",
        )->html;

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<b ', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:image', $html);
        self::assertStringNotContainsString('onclick="', $html);
        self::assertStringContainsString('&lt;b onclick', $html);
    }

    public function testIgnoresIdAttributesThatCouldClashWithThePageLayout(): void
    {
        $html = $this->markdown->render('Absatz {#inhalt .hinweis}')->html;

        self::assertStringNotContainsString('id="inhalt"', $html);
        self::assertStringContainsString('class="hinweis"', $html);
    }

    public function testRemovesLeadingTitleHeading(): void
    {
        $html = $this->markdown->render("# Brioche\n\nEinleitung\n\n# Zweite H1")->html;

        self::assertStringNotContainsString('<h1>Brioche', $html);
        self::assertStringContainsString('<h1>Zweite H1</h1>', $html);
    }
}
