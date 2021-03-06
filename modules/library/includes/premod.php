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

use Library\Tree;

echo '<div class="phdr"><strong><a href="?">' . __('Library') . '</a></strong> | ' . __('Moderation Articles') . '</div>';

if ($id && isset($_GET['yes'])) {
    $sql = 'UPDATE `library_texts` SET `premod`=1 WHERE `id`=' . $id;
    echo '<div class="rmenu">' . __('Article') . ' <strong>' . $tools->checkout($db->query('SELECT `name` FROM `library_texts` WHERE `id`=' . $id)->fetchColumn()) . '</strong> ' . __('Added to the database') . '</div>';
} elseif (isset($_GET['all'])) {
    $sql = 'UPDATE `library_texts` SET `premod`=1';
    echo '<div>' . __('All Articles added in database') . '</div>';
}

if (isset($_GET['yes']) || isset($_GET['all'])) {
    $db->exec($sql);
}

$total = $db->query('SELECT COUNT(*) FROM `library_texts` WHERE `premod`=0')->fetchColumn();
$page = $page >= ceil($total / $user->config->kmess) ? ceil($total / $user->config->kmess) : $page;
$start = $page == 1 ? 0 : ($page - 1) * $user->config->kmess;

if ($total) {
    $stmt = $db->query('SELECT `id`, `name`, `time`, `uploader`, `uploader_id`, `cat_id` FROM `library_texts` WHERE `premod`=0 ORDER BY `time` DESC LIMIT ' . $start . ',' . $user->config->kmess);
    $i = 0;

    while ($row = $stmt->fetch()) {
        $dir_nav = new Tree($row['cat_id']);
        $dir_nav->processNavPanel();
        echo '<div class="list' . (++$i % 2 ? 2 : 1) . '">'
            . (file_exists(UPLOAD_PATH . 'library/images/small/' . $row['id'] . '.png')
                ? '<div class="avatar"><img src="../upload/library/images/small/' . $row['id'] . '.png" alt="screen" /></div>'
                : '')
            . '<div class="righttable"><a href="?id=' . $row['id'] . '">' . $tools->checkout($row['name']) . '</a></div>'
            . '<div class="sub">' . __('Who added') . ': ' . $tools->checkout($row['uploader']) . ' (' . $tools->displayDate($row['time']) . ')</div>'
            . '<div>' . $dir_nav->printNavPanel() . '</div>'
            . '<a href="?act=premod&amp;yes&amp;id=' . $row['id'] . '">' . __('Approve') . '</a> | <a href="?act=del&amp;type=article&amp;id=' . $row['id'] . '">' . __('Delete') . '</a>'
            . '</div>';
    }
}

echo '<div class="phdr">' . __('Total') . ': ' . (int) $total . '</div>';
echo ($total > $user->config->kmess) ? '<div class="topmenu">' . $tools->displayPagination('?act=premod&amp;', $start, $total, $user->config->kmess) . '</div>' : '';
echo $total ? '<div><a href="?act=premod&amp;all">' . __('Approve all') . '</a></div>' : '';
echo '<p><a href="?">' . __('To Library') . '</a></p>' . PHP_EOL;
