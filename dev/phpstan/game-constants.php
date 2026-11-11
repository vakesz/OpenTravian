<?php

declare(strict_types=1);

// Constants that the game defines at runtime (deployment/include/env.php,
// include/bootstrap.php, include/mainInclude.php). PHPStan cannot see a define()
// inside a conditional bootstrap, so declare them here for analysis only.
define('CONNECTION_LOCAL_FILE', '');
define('LOADED_PAGE', '');
