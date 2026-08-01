<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Service;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Struct\Struct;

final class GalleryStruct extends Struct
{
    public function __construct(
        public readonly MediaCollection $media,
        public readonly int $totalCount,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'rc_customer_image_gallery';
    }
}
