<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryStruct;
use Shopware\Core\Content\Media\MediaCollection;

final class GalleryStructTest extends TestCase
{
    public function testConstructAndProperties(): void
    {
        $collection = new MediaCollection();
        $struct = new GalleryStruct($collection, 0);

        self::assertSame($collection, $struct->media);
        self::assertSame(0, $struct->totalCount);
    }

    public function testGetApiAlias(): void
    {
        $struct = new GalleryStruct(new MediaCollection(), 5);
        self::assertSame('rc_customer_image_gallery', $struct->getApiAlias());
    }
}
