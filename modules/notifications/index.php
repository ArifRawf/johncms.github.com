<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

use Johncms\Api\UserInterface;
use Johncms\Utility\NewsWidget;
use League\Plates\Engine;
use Psr\Container\ContainerInterface;

defined('_IN_JOHNCMS') || die('Error: restricted access');

/**
 * @var ContainerInterface $container
 * @var Engine $view
 */

$container = App::getContainer();
$view = $container->get(Engine::class);

// Регистрируем Namespace для шаблонов модуля
$view->addFolder('notifications', __DIR__ . '/templates/');

$notifications = [];

// Дополнительные уведомления для администраторов
if ($user->rights >= 7) {

    // Пользователи на регистрации
    $reg_total = $db->query("SELECT COUNT(*) FROM `users` WHERE `preg`='0'")->fetchColumn();
    if ($reg_total) {
        $notifications[] = [
            'name' => _t('Users on registration'),
            'url' => '/admin/index.php?act=reg',
            'counter' => $reg_total,
            'type' => 'info',
        ];
    }

    // Статьи на модерации
    $library_mod = $this->db->query('SELECT COUNT(*) FROM `library_texts` WHERE `premod` = 0')->fetchColumn();
    if ($library_mod) {
        $notifications[] = [
            'name' => _t('Articles on moderation'),
            'url' => '/library/?act=premod',
            'counter' => $library_mod,
            'type' => 'info',
        ];
    }

    // Загрузки на модерации
    $downloads_mod = $this->db->query("SELECT COUNT(*) FROM `download__files` WHERE `type` = '3'")->fetchColumn();
    if ($downloads_mod) {
        $notifications[] = [
            'name' => _t('Downloads on moderation'),
            'url' => 'downloads/?act=mod_files',
            'counter' => $downloads_mod,
            'type' => 'info',
        ];
    }

}

// Сообщение о бане
if (!empty($user->ban)) {
    $notifications[] = [
        'name' => _t('Ban', 'system'),
        'url' => '/profile/?act=ban',
        'counter' => 0,
        'type' => 'warning',
    ];
}

// Системные сообщения
$list = [];
$new_sys_mail = $db->query("SELECT COUNT(*) FROM `cms_mail` WHERE `from_id`='" . $user->id . "' AND `read`='0' AND `sys`='1' AND `delete`!='" . $user->id . "'")->fetchColumn();
if ($new_sys_mail) {
    $notifications[] = [
        'name' => _t('System messages', 'system'),
        'url' => '/mail/index.php?act=systems',
        'counter' => $new_sys_mail,
        'type' => 'info',
    ];
}

// Личные сообщения
$new_mail = $db->query("SELECT COUNT(*) FROM `cms_mail`
                            LEFT JOIN `cms_contact` ON `cms_mail`.`user_id`=`cms_contact`.`from_id` AND `cms_contact`.`user_id`='" . $user->id . "'
                            WHERE `cms_mail`.`from_id`='" . $user->id . "'
                            AND `cms_mail`.`sys`='0'
                            AND `cms_mail`.`read`='0'
                            AND `cms_mail`.`delete`!='" . $user->id . "'
                            AND `cms_contact`.`ban`!='1'")->fetchColumn();
if ($new_mail) {
    $notifications[] = [
        'name' => _t('Mail', 'system'),
        'url' => '/mail/index.php?act=new',
        'counter' => $new_mail,
        'type' => 'info',
    ];
}

// Комментарии в личной гостевой
if ($user->comm_count > $user->comm_old) {
    $notifications[] = [
        'name' => _t('Guestbook', 'system'),
        'url' => '/profile/?act=guestbook&amp;user=' . $user->id,
        'counter' => ($user->comm_count - $user->comm_old),
        'type' => 'info',
    ];
}

// Комментарии в альбомах
$new_album_comm = $db->query('SELECT COUNT(*) FROM `cms_album_files` WHERE `user_id` = \'' . $user->id . '\' AND `unread_comments` = 1')->fetchColumn();
if ($new_album_comm) {
    $notifications[] = [
        'name' => _t('Comments', 'system'),
        'url' => '/album/index.php?act=top&amp;mod=my_new_comm',
        'counter' => 0,
        'type' => 'info',
    ];
}

echo $view->render('notifications::index', [
    'notifications' => $notifications,
]);