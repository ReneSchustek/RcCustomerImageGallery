# Changelog

## [1.1.0] - 2026-06-28

### Geändert (Breaking)
- **Bildquelle umgestellt:** Die Galerie liest die Bilder nicht mehr aus Produkt-Bewertungen (`product_review` / CustomField `rc_review_image_id`), sondern aus einer **pro Produkt manuell gepflegten, geordneten Liste von Media-IDs**.

### Hinzugefügt
- **Datenmodell:** Idempotenter Installer für das CustomFieldSet `rc_customer_image_gallery` (Relation `product`) mit JSON-Feld `rc_customer_image_gallery_media_ids`. 3-Ebenen-Idempotenz (Set-ID, Field-ID, Relation-ID per Name aufgelöst; Type-Drift-Reconcile). Aufruf in `install()`/`update()`, Entfernen in `uninstall()` nur bei `!keepUserData`.
- **Admin:** Produkt-Detail-Tab „Kundenbilder-Galerie" mit `sw-media-modal-v2` (Mehrfach-Auswahl) und sortierbarer Vorschau (hoch/runter/entfernen), gebunden an `product.customFields.rc_customer_image_gallery_media_ids`. Snippets DE/EN.
- **Storefront:** `GalleryLoader.loadForProduct` löst die gepflegten Media-IDs in **einer** `media.repository`-Query auf (`EqualsAnyFilter('id', ...)`) und stellt die Pflege-Reihenfolge wieder her (DAL liefert unsortiert).
- **Twig:** Galerie als zusätzlicher Tab „Kundenbilder" in der Beschreibung/Bewertungen-Tableiste (`{% sw_extends %}` + `{{ parent() }}`).

### Entfernt
- Review-basierte Bildquelle im `GalleryLoader` und die `MediaEntity`-Shell-Konstruktion ohne DAL-Auflösung.

### Tests
- Loader-Unit-Tests gegen gemocktes `media.repository` (Reihenfolge-Treue, fehlende/leere/duplizierte IDs, Limit-Clamp). Struct-Test beibehalten. Alte Review-Tests ersetzt.

## [1.0.0] - 2026-05-12

### Hinzugefügt
- Erst-Release. **Liest Kundenbilder aus dem CustomField `rc_review_image_id` der ProductReviews** und stellt sie als `GalleryStruct` als Page-Extension `rcCustomerImageGallery` zur Verfügung
- **Kein eigener Upload-Pfad** — Bilder werden über den Standard-Customer-Account-Pfad bei der Bewertungs-Erstellung hochgeladen (CustomField wird vom Storefront-Form ggf. separat gepflegt). Damit entfallen Storage-/Moderation-/ACL-/DSGVO-Komplikationen
- `ProductPageSubscriber` auf `ProductPageLoadedEvent`
- Plugin-Config: `enabled` (Bool), `limit` (1-50, Default 12)
- 6 Unit-Tests (GalleryStruct + GalleryLoader inkl. Edge-Cases)

### Bewusste Einschränkungen
- **MVP-Scope:** Read-Only-Galerie. Upload-Form muss vom Customer-Account-Theme bereitgestellt werden (Standard-Shopware-Account → My-Reviews + ProductReview-CustomField)
- **DSGVO:** Bilder gehören zu Reviews, die der Customer selbst veröffentlicht hat — implizite Zustimmung vorhanden. Löschpfad: Review löschen entfernt auch die Galerie-Anzeige

### Sicherheit
- Filter `status=true` greift — nur freigegebene Reviews
- `rc_review_image_id` wird nur als String validiert; volles Media-Lookup erfolgt im Twig über Shopware-Standard-Media-Repository (Auto-Escape, Path-Safety)
