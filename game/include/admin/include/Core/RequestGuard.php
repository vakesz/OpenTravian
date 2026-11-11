<?php

use Core\Session;
use Game\AdminUi;

/**
 * CSRF gate for the control panel.
 *
 * - Every POST must carry the session checker token (compared with hash_equals).
 * - A GET that would change state is never executed. Instead of running the
 *   controller, the visitor sees a confirmation page whose POST form carries
 *   the same parameters plus the checker; the confirmed POST is then replayed
 *   into $_GET so the controller logic itself does not change.
 * - Forms that post without a token (older templates) get one injected on output.
 *
 * MUTATING_GET lists, per lower-cased action, the query parameters that make a
 * GET state-changing: '*' means any value, otherwise the listed values only.
 */
final class AdminRequestGuard
{
    public const CONFIRM_FIELD = 'adminConfirmed';

    private const MUTATING_GET = [
        'activationlist' => ['section' => ['del']],
        'deleting' => ['section' => ['del', 'deleteNow']],
        'editplayer' => ['section' => [
            'deletePlayer', 'fillResources', 'killHero', 'setAsNormalUser', 'setAsFakeUser',
            'changeTribeToRomans', 'changeTribeToTeutons', 'changeTribeToGuals',
            'changeTribeToEgyptians', 'changeTribeToHuns',
        ]],
        'editvillage' => [
            'r' => '*', 'deleteTraining' => '*', 'releaseArtifact' => '*', 'activateArtifact' => '*',
            'deleteResearch' => '*', 'deleteBuilding' => '*', 'deleteDemolish' => '*',
        ],
        'editalliance' => ['section' => ['deleteAlliance']],
        'bannedlist' => ['section' => ['unban']],
        'cleanup' => ['do' => '*'],
        'fixes' => ['fix' => '*', 'fixKid' => '*'],
        'reportedmessages' => ['delete' => '*', 'deleteAll' => '*'],
        'playeraccusations' => ['handle' => '*'],
        'privateinfobox' => ['section' => ['delete']],
        'publicinfobox' => ['section' => ['delete']],
        'ipban' => ['del' => '*'],
        'badwords' => ['del' => '*'],
        'blacklistednames' => ['del' => '*'],
        'blacklistemail' => ['del' => '*'],
        'filteredurls' => ['del' => '*'],
        'goldpackagecodegenerator' => ['section' => ['del']],
        'giftgoldpackagecodegenerator' => ['section' => ['del']],
        'multiaccount' => ['id' => '*'],
        'detector' => [
            'banPlayer' => '*',
            'releaseBannedPlayer' => '*',
            'deletePlayer' => '*',
            'clearDeletion' => '*',
        ],
    ];

    private function __construct()
    {
    }

    /** @return array<string,string|list<string>> */
    public static function mutatingGetRules(): array
    {
        return self::MUTATING_GET;
    }

    /**
     * True when a GET with these query parameters would change state for the action.
     *
     * @param array<mixed> $query
     */
    public static function isStateChangingGet(string $action, array $query): bool
    {
        $rules = self::MUTATING_GET[strtolower($action)] ?? [];
        foreach ($rules as $key => $values) {
            if (!array_key_exists($key, $query) || !is_scalar($query[$key])) {
                continue;
            }
            if ($values === '*' || in_array((string)$query[$key], $values, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the HTML to show instead of the controller, or null when the
     * request may proceed. Replays a confirmed POST into $_GET.
     */
    public static function intercept(string $action): ?string
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            if (!Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
                http_response_code(400);
                return '<h1>Request not accepted</h1>'
                    . AdminUi::notice('The form token is missing or has expired. Go back, reload the page and try again.', 'error')
                    . '<div class="adminActions">' . AdminUi::button('Back to the control panel', 'green', ['href' => 'admin.php']) . '</div>';
            }
            if (($_POST[self::CONFIRM_FIELD] ?? '') === '1') {
                foreach ($_POST as $key => $value) {
                    if ($key !== self::CONFIRM_FIELD && $key !== Session::getCheckerName()) {
                        $_GET[$key] = $value;
                    }
                }
            }
            return null;
        }
        if (self::isStateChangingGet($action, $_GET)) {
            return self::confirmation($_GET);
        }
        return null;
    }

    /**
     * @param array<mixed> $query
     */
    private static function confirmation(array $query): string
    {
        $fields = '';
        foreach ($query as $key => $value) {
            if ($key === Session::getCheckerName() || !is_scalar($value)) {
                continue;
            }
            $fields .= '<input type="hidden" name="' . AdminUi::esc($key) . '" value="' . AdminUi::esc($value) . '">';
        }
        $details = '';
        foreach ($query as $key => $value) {
            if ($key !== 'action' && $key !== Session::getCheckerName() && is_scalar($value)) {
                $details .= '<tr><td class="head">' . AdminUi::esc($key) . '</td><td>' . AdminUi::esc($value) . '</td></tr>';
            }
        }
        $body = AdminUi::notice('This changes data in the world and cannot be undone.', 'warning')
            . ($details !== '' ? '<table class="adminTable"><tbody>' . $details . '</tbody></table>' : '')
            . '<form method="post" action="admin.php" class="adminActions">'
            . $fields
            . '<input type="hidden" name="' . self::CONFIRM_FIELD . '" value="1">'
            . Session::getCheckerInput()
            . AdminUi::button('Confirm', 'green')
            . AdminUi::button('Cancel', 'grey', ['href' => 'admin.php'])
            . '</form>';
        return '<h1>Confirm this action</h1>' . AdminUi::box('Action: ' . (string)($query['action'] ?? ''), $body);
    }

    /** Adds the checker token to every POST form that does not already carry one. */
    public static function injectTokens(?string $html): ?string
    {
        if ($html === null || stripos($html, '<form') === false) {
            return $html;
        }
        $input = Session::getCheckerInput();
        $name = Session::getCheckerName();
        $out = '';
        $offset = 0;
        while (($start = stripos($html, '<form', $offset)) !== false) {
            $tagEnd = strpos($html, '>', $start);
            if ($tagEnd === false) {
                break;
            }
            $close = stripos($html, '</form>', $tagEnd);
            $blockEnd = $close === false ? strlen($html) : $close;
            $tag = substr($html, $start, $tagEnd - $start + 1);
            $isPost = preg_match('/\bmethod\s*=\s*["\']?post\b/i', $tag) === 1;
            $out .= substr($html, $offset, $tagEnd + 1 - $offset);
            if ($isPost && !str_contains(substr($html, $tagEnd, $blockEnd - $tagEnd), $name)) {
                $out .= $input;
            }
            $offset = $tagEnd + 1;
        }
        return $out . substr($html, $offset);
    }
}
