<?php

namespace bigdropinc\interactions;

use Yii;

trait NestedInteractionTrait
{

    protected $interactionData;
    protected $_nested = [];
    protected $nestedValid = true;

    protected function getNestedModels()
    {
        $nested = [];
        foreach ($this->nested() as $nestedModel) {
            $attribute = $nestedModel[0];
            $model = $nestedModel[1];
            $object = Yii::createObject($model);
            if ($nestedModel['relation'] == self::RELATION_HAS_MANY) {
                $object = [$object];
            }
            $nested[$attribute] = $object;
            $this->_nested[$attribute] = [
                'class' => $model,
                'relation' => $nestedModel['relation']
            ];
        }
        return $nested;
    }

    protected function nested()
    {
        return [];
    }

    protected function createNested($attribute, $params, $additionalParamsForCreate = [])
    {
        $relation = null;
        if ($nested = $this->_nested[$attribute]) {
            if ($nested['relation'] == self::RELATION_HAS_MANY) {
                $relation = [];
                foreach ($params as $nestedAttribute => $nestedDataArray) {
                    foreach ($nestedDataArray as $nestedData) {
                        $paramsForCreate = array_merge($additionalParamsForCreate, [$nestedAttribute => $nestedData]);
                        $relation[] = (new $nested['class'])($paramsForCreate);
                    }
                }
            } else {
                $relation = (new $nested['class'])($params);
            }
        }
        return $this->$attribute = $relation;
    }

    protected function loadNested($data)
    {
        foreach ($this->_nested as $attribute => $nested) {
            if ($nested['relation'] == self::RELATION_HAS_MANY) {
                $models = $this->$attribute;
                $model = reset($models);
                $formName = $model->formName();

                if (isset($data[$formName])) {
                    $fieldsCount = count($data[$formName]);
                    $models = [];
                    for ($i = 0; $i < $fieldsCount; $i++) {
                        $models[] = Yii::createObject($nested['class']);
                    }
                    static::loadMultiple($models, $data, $formName);
                }
                $this->$attribute = $models;
            } else {
                $model = $this->$attribute;
                $this->$attribute->load($data, $model->formName());
            }
        }
    }

    protected function validateNested()
    {
        $result = true;
        foreach ($this->_nested as $attribute => $nested) {
            if ($nested['relation'] == self::RELATION_HAS_MANY) {
                $models = $this->$attribute;
                $result = $result && static::validateMultiple($models);
            } else {
                $model = $this->$attribute;
                $result = $result && $model->validate();
            }
        }
        return $result;
    }

    protected function executeNested($attribute, $params = [])
    {
        $result = null;
        if ($nested = $this->_nested[$attribute]) {
            if ($nested['relation'] == self::RELATION_HAS_MANY) {
                $models = $this->$attribute;
                $result = [];
                foreach ($models as $model) {
                    $model->runPrepare($params);

                }

                static::loadMultiple($models, $this->interactionData);

                foreach ($models as $model) {
                    if ($model->validate()) {
                        $result[] = $model->internalExecute();
                    } else {
                        $this->nestedValid = false;
                    }
                }
            } else {
                $model = $this->$attribute;
                $model->runPrepare($params);
                if ($this->nestedValid = $model->validate()) {
                    $result = $model->internalExecute();
                }
            }
        }
        return $result;
    }
}
