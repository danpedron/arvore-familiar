<?php
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// O Nominatim exige um User-Agent identificável e no máximo 1 requisição por segundo.
// Por isso passamos pelo backend em vez de chamar direto do navegador.
$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $q,
    'format' => 'json',
    'addressdetails' => 1,
    'limit' => 6,
    'accept-language' => 'pt-BR',
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['User-Agent: ArvoreFamiliar-SelfHosted/1.0 (uso pessoal)'],
    CURLOPT_TIMEOUT => 6,
]);
$resposta = curl_exec($ch);
$erroCurl = curl_error($ch);
curl_close($ch);

if ($resposta === false) {
    http_response_code(502);
    echo json_encode(['erro' => 'Não foi possível consultar o serviço de mapas agora: ' . $erroCurl]);
    exit;
}

$resultadosOsm = json_decode($resposta, true) ?: [];

$resultados = array_map(function ($r) {
    return [
        'nome' => $r['display_name'],
        'lat' => (float) $r['lat'],
        'lng' => (float) $r['lon'],
    ];
}, $resultadosOsm);

echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
