<?php

namespace bigdropinc\interactions\errors;

use Throwable;
use yii\base\UserException;

class InteractionInvalidError extends UserException
{
    public $interaction;

    public function __construct($interaction, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->interaction = $interaction;
        parent::__construct($message, $code, $previous);
    }

}