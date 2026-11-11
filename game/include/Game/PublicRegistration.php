<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\WebService;
use Core\Security\Password;
use Game\SocialAuth\IdentityClaims;
use Game\SocialAuth\PendingSignup;
use Model\OptionModel;

/**
 * Server side of the public `#registration` overlay.
 *
 * Step one collects a confirmed e-mail address and the newsletter opt-in.
 * Step two collects the local account details. The pending account is stored
 * in the world's activation table and the player continues in /activate.php,
 * which offers the tribe and start position and then creates the user.
 */
final class PublicRegistration
{
    public const NAME_PATTERN = '/^[A-Za-z0-9_.-]{3,15}$/D';
    public const PASSWORD_MAX_LENGTH = 20;

    /** @var array<string,string> */
    private array $errors = [];
    private string $formError = '';
    /** @var array{name:string,email:string,preKey:string,newsletter:bool} */
    private array $input = ['name' => '', 'email' => '', 'preKey' => '', 'newsletter' => false];
    /** @var array<string,mixed> */
    private array $world;
    private bool $showAccountStep = false;
    private ?string $redirect = null;
    private bool $submitted = false;
    /** A verified social identity waiting for its game account (name selection only). */
    private ?IdentityClaims $social = null;
    private ?PendingSignup $pendingSignup = null;
    /** @var array<string,string> Public start-page copy (selected public locale). */
    private array $copy = [];

    /**
     * Process a registration POST (when there is one) and return the state the
     * overlay template renders.
     *
     * @param array<string,string> $copy Public start-page catalogue values of the
     *        selected public locale; reference-backed email messages come from it
     *        so the overlay follows the public locale, not the game language.
     * @return array{
     *   world:array<string,mixed>,
     *   input:array{name:string,email:string,preKey:string,newsletter:bool},
     *   errors:array<string,string>,
     *   formError:string,
     *   showAccountStep:bool,
     *   passwordMax:int,
     *   open:bool,
     *   redirect:?string,
     *   social:bool
     * }
     */
    public static function handle(array $copy): array
    {
        $flow = new self();
        $flow->copy = $copy;
        $flow->world = RegistrationWorld::summary();
        if ($flow->world['registrationOpen']) {
            $flow->pendingSignup = PendingSignup::fromGlobals();
            $flow->social = $flow->pendingSignup->current();
            if ($flow->social !== null) {
                // The provider email is authoritative; the overlay opens on the name step.
                $flow->input['email'] = $flow->social->email;
                $flow->showAccountStep = true;
            }
        }
        if ($flow->world['registrationOpen'] && WebService::isPost() && self::isRegistrationPost()) {
            $flow->submitted = true;
            $flow->submit();
        }

        return [
            'world' => $flow->world,
            'input' => $flow->input,
            'errors' => $flow->errors,
            'formError' => $flow->formError,
            'showAccountStep' => $flow->showAccountStep,
            'passwordMax' => self::PASSWORD_MAX_LENGTH,
            'open' => $flow->submitted || $flow->social !== null,
            'redirect' => $flow->redirect,
            'social' => $flow->social !== null,
        ];
    }

    private static function isRegistrationPost(): bool
    {
        return isset($_POST['stage']) && in_array($_POST['stage'], ['email', 'account'], true);
    }

