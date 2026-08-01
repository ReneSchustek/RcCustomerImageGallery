<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Subscriber;

use Ruhrcoder\RcCustomerImageGallery\Service\GalleryLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ProductPageSubscriber implements EventSubscriberInterface
{
    private const CONFIG_ENABLED = 'RcCustomerImageGallery.config.enabled';
    private const CONFIG_LIMIT = 'RcCustomerImageGallery.config.limit';
    private const DEFAULT_LIMIT = 12;

    public function __construct(
        private readonly GalleryLoader $galleryLoader,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        if (!$this->isEnabled($salesChannelId)) {
            return;
        }

        $product = $event->getPage()->getProduct();
        if ($product->getId() === '') {
            return;
        }

        $limit = $this->getLimit($salesChannelId);
        $gallery = $this->galleryLoader->loadForProduct(
            $product,
            $event->getSalesChannelContext()->getContext(),
            $limit,
        );

        $event->getPage()->addExtension('rcCustomerImageGallery', $gallery);
    }

    private function isEnabled(?string $salesChannelId): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_ENABLED, $salesChannelId);

        return $value === null ? true : (bool) $value;
    }

    private function getLimit(?string $salesChannelId): int
    {
        $value = $this->systemConfigService->get(self::CONFIG_LIMIT, $salesChannelId);
        if (\is_int($value) || (\is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        return self::DEFAULT_LIMIT;
    }
}
