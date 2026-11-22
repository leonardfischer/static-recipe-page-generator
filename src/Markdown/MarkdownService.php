<?php

declare(strict_types=1);

namespace Rezepte\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Encapsulates the complete Markdown processing:
 * CommonMark + GFM + extensions and HTML sanitizing.
 */
final readonly class MarkdownService
{
    private MarkdownConverter $converter;
    private HtmlSanitizer $sanitizer;

    /**
     * @param string $headingIdPrefix prefix for heading anchors, e.g. "abschnitt" => #abschnitt-zutaten
     * @param string $permalinkTitle  title attribute of the heading permalinks (website language)
     */
    public function __construct(
        string $headingIdPrefix,
        string $permalinkTitle,
    ) {
        $environment = new Environment([
            // Security: raw HTML is escaped, javascript:/data: links are removed.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 1000,
            'attributes' => [
                // No "id": it could clash with IDs of the page layout (e.g. the skip link target).
                'allow' => ['class', 'title', 'lang', 'start', 'width', 'height'],
            ],
            'heading_permalink' => [
                'id_prefix' => $headingIdPrefix,
                'fragment_prefix' => $headingIdPrefix,
                'insert' => 'after',
                'symbol' => '#',
                'title' => $permalinkTitle,
                'min_heading_level' => 2,
                'max_heading_level' => 4,
            ],
            'table_of_contents' => [
                // Table of contents only where [TOC] appears in the Markdown.
                'position' => 'placeholder',
                'placeholder' => '[TOC]',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'html_class' => 'table-of-contents',
            ],
            'footnote' => [
                'backref_symbol' => '↩︎',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new TableOfContentsExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new DescriptionListExtension());
        $environment->addExtension(new SmartPunctExtension());
        $environment->addExtension(new AttributesExtension());

        // The recipe title comes from meta.json; a leading H1 in the Markdown
        // would display it twice.
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
            $first = $event->getDocument()->firstChild();
            if ($first instanceof Heading && $first->getLevel() === 1) {
                $first->detach();
            }
        }, 100);

        $this->converter = new MarkdownConverter($environment);
        $this->sanitizer = new HtmlSanitizer(self::sanitizerConfig());
    }

    public function render(string $markdown): RenderedMarkdown
    {
        $result = $this->converter->convert($markdown);

        return new RenderedMarkdown(
            html: $this->sanitizer->sanitize($result->getContent()),
            imageSources: self::imageSources($result->getDocument()),
        );
    }

    /**
     * @return list<string> URLs of all images embedded in the Markdown
     */
    private static function imageSources(Document $document): array
    {
        $sources = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Image) {
                $sources[] = $node->getUrl();
            }
        }

        return $sources;
    }

    /**
     * Second line of defence: only known, safe elements/attributes may
     * pass through the Markdown HTML.
     */
    private static function sanitizerConfig(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('input', ['type', 'checked', 'disabled'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            // Images must come from the recipe directory (checked during the build).
            ->allowMediaSchemes([])
            ->allowRelativeMedias()
            ->withMaxInputLength(-1);

        foreach (['id', 'class', 'title', 'lang', 'role', 'aria-hidden', 'aria-label'] as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        return $config
            ->allowAttribute('start', ['ol'])
            ->allowAttribute('align', ['td', 'th'])
            ->allowAttribute('rel', ['a']);
    }
}
