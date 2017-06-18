<?php
/**
 * Created by PhpStorm.
 * User: vadim
 * Date: 18.06.17
 * Time: 19:28
 */

namespace bigdropinc\interactions;


trait InteractionEventsTrait
{
    protected function beforeExecute()
    {
        $this->trigger(InteractionEventsInterface::EVENT_BEFORE_EXECUTE);
    }

    protected function afterExecute()
    {
        $this->trigger(InteractionEventsInterface::EVENT_AFTER_EXECUTE);
    }

    protected function beforeLoad()
    {
        $this->trigger(InteractionEventsInterface::EVENT_BEFORE_LOAD);
    }

    protected function afterLoad()
    {
        $this->trigger(InteractionEventsInterface::EVENT_AFTER_LOAD);
    }

    protected function onSuccess()
    {
        $this->trigger(InteractionEventsInterface::EVENT_ON_SUCCESS);
    }

    protected function onErrors()
    {
        $this->trigger(InteractionEventsInterface::EVENT_ON_ERRORS);
    }
}