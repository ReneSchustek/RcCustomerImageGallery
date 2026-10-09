<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryLoader;
use Ruhrcoder\RcCustomerImageGallery\Service\GalleryStruct;
use Ruhrcoder\RcCustomerImageGallery\Subscriber\ProductPageSubscriber;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Die Entscheidungen, die vor dem Laden der Galerie fallen.
 *
 * Alle drei sind still, wenn sie falsch ausgehen: Der Kunde sieht keine Bilder, und nichts
 * meldet sich. Deshalb steht die Gegenprobe jeweils daneben.
 */
final class ProductPageSubscriberTest extends TestCase
{
    public function testItAttachesTheGalleryToThePage(): void
    {
        $event = $this->event();

        $this->subscriber(['RcCustomerImageGallery.config.enabled' => true])->onProductPageLoaded($event);

        self::assertTrue($event->getPage()->hasExtension('rcCustomerImageGallery'));
    }

    /**
     * Ohne Einstellung ist die Galerie an. Ein Plugin, das nach der Installation nichts tut,
     * sieht aus wie ein kaputtes.
     */
    public function testWithoutAnySettingTheGalleryIsOn(): void
    {
        $event = $this->event();

        $this->subscriber([])->onProductPageLoaded($event);

        self::assertTrue($event->getPage()->hasExtension('rcCustomerImageGallery'));
    }

    public function testASwitchedOffGalleryStaysAway(): void
    {
        $event = $this->event();

        $this->subscriber(['RcCustomerImageGallery.config.enabled' => false])->onProductPageLoaded($event);

        self::assertFalse($event->getPage()->hasExtension('rcCustomerImageGallery'));
    }

    /**
     * Was: Die eingestellte Zahl erreicht den Lader.
     * Warum: Sie ist die einzige Einstellung mit sichtbarer Wirkung. Käme sie nicht an, fiele
     *        das niemandem auf — es sähe nach „so viele Bilder gibt es eben" aus.
     */
    public function testTheConfiguredLimitReachesTheLoader(): void
    {
        $loader = $this->createMock(GalleryLoader::class);
        $loader->expects(self::once())
            ->method('loadForProduct')
            ->with(self::anything(), self::anything(), 4)
            ->willReturn(new GalleryStruct(new MediaCollection(), 0));

        $this->subscriber(['RcCustomerImageGallery.config.limit' => 4], $loader)
            ->onProductPageLoaded($this->event());
    }

    /**
     * Das Eingabefeld liefert die Zahl je nach Herkunft als Zeichenkette. Ohne diese
     * Umwandlung stünde dort eine Zeichenkette, und der Lader bekäme sie nie zu sehen.
     */
    public function testANumericStringCountsAsANumber(): void
    {
        $loader = $this->createMock(GalleryLoader::class);
        $loader->expects(self::once())
            ->method('loadForProduct')
            ->with(self::anything(), self::anything(), 7)
            ->willReturn(new GalleryStruct(new MediaCollection(), 0));

        $this->subscriber(['RcCustomerImageGallery.config.limit' => '7'], $loader)
            ->onProductPageLoaded($this->event());
    }

    /**
     * **Der Punkt, um den es geht.** Steht nichts Brauchbares in der Einstellung, entscheidet
     * der Lader — der Subscriber führt keine eigene Vorgabe mehr. Zwei Vorgaben liefen
     * auseinander, sobald jemand nur eine von beiden ändert.
     *
     * Geprüft über den Aufruf ohne dritten Parameter: Genau dann greift die Vorgabe des Laders.
     */
    public function testAnUnusableSettingLeavesTheDecisionToTheLoader(): void
    {
        foreach ([null, '', 'zwölf', '3,5', -1.5] as $unbrauchbar) {
            $loader = $this->createMock(GalleryLoader::class);
            $loader->expects(self::once())
                ->method('loadForProduct')
                ->with(self::anything(), self::anything())
                ->willReturn(new GalleryStruct(new MediaCollection(), 0));

            $this->subscriber(['RcCustomerImageGallery.config.limit' => $unbrauchbar], $loader)
                ->onProductPageLoaded($this->event());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function subscriber(array $config, ?GalleryLoader $loader = null): ProductPageSubscriber
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key) => $config[$key] ?? null,
        );

        if ($loader === null) {
            $loader = $this->createMock(GalleryLoader::class);
            $loader->method('loadForProduct')->willReturn(new GalleryStruct(new MediaCollection(), 0));
        }

        return new ProductPageSubscriber($loader, $systemConfig);
    }

    private function event(): ProductPageLoadedEvent
    {
        $product = new SalesChannelProductEntity();
        $product->setId('019f0000000000000000000000000001');
        $product->setUniqueIdentifier('019f0000000000000000000000000001');

        $page = new ProductPage();
        $page->setProduct($product);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('019f0000000000000000000000000002');
        $salesChannel->setUniqueIdentifier('019f0000000000000000000000000002');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return new ProductPageLoadedEvent($page, $context, new Request());
    }
}
