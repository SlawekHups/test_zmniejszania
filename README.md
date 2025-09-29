# 📸 Profesjonalny Procesor Zdjęć

Nowoczesna aplikacja webowa do konwersji, zmniejszania i optymalizacji zdjęć JPEG z zachowaniem metadanych EXIF, inteligentną walidacją pojemności i profesjonalnym zarządzaniem datami.

## ✨ Funkcjonalności

### 🎯 Główne możliwości
- **Konwersja formatów**: JPG → JPEG z optymalizacją jakości
- **Inteligentne zmniejszanie**: Zachowanie proporcji do maksymalnego rozmiaru
- **Zarządzanie datami**: Sortowanie według dat EXIF lub modyfikacji pliku
- **Auto-rotacja**: Automatyczna korekcja orientacji według EXIF
- **Wsadowe przetwarzanie**: Obsługa wielu plików jednocześnie (do 300 plików)
- **Progressive JPEG**: Optymalizacja dla szybszego ładowania w przeglądarce
- **Walidacja pojemności**: Sprawdzanie limitów przed przetwarzaniem
- **Progresywne przetwarzanie**: Real-time feedback i combined ZIP
- **Strona startowa**: Intuicyjna nawigacja między wersjami aplikacji
- **Paski postępu z procentami**: Dokładny monitoring przetwarzania w czasie rzeczywistym

### 🔧 Zaawansowane opcje
- **Konfigurowalna jakość**: 50-100% kompresji JPEG
- **Elastyczne sortowanie**: Po dacie EXIF, dacie pliku lub nazwie
- **Różne formaty nazw**: Oryginalne, numerowane, z datą
- **Bezpieczne uploadowanie**: Walidacja typów MIME i rozmiarów
- **Zarządzanie sesjami**: Tymczasowe przechowywanie z auto-cleanup
- **Centralna konfiguracja**: Automatyczne ustawianie limitów PHP

## 🚀 Szybki Start

### Wymagania systemowe
- **PHP 7.4+** z rozszerzeniami:
  - `gd` (wymagane)
  - `exif` (zalecane)
  - `zip` (wymagane)
- **Apache/Nginx** z obsługą PHP
- **Dostęp do zapisu** w katalogu tymczasowym

### Instalacja i uruchomienie
1. Skopiuj wszystkie pliki do katalogu serwera web
2. Uruchom serwer development:
   ```bash
   ./start.sh
   ```
3. Otwórz aplikację w przeglądarce:
   - **🏠 Strona główna**: http://localhost:8000/
   - **📱 Klasyczna**: http://localhost:8000/klasyczna.html
   - **⚡ Progresywna**: http://localhost:8000/progressive_simple_fix.html
   - **🔧 Diagnostyka**: http://localhost:8000/check.php

### Automatyczna konfiguracja
Skrypt `start.sh` automatycznie:
- ✅ Zatrzymuje istniejący serwer na porcie 8000
- ✅ Uruchamia nowy serwer z limitami PHP przez parametry `-d`
- ✅ Sprawdza czy serwer działa poprawnie
- ✅ Wyświetla aktualne limity i dostępne aplikacje

## 📋 Instrukcja użytkowania

### Krok 1: Wybór zdjęć
- Przeciągnij pliki do obszaru uploadu lub kliknij "Wybierz zdjęcia"
- Obsługiwane formaty: JPG, JPEG (do 100MB na plik)
- Możesz wybrać wiele plików jednocześnie (do 300 plików)

### Krok 2: Walidacja pojemności (Automatyczna)
System automatycznie sprawdza:
- **Limity PHP**: max_file_uploads, memory_limit, post_max_size
- **Typy plików**: Walidacja czy to obrazy JPEG
- **Szacowany czas**: Przewidywany czas przetwarzania
- **Zużycie pamięci**: Oszacowanie potrzebnej pamięci

**Statusy walidacji:**
- ✅ **OK (Zielony)**: Można przetwarzać bez problemów
- ⚠️ **Warning (Żółty)**: Przetwarzanie możliwe, ale może trwać długo
- ❌ **Error (Czerwony)**: Błędy krytyczne - zmniejsz liczbę plików

### Krok 3: Konfiguracja (opcjonalnie)
Kliknij "⚙️ Ustawienia zaawansowane" aby dostosować:

**🖼️ Ustawienia obrazu:**
- Maksymalny rozmiar: 100-4000px (domyślnie 800px)
- Jakość JPEG: 50-100% (domyślnie 85%)
- Progressive JPEG: Tak/Nie

**📅 Zarządzanie datami:**
- Zachowaj daty EXIF: Zalecane dla zachowania chronologii
- Sortuj według: Data EXIF / Data pliku / Nazwa
- Kolejność: Od najstarszego / Od najnowszego

**🔧 Opcje dodatkowe:**
- Auto-rotacja EXIF: Automatyczna korekcja orientacji
- Zachowaj oryginały: Tak/Nie
- Format nazw: Oryginalne / Numerowane / Z datą

### Krok 4: Wybór trybu przetwarzania

**Klasyczny interfejs:**
- Wszystkie pliki przetwarzane jednocześnie
- Jeden ZIP z wszystkimi plikami

**Progresywny interfejs:**
- **"Wszystkie naraz"**: Jedna sesja, jeden ZIP (zalecane do 50 plików)
- **"Po partiach"**: Real-time feedback, combined ZIP (zalecane dla 50+ plików)

### Krok 5: Przetwarzanie i pobieranie
- **Obserwuj postęp w czasie rzeczywistym** z dokładnymi procentami
- **Pobierz pojedyncze pliki** lub całe archiwum ZIP
- **Pliki sortowane** według daty EXIF (od najstarszego do najnowszego)
- **Komunikaty statusu** opisujące aktualny etap przetwarzania

## 🏠 Strona startowa i nawigacja

### 🎯 Centralne miejsce dostępu
Nowa strona główna (`index.html`) zapewnia:
- **🚀 Łatwy wybór wersji** - elegancki design z opisem każdej wersji
- **📊 Status serwera** - sprawdzanie konfiguracji w czasie rzeczywistym
- **🔗 Intuicyjna nawigacja** - przyciski do wszystkich narzędzi
- **📱 Responsywny design** - działa na wszystkich urządzeniach

### 📊 Paski postępu z procentami
Obie wersje aplikacji zostały wzbogacone o:

**📱 Wersja klasyczna:**
- **Symulowany postęp** dostosowany do liczby plików
- **Dokładne procenty** (0% → 95% → 100%)
- **Inteligentne komunikaty** zmieniające się z postępem:
  - 0-25%: "Przygotowywanie plików..."
  - 25-50%: "Zmniejszanie zdjęć..."
  - 50-75%: "Optymalizacja jakości..."
  - 75-90%: "Zapisywanie wyników..."
  - 90%+: "Finalizowanie..."

**⚡ Wersja progresywna:**
- **Rzeczywisty postęp** na podstawie przetworzonych plików
- **Procenty w czasie rzeczywistym** na głównym pasku
- **Synchronizacja ze statystykami** plików
- **Dokładne obliczenia** (przetworzone/wszystkie × 100%)

## 🎯 Najważniejsze cechy

### 📅 Inteligentne zarządzanie datami
Aplikacja priorytetowo używa dat z EXIF (DateTimeOriginal), co zapewnia:
- **Chronologiczne sortowanie** według rzeczywistej daty zdjęcia
- **Zachowanie metadanych** czasowych w przetworzonych plikach
- **Automatyczne fallback** na datę modyfikacji pliku jeśli brak EXIF

### 🔍 Walidacja pojemności
Przed każdym przetwarzaniem system sprawdza:
- **Limity PHP**: Zapobiega przekroczeniu limitów serwera
- **Analiza plików**: Sprawdza typy, rozmiary i liczbę plików
- **Rekomendacje**: Sugeruje najlepszy tryb przetwarzania
- **Szacowania**: Przewiduje czas i zużycie pamięci

### 🔒 Bezpieczeństwo
- Walidacja typów MIME
- Sanityzacja nazw plików
- Ograniczenia rozmiaru plików
- Automatyczne usuwanie plików tymczasowych
- Ochrona przed path traversal

### ⚡ Wydajność
- Optymalizacja pamięci dla dużych plików
- Progressive JPEG dla szybszego ładowania
- Kompresja z zachowaniem jakości
- Wsadowe przetwarzanie
- Progresywne przetwarzanie dla dużych zbiorów

## 📁 Struktura projektu

### 🌐 Interfejsy użytkownika
- **`index.html`** - Strona startowa z wyborem wersji i nawigacją
- **`klasyczna.html`** - Klasyczny interfejs z walidacją pojemności i paskami postępu
- **`progressive_simple_fix.html`** - Progresywny interfejs z trybami przetwarzania

### ⚙️ Backend API
- **`process.php`** - Główny procesor obrazów (konwersja + resize)
- **`download.php`** - Pobieranie pojedynczych plików i archiwów ZIP
- **`validate_capacity.php`** - Walidacja pojemności przed przetwarzaniem
- **`create_combined_zip.php`** - Łączenie plików z wielu sesji progresywnych

### 🔧 Konfiguracja i narzędzia
- **`config.php`** - Centralna konfiguracja (limity PHP, ustawienia aplikacji)
- **`logger.php`** - System szczegółowego logowania
- **`server_200.php`** - Router dla PHP Development Server
- **`start.sh`** - Uniwersalny skrypt startowy serwera
- **`check.php`** - Sprawdzenie dostępności rozszerzeń i limitów PHP
- **`status.php`** - Status sesji przetwarzania

### 🗂️ Pliki referencyjne
- **`1_zmien_jpg_JPG_na_jepg.php`** - Oryginalny skrypt konwersji JPG→JPEG
- **`2_resize_gd_keep_name_set_mtime.php`** - Oryginalny skrypt resize z EXIF
- **`1_do_zmiany_na_jpeg/`** - Pliki źródłowe do testów
- **`2_produkty_sklep/`** - Przykładowe wyniki konwersji
- **`3_img800-max/`** - Przykładowe wyniki resize

## ⚙️ Konfiguracja automatyczna

### Limity PHP (Ustawiane automatycznie)
```php
'upload_max_filesize' => '100M',    // Maksymalny rozmiar pliku
'post_max_size' => '8000M',         // Maksymalny rozmiar POST (80×100MB)
'max_file_uploads' => '350',        // Maksymalna liczba plików
'max_input_vars' => '8000',         // Maksymalna liczba zmiennych
'max_execution_time' => '1200',     // 20 minut na przetwarzanie
'memory_limit' => '4096M',          // 4GB pamięci
'max_input_time' => '1200',         // 20 minut na upload
```

### Zalecane limity przetwarzania
- **1-10 plików**: Optymalne (szybkie przetwarzanie)
- **11-50 plików**: Dobre (średni czas przetwarzania)
- **51-200 plików**: Możliwe (długie przetwarzanie - użyj trybu progresywnego)
- **201-300 plików**: Duże partie (zalecany tryb progresywny po partiach)
- **300+ plików**: Ekstremalne (podziel na mniejsze partie)

## 🔍 Diagnostyka i rozwiązywanie problemów

### Sprawdzenie konfiguracji
```bash
# Sprawdź limity PHP
curl http://localhost:8000/check.php

# Test konfiguracji
php -r "require_once 'config.php'; print_r(AppConfig::getPHPLimits());"

# Logi serwera
tail -f server.log
```

### Typowe problemy

#### Problem: "Unexpected token '<'"
**Przyczyna**: Zbyt dużo plików dla aktualnych limitów PHP  
**Rozwiązanie**: Uruchom `./start.sh` aby ustawić poprawne limity

#### Problem: "Address already in use"
**Przyczyna**: Port 8000 jest zajęty  
**Rozwiązanie**: Skrypt automatycznie zatrzymuje stary serwer

#### Problem: Pliki nie przetwarzają się
**Przyczyna**: Limity pamięci lub czasu wykonania  
**Rozwiązanie**: Sprawdź `curl http://localhost:8000/check.php`

#### Błąd "Rozszerzenie GD nie jest dostępne"
```bash
# Ubuntu/Debian
sudo apt-get install php-gd

# CentOS/RHEL
sudo yum install php-gd

# Restart serwera web
sudo systemctl restart apache2
```

#### Problemy z datami EXIF
- Zainstaluj rozszerzenie `php-exif`
- Sprawdź czy pliki mają metadane EXIF
- Użyj sortowania po dacie pliku jako alternatywy

## 🆚 Porównanie z poprzednimi skryptami

### Stary system (2 oddzielne skrypty):
❌ Ręczne uruchamianie każdego skryptu  
❌ Brak interfejsu graficznego  
❌ Podstawowa obsługa błędów  
❌ Brak sortowania według dat EXIF  
❌ Nadpisywanie plików  
❌ Brak walidacji pojemności  

