<?php

namespace bigdropinc\interactions;

/**
 * Class ActiveInteraction
 * @package backend\interactions
 */
abstract class ActiveInteraction extends ActiveInteractionBase implements NestedInteractionsRelationsInterface
{
    use NestedInteractionTrait;

    public function isSuccess()
    {
        return parent::isSuccess() && $this->nestedValid; // TODO: Change the autogenerated stub
    }

    protected function loadInternal($data)
    {
        $result = parent::loadInternal($data);
        $this->loadNested($data);

        return $result;
    }

    protected function initAttributes()
    {
        parent::initAttributes();
        $this->_attributes = array_merge($this->_attributes, $this->getNestedModels());
    }


}