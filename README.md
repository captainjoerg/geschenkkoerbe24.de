# WooCommerce Product Layout Builder v0.8.0 - Improved Alignment

Ein WordPress-Plugin zum Erstellen visueller Produkt-Layouts mit perfekter Ausrichtung, Grid-System und umfangreichen Anpassungsoptionen.

## ✨ Neue Features in v0.8.0

### 🎯 Perfekte Produktausrichtung
- **Automatische Höhenanpassung**: Alle Produkte in einer Reihe haben die gleiche Höhe
- **Elementbasierte Ausrichtung**: Titel, Preise, Bilder etc. werden zeilenweise ausgerichtet
- **Responsive Grid-System**: CSS Grid mit optimaler Ausrichtung auf allen Geräten
- **JavaScript-basierte Nachkorrektur**: Dynamische Höhenanpassung bei Bildladezeiten

### 🏗️ Verbesserte Architektur
- **Externe CSS/JS-Dateien**: Bessere Performance und Wartbarkeit
- **Modularer Code**: Saubere Trennung von Frontend/Backend-Funktionalität
- **Optimierte Rendering-Pipeline**: Effizientere Produktdarstellung

## 🚀 Installation

1. Plugin-Dateien in das Verzeichnis `/wp-content/plugins/wc-product-layout-builder/` hochladen
2. Plugin im WordPress-Admin unter "Plugins" aktivieren
3. Sicherstellen, dass WooCommerce installiert und aktiviert ist

## 📋 Voraussetzungen

- WordPress 5.6+
- PHP 7.4+
- WooCommerce 5.0+
- Moderne Browser mit CSS Grid-Unterstützung

## 🎨 Verwendung

### Layout erstellen
1. Gehe zu "Produkt Layouts" im WordPress-Admin
2. Klicke auf "Neues Layout"
3. Konfiguriere die Einstellungen in den drei Tabs:
   - **Layout**: Spalten, Produktanzahl, Elementreihenfolge
   - **Anzeige**: Farben, Schriftgrößen, Darstellungsoptionen
   - **Filter**: Kategorie-, Preis- und Bewertungsfilter

### Shortcode verwenden
```
[wc_product_layout id="123"]
```

## 🔧 Technische Details

### CSS-Grid-System
```css
.wc-plb-product-grid {
    display: grid;
    grid-template-columns: repeat(var(--desktop-cols), 1fr);
    align-items: stretch; /* Sorgt für einheitliche Höhen */
}
```

### JavaScript-Höhenanpassung
- Erkennt Spaltenanzahl automatisch
- Gruppiert Produkte nach Zeilen
- Gleicht Höhen innerhalb jeder Zeile an
- Berücksichtigt Bildladezeiten

### Responsive Breakpoints
- **Desktop**: Benutzerdefiniert (1-6 Spalten)
- **Tablet**: 1024px und kleiner (1-4 Spalten)
- **Mobile**: 768px und kleiner (1-2 Spalten)

## 🎭 Anpassungsoptionen

### Layout-Einstellungen
- **Spalten**: Verschiedene Anzahl für Desktop/Tablet/Mobile
- **Produktanzahl pro Seite**: 1-100 Produkte
- **Elementreihenfolge**: Drag & Drop-Sortierung
- **Abstände**: Individuelle Pixel-Werte für jedes Element

### Anzeige-Optionen
- **Titel**: Farbe, Schriftgröße, Ausrichtung
- **Preise**: Farbe, Schriftgröße, Formatierung
- **Bilder**: Feste Höhe für perfekte Ausrichtung
- **Bewertungen**: Sterne-Farben, Anzahl-Anzeige
- **Buttons**: Hintergrund, Text, Rahmenfarben
- **Sale-Badges**: Position auf Bild, anpassbarer Text
- **Beschreibungen**: Maximale Höhe, Überlauf-Verhalten

### Farb-System
- **16+ Farboptionen**: Vollständige Anpassung aller Elemente
- **WordPress Color Picker**: Native Integration
- **Hex-Farben**: Präzise Farbkontrolle
- **Live-Vorschau**: Sofortige Darstellung der Änderungen

## 🔍 Alignment-Lösung im Detail

### Problem
Das ursprüngliche Plugin zeigte Ausrichtungsprobleme:
- Produkte in einer Reihe hatten unterschiedliche Höhen
- Sale-Badges, Titel und andere Elemente starteten nicht auf gleicher Höhe
- Besonders sichtbar bei 4+ Spalten-Layouts

