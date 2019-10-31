<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

use Batumibiz\Captcha\Captcha;
use Johncms\Api\ConfigInterface;
use Johncms\Api\ToolsInterface;
use Johncms\Api\UserInterface;
use Johncms\Users\User;
use League\Plates\Engine;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$container = App::getContainer();

/** @var ConfigInterface $config */
$config = $container->get(ConfigInterface::class);

/** @var UserInterface $user */
$user = $container->get(UserInterface::class);

/** @var Engine $view */
$view = $container->get(Engine::class);
$view->addFolder('login', __DIR__ . '/templates/');

$id = isset($_POST['id']) ? abs((int) ($_POST['id'])) : 0;
$referer = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : $config->homeurl;

if ($user->isValid()) {
    ////////////////////////////////////////////////////////////
    // Выход с сайта                                          //
    ////////////////////////////////////////////////////////////
    if (isset($_POST['logout'])) {
        // Выход с сайта: удаляем COOKIE и очищаем сессию
        setcookie('cuid', '', time() - 3600, '/');
        setcookie('cups', '', time() - 3600, '/');
        $_SESSION = [];
        header('Location: ' . $config->homeurl);
    } else {
        // Показываем запрос на подтверждение выхода с сайта
        echo $view->render('login::logout', ['referer' => $referer]);
    }
} else {
    ////////////////////////////////////////////////////////////
    // Вход на сайт                                           //
    ////////////////////////////////////////////////////////////

    /** @var PDO $db */
    $db = $container->get(PDO::class);

    /** @var ToolsInterface $tools */
    $tools = $container->get(ToolsInterface::class);

    $error = [];
    $captcha = false;
    $display_form = 1;
    $user_login = filter_input(INPUT_POST, 'n', FILTER_SANITIZE_STRING);
    $user_pass = filter_input(INPUT_POST, 'p', FILTER_SANITIZE_STRING);
    $captchaCode = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);
    $remember = isset($_POST['mem']);

    if (empty($user_login)) {
        $error[] = _t('You have not entered login', 'system');
    }

    if (empty($user_pass)) {
        $error[] = _t('You have not entered password', 'system');
    }

    if (! $error) {
        // Запрос в базу на юзера
        $stmt = $db->prepare('SELECT * FROM `users` WHERE `name_lat` = ? LIMIT 1');
        $stmt->execute([$tools->rusLat($user_login)]);

        if ($stmt->rowCount()) {
            $loginUser = new User($stmt->fetch());

            if ($loginUser->failed_login > 2) {
                if ($captchaCode) {
                    if (mb_strlen($captchaCode) > 2 && strtolower($captchaCode) === strtolower($_SESSION['code'])) {
                        // Если введен правильный проверочный код
                        $captcha = true;
                    } else {
                        // Если проверочный код указан неверно
                        $error[] = _t('The security code is not correct', 'system');
                    }

                    unset($_SESSION['code']);
                } else {
                    // Показываем CAPTCHA
                    $display_form = 0;
                    $cap = new Captcha;
                    $code = $cap->generateCode();
                    $_SESSION['code'] = $code;

                    echo $view->render('login::captcha', [
                        'captcha'    => $cap->generateImage($code),
                        'user_login' => $user_login,
                        'user_pass'  => $user_pass,
                        'remember'   => $remember,
                        'id'         => $loginUser->id
                    ]);
                }
            }

            if ($loginUser->failed_login < 3 || $captcha) {
                if (md5(md5($user_pass)) == $loginUser->password) {
                    // Если логин удачный
                    $display_form = 0;
                    $db->exec("UPDATE `users` SET `failed_login` = '0' WHERE `id` = " . $loginUser->id);

                    if (! $loginUser->preg) {
                        // Если регистрация не подтверждена
                        echo '<div class="rmenu"><p>' . _t('Sorry, but your request for registration is not considered yet. Please, be patient.',
                                'system') . '</p></div>';
                    } else {
                        // Если все проверки прошли удачно, подготавливаем вход на сайт
                        if (isset($_POST['mem'])) {
                            // Установка данных COOKIE
                            $cuid = (string) $loginUser->id;
                            $cups = md5($user_pass);
                            setcookie('cuid', $cuid, time() + 3600 * 24 * 365, '/');
                            setcookie('cups', $cups, time() + 3600 * 24 * 365, '/');
                        }

                        // Установка данных сессии
                        $_SESSION['uid'] = $loginUser->id;
                        $_SESSION['ups'] = md5(md5($user_pass));

                        $db->exec("UPDATE `users` SET `sestime` = '" . time() . "' WHERE `id` = " . $loginUser->id);
                        header('Location: ' . $config->homeurl);

                        echo '<div class="gmenu"><p><b><a href="' . $config->homeurl . '">' . _t('Enter site',
                                'system') . '</a></b></p></div>';
                    }
                } else {
                    // Если логин неудачный
                    if ($loginUser->failed_login < 3) {
                        // Прибавляем к счетчику неудачных логинов
                        $db->exec("UPDATE `users` SET `failed_login` = '" . ++$loginUser->failed_login . "' WHERE `id` = " . $loginUser->id);
                    }

                    $error[] = _t('Authorization failed', 'system');
                }
            }
        } else {
            $error[] = _t('Authorization failed', 'system');
        }
    }

    if ($display_form) {
        // Показываем LOGIN форму
        echo $view->render('login::login', [
            'error'      => isset($_POST['login']) ? $error : [],
            'user_login' => $user_login ?? ''
        ]);
    }
}
