<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryCustomFieldInstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;

/**
 * Der Installer läuft bei jeder Installation und bei jedem Update erneut.
 *
 * Alles, was er zweimal anlegen will, kollidiert beim zweiten Mal mit einem eindeutigen
 * Schlüssel — und ein gescheiterter Installer nimmt die Installation mit. Deshalb prüfen die
 * Fälle hier genau das: Wird ein bestehender Bestand **wiedererkannt**, statt neu angelegt?
 */
final class GalleryCustomFieldInstallerTest extends TestCase
{
    /**
     * Was: Ein Feld, das es schon gibt, kommt mit seiner Kennung in die Nutzlast.
     * Warum: Ohne Kennung macht Shopware daraus ein Anlegen, und der eindeutige Schlüssel auf
     *        `custom_field.name` bricht — bei jedem Update einer Bestandsinstallation.
     */
    public function testAnExistingFieldIsUpdatedInsteadOfCreatedAgain(): void
    {
        $fieldId = Uuid::randomHex();
        $field = new CustomFieldEntity();
        $field->setId($fieldId);
        $field->setUniqueIdentifier($fieldId);
        $field->setName(GalleryCustomFieldInstaller::MEDIA_IDS_FIELD);
        $field->setType('json');

        $payload = $this->runInstall(fields: [$field]);

        self::assertSame($fieldId, $payload['customFields'][0]['id'] ?? null);
    }

    /**
     * Was: Ein bestehendes Set wird über seine Kennung fortgeschrieben.
     * Warum: Dieselbe Falle eine Ebene höher — ohne Kennung kollidiert der eindeutige
     *        Schlüssel auf dem Namen des Sets.
     */
    public function testAnExistingSetKeepsItsId(): void
    {
        $setId = Uuid::randomHex();

        $payload = $this->runInstall(set: $this->set($setId));

        self::assertSame($setId, $payload['id'] ?? null);
    }

    /**
     * Was: Die vorhandene Zuordnung zur Produkt-Entität wird wiederverwendet.
     * Warum: Auf `custom_field_set_relation.entity_name` liegt ebenfalls ein eindeutiger
     *        Schlüssel. Das ist die dritte Ebene, und sie fehlt in den meisten Installern.
     */
    public function testTheExistingRelationIsReused(): void
    {
        $setId = Uuid::randomHex();
        $relationId = Uuid::randomHex();

        $relation = new CustomFieldSetRelationEntity();
        $relation->setId($relationId);
        $relation->setUniqueIdentifier($relationId);
        $relation->setEntityName('product');

        $set = $this->set($setId);
        $set->setRelations(new CustomFieldSetRelationCollection([$relation]));

        $payload = $this->runInstall(set: $set);

        self::assertSame($relationId, $payload['relations'][0]['id'] ?? null);
        self::assertSame('product', $payload['relations'][0]['entityName'] ?? null);
    }

    /**
     * Was: Ein Feld mit abweichendem Typ wird vor dem Schreiben entfernt.
     * Warum: Der Typ eines Feldes ist in Shopware unveränderlich. Ein Bestand aus einer
     *        früheren Fassung mit anderem Typ ließe sich nicht fortschreiben — der Installer
     *        müsste scheitern oder den Typ stillschweigend ignorieren. Er löscht stattdessen
     *        und legt neu an.
     */
    public function testAFieldWithADriftedTypeIsDroppedFirst(): void
    {
        $fieldId = Uuid::randomHex();
        $drift = new CustomFieldEntity();
        $drift->setId($fieldId);
        $drift->setUniqueIdentifier($fieldId);
        $drift->setName(GalleryCustomFieldInstaller::MEDIA_IDS_FIELD);
        $drift->setType('text');

        $deleted = [];
        $this->runInstall(fields: [$drift], deleted: $deleted);

        self::assertSame([['id' => $fieldId]], $deleted);
    }

    /**
     * Die Gegenprobe: Stimmt der Typ, wird nichts gelöscht. Ein Installer, der bei jedem Lauf
     * Felder wegwirft und neu anlegt, verlöre die daran hängenden Werte.
     */
    public function testAMatchingTypeIsLeftAlone(): void
    {
        $fieldId = Uuid::randomHex();
        $matching = new CustomFieldEntity();
        $matching->setId($fieldId);
        $matching->setUniqueIdentifier($fieldId);
        $matching->setName(GalleryCustomFieldInstaller::MEDIA_IDS_FIELD);
        $matching->setType('json');

        $deleted = [];
        $this->runInstall(fields: [$matching], deleted: $deleted);

        self::assertSame([], $deleted);
    }

    /**
     * Führt den Installer mit vorgegebenem Bestand aus und gibt die geschriebene Nutzlast
     * zurück.
     *
     * @param list<CustomFieldEntity>    $fields  Was in `custom_field` schon steht
     * @param list<array<string, mixed>> $deleted Sammelstelle für gelöschte Felder
     *
     * @return array<string, mixed>
     */
    private function runInstall(array $fields = [], ?CustomFieldSetEntity $set = null, array &$deleted = []): array
    {
        $context = Context::createDefaultContext();

        $setRepository = $this->createMock(EntityRepository::class);
        $setRepository->method('search')->willReturn($this->fieldSetResult($set, $context));

        $written = [];
        $setRepository->method('upsert')->willReturnCallback(
            function (array $data) use (&$written): EntityWrittenContainerEvent {
                $written = $data[0] ?? [];

                return $this->createMock(EntityWrittenContainerEvent::class);
            },
        );

        $fieldRepository = $this->createMock(EntityRepository::class);
        $fieldRepository->method('search')->willReturn($this->fieldResult($fields, $context));
        $fieldRepository->method('delete')->willReturnCallback(
            function (array $data) use (&$deleted): EntityWrittenContainerEvent {
                $deleted = $data;

                return $this->createMock(EntityWrittenContainerEvent::class);
            },
        );

        (new GalleryCustomFieldInstaller($setRepository, $fieldRepository))->install($context);

        return $written;
    }

    private function set(string $id): CustomFieldSetEntity
    {
        $set = new CustomFieldSetEntity();
        $set->setId($id);
        $set->setUniqueIdentifier($id);
        $set->setName(GalleryCustomFieldInstaller::SET_NAME);

        return $set;
    }

    /**
     * @param list<CustomFieldEntity> $fields
     *
     * @return EntitySearchResult<CustomFieldCollection>
     */
    private function fieldResult(array $fields, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            'custom_field',
            \count($fields),
            new CustomFieldCollection($fields),
            null,
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            $context,
        );
    }

    /**
     * @return EntitySearchResult<CustomFieldSetCollection>
     */
    private function fieldSetResult(?CustomFieldSetEntity $set, Context $context): EntitySearchResult
    {
        $entries = $set === null ? [] : [$set];

        return new EntitySearchResult(
            'custom_field_set',
            \count($entries),
            new CustomFieldSetCollection($entries),
            null,
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            $context,
        );
    }
}
