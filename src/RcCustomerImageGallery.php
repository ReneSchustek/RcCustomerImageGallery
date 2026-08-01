<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryCustomFieldInstaller;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;

final class RcCustomerImageGallery extends Plugin
{
    public function install(InstallContext $context): void
    {
        parent::install($context);
        $this->getInstaller()->install($context->getContext());
    }

    public function update(UpdateContext $context): void
    {
        parent::update($context);
        $this->getInstaller()->install($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if (!$context->keepUserData()) {
            $this->getInstaller()->uninstall($context->getContext());
        }
    }

    private function getInstaller(): GalleryCustomFieldInstaller
    {
        $container = $this->container;
        if ($container === null) {
            throw new \RuntimeException('Plugin-Container ist im aktuellen Lifecycle-Zustand nicht verfuegbar.');
        }

        /** @var EntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = $container->get('custom_field_set.repository');
        /** @var EntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = $container->get('custom_field.repository');

        // Logger ist im Lifecycle-Container nicht garantiert -- Fallback auf NullLogger.
        $logger = $container->has('logger') ? $container->get('logger') : new NullLogger();
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        return new GalleryCustomFieldInstaller($setRepository, $fieldRepository, $logger);
    }
}
