<?php
$json = file_get_contents('https://recherche-entreprises.api.gouv.fr/search?q=decathlon&per_page=1');
$data = json_decode($json, true);
$first = $data['results'][0];

echo "--- KEYS ---\n";
print_r(array_keys($first));

echo "\n--- SPECIFIC DATA ---\n";
echo "activite_principale: " . ($first['activite_principale'] ?? 'N/A') . "\n";
echo "libelle_activite_principale: " . ($first['libelle_activite_principale'] ?? 'N/A') . "\n";
echo "libelle_section_activite_principale: " . ($first['libelle_section_activite_principale'] ?? 'N/A') . "\n";
echo "site_web / site_internet: " . ($first['site_web'] ?? $first['site_internet'] ?? 'N/A') . "\n";
