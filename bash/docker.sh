echo "Build dell'immagine e avvio dei container (può richiedere qualche minuto)..."
docker compose build

# Con container_name fisso nel docker-compose.yml, la prima 'up -d' può
# occasionalmente incappare in una race condition interna di Docker Compose
# (tentativo doppio di creare lo stesso container, es. "riccardomandich_db"
# già in uso) mentre aspetta che 'db' diventi healthy per sbloccare 'app'.
# È innocuo e idempotente: un secondo tentativo va sempre a buon fine perché
# trova il container già creato. Aggiungiamo quindi un retry automatico
# invece di far fallire tutto lo script per un problema transitorio.
if ! docker compose up -d --wait --wait-timeout 120; then
    echo "Primo tentativo di avvio non riuscito (probabile race condition nota di Compose), riprovo..."
    sleep 3
    docker compose up -d --wait --wait-timeout 120
fi

echo "Attendo che il database sia pronto..."
sleep 8

echo "Genero APP_KEY e applico le migration..."
docker compose exec -T app php artisan migrate --force
echo
