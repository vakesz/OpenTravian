<?php

declare(strict_types=1);

namespace Game\Webhook;

/**
 * Operator settings of the Discord world-event webhook. The webhook URL is a secret (anyone holding it can post
 * to the channel), so like SMTP, payments and the CAPTCHA keys it is kept only in a 0600 PHP state file in the
 * generated state directory (webhook.local.php), never in SQL, globalConfig.php, forms, logs or the repository.
 * The webhook is off by default and, once on, announces only the events the operator ticked.
 */
final class WebhookConfig
{
    /** Event ids and their admin labels. */
    public const EVENTS = [
        'worldStart' => 'World start',
        'worldEnd' => 'World end',
        'artifactsReleased' => 'Artifacts released',
        'wwPlansReleased' => 'World Wonder plans released',
        'wwMilestone' => 'World Wonder level milestone',
        'allianceFounded' => 'Alliance founded',
        'roundWinner' => 'Round winner',
    ];

    public const DEFAULT_MILESTONES = [10, 25, 50, 75, 100];

    private const URL_PATTERN = '#\Ahttps://(?:discord|discordapp)\.com/api/(?:v\d+/)?webhooks/\d{5,25}/[A-Za-z0-9_-]{20,120}\z#';

    public function __construct(private readonly string $path)
    {
    }

    public static function fromEnvironment(): self
    {
        $override = getenv('TRAVIAN_WEBHOOK_CONFIG');
        $state = getenv('TRAVIAN_STATE_DIR');
        if (is_string($override) && $override !== '') {
            return new self($override);
        }
        if (defined('STATE_DIR')) {
            return new self(rtrim((string) constant('STATE_DIR'), '/') . '/webhook.local.php');
        }

        return new self(is_string($state) && $state !== '' ? rtrim($state, '/') . '/webhook.local.php' : '');
    }

    public function path(): string
    {
        return $this->path;
    }

    /** On only with the switch set and a valid URL stored. */
    public function enabled(): bool
    {
        $config = $this->read();

        return ($config['enabled'] ?? false) === true && $this->url() !== null;
    }

    public function url(): ?string
    {
        $url = $this->read()['url'] ?? null;

        return is_string($url) && self::validUrl($url) ? $url : null;
    }

    public function eventEnabled(string $event): bool
    {
        return $this->enabled() && in_array($event, $this->events(), true);
    }

    /** @return list<string> */
    public function events(): array
    {
        $events = $this->read()['events'] ?? [];

        return is_array($events) ? array_values(array_filter($events, static fn ($event) => is_string($event) && isset(self::EVENTS[$event]))) : [];
    }

    /** @return list<int> World Wonder levels that are announced, ascending. */
    public function milestones(): array
    {
        $levels = $this->read()['milestones'] ?? self::DEFAULT_MILESTONES;

        return self::cleanMilestones(is_array($levels) ? $levels : self::DEFAULT_MILESTONES) ?: self::DEFAULT_MILESTONES;
    }

    /**
     * Admin form values. The URL never leaves the state file: only whether it is set.
     *
     * @return array{enabled:bool,configured:bool,suffix:string,events:list<string>,milestones:string}
     */
    public function formValues(): array
    {
        $url = $this->url();

        return [
            'enabled' => ($this->read()['enabled'] ?? false) === true,
            'configured' => $url !== null,
            'suffix' => $url === null ? '' : '…' . substr($url, -4),
            'events' => $this->events(),
            'milestones' => implode(', ', $this->milestones()),
        ];
    }

    /**
     * Parses the admin form. A blank URL keeps the stored one; "remove" deletes the URL and switches the webhook off.
     *
     * @param array<string,mixed> $submitted
     * @return array{config:array<string,mixed>|null,errors:list<string>}
     */
    public function fromSubmission(array $submitted): array
    {
        $errors = [];
        $url = is_string($submitted['webhook_url'] ?? null) ? trim($submitted['webhook_url']) : '';
        $remove = ($submitted['webhook_remove'] ?? '') === '1';
        if ($remove) {
            $url = '';
        } elseif ($url === '') {
            $url = $this->url() ?? '';
        } elseif (!self::validUrl($url)) {
            $errors[] = 'The webhook URL must be a https://discord.com/api/webhooks/<id>/<token> address.';
        }
        $enabled = !$remove && ($submitted['webhook_enabled'] ?? '') === '1';
        if ($enabled && $url === '') {
            $errors[] = 'Enter the webhook URL before switching the webhook on.';
        }
        $events = [];
        foreach (is_array($submitted['webhook_events'] ?? null) ? $submitted['webhook_events'] : [] as $event) {
            if (is_string($event) && isset(self::EVENTS[$event])) {
                $events[$event] = $event;
            }
        }
        $milestones = [];
        foreach (explode(',', is_string($submitted['webhook_milestones'] ?? null) ? $submitted['webhook_milestones'] : '') as $part) {
            if (trim($part) !== '') {
                $milestones[] = trim($part);
            }
        }
        $levels = self::cleanMilestones($milestones);
        if ($milestones === []) {
            $levels = self::DEFAULT_MILESTONES;
        } elseif (count($levels) !== count($milestones)) {
            $errors[] = 'Milestones are whole World Wonder levels from 1 to 100, separated by commas.';
        }

        return [
            'config' => $errors === [] ? ['enabled' => $enabled, 'url' => $url, 'events' => array_values($events), 'milestones' => $levels] : null,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $config as returned by fromSubmission() */
    public function save(array $config): bool
    {
        $directory = $this->path === '' ? '' : dirname($this->path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($this->path)) {
            return false;
        }
        $url = is_string($config['url'] ?? null) && self::validUrl($config['url']) ? $config['url'] : '';
        $clean = [
            'enabled' => ($config['enabled'] ?? false) === true && $url !== '',
            'url' => $url,
            'events' => array_values(array_filter((array) ($config['events'] ?? []), static fn ($event) => is_string($event) && isset(self::EVENTS[$event]))),
            'milestones' => self::cleanMilestones((array) ($config['milestones'] ?? [])) ?: self::DEFAULT_MILESTONES,
        ];
        $temporary = tempnam($directory, '.travian-webhook-');
        if ($temporary === false) {
            return false;
        }
        $contents = "<?php\n\n/** Generated deployment-only Discord webhook settings. */\n\$webhookConfig = " . var_export($clean, true) . ";\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)
            || !rename($temporary, $this->path) || !chmod($this->path, 0600)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            return false;
        }

        return true;
    }

    public static function validUrl(string $url): bool
    {
        return preg_match(self::URL_PATTERN, $url) === 1;
    }

    /**
     * @param array<mixed> $levels
     * @return list<int>
     */
    private static function cleanMilestones(array $levels): array
    {
        $clean = [];
        foreach ($levels as $level) {
            if ((is_int($level) || (is_string($level) && ctype_digit($level))) && (int) $level >= 1 && (int) $level <= 100) {
                $clean[(int) $level] = (int) $level;
            }
        }
        ksort($clean);

        return array_values($clean);
    }

    /** @return array<string,mixed> */
    private function read(): array
    {
        if ($this->path === '' || !is_file($this->path) || is_link($this->path)) {
            return [];
        }
        try {
            $config = (static function (string $file): mixed {
                $webhookConfig = null;
                require $file;
                /** @var mixed $webhookConfig */

                return $webhookConfig;
            })($this->path);
        } catch (\Throwable) {
            return [];
        }

        return is_array($config) ? $config : [];
    }
}
