# RcCustomerImageGallery

Shopware 6 Plugin — Kundenbild-Galerie für Produktseiten.

---

## Was das Plugin macht

Stellt pro Produkt eine Bildergalerie auf der Produktdetailseite bereit. Die Bilder werden **manuell aus der Media-Bibliothek** gewählt: Im Produkt-Detail gibt es einen eigenen Tab „Kundenbilder-Galerie", in dem Bilder über die Standard-Media-Auswahl (`sw-media-modal-v2`, Mehrfach-Auswahl) hinzugefügt und per Vorschau sortiert werden. Im Shop erscheinen die gewählten Bilder als zusätzlicher Tab „Kundenbilder" in der Beschreibung/Bewertungen-Tableiste der Produktseite.

### Funktionsweise

- **Datenmodell:** CustomFieldSet `rc_customer_image_gallery` (Relation `product`) mit dem JSON-Feld `rc_customer_image_gallery_media_ids` (geordnete Liste von Media-UUIDs). Der Installer ist idempotent (mehrfaches Install/Update ohne Kollision).
- **Admin:** Produkt-Tab mit Media-Modal und sortierbarer Vorschau (nach oben/unten verschieben, entfernen).
- **Storefront:** Die hinterlegten Media-IDs werden in einer einzigen `media.repository`-Query aufgelöst; die im Admin gepflegte Reihenfolge bleibt erhalten.
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

<!-- TRIAGE-WORKFLOW: auto-managed by triage-deploy.ps1 -->
