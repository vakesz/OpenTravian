<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class FavoriteSessionStub
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function getStoredFavoriteTab(string $name): ?int
        {
            return null;
        }
    }
}

namespace Tests\Stub {
    final class FavoriteBatchViewStub
    {
        /** @param array<string, mixed> $vars */
        public static function render(string $template, array $vars): string
        {
            return json_encode($vars, JSON_THROW_ON_ERROR);
        }
    }
}

namespace {
    class_alias(\Tests\Stub\FavoriteSessionStub::class, 'Core\\Session');
    class_alias(\Tests\Stub\FavoriteBatchViewStub::class, 'resources\\View\\PHPBatchView');

    function T(string $section, string $key): string
    {
        return "{$section}.{$key}";
    }

    function villageStatisticsUrl(int $tab): string
    {
        return "/village/statistics/{$tab}";
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    $vars = ['selectedTabId' => 0];
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        require __DIR__ . '/../../game/include/resources/Templates/village-statistics/menuEnabled.php';
        $rendered = (string)ob_get_clean();
    } catch (\Throwable $error) {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
        fwrite(STDERR, "A fresh village-overview account must render without a favourite-tab warning: {$error->getMessage()}\n");
        exit(1);
    } finally {
        restore_error_handler();
    }

    if (!str_contains($rendered, '"favorSlug":null')) {
        fwrite(STDERR, "A fresh village-overview account must leave every tab unfavourited.\n");
        exit(1);
    }

    echo "Fresh village-overview favourite contract passed.\n";
}
