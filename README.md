# RcCustomerImageGallery

Shopware-6-Plugin für eine Galerie mit Kundenbildern auf der Produktseite.

---

## Was das Plugin macht

Jedes Produkt bekommt auf seiner Detailseite eine eigene Bildergalerie. Welche Bilder darin stehen, wählst du **von Hand aus der Media-Bibliothek**: Am Produkt gibt es dafür den Tab „Kundenbilder-Galerie". Dort fügst du Bilder über die Standard-Media-Auswahl hinzu (`sw-media-modal-v2`, Mehrfach-Auswahl) und bringst sie in der Vorschau in die gewünschte Reihenfolge. Im Shop tauchen sie dann als zusätzlicher Tab „Kundenbilder" neben Beschreibung und Bewertungen auf.

### Funktionsweise

- **Datenmodell:** CustomFieldSet `rc_customer_image_gallery` (Relation `product`) mit dem JSON-Feld `rc_customer_image_gallery_media_ids` (geordnete Liste von Media-UUIDs). Der Installer ist idempotent, ein wiederholtes Install oder Update kollidiert also nicht.
- **Admin:** Produkt-Tab mit Media-Modal und sortierbarer Vorschau (nach oben/unten verschieben, entfernen).
- **Storefront:** Eine einzige `media.repository`-Query löst die hinterlegten Media-IDs auf, und die im Admin gepflegte Reihenfolge bleibt dabei erhalten.
- **Konfiguration:** Galerie an/aus sowie maximale Bildanzahl (1–50, Default 12) je Sales Channel.

---

## Voraussetzungen

- Shopware 6.7 oder 6.8
- PHP 8.2+

---

## Installation

```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate RcCustomerImageGallery
php bin/console cache:clear
```

---

## Entwicklung

```bash
composer install
composer quality   # cs-check + phpstan + test
```

---

Entwickelt von [Ruhrcoder](https://ruhrcoder.de)

