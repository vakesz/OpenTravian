<?php

declare(strict_types=1);

namespace Core;

use Database\DB;

class EmailService
{
    public static function sendYouRegisteredOn(string $email, string $worldId, string $username, string $gameWorldUrl): int
    {
        global $twig;
        $params = [
            'DIRECTION' => Translator::getDirection(),
            'PLAYER_NAME' => $username,
            'GAME_WORLD_URL' => $gameWorldUrl,
            'WORLD_ID' => $worldId,
        ];
        $subject = sprintf(T("Thank you for registering on %s"), $worldId);
        $content = $twig->render('mail/registrationComplete.twig', $params);
        return self::sendMail($email, $subject, $content);
    }

    public static function sendActivationMail(string $email, int $serverId, string $worldId, string $username, string $activationCode): int
    {
        global $twig;
        $params = [
            'DIRECTION' => Translator::getDirection(),
            'PLAYER_NAME' => $username,
            'ACTIVATE_URL' => WebService::getIndexUrl() . Translator::getHrefLang() . '?activationCode=' . $activationCode . '&server=' . $worldId . '#activation',
            'ACTIVATION_CODE' => $activationCode,
            'WORLD_ID' => $worldId,
        ];
        $subject = sprintf(T("Thank you for registering on %s"), $worldId);
        $content = $twig->render('mail/activation.twig', $params);
        return self::sendMail($email, $subject, $content);
    }

    public static function sendMail(string $to, string $subject, string $html, int $priority = 0): int
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("INSERT INTO mailServer (toEmail, subject, html, priority) VALUES (?,?,?,?)");
        $stmt->execute([$to, $subject, $html, $priority]);
        return $stmt->rowCount();
    }

    /**
     * @param list<array{worldId: mixed, username: mixed, gameWorldUrl: mixed}> $gameWorlds
     */
    public static function sendForgottenAccounts(string $email, array $gameWorlds): int
    {
        global $twig;
        $params = [
            'DIRECTION' => Translator::getDirection(),
            'gameWorlds' => $gameWorlds,
        ];
        $subject = T("You have requested a search for your game world");
        $content = $twig->render('mail/forgottenWorlds.twig', $params);
        return self::sendMail($email, $subject, $content);
    }

    public static function sendPasswordForgotten(string $email, int $worldUniqueId, string $worldId, int $uid, string $recoveryCode): int
    {
        global $twig;
        $params = [
            'DIRECTION' => Translator::getDirection(),
            'WORLD_ID' => $worldId,
            'CHANGE_PASSWORD_URL' => WebService::getIndexUrl() . Translator::getHrefLang() . '?recoveryCode=' . $recoveryCode . '&uid=' . $uid . '&server=' . $worldId . '&serverId=' . $worldUniqueId . '#recovery',
        ];
        $subject = sprintf(T("You have requested a new password for %s"), $worldId);
        $content = $twig->render('mail/requestNewPassword.twig', $params);
        return self::sendMail($email, $subject, $content);
    }
}