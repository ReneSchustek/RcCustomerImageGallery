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

        $context = $event->getSalesChannelContext()->getContext();
        $limit = $this->configuredLimit($salesChannelId);

        // Ohne eingestellte Zahl entscheidet der Lader. Eine eigene Vorgabe hier wäre eine
        // zweite Wahrheit — und die beiden liefen auseinander, sobald jemand nur eine von
        // beiden ändert.
        $gallery = $limit === null
            ? $this->galleryLoader->loadForProduct($product, $context)
            : $this->galleryLoader->loadForProduct($product, $context, $limit);

        $event->getPage()->addExtension('rcCustomerImageGallery', $gallery);
    }

    private function isEnabled(?string $salesChannelId): bool
    {
        $value = $this->systemConfigService->get(self::CONFIG_ENABLED, $salesChannelId);

        return $value === null ? true : (bool) $value;
    }

    /**
     * Die eingestellte Bildzahl — oder `null`, wenn nichts Brauchbares eingestellt ist.
     *
     * Das Eingabefeld liefert je nach Herkunft eine Zahl oder eine Zeichenkette; alles andere
     * (leer, Text, Komma) gilt als nicht eingestellt. Die Ober- und Untergrenze setzt der
     * Lader, nicht diese Stelle.
     */
    private function configuredLimit(?string $salesChannelId): ?int
    {
        $value = $this->systemConfigService->get(self::CONFIG_LIMIT, $salesChannelId);

        if (\is_int($value)) {
            return $value;
        }

        return \is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
