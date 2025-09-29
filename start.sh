#!/bin/bash

# ===============================================
# UNIWERSALNY SKRYPT STARTOWY
# Automatycznie wykrywa i uruchamia serwer z odpowiednimi limitami
# ===============================================

echo "🚀 Procesor Zdjęć - Uruchamianie serwera..."

# Zatrzymaj istniejący serwer
pkill -f "php -S localhost:8000" 2>/dev/null
sleep 1

# Sprawdź czy port jest wolny
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  Port 8000 jest zajęty. Forsownie zamykam..."
    lsof -ti:8000 | xargs kill -9 2>/dev/null
    sleep 2
fi

echo "✅ Uruchamianie serwera z automatycznymi limitami PHP..."

# Uruchom serwer z parametrami -d (max_file_uploads nie można zmienić przez ini_set)
php -d max_file_uploads=250 \
    -d upload_max_filesize=100M \
    -d post_max_size=5000M \
    -d max_input_vars=5000 \
    -d max_execution_time=900 \
    -d memory_limit=2048M \
    -d max_input_time=900 \
    -S localhost:8000 server_200.php > server.log 2>&1 &

# Poczekaj na uruchomienie
sleep 3

# Sprawdź czy serwer działa
if curl -s http://localhost:8000/check.php > /dev/null; then
    echo "✅ Serwer uruchomiony pomyślnie!"
    echo ""
    echo "🌐 Dostępne aplikacje:"
    echo "   🏠 Strona główna: http://localhost:8000/"
    echo "   📱 Klasyczna:     http://localhost:8000/klasyczna.html"
    echo "   ⚡ Progresywna:   http://localhost:8000/progressive_simple_fix.html"
    echo "   🔧 Diagnostyka:  http://localhost:8000/check.php"
    echo ""
    
    # Sprawdź aktualne limity
    echo "📊 Aktualne limity PHP:"
    php -r "
    require_once 'config.php';
    \$limits = AppConfig::getPHPLimits();
    echo '   max_file_uploads: ' . \$limits['max_file_uploads'] . PHP_EOL;
    echo '   post_max_size: ' . \$limits['post_max_size'] . PHP_EOL;
    echo '   memory_limit: ' . \$limits['memory_limit'] . PHP_EOL;
    echo '   max_execution_time: ' . \$limits['max_execution_time'] . 's' . PHP_EOL;
    "
    echo ""
    echo "💡 Możesz teraz przesłać do 250 plików jednocześnie!"
    echo "📝 Logi serwera: tail -f server.log"
    
else
    echo "❌ Błąd uruchamiania serwera!"
    echo "📝 Sprawdź logi: cat server.log"
    exit 1
fi
