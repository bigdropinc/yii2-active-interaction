<?php
/**
 * Created by PhpStorm.
 * User: bigdrop
 * Date: 22.05.17
 * Time: 17:20
 */

namespace bigdropinc\interactions\validators;


use Yii;
use yii\validators\Validator;

class ObjectValidator extends Validator
{
    public $instanceOf;

    public function validateAttribute($model, $attribute)
    {
        if(!($model->$attribute instanceof $this->instanceOf)){
            $this->addError($model, $attribute, 'test');
        }
    }

}