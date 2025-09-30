#!/bin/bash

# ===============================================
# SKRYPT ZATRZYMUJĄCY SERWER
# Bezpiecznie wyłącza serwer PHP Development Server
# ===============================================

echo "🛑 Zatrzymywanie serwera Procesora Zdjęć..."

# Sprawdź czy serwer działa na porcie 8000
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo "📍 Znaleziono serwer na porcie 8000"
    
    # Pobierz PID procesu
    PID=$(lsof -ti:8000)
    
    if [ ! -z "$PID" ]; then
        echo "🔍 PID serwera: $PID"
        
        # Spróbuj zatrzymać proces łagodnie (SIGTERM)
        echo "⏳ Łagodne zamykanie serwera (SIGTERM)..."
        kill $PID 2>/dev/null
        
        # Poczekaj 2 sekundy
        sleep 2
        
        # Sprawdź czy proces nadal działa
        if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
            echo "⚠️  Proces nie odpowiada, wymuszam zamknięcie (SIGKILL)..."
            kill -9 $PID 2>/dev/null
            sleep 1
        fi
        
        # Sprawdź końcowy status
        if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
            echo "❌ Nie udało się zatrzymać serwera!"
            echo "💡 Spróbuj ręcznie: kill -9 $PID"
            exit 1
        else
            echo "✅ Serwer zatrzymany pomyślnie!"
        fi
    fi
else
    echo "ℹ️  Serwer nie jest uruchomiony na porcie 8000"
fi

# Zatrzymaj wszystkie procesy PHP z server_200.php (na wszelki wypadek)
echo ""
echo "🔍 Sprawdzanie dodatkowych procesów PHP..."
PROCESSES=$(ps aux | grep "server_200.php" | grep -v grep)

if [ ! -z "$PROCESSES" ]; then
    echo "⚠️  Znaleziono dodatkowe procesy PHP:"
    echo "$PROCESSES"
    echo ""
    echo "🧹 Czyszczenie procesów..."
    pkill -f "server_200.php"
    sleep 1
    echo "✅ Wyczyszczono"
else
    echo "✅ Brak dodatkowych procesów"
fi

echo ""
echo "🎉 Gotowe! Serwer został zatrzymany."
echo ""
echo "💡 Aby uruchomić ponownie: ./start.sh"
