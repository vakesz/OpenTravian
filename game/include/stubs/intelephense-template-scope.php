<?php

/**
 * Intelephense helper stub for include-scope template variables.
 *
 * Templates under game/include/resources/Templates/ are rendered by
 * resources\View\PHPBatchView, which passes $vars into require() scope.
 * Static analysis cannot see that injection; use a file-local
 * `@var array $vars` annotation when editing a template, and keep
 * intelephense.diagnostics.undefinedVariables disabled workspace-wide.
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace {
    /** @var array<string, mixed> $vars */
    $vars = [];
}
