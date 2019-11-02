<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

defined('_IN_JOHNCMS') || die('Error: restricted access');

$textl = _t('Mail');

if ($id) {
    $req = $db->query("SELECT * FROM `cms_mail` WHERE (`user_id`='" . $systemUser->id . "' OR `from_id`='" . $systemUser->id . "') AND `id` = '${id}' AND `file_name` != '' AND `delete`!='" . $systemUser->id . "' LIMIT 1");

    if (! $req->rowCount()) {
        //Выводим ошибку
        echo $view->render('system::app/old_content', [
            'title'   => $textl,
            'content' => $tools->displayError(_t('Such file does not exist')),
        ]);
        exit;
    }

    $res = $req->fetch();

    if (file_exists(UPLOAD_PATH . 'mail/' . $res['file_name'])) {
        $db->exec("UPDATE `cms_mail` SET `count` = `count`+1 WHERE `id` = '${id}' LIMIT 1");
        header('Location: ../upload/mail/' . $res['file_name']);
        exit;
    }
    echo $tools->displayError(_t('Such file does not exist'));
} else {
    echo $tools->displayError(_t('No file selected'));
}
