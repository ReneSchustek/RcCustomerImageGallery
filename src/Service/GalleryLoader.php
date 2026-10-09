<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Service;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Löst die im Produkt-CustomField hinterlegten Media-IDs in echte MediaEntities auf.
 *
 * Die Bildquelle ist die manuell im Admin-Tab gepflegte, geordnete ID-Liste
 * (CustomField rc_customer_image_gallery_media_ids). Die DAL liefert unabhängig vom
 * Abfrageweg keine garantierte Reihenfolge, daher wird die ID-Reihenfolge nach dem
 * Laden in EINER Query wiederhergestellt.
 */
// Bewusst nicht `final`: Der Subscriber wird gegen eine Doublette dieses Laders geprüft.
// Ohne das ginge nur der Weg über ein nachgebautes Ablage-Doppel — dann prüfte der Test
// die Suchbedingungen des Laders statt der Entscheidung, um die es dem Subscriber geht.
class GalleryLoader
{
    private const DEFAULT_LIMIT = 12;
    private const MAX_LIMIT = 50;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function loadForProduct(ProductEntity $product, Context $context, int $limit = self::DEFAULT_LIMIT): GalleryStruct
    {
        $clampedLimit = max(1, min(self::MAX_LIMIT, $limit));

        $mediaIds = $this->extractMediaIds($product);
        if ($mediaIds === []) {
            return new GalleryStruct(new MediaCollection(), 0);
        }

        $mediaIds = \array_slice($mediaIds, 0, $clampedLimit);

        // Die Kennungen gehören in den Criteria-Aufbau, nicht in einen Filter: Shopware löst sie
        // dann über den Primärschlüssel auf, statt eine Bedingung über die Tabelle zu legen.
        $criteria = new Criteria($mediaIds);
        $criteria->addAssociation('thumbnails');
        $criteria->setLimit($clampedLimit);

        $media = $this->mediaRepository->search($criteria, $context)->getEntities();

        $ordered = $this->restoreOrder($mediaIds, $media);

        return new GalleryStruct(new MediaCollection($ordered), \count($ordered));
    }

    /**
     * Liest die geordnete, deduplizierte Liste nicht-leerer Media-IDs aus dem CustomField.
     *
     * @return list<string>
     */
    private function extractMediaIds(ProductEntity $product): array
    {
        $customFields = $product->getCustomFields() ?? [];
        $raw = $customFields[GalleryCustomFieldInstaller::MEDIA_IDS_FIELD] ?? null;
        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (\is_string($value) && $value !== '' && !\in_array($value, $ids, true)) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    /**
     * Stellt die Reihenfolge der gepflegten ID-Liste wieder her (DAL gibt unsortiert zurück).
     *
     * @param list<string> $mediaIds
     *
     * @return list<MediaEntity>
     */
    private function restoreOrder(array $mediaIds, MediaCollection $media): array
    {
        $byId = [];
        foreach ($media as $entity) {
            $byId[$entity->getId()] = $entity;
        }

        $ordered = [];
        foreach ($mediaIds as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }
}
