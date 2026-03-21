<?php
error_reporting(0);
$url = 'https://recherche-entreprises.api.gouv.fr/openapi.json';
$json = json_decode(file_get_contents($url), true);
$params = $json['paths']['/search']['get']['parameters'] ?? [];
foreach($params as $p) {
    echo $p['name'] . "\n";
}
