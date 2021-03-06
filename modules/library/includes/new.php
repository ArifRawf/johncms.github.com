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

use Library\Hashtags;
use Library\Rating;

echo '<div class="phdr"><strong><a href="?">' . __('Library') . '</a></strong> | ' . __('New Articles') . '</div>';

$total = $db->query("SELECT COUNT(*) FROM `library_texts` WHERE `time` > '" . (time() - 259200) . "' AND `premod`=1")->fetchColumn();
$page = $page >= ceil($total / $user->config->kmess) ? ceil($total / $user->config->kmess) : $page;
$start = $page == 1 ? 0 : ($page - 1) * $user->config->kmess;
$sql = $db->query(
    "SELECT `id`, `name`, `time`, `uploader`, `uploader_id`, `count_views`, `comments`, `comm_count`, `cat_id`, `announce` FROM `library_texts`
    WHERE `time` > '" . (time() - 259200) . "'
    AND `premod`=1
    ORDER BY `time` DESC
    LIMIT " . $start . ',' . $user->config->kmess
);
$nav = ($total > $user->config->kmess) ? '<div class="topmenu">' . $tools->displayPagination('?act=new&amp;', $start, $total, $user->config->kmess) . '</div>' : '';
echo $nav;
if ($total) {
    $i = 0;
    while ($row = $sql->fetch()) {
        echo '<div class="list' . (++$i % 2 ? 2 : 1) . '">'
            . (file_exists(UPLOAD_PATH . 'library/images/small/' . $row['id'] . '.png')
                ? '<div class="avatar"><img src="../upload/library/images/small/' . $row['id'] . '.png" alt="screen" /></div>'
                : '')
            . '<div class="righttable"><h4><a href="?id=' . $row['id'] . '">' . $tools->checkout($row['name']) . '</a></h4>'
            . '<div><small>' . $tools->checkout($row['announce'], 0, 2) . '</small></div></div>';

        // Описание к статье
        $obj = new Hashtags($row['id']);
        $rate = new Rating($row['id']);
        echo '<table class="desc">'
            // Раздел
            . '<tr>'
            . '<td class="caption">' . __('Section') . ':</td>'
            . '<td><a href="?do=dir&amp;id=' . $row['cat_id'] . '">' . $tools->checkout($db->query('SELECT `name` FROM `library_cats` WHERE `id`=' . $row['cat_id'])->fetchColumn()) . '</a></td>'
            . '</tr>'
            // Тэги
            . ($obj->getAllStatTags() ? '<tr><td class="caption">' . __('Tags') . ':</td><td>' . $obj->getAllStatTags(1) . '</td></tr>' : '')
            // Кто добавил?
            . '<tr>'
            . '<td class="caption">' . __('Who added') . ':</td>'
            . '<td><a href="' . di('config')['johncms']['homeurl'] . '/profile/?user=' . $row['uploader_id'] . '">' . $tools->checkout($row['uploader']) . '</a> (' . $tools->displayDate($row['time']) . ')</td>'
            . '</tr>'
            // Рейтинг
            . '<tr>'
            . '<td class="caption">' . __('Rating') . ':</td>'
            . '<td>' . $rate->viewRate() . '</td>'
            . '</tr>'
            // Прочтений
            . '<tr>'
            . '<td class="caption">' . __('Number of readings') . ':</td>'
            . '<td>' . $row['count_views'] . '</td>'
            . '</tr>'
            // Комментарии
            . '<tr>';
        if ($row['comments']) {
            echo '<td class="caption"><a href="?act=comments&amp;id=' . $row['id'] . '">' . __('Comments') . '</a>:</td><td>' . $row['comm_count'] . '</td>';
        } else {
            echo '<td class="caption">' . __('Comments') . ':</td><td>' . __('Comments are closed') . '</td>';
        }
        echo '</tr></table>';

        echo '</div>';
    }
}
echo '<div class="phdr">' . __('Total') . ': ' . (int) $total . '</div>';
echo $nav;
echo '<p><a href="?">' . __('To Library') . '</a></p>';
