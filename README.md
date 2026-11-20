# FGR Duplicate Post

Ein WordPress-Plugin der [Freien Gestalterischen Republik](https://fgr.design).

Dupliziert Posts, Seiten und Custom Post Types mit einem Klick – inklusive aller Meta-Daten, Taxonomien, Custom Fields und Page-Builder-Inhalte (WPBakery, Elementor, Gutenberg).

## Funktionen

- **Ein-Klick-Duplikation** direkt aus der Listen-Ansicht im Backend
- **Alle Inhalte werden kopiert:** Titel, Text, Excerpt, Custom Fields, ACF-Felder, WPBakery-Shortcodes, Elementor-Daten
- **Taxonomien werden kopiert:** Kategorien, Tags und alle Custom Taxonomies
- **Kompatibel mit allen Post Types:** Posts, Seiten und Custom Post Types
- **Elementor-Unterstützung:** CSS wird automatisch neu generiert
- **Konfigurierbar:** Status des Duplikats, Weiterleitung, Titel-Suffix, ausgeschlossene Post-Types
- **Automatische Updates** via GitHub Releases

## Installation

1. Plugin-ZIP herunterladen
2. In WordPress unter **Plugins → Installieren → Plugin hochladen** hochladen
3. Plugin aktivieren

## Einstellungen

Die Einstellungen sind im WordPress-Backend unter **FGR Plugins → Duplicate Post** zu finden.

| Einstellung | Beschreibung |
|-------------|-------------|
| Status des Duplikats | Entwurf / Gleicher Status / Ausstehend |
| Nach dem Duplizieren | Zurück zur Liste / Duplikat direkt bearbeiten |
| Titel-Suffix | Text der an den Duplikat-Titel angehängt wird (z. B. „(Kopie)") |
| Ausgeschlossene Post-Types | Post-Types ohne Duplizieren-Button |

## WooCommerce-Produkte

Produkte werden über die WooCommerce-eigene Kopierfunktion dupliziert. Dabei gilt:

- **Neue Artikelnummer (SKU):** WooCommerce hängt eine laufende Nummer an (z. B. `ABC` → `ABC-1`).
- **Verkaufszahl und Bewertungen** werden auf 0 gesetzt.
- **Lagerbestand** wird auf 0 gesetzt (bei aktivierter Lagerverwaltung), damit keine Restbestände des Originals übernommen werden.
- **Varianten** werden mitkopiert (mit neuer SKU und Lagerbestand 0).
- **Nicht übernommen** werden: FooEvents-Ticketzähler (`_WooCommerceEventsCounter`), Google-for-WooCommerce-Verknüpfung (`_wc_gla_*`), Lagerwarnungs-Status der WooCommerce-App (`_wc_push_notification_*`). Weitere Schlüssel lassen sich über den Filter `fgr_duplicate_post_product_meta_excluded` ausschließen.
- **Bestellungen und FooEvents-Tickets** gehören nicht zum Produkt und werden nie kopiert. Die Kopie startet mit einer leeren Teilnehmerliste.

Titel-Suffix und Status des Duplikats kommen wie bei allen Inhalten aus den Plugin-Einstellungen.

## Versionierung

`MAJOR.MINOR.PATCH`

## Autor

[Freie Gestalterische Republik](https://fgr.design)
