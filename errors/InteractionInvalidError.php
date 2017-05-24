<?php
/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 17.05.17
 * Time: 12:56
 */

namespace bigdropinc\interactions\errors;

use Throwable;
use yii\base\UserException;

class InteractionInvalidError extends UserException
{
    public $interaction;

    public function __construct($interaction, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}