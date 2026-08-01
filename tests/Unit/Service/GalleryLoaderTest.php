<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryCustomFieldInstaller;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryLoader;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

final class GalleryLoaderTest extends TestCase
{
    public function testReturnsEmptyGalleryWhenCustomFieldMissing(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->never())->method('search');

        $loader = new GalleryLoader($repo);
        $struct = $loader->loadForProduct($this->product(null), Context::createDefaultContext());

        self::assertInstanceOf(GalleryStruct::class, $struct);
        self::assertSame(0, $struct->totalCount);
        self::assertCount(0, $struct->media);
    }

    public function testReturnsEmptyGalleryWhenIdListEmpty(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->never())->method('search');

        $loader = new GalleryLoader($repo);
        $struct = $loader->loadForProduct($this->product([]), Context::createDefaultContext());

        self::assertSame(0, $struct->totalCount);
    }

    public function testRestoresIdOrderRegardlessOfDalOrder(): void
    {
        // CustomField-Reihenfolge: b, a, c -- DAL liefert unsortiert: a, b, c
        $product = $this->product(['id-b', 'id-a', 'id-c']);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->resultWith([
            $this->media('id-a'),
            $this->media('id-b'),
            $this->media('id-c'),
        ]));

        $loader = new GalleryLoader($repo);
        $struct = $loader->loadForProduct($product, Context::createDefaultContext());

        $orderedIds = array_map(static fn (MediaEntity $m): string => $m->getId(), array_values($struct->media->getElements()));

        self::assertSame(['id-b', 'id-a', 'id-c'], $orderedIds);
        self::assertSame(3, $struct->totalCount);
    }

    public function testSkipsIdsWithoutResolvedMedia(): void
    {
        $product = $this->product(['id-a', 'id-missing', 'id-c']);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->resultWith([
            $this->media('id-c'),
            $this->media('id-a'),
        ]));

        $loader = new GalleryLoader($repo);
        $struct = $loader->loadForProduct($product, Context::createDefaultContext());

        $orderedIds = array_map(static fn (MediaEntity $m): string => $m->getId(), array_values($struct->media->getElements()));

        self::assertSame(['id-a', 'id-c'], $orderedIds);
        self::assertSame(2, $struct->totalCount);
    }

    public function testIgnoresNonStringAndEmptyAndDuplicateIds(): void
    {
        $product = $this->product(['id-a', '', null, 42, 'id-a', 'id-b']);

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(static function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();
                    if (\count($filters) !== 1 || !$filters[0] instanceof EqualsAnyFilter) {
                        return false;
                    }

                    return $filters[0]->getValue() === ['id-a', 'id-b'];
                }),
                $this->isInstanceOf(Context::class),
            )
            ->willReturn($this->resultWith([
                $this->media('id-a'),
                $this->media('id-b'),
            ]));

        $loader = new GalleryLoader($repo);
        $struct = $loader->loadForProduct($product, Context::createDefaultContext());

        self::assertSame(2, $struct->totalCount);
    }

    public function testClampsLimitAndSlicesIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 60; $i++) {
            $ids[] = 'id-' . $i;
        }
        $product = $this->product($ids);

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(static function (Criteria $criteria): bool {
                    if ($criteria->getLimit() !== 50) {
                        return false;
                    }
                    $filters = $criteria->getFilters();
                    if (\count($filters) !== 1 || !$filters[0] instanceof EqualsAnyFilter) {
                        return false;
                    }

                    return \count($filters[0]->getValue()) === 50;
                }),
                $this->isInstanceOf(Context::class),
            )
            ->willReturn($this->resultWith([]));

        $loader = new GalleryLoader($repo);
        $loader->loadForProduct($product, Context::createDefaultContext(), 9999);
    }

    /**
     * @param list<mixed>|null $mediaIds
     */
    private function product(?array $mediaIds): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId('prod-1');
        $product->setUniqueIdentifier('prod-1');
        if ($mediaIds !== null) {
            $product->setCustomFields([GalleryCustomFieldInstaller::MEDIA_IDS_FIELD => $mediaIds]);
        }

        return $product;
    }

    private function media(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);
        $media->setUniqueIdentifier($id);

        return $media;
    }

    /**
     * @param list<MediaEntity> $media
     *
     * @return EntitySearchResult<MediaCollection>
     */
    private function resultWith(array $media): EntitySearchResult
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new MediaCollection($media));

        return $result;
    }
}
