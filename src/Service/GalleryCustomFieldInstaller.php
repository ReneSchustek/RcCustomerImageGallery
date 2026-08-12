<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Idempotenter Installer für das Produkt-CustomFieldSet der Kundenbild-Galerie.
 *
 * Nach Haus-Standard (3-Ebenen-Idempotenz): Set-ID, Field-ID und Relation-ID werden
 * vor jedem Upsert per Name aufgelöst und in die Payload gespiegelt, damit ein
 * Re-Install/Update keine uniq-Indizes (custom_field.name,
 * custom_field_set_relation.entity_name) verletzt. Type-Drift aus älteren
 * Installationen wird vor dem Upsert reconciled, weil der Feld-Type in Shopware
 * immutable ist.
 */
final class GalleryCustomFieldInstaller
{
    public const SET_NAME = 'rc_customer_image_gallery';
    public const MEDIA_IDS_FIELD = 'rc_customer_image_gallery_media_ids';

    private const LOG_CONTEXT = 'ruhrcoder_customer_image_gallery.installer';

    /**
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     * @param EntityRepository<CustomFieldCollection>    $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldRepository,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function install(Context $context): void
    {
        $fields = $this->buildFieldDefinitions();

        // Type-Drift aus historischen Installationen reconcilen: type ist in Shopware
        // immutable, daher droppen wir Drift-Felder bevor enrichWithExistingIds versucht,
        // ihre IDs zu mappen. Der nachfolgende upsert legt sie dann mit korrektem Type neu an.
        $this->reconcileTypeDrift($fields, $context);

        // Bestehende Felder per Name -> ID auflösen, damit upsert UPDATE statt INSERT macht
        // (sonst kollidiert der uniq.custom_field.name-Index beim Upgrade einer Bestandsinstallation).
        $fields = $this->enrichWithExistingIds($fields, $context);

        // Set + Set-Relation analog idempotent: bestehende IDs in Payload einreichen,
        // sonst kollidiert uniq.custom_field_set_relation.entity_name beim Re-Upsert.
        $existingSet = $this->resolveExistingSet(self::SET_NAME, $context);

        $data = [
            'name' => self::SET_NAME,
            'config' => [
                'label' => [
                    'de-DE' => 'Kundenbilder-Galerie',
                    'en-GB' => 'Customer Image Gallery',
                ],
            ],
            'customFields' => $fields,
            'relations' => $this->buildRelationsPayload($existingSet),
        ];

        if ($existingSet !== null) {
            $data['id'] = $existingSet->getId();
        }

        try {
            $this->customFieldSetRepository->upsert([$data], $context);

            $this->logger->info('RcCustomerImageGallery: CustomFieldSet installiert/aktualisiert.', [
                'context' => self::LOG_CONTEXT,
                'setName' => self::SET_NAME,
                'fieldCount' => count($fields),
                'updatedSet' => $existingSet !== null,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('RcCustomerImageGallery: CustomFieldSet-Install fehlgeschlagen.', [
                'context' => self::LOG_CONTEXT,
                'setName' => self::SET_NAME,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('RcCustomerImageGallery: CustomFieldSet-Install fehlgeschlagen (%s).', self::SET_NAME), 0, $exception);
        }
    }

    public function uninstall(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::SET_NAME));
        $set = $this->customFieldSetRepository->search($criteria, $context)->getEntities()->first();

        if (!$set instanceof CustomFieldSetEntity) {
            $this->logger->info('RcCustomerImageGallery: CustomFieldSet bereits abwesend, Uninstall ist No-op.', [
                'context' => self::LOG_CONTEXT,
                'setName' => self::SET_NAME,
            ]);

            return;
        }

        try {
            $this->customFieldSetRepository->delete([['id' => $set->getId()]], $context);

            $this->logger->info('RcCustomerImageGallery: CustomFieldSet entfernt.', [
                'context' => self::LOG_CONTEXT,
                'setName' => self::SET_NAME,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('RcCustomerImageGallery: CustomFieldSet-Uninstall fehlgeschlagen.', [
                'context' => self::LOG_CONTEXT,
                'setName' => self::SET_NAME,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('RcCustomerImageGallery: CustomFieldSet-Uninstall fehlgeschlagen (%s).', self::SET_NAME), 0, $exception);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function reconcileTypeDrift(array $fields, Context $context): void
    {
        $expectedTypeByName = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;
            if (is_string($name) && is_string($type) && $name !== '') {
                $expectedTypeByName[$name] = $type;
            }
        }

        if ($expectedTypeByName === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', array_keys($expectedTypeByName)));

        $toDelete = [];
        $drift = [];
        foreach ($this->customFieldRepository->search($criteria, $context)->getEntities() as $entity) {
            if (!$entity instanceof CustomFieldEntity) {
                continue;
            }
            $name = $entity->getName();
            $currentType = $entity->getType();
            $expectedType = $expectedTypeByName[$name] ?? null;
            if ($expectedType !== null && $currentType !== $expectedType) {
                $toDelete[] = ['id' => $entity->getId()];
                $drift[] = [
                    'name' => $name,
                    'oldType' => $currentType,
                    'newType' => $expectedType,
                ];
            }
        }

        if ($toDelete === []) {
            return;
        }

        $this->customFieldRepository->delete($toDelete, $context);

        $this->logger->info('RcCustomerImageGallery: Type-Drift in CustomField-Definitionen aufgelöst.', [
            'context' => self::LOG_CONTEXT,
            'droppedCount' => count($toDelete),
            'drift' => $drift,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithExistingIds(array $fields, Context $context): array
    {
        $names = array_values(array_filter(
            array_map(static fn (array $field): mixed => $field['name'] ?? null, $fields),
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));

        if ($names === []) {
            return $fields;
        }

        $existingIds = $this->resolveExistingFieldIds($names, $context);
        if ($existingIds === []) {
            return $fields;
        }

        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? null;
            if (is_string($name) && isset($existingIds[$name])) {
                $fields[$index]['id'] = $existingIds[$name];
            }
        }

        return $fields;
    }

    /**
     * @param array<int, string> $names
     *
     * @return array<string, string>
     */
    private function resolveExistingFieldIds(array $names, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $map = [];
        foreach ($this->customFieldRepository->search($criteria, $context)->getEntities() as $entity) {
            if (!$entity instanceof CustomFieldEntity) {
                continue;
            }
            $name = $entity->getName();
            if (is_string($name) && $name !== '') {
                $map[$name] = $entity->getId();
            }
        }

        return $map;
    }

