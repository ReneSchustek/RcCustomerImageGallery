<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCustomerImageGallery\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * Nagelt die Einhängestellen der Galerie fest.
 *
 * Ein `sw_extends`-Block, den es im Ziel-Template nicht gibt, wird von Twig wortlos
 * übergangen — das Markup erscheint dann nie, und nichts wird rot. Genau daran ist in einem
 * Schwester-Plugin ein Banner monatelang unsichtbar geblieben.
 *
 * Beide Blöcke hier gehören zur Bewertungs-Registerkarte der Produktseite. Benennt der Kern
 * sie um, fällt es an dieser Stelle auf und nicht erst im Laden.
 */
final class GalleryTemplateContractTest extends TestCase
{
    private const OVERRIDDEN_BLOCKS = [
        'element_product_description_reviews_tabs_navigation_review',
        'element_product_description_reviews_tabs_content_review',
    ];

    public function testItExtendsTheCoreReviewsElement(): void
    {
        self::assertStringContainsString(
            "{% sw_extends '@Storefront/storefront/element/cms-element-product-description-reviews.html.twig' %}",
            $this->template(),
        );
    }

    public function testBothBlocksOfTheReviewTabAreOverridden(): void
    {
        $template = $this->template();

        foreach (self::OVERRIDDEN_BLOCKS as $block) {
            self::assertStringContainsString(
                '{% block ' . $block . ' %}',
                $template,
                \sprintf('Ohne %s erscheint die Registerkarte nicht.', $block),
            );
        }
    }

    /**
     * Die Galerie ergänzt die Bewertungen, sie ersetzt sie nicht. Fiele der Aufruf des
     * Kern-Inhalts weg, verlöre die Produktseite ihre Bewertungen — ein Schaden, der weit über
     * dieses Plugin hinausginge.
     */
    public function testTheCoreContentIsKept(): void
    {
        self::assertStringContainsString('{{ parent() }}', $this->template());
    }

    private function template(): string
    {
        $pfad = __DIR__
            . '/../../../src/Resources/views/storefront/element/cms-element-product-description-reviews.html.twig';

        $inhalt = file_get_contents($pfad);
        self::assertIsString($inhalt, 'Die Vorlage der Bewertungs-Registerkarte fehlt.');

        return $inhalt;
    }
}
