<?php
/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 22.05.17
 * Time: 14:13
 */

namespace bigdropinc\interactions\concerns;


trait InteractionPrimaryKeyTrait
{
    private $_primaryKey;

    public function getPk()
    {
        return $this->_primaryKey;
    }

    public function setPk($key)
    {
        return $this->_primaryKey = $key;
    }
}