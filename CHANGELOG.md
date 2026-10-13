# Changelog

## [1.2.3] - 2026-10-13: Ecken wie das Theme

### Geändert

- Die Kundenbilder nehmen den Radius des Themes statt fester 4 Pixel: 2 in Design 2027, 8 im bisherigen Theme.

> **Deployment:** `plugin:update RcCustomerImageGallery`, `theme:compile`, `cache:clear`.

## [1.2.2] - 2026-10-09: Die Zusatzfelder heißen wie die Erweiterung

### Geändert

- Unter „Zusatzfelder“ am Artikel stand „Kundenbilder-Galerie“, unter Erweiterungen „Kundenbild-Galerie“. Beide heißen jetzt gleich. Der Reiter am Produkt behält vorerst seinen Namen; er ändert sich erst mit dem nächsten Bau der Verwaltungsoberfläche.

## [1.2.1] - 2026-08-17: Die Bilder lassen sich wieder auswählen

### Behoben

- **Am Produkt fehlte der Reiter, über den die Kundenbilder ausgewählt werden.** Damit war die
  Erweiterung praktisch nicht bedienbar: Der Reiter im Shop erscheint erst, wenn Bilder zugeordnet
  sind, und zuordnen ließ sich keines. Ursache waren die fehlenden gebauten Dateien des
  Backoffice-Teils: Ohne sie kündigt Shopware das Bündel nicht an und lädt es nie, und eine
  Fehlermeldung gibt es dabei nicht. Wer den Verwaltungsbereich nach der Installation neu baut, hat den Reiter nie
  vermisst.

## [1.2.0] - 2026-08-12: Aufgeräumt und abgesichert

### Geändert

- **Die Bildzahl hat nur noch eine Vorgabe.** Sie stand doppelt im Code: einmal beim Lader, einmal
  bei der Anbindung an die Produktseite. Wer nur eine von beiden geändert hätte, hätte je nach Weg
  eine andere Zahl bekommen. Jetzt entscheidet der Lader; die Anbindung reicht nur weiter, was
  eingestellt ist. Sichtbar ändert sich nichts.
- **Ein Textbaustein ohne Verwendung ist entfernt.** `heading` wurde in keiner Vorlage benutzt.

### Neu

- **Prüfungen für die Anbindung an die Produktseite und für den Installer.** Bisher waren nur Lader
  und Struktur geprüft. Neu abgedeckt: die Ein- und Ausschaltung, die eingestellte Bildzahl in allen
  Schreibweisen, die Wiedererkennung von Feld, Feldsatz und Zuordnung bei einer erneuten
  Installation, und der Umgang mit einem Feld, dessen Typ aus einer früheren Fassung abweicht.
- **Ein Vertragstest für die Einhängestellen.** Ein Block, den es in der Kernvorlage nicht gibt,
  wird von Twig wortlos übergangen. Die Registerkarte erschiene nie, und nichts würde rot.

## [1.1.1] - 2026-08-10: Backoffice-Übersetzungen und Vorbereitung auf 6.8

### Geändert

- **Die Übersetzungen im Backoffice werden wieder angemeldet.** Der bisherige Weg existiert in Shopware 6.7 nicht mehr; der Aufruf brach beim Laden der Administration ab, und alles danach wurde nicht mehr ausgeführt.
- **Vorbereitung auf die nächste Shopware-Hauptversion.** Der Zugriff auf Suchergebnisse folgt der Schreibweise, die Shopware 6.8 verlangt. Am Verhalten ändert sich nichts.

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
- **Kein eigener Upload-Pfad.** Bilder werden über den Standard-Customer-Account-Pfad bei der Bewertungs-Erstellung hochgeladen (CustomField wird vom Storefront-Form ggf. separat gepflegt). Damit entfallen Storage-/Moderation-/ACL-/DSGVO-Komplikationen
- `ProductPageSubscriber` auf `ProductPageLoadedEvent`
- Plugin-Config: `enabled` (Bool), `limit` (1-50, Default 12)
- 6 Unit-Tests (GalleryStruct + GalleryLoader inkl. Edge-Cases)

### Bewusste Einschränkungen
- **MVP-Scope:** Read-Only-Galerie. Upload-Form muss vom Customer-Account-Theme bereitgestellt werden (Standard-Shopware-Account → My-Reviews + ProductReview-CustomField)
- **DSGVO:** Bilder gehören zu Reviews, die der Customer selbst veröffentlicht hat, die Zustimmung liegt damit implizit vor. Löschpfad: Review löschen entfernt auch die Galerie-Anzeige

### Sicherheit
- Filter `status=true` greift, es zählen also nur freigegebene Reviews
- `rc_review_image_id` wird nur als String validiert; volles Media-Lookup erfolgt im Twig über Shopware-Standard-Media-Repository (Auto-Escape, Path-Safety)
