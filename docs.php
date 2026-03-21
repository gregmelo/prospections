<?php
$data = json_decode(file_get_contents('https://recherche-entreprises.api.gouv.fr/swagger.json'), true);
$params = $data['paths']['/search']['get']['parameters'];
foreach($params as $p) {
    echo $p['name'] . ': ' . ($p['description'] ?? '') . "\n";
}
