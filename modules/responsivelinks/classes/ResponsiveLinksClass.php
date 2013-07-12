<?php

/**
 * Class ResponsiveLinksClass
 */
class ResponsiveLinksClass extends ObjectModel
{
    public $position;
    public $title;
    public $url;
    public $id_category;
    public $id_cms;
    public $id_cms_category;
    public $id_product;
    public $id_parent;
    public $id_child;

    public static $definition = array(
        'table' => 'responsivelinks',
        'primary' => 'id_responsivelinks',
        /* TODO : enable multishop */
        'multilang' => true,
        'fields' => array(
            'position'          => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'title'             => array('type' => self::TYPE_STRING, 'lang' => true, 'size' => 255),
            'url'               => array('type' => self::TYPE_STRING, 'lang' => true, 'size' => 255),
            'id_category'       => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'),
            'id_cms'            => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'),
            'id_cms_category'   => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'),
            'id_product'        => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'),
            'id_parent'         => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'),
            'id_child'          => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'default' => '0'))
    );

    /**
      * Check then return multilingual fields for database interaction
      *
      * @return array Multilingual fields
      */
    public function getTranslationsFieldsChild()
    {
        parent::validateFieldsLang();

        $fieldsArray = array('title', 'url');
        $fields = array();
        $languages = Language::getLanguages(false);
        $defaultLanguage = (int)(Configuration::get('PS_LANG_DEFAULT'));
        foreach ($languages as $language)
        {
            $fields[$language['id_lang']]['id_lang'] = (int)($language['id_lang']);
            $fields[$language['id_lang']][$this->identifier] = (int)($this->id);
            foreach ($fieldsArray as $field)
            {
                if (!Validate::isTableOrIdentifier($field))
                    die(Tools::displayError());
                if (isset($this->{$field}[$language['id_lang']]) AND !empty($this->{$field}[$language['id_lang']]))
                    $fields[$language['id_lang']][$field] = pSQL($this->{$field}[$language['id_lang']], true);
                elseif (in_array($field, $this->fieldsRequiredLang))
                    $fields[$language['id_lang']][$field] = pSQL($this->{$field}[$defaultLanguage], true);
                else
                    $fields[$language['id_lang']][$field] = '';
            }
        }
        return $fields;
    }

    public function copyFromPost()
    {
        /* Classical fields */
        foreach ($_POST AS $key => $value)
            if (key_exists($key, $this) AND $key != 'id_'.$this->table)
                $this->{$key} = $value;

        /* Multilingual fields */
        if (sizeof(ResponsiveLinksClass::$definition['fields']))
        {
            $languages = Language::getLanguages(false);
            foreach ($languages AS $language)
                foreach (ResponsiveLinksClass::$definition['fields'] AS $field => $validation)
                    if (isset($_POST[$field.'_'.(int)($language['id_lang'])]))
                        $this->{$field}[(int)($language['id_lang'])] = $_POST[$field.'_'.(int)($language['id_lang'])];
        }
    }

    /**
     * Return all links with parent or not
     *
     * @param $id_lang id Customer language
     * @param bool $has_parent check only parent link or not
     * @return array of ResponsiveLinksClass
     */
    public static function findAll($id_lang, $has_parent = false)
    {
        $result = Db::getInstance()->ExecuteS('
            SELECT *
            FROM '._DB_PREFIX_.'responsivelinks
            '.($has_parent == true ? ' WHERE id_parent = 0' : '').'
            ORDER by position ASC
        ');

        foreach($result as $link => $value)
        {
            $result[$link] = new ResponsiveLinksClass($value['id_responsivelinks'], $id_lang);
        }

        return $result;
    }

    /**
     * Return all links from a parent
     *
     * @param $id parent link
     * @param $id_lang id Customer language
     * @return array of ResponsiveLinksClass
     */
    public static function findSub($id, $id_lang)
    {
        $result = Db::getInstance()->ExecuteS('
            SELECT *
            FROM '._DB_PREFIX_.'responsivelinks
            WHERE id_parent = '.(int)$id.'
            ORDER by position ASC
        ');

        foreach($result as $link => $value)
        {
            $result[$link] = new ResponsiveLinksClass($value['id_responsivelinks'], $id_lang);
        }

        return $result;
    }

    /**
     * Delete all child links from a link
     *
     * @return bool isDeleted
     */
    public function deleteSubLinks()
    {
        //get all sub links for deletion
        foreach ($this->findSub($this->id, Context::getContext()->cookie->id_lang) as $linkSub)
        {
            $linkSub->deleteSubLinks();

            if (!$linkSub->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return max position from all responsive links
     *
     * @return int position
     */
    public static function getMaxPosition()
    {
        $result = Db::getInstance()->getRow('
            SELECT MAX(r.position) as position
            FROM '._DB_PREFIX_.'responsivelinks r
        ');

        if (!$result['position']) {
            $return = 1;
        } else {
            $return = $result['position'] + 1;
        }

        return $return;
    }

    /**
     * Update the position of ResponsiveLinksClass objects
     *
     * @param $positions id of each ResponsiveLinksClass sorted
     * @return bool
     */
    public function updatePosition($positions)
    {
        $i = 1;

        foreach ($positions as $idLink)
        {
            if ($idLink <> '') {
                if (!Db::getInstance()->update('responsivelinks', array('position' => $i), 'id_responsivelinks = '.$idLink)) {
                    return false;
                }

                $i++;
            }

        }

        return true;
    }
}