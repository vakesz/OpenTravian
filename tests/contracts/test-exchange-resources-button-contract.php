<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/GoldHelper.php');

if ($source === false
    || !str_contains($source, "if (\$cost !== -1) {\n            if (\$village->isResourcesAvailable(\$cost))")
    || !str_contains($source, "\$err = \$village->contractResourcesLink(\$cost);")
    || !str_contains($source, "if (\$err['code'] > 0)")) {
    fwrite(STDERR, "The cost-free marketplace exchange button must not pass a scalar sentinel to village resource checks.\n");
    exit(1);
}

echo "Exchange-resources button contract passed.\n";
