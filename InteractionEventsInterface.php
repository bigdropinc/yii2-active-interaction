<?php

namespace bigdropinc\interactions;

interface InteractionEventsInterface
{
    const EVENT_BEFORE_LOAD = 'beforeLoad';
    const EVENT_AFTER_LOAD = 'afterLoad';
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE = 'afterExecute';
    const EVENT_ON_SUCCESS = 'onSuccess';
    const EVENT_ON_ERRORS = 'onErrors';
}
