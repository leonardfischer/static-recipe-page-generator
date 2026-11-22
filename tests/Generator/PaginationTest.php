<?php

declare(strict_types=1);

namespace Rezepte\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Rezepte\Generator\Pagination;

final class PaginationTest extends TestCase
{
    public function testBuildsPageUrls(): void
    {
        $pagination = new Pagination(2, 3, '/brot/');

        self::assertSame('/brot/', $pagination->urlPath(1));
        self::assertSame('/brot/seite-3/', $pagination->urlPath(3));
        self::assertTrue($pagination->hasPrevious());
        self::assertTrue($pagination->hasNext());
        self::assertSame([1, 2, 3], $pagination->pages());
    }

    public function testCountsPages(): void
    {
        self::assertSame(3, Pagination::pageCount(25, 12));
        self::assertSame(1, Pagination::pageCount(12, 12));
        self::assertSame(1, Pagination::pageCount(0, 12));
    }

    public function testRecognisesPageSegments(): void
    {
        self::assertTrue(Pagination::isPageSegment('seite-2'));
        self::assertFalse(Pagination::isPageSegment('seiten-tipps'));
        self::assertFalse(Pagination::isPageSegment('seite-'));
    }
}