    private function resolveExistingSet(string $name, Context $context): ?CustomFieldSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->addAssociation('relations');

        $set = $this->customFieldSetRepository->search($criteria, $context)->getEntities()->first();

        return $set instanceof CustomFieldSetEntity ? $set : null;
    }

    /**
     * Liefert die Relations als Upsert-Payload. Wenn das Set bereits eine
     * product-Relation hat, wird deren ID mitgegeben - sonst legt Shopware eine neue an
     * und der unique-Index (set_id, entity_name) bricht beim Re-Upsert.
     *
     * @return array<int, array<string, string>>
     */
    private function buildRelationsPayload(?CustomFieldSetEntity $existingSet): array
    {
        $productRelation = ['entityName' => 'product'];

        if ($existingSet === null) {
            return [$productRelation];
        }

        $relations = $existingSet->getRelations();
        if ($relations === null) {
            return [$productRelation];
        }

        foreach ($relations as $relation) {
            if ($relation->getEntityName() === 'product') {
                $productRelation['id'] = $relation->getId();
                break;
            }
        }

        return [$productRelation];
    }

    /**
     * Ein einziges JSON-Feld hält die geordnete Liste der Media-UUIDs. Die Auswahl
     * erfolgt über den eigenen Produkt-Tab (sw-media-modal-v2), daher braucht das Feld
     * keine Default-Storefront-Komponente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFieldDefinitions(): array
    {
        return [
            [
                'name' => self::MEDIA_IDS_FIELD,
                'type' => CustomFieldTypes::JSON,
                'config' => [
                    'label' => [
                        'de-DE' => 'Kundenbilder-Galerie: Medien',
                        'en-GB' => 'Customer Image Gallery: Media',
                    ],
                    'helpText' => [
                        'de-DE' => 'Geordnete Liste der Media-IDs, die in der Produkt-Galerie angezeigt werden. Pflege über den Tab „Kundenbilder-Galerie".',
                        'en-GB' => 'Ordered list of media IDs shown in the product gallery. Managed via the "Customer Image Gallery" tab.',
                    ],
                    'customFieldType' => 'json',
                    'customFieldPosition' => 1,
                ],
            ],
        ];
    }
}
