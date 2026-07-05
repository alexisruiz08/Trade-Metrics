<?php
// api/get_ticker_prices.php

// --- CONFIGURACIÓN ---
$googleSheetUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRcJ627hJGrJiOiHfAJyNZQWczsff_8InNB2i1B4dqqYfXBG-uKmhFbi3Mtc39biuaEjylIRJ6TFNf3/pub?gid=0&single=true&output=csv'; // Asegúrate de que termine en output=csv
$cache_file = 'ticker_cache.json'; 

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=UTF-8');

// 2. Precios previos para las flechas en app.php
$previous_prices = [];

if (file_exists($cache_file)) {
    $old_data = json_decode(file_get_contents($cache_file), true);
    if (isset($old_data['prices'])) {
        $previous_prices = $old_data['prices'];
    }
}


// 3. Obtener datos (Método idéntico a tu api.php)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleSheetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
$csvContent = curl_exec($ch);
curl_close($ch);

$new_prices = [];

if ($csvContent) {
    // --- MEJORA AQUÍ: Normalizar saltos de línea para que lea todas las filas ---
    $csvContent = str_replace("\r", "", $csvContent);
    $lines = explode("\n", $csvContent);
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        // Usamos str_getcsv para manejar comas internas si las hubiera
        $data = str_getcsv($line);
        
        // Columna E (4) y Columna F (5)
        if (isset($data[4]) && isset($data[5])) {
            $symbol = strtoupper(trim($data[4]));
            $priceRaw = trim($data[5]);

            // Saltamos cabeceras o filas vacías
            if ($symbol === 'NOMBRE' || $symbol === 'SYMBOL' || $symbol === '') continue;

            // Limpiamos el precio: quitamos todo lo que no sea número, punto o coma
            // y convertimos coma a punto para que PHP lo entienda como float
            $priceClean = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $priceRaw));
            
            if (is_numeric($priceClean)) {
                $new_prices[$symbol] = (float)$priceClean;
            }
        }
    }
}

// 4. Fallback: Si Google falla, no borramos lo que teníamos
if (empty($new_prices)) {
    $new_prices = $previous_prices;
}

// 5. Respuesta final
$response_data = [
    "date" => date('Y-m-d H:i:s'),
    "prices" => $new_prices,
    "previousPrices" => $previous_prices
];

file_put_contents($cache_file, json_encode($response_data));
echo json_encode($response_data);