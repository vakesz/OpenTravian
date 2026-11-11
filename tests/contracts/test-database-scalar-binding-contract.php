<?php

$source = file_get_contents(__DIR__ . '/../../game/include/Core/Database/DB.php');

if ($source === false) {
    fwrite(STDERR, "Unable to read DB helper.\n");
    exit(1);
}

$requiredFragments = [
    'public function fetchScalar($query, $parameters = [], $default = false)',
    'is_array($parameters) ? $parameters : [$parameters]',
    '$statement = $this->run($query,',
    '$result = $statement->get_result();',
    '$result = $this->query($query);',
];

foreach ($requiredFragments as $fragment) {
    if (!str_contains($source, $fragment)) {
        fwrite(STDERR, "Missing scalar-query binding contract: {$fragment}\n");
        exit(1);
    }
}

echo "Database scalar binding contract passed.\n";