### Nowy system (aplikacja webowa):
✅ Jednoetapowy proces z GUI  
✅ Zaawansowana konfiguracja  
✅ Profesjonalna obsługa błędów  
✅ Inteligentne sortowanie według dat  
✅ Bezpieczne nazwy plików  
✅ Wsadowe przetwarzanie  
✅ Monitoring postępu  
✅ Archiwizacja wyników  
✅ Walidacja pojemności przed przetwarzaniem  
✅ Progresywne przetwarzanie z real-time feedback  
✅ Strona startowa z intuicyjną nawigacją  
✅ Paski postępu z dokładnymi procentami  
✅ Zwiększone limity do 300 plików jednocześnie  

## 🧪 API i testowanie

### Endpoint walidacji pojemności
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"files":[{"name":"test.jpg","size":1000000,"type":"image/jpeg"}]}' \
  http://localhost:8000/validate_capacity.php
```

### Przykład odpowiedzi walidacji
```json
{
  "success": true,
  "validation_status": "ok",
  "can_process": true,
  "summary": {
    "file_count": 5,
    "total_size": "12.5MB",
    "estimated_time": "0.1 min",
    "estimated_memory": "50MB"
  },
  "errors": [],
  "warnings": [],
  "recommendations": []
}
```

## 📈 Monitoring i logi

Aplikacja automatycznie loguje:
- Informacje o przetwarzanych plikach
- Błędy konwersji i walidacji
- Statystyki wydajności
- Status sesji użytkownika
- Szczegółowe logi walidacji pojemności

Logi są dostępne w odpowiedzi JSON z `process.php` i w plikach `.log`.

## 📊 Statystyki projektu

### ✅ Zachowane pliki (21 plików + 4 katalogi)
- 3 interfejsy HTML (strona startowa + 2 wersje aplikacji)
- 6 skryptów PHP (backend)
- 4 pliki konfiguracji/narzędzi
- 4 pliki dokumentacji
- 3 katalogi z przykładowymi plikami

### 🗑️ Usunięte podczas czyszczenia (~35 plików + 2 katalogi)
- Stare wersje interfejsów (4 pliki)
- Nieużywane skrypty backend (4 pliki)
- Pliki testowe i debugowe (15 plików)
- Pliki analizy jednorazowej (8 plików)
- Logi i pliki tymczasowe (4 pliki)
- Katalogi testowe (~200 MB oszczędności)

## 🔮 Przyszłe ulepszenia

- 📊 Podgląd miniaturek przed przetwarzaniem
- 🎨 Wsparcie dla więcej formatów (PNG, WebP)
- 📱 Optymalizacja mobilna
- 🌐 Wsparcie wielojęzyczne
- 📈 Zaawansowane statystyki
- 🔄 API REST dla integracji

---

## ✅ Projekt gotowy do produkcji!

Wszystkie niepotrzebne pliki zostały usunięte. Projekt jest teraz:
- **Czysty** - tylko niezbędne pliki
- **Zorganizowany** - logiczna struktura
- **Udokumentowany** - kompletna dokumentacja
- **Funkcjonalny** - wszystkie funkcje działają
- **Skalowalny** - gotowy na rozszerzenia
- **Bezpieczny** - walidacja pojemności i limity PHP

🎉 **Można bezpiecznie używać w produkcji!**

**Wersja**: 3.1  
**Autor**: Twój Zespół  
**Licencja**: MIT  
**Wsparcie**: [GitHub Issues](https://github.com/your-repo/issues)

---

## 🆕 Changelog v3.1

### ✨ Nowe funkcje:
- **🏠 Strona startowa** - intuicyjna nawigacja między wersjami
- **📊 Paski postępu z procentami** - dokładny monitoring w obu wersjach
- **🔗 Przyciski nawigacji** - łatwe przechodzenie między wersjami
- **⚡ Zwiększone limity** - do 300 plików jednocześnie (4GB RAM)

### 🛠️ Poprawki:
- **Naprawiony bug podwójnego kliknięcia** przy dodawaniu zdjęć
- **Ulepszone paski postępu** - większe, czytelniejsze z procentami
- **Zwiększona pamięć** - z 2GB do 4GB dla większych partii
- **Wydłużony czas** - z 15 do 20 minut na przetwarzanie

### 🎯 Ulepszona użyteczność:
- **Nie trzeba pamiętać linków** - wszystko dostępne z strony głównej
- **Jasny feedback** - komunikaty opisujące etapy przetwarzania
- **Responsywny design** - działa na wszystkich urządzeniach