<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

namespace Library;

use Johncms\System\Legacy\Tools;

/**
 * Класс дерева (Nested Sets)
 * Class Tree
 *
 * @package Library
 * @author  Koenig(Compolomus)
 */
class Tree
{
    /**
     * Массив результата
     * @var array
     */
    private $result = [];

    /**
     * Массив количества удаленных объектов
     * @var array
     */
    private $cleaned = ['images' => 0, 'comments' => 0, 'tags' => 0];

    /**
     * Обязательный аргумент, индификатор текущей вложенности parent
     * @var int|bool
     */
    private $start_id = false;

    private $child;

    private $parent;

    /** @var \PDO $db */
    private $db;

    /**
     * @var Tools
     */
    private $tools;

    public function __construct($id)
    {
        $this->start_id = $id;
        $this->db = di(\PDO::class);
        $this->tools = di(Tools::class);
    }

    /**
     * Рекурсивно проходит по дереву собирая в массив типы и уникальные иды каталогов
     * @param int $id
     * @return Tree
     */
    public function getAllChildsId($id = 0)
    {
        $id = $id == 0 ? $this->start_id : $id;
        $stmt = $this->db->prepare('SELECT `dir` FROM `library_cats` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $dirtype = $stmt->fetchColumn();
        $stmt = $this->db->prepare('SELECT `id` FROM ' . ($dirtype ? '`library_cats`' : '`library_texts`') . ' WHERE ' . ($dirtype ? '`parent`' : '`cat_id`') . ' = ?');
        $stmt->execute([$id]);
        $this->result['dirs'][$id] = $id;
        if ($stmt->rowCount()) {
            while ($this->child = $stmt->fetch()) {
                $this->result[($dirtype ? 'dirs' : 'texts')][$this->child['id']] = $this->child['id'];
                if ($dirtype) {
                    $this->getAllChildsId($this->child['id']);
                }
            }
        }

        return $this;
    }

    /**
     * Очистка статей, удаляет комментарии, картинки и теги от статей
     * @param mixed $data
     * @return array
     */
    public function cleanTrash($data)
    {
        if (! is_array($data)) {
            $stmt = $this->db->prepare('DELETE FROM `cms_library_comments` WHERE `sub_id` = ?');
            $stmt->execute([$data]);
            $this->cleaned['comments'] += $stmt->rowCount();

            $obj = new Hashtags($data);
            $this->cleaned['tags'] += $obj->delTags();

            if (file_exists(UPLOAD_PATH . 'library/images/small/' . $data . '.png')) {
                unlink(UPLOAD_PATH . 'library/images/big/' . $data . '.png');
                unlink(UPLOAD_PATH . 'library/images/orig/' . $data . '.png');
                unlink(UPLOAD_PATH . 'library/images/small/' . $data . '.png');
                $this->cleaned['images'] += 3;
            }
        } else {
            array_map([$this, 'cleanTrash'], $data);
        }

        return $this->cleaned;
    }

    /**
     * Удаляет ветку , возвращает количество удаленных каталогов, статей, тегов, коментариев и изображений в массиве
     * @param void
     * @return array
     */
    public function cleanDir()
    {
        $array = $this->result();
        $dirs = array_key_exists('dirs', $array) ? $array['dirs'] : 0;
        $texts = array_key_exists('texts', $array) ? $array['texts'] : 0;

        $trash = $this->cleanTrash($array['texts']);

        $place_holders_dirs = implode(', ', array_fill(0, count($dirs), '?'));
        $place_holders_texts = implode(',', array_fill(0, count($texts), '?'));

        $stmt = $this->db->prepare('DELETE FROM `library_cats` WHERE `id` IN(' . $place_holders_dirs . ')');
        $stmt->execute(array_values($dirs));
        $dirs = $stmt->rowCount();
        $stmt = $this->db->prepare('DELETE FROM `library_texts` WHERE `id` IN(' . $place_holders_texts . ')');
        $stmt->execute(array_values($texts));
        $texts = $stmt->rowCount();

        return array_merge(['dirs' => $dirs, 'texts' => $texts], $trash);
    }

    /**
     * Рекурсивно проходит по ветке и собирает дочерние вложения
     * @param int $parent
     * @return Tree
     */
    public function getChildsDir($parent = 0)
    {
        $parent = $parent == 0 ? $this->start_id : $parent;
        $stmt = $this->db->prepare('SELECT `id` FROM `library_cats` WHERE `parent` = ? AND `dir` = 1');
        $stmt->execute([$parent]);
        if ($stmt->rowCount()) {
            while ($this->child = $stmt->fetch()) {
                $this->result[] = $this->child['id'];
                $this->getChildsDir($this->child['id']);
            }
        }

        return $this;
    }

    /**
     * Рекурсивно проходит по дереву до корня, собирает массив с идами и именами разделов
     * @param int $id
     * @return Tree
     */
    public function processNavPanel($id = 0)
    {
        $id = $id == 0 ? $this->start_id : $id;
        $stmt = $this->db->prepare('SELECT `id`, `name`, `parent` FROM `library_cats` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $this->parent = $stmt->fetch();
        $this->result[] = ['id' => $this->parent['id'], 'name' => $this->parent['name']];
        if ($this->parent['parent'] != 0) {
            $this->processNavPanel($this->parent['parent']);
        } else {
            krsort($this->result);
        }

        return $this;
    }

    /**
     * Собирает ссылки в верхнюю панель навигации
     * @param void
     * @return string
     */
    public function printNavPanel()
    {
        $array = $this->result();
        $cnt = count($array);
        $return = [];
        $x = 1;
        foreach ($array as $cat) {
            $return[] = $x == $cnt ? '<strong>' . $this->tools->checkout($cat['name']) . '</strong>' : '<a href="?do=dir&amp;id=' . $cat['id'] . '">' . $this->tools->checkout($cat['name']) . '</a>';
            $x++;
        }

        return '<a href="?"><strong>' . __('Library') . '</strong></a> | ' . implode(' | ', $return);
    }

    /**
     * Получение результата
     * @return array
     */
    public function result()
    {
        return $this->result;
    }
}