    private function text(string $key, int $maxLength): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? substr(trim($value), 0, $maxLength) : '';
    }

    private function submit(): void
    {
        $this->input = [
            'name' => $this->text('name', 15),
            'email' => $this->text('email', 90),
            'preKey' => $this->text('preKey', 32),
            'newsletter' => ($_POST['newsletter'] ?? null) === '1',
        ];
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['passwordConfirm']) && is_string($_POST['passwordConfirm'])
            ? $_POST['passwordConfirm']
            : '';

        if ($this->social !== null) {
            $this->submitSocial();

            return;
        }

        if ($_POST['stage'] === 'email') {
            $this->validateEmail();
            $this->showAccountStep = $this->errors === [];

            return;
        }

        $this->showAccountStep = true;

        if (!throttle_public_form('register', 10, 3600)) {
            $this->formError = T('Login', 'tooManyRequests');

            return;
        }

        $name = $this->input['name'];
        $this->validateName($name);

        $this->validateEmail();

        if (strlen($password) > self::PASSWORD_MAX_LENGTH) {
            $this->errors['password'] = sprintf(
                T('Login', 'Use at most %d characters.'),
                self::PASSWORD_MAX_LENGTH,
            );
        } elseif (($problem = Password::validate($password, $name)) !== null) {
            $this->errors['password'] = $problem === 'passwordLikeName'
                ? T('Login', 'The password must differ from your account name.')
                : sprintf(T('Login', 'Use at least %d characters.'), Password::MIN_LENGTH);
        } elseif (!hash_equals($password, $confirm)) {
            $this->errors['passwordConfirm'] = T('Login', 'The passwords do not match.');
        }

        $keyId = $this->validatePreRegistrationKey();
        if (isset($this->errors['email'])) {
            // The e-mail field lives on the first step; send the player back to it.
            $this->showAccountStep = false;
        }
        if ($this->errors !== []) {
            return;
        }

        $this->createActivation($name, Password::hash($password), $this->input['newsletter'], $keyId);
    }

    /**
     * Name step for a verified social identity: no email or password fields.
     * The email comes from the provider (never from the POST), so the
     * email-taken check was already done by the OAuth callback.
     */
    private function submitSocial(): void
    {
        $this->showAccountStep = true;
        if ($_POST['stage'] !== 'account') {
            return;
        }
        if (!throttle_public_form('register', 10, 3600)) {
            $this->formError = T('Login', 'tooManyRequests');

            return;
        }
        $email = $this->social->email ?? '';
        $this->input['email'] = $email;
        $this->validateName($this->input['name']);
        if ($this->emailBlocked($email)) {
            $this->formError = T('Login', 'This email address cannot be used.');
        }
        $keyId = $this->validatePreRegistrationKey();
        if ($this->errors !== [] || $this->formError !== '') {
            return;
        }
        $token = $this->createActivation(
            $this->input['name'],
            PendingSignup::unusablePasswordHash(),
            false,
            $keyId,
        );
        if ($token !== null) {
            $this->pendingSignup?->bindActivationToken($token);
        }
    }

    private function validateName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $this->errors['name'] = T('Login', 'Use 3 to 15 letters, numbers, dots, underscores or hyphens.');
        } elseif ((new OptionModel())->isNameBlackListed($name)) {
            $this->errors['name'] = T('Login', 'This name is not allowed.');
        } elseif ($this->nameTaken($name)) {
            $this->errors['name'] = T('Login', 'This name is already taken.');
        }
    }

    /** @return int|null the matched pre-registration key id (null when none or invalid) */
    private function validatePreRegistrationKey(): ?int
    {
        if (!$this->world['keyOnly']) {
            return null;
        }
        $keyId = $this->preRegistrationKeyId($this->input['preKey']);
        if ($keyId === null) {
            $this->errors['preKey'] = T('Login', 'This registration key is not valid.');
        }

        return $keyId;
    }

    /** Stores the pending account and continues in /activate.php; returns its token on success. */
    private function createActivation(string $name, string $passwordHash, bool $newsletter, ?int $keyId): ?string
    {
        $token = bin2hex(random_bytes(32));
        $created = DB::getInstance()->run(
            'INSERT INTO activation (name, password, email, newsletter, token, refUid, time) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $passwordHash,
                $this->input['email'],
                $newsletter ? 1 : 0,
                $token,
                $this->referrerId(),
                time(),
            ],
        );
        if ($created === false) {
            $this->formError = T('Login', 'The account could not be created. Please try again.');

            return null;
        }
        if ($keyId !== null) {
            GlobalDB::getInstance()->run('UPDATE preregistration_keys SET used=1 WHERE id=?', [$keyId]);
        }
        $_SESSION[WebService::fixSessionPrefix('token')] = $token;
        $this->redirect = '/activate.php';

        return $token;
    }

    private function validateEmail(): void
    {
        $email = $this->input['email'];
        if ($email === '') {
            $this->errors['email'] = $this->copy['Please enter your email address'];
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors['email'] = $this->copy['Please enter a valid email address. E.g. mymail@provider.com'];
        } elseif ($this->emailBlocked($email)) {
            $this->errors['email'] = T('Login', 'This email address cannot be used.');
        } elseif ($this->emailTaken($email)) {
            $this->errors['email'] = $this->copy['Your email address is already connected to a Legends Lobby Account.'];
        }
    }

    /**
     * @param list<int>|list<string> $parameters
     */
    private function exists(DB $db, string $sql, array $parameters): bool
    {
        $statement = $db->run($sql, $parameters);
        $result = $statement ? $statement->get_result() : false;

        return $result && (int)($result->fetch_row()[0] ?? 0) > 0;
    }

    private function nameTaken(string $name): bool
    {
        $db = DB::getInstance();

        return $this->exists($db, 'SELECT COUNT(id) FROM users WHERE name=?', [$name])
            || $this->exists($db, 'SELECT COUNT(id) FROM activation WHERE name=?', [$name]);
    }

    private function emailTaken(string $email): bool
    {
        $db = DB::getInstance();

        return $this->exists($db, 'SELECT COUNT(id) FROM users WHERE email=?', [$email])
            || $this->exists($db, 'SELECT COUNT(id) FROM activation WHERE email=?', [$email]);
    }

    private function emailBlocked(string $email): bool
    {
        return $this->exists(
            GlobalDB::getInstance(),
            'SELECT COUNT(id) FROM email_blacklist WHERE email=?',
            [$email],
        );
    }

    private function preRegistrationKeyId(string $key): ?int
    {
        if ($key === '') {
            return null;
        }
        $statement = GlobalDB::getInstance()->run(
            'SELECT id FROM preregistration_keys WHERE worldId=? AND pre_key=? AND used=0 LIMIT 1',
            [(string)Config::getProperty('settings', 'worldUniqueId'), $key],
        );
        $result = $statement ? $statement->get_result() : false;
        $row = $result ? $result->fetch_row() : null;

        return $row ? (int)$row[0] : null;
    }

    /** Refer-a-friend code `<worldId>_<playerId>`, honoured only for a real player of this world. */
    private function referrerId(): int
    {
        $code = self::referralCode();
        if ($code === '' || preg_match('/^([A-Za-z0-9_-]{1,5})_([0-9]{1,9})$/D', $code, $match) !== 1
            || $match[1] !== (string)getWorldId()) {
            return 0;
        }

        return $this->exists(
            DB::getInstance(),
            'SELECT COUNT(id) FROM users WHERE id=? AND id>2',
            [(int)$match[2]],
        ) ? (int)$match[2] : 0;
    }

    /** The invite code from the query string (or form), or '' when absent or malformed. */
    public static function referralCode(): string
    {
        $code = $_GET['uc'] ?? ($_POST['uc'] ?? '');

        return is_string($code) && preg_match('/^[A-Za-z0-9_-]{1,16}$/D', $code) === 1 ? $code : '';
    }
}