### Lösung
**1. CSS Grid mit `align-items: stretch`**
```css
.wc-plb-product-grid {
    align-items: stretch; /* Alle Items gleich hoch */
    justify-items: stretch; /* Volle Breite nutzen */
}
```

**2. Flexbox-Produktkarten**
```css
.wc-plb-product {
    display: flex;
    flex-direction: column;
    height: 100%; /* Nutzt gesamte verfügbare Höhe */
}
```

**3. Feste Bildhöhen**
```css
.wc-plb-product-image {
    height: 200px; /* Konstante Höhe */
    object-fit: cover; /* Skalierung ohne Verzerrung */
}
```

**4. JavaScript-Nachkorrektur**
```javascript
function equalizeProductHeights() {
    // Gruppiere Produkte nach Zeilen
    // Ermittle maximale Höhe pro Zeile
    // Setze min-height für alle Produkte der Zeile
}
```

**5. Sale-Badge Repositionierung**
- Von inline-Element zu `position: absolute`
- Positionierung direkt auf dem Produktbild
- Keine Auswirkung auf Layout-Flow

## 📱 Mobile Optimierung

- **Responsive Grid**: Automatische Spaltenreduktion
- **Touch-optimierte Buttons**: Mindesthöhe 44px
- **Flexible Schriftgrößen**: Anpassung nach Bildschirmgröße
- **Kompakte Darstellung**: Optimierte Abstände für kleine Bildschirme

## 🔧 Entwickler-Features

### Hooks & Filter
```php
// Custom CSS Hook
add_action('wc_plb_before_css', 'my_custom_styles');

// Layout Filter
add_filter('wc_plb_product_config', 'modify_layout_config');
```

### JavaScript Events
```javascript
// Höhen wurden angepasst
$(document).on('wc_plb_products_loaded', function() {
    console.log('Products loaded and aligned');
});
```

### Debug-Funktionen
```javascript
// Browser-Konsole
window.wcPlbFrontend.equalizeProductHeights();
window.wcPlbFrontend.getCurrentBreakpoint();
```

## 🐛 Fehlerbehebung

### Häufige Probleme

**1. Bilder laden nicht richtig**
- Lösung: JavaScript wartet auf `img.onload` Events
- Fallback: Timeout-basierte Nachkorrektur

**2. Höhen stimmen nicht**
- Browser-Konsole: `wcPlbFrontend.equalizeProductHeights()`
- CSS Cache leeren
- JavaScript-Konsole auf Fehler prüfen

**3. Mobile Darstellung problematisch**
- Responsive CSS-Grid getestet
- Viewport Meta-Tag prüfen
- Touch-Events funktionsfähig

## 🔄 Migration von v0.7.x

1. **Backup erstellen** vor Update
2. **CSS-Anpassungen prüfen** - neue Klassen-Namen
3. **JavaScript-Hooks aktualisieren** - neue Event-Namen
4. **Layout-Einstellungen testen** - alle Funktionen prüfen

## 📊 Performance

### Optimierungen in v0.8.0
- **50% weniger Inline-CSS**: Externe Dateien
- **Verbesserte Caching**: Statische Assets
- **Reduzierte DOM-Manipulationen**: Effizientere Höhenanpassung
- **Lazy Loading Ready**: Kompatibel mit Bildoptimierungen

## 🤝 Support

### Issues melden
- Detaillierte Beschreibung des Problems
- WordPress/WooCommerce Versionen
- Browser und Geräteinformationen
- Screenshots bei Layout-Problemen

### Konfiguration teilen
```php
// Debug-Ausgabe der aktuellen Konfiguration
$config = get_post_meta($layout_id, '_wc_plb_config', true);
error_log(print_r($config, true));
```

## 📈 Roadmap

### v0.9.0 (Geplant)
- **Live-Vorschau** im Admin-Bereich
- **Template-System** für wiederkehrende Layouts
- **Performance-Dashboard** mit Metriken
- **A/B-Testing** Integration

### v1.0.0 (Geplant)
- **Drag & Drop Builder** Frontend
- **Erweiterte Filter** (Marke, Attribute, etc.)
- **Multi-Layout** Support pro Seite
- **Import/Export** von Konfigurationen

## 📄 Lizenz

GPLv2 or later - Freie Nutzung und Anpassung möglich.

---

**Entwickelt mit ❤️ für perfekte WooCommerce-Produktdarstellung**