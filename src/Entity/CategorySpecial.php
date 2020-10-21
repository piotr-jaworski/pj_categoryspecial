<?php
namespace PjCategorySpecial\Entity;

use PrestaShop\PrestaShop\Adapter\Entity\ObjectModel;
use Db;
use Validate;

class CategorySpecial extends ObjectModel
{

    /**
     * @var int
     */
    public $id_category;

    /**
     * @var int
     */
    public $special;

    public static $definition = [
        'table' => 'category_special',
        'primary' => 'id_category_special',
        'fields' => [
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'special' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];

    public static function getByCategory($categoryId)
    {

        if(!Validate::isUnsignedId($categoryId)){
            throw new Exception('bad Id');
        }
        $db = Db::getInstance();
        $id = $db->getValue('
            SELECT id_category_special FROM `' . _DB_PREFIX_ . 'category_special`
                WHERE `id_category` = "'.pSQL($categoryId).'"'
        );
        $obj = new CategorySpecial($id);
        if(empty($obj->id)){
            $obj->id_category = $categoryId;
            $obj->special = false;
        }else{
            $obj->special = (bool) $obj->special;
        }
        return $obj;
    }

    public static function toggleSpecial($categoryId)
    {
        $obj = static::getByCategory($categoryId);
        $obj->special = !$obj->special;

        return $obj->save();
    }
}