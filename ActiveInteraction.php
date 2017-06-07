<?php

namespace bigdropinc\interactions;

use bigdropinc\interactions\errors\InteractionInvalidError;
use ReflectionMethod;
use Yii;
use yii\base\Model;
use yii\helpers\StringHelper;

/**
 * Class ActiveInteraction
 * @package backend\interactions
 */
abstract class ActiveInteraction extends Model
{

    const RELATION_HAS_ONE = 'hasOne';
    const RELATION_HAS_MANY = 'hasMany';

    const EVENT_BEFORE_LOAD = 'beforeLoad';
    const EVENT_AFTER_LOAD = 'afterLoad';
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE = 'afterExecute';
    const EVENT_ON_SUCCESS = 'onSuccess';
    const EVENT_ON_ERRORS = 'onErrors';

    public $waitForLoad;

    protected $result, $_nested = [], $nestedValid = true, $executed = false;

    protected $_attributes;

    private $interactionData;

    /**
     * Realisation of your business logic
     *
     * @return mixed
     */
    abstract protected function execute();

    /**
     * Method to simply run and execute interaction
     * It should be call when your logic depends only from incoming params and
     * don't need any initialization
     *
     * @param $params
     * @return ActiveInteraction
     */
    public static final function run($params)
    {
        $config = [];
        if (isset($params['config'])) {
            $config = $params['config'];
            unset($params['config']);
        }

        $config['waitForLoad'] = false;

        return static::create($config)->runExecution($params);
    }

    /**
     * Same as a run, but raise an exception on execution fault
     *
     * @param $params
     * @param array $config
     * @return ActiveInteraction
     * @throws InteractionInvalidError
     */
    public static final function forceRun($params, $config = [])
    {
        $interaction = static::run($params, $config);
        if ($interaction->hasErrors()) {
            throw new InteractionInvalidError($interaction);
        }
        return $interaction;
    }

    /**
     * Method to initialize interaction.
     * Should be call when your want to separate initialization of interaction from it execution
     * To config should be send default data that not depends of incoming params
     * Config will use to create object using DI
     * Config may contain a params field. It will be passed to a prepare method
     *
     * @param array $params
     * @return ActiveInteraction
     */
    public static final function create($config = [])
    {
        if (!isset($config['waitForLoad'])) {
            $config['waitForLoad'] = true;
        }
        $config['class'] = static::class;

        $prepareMethodName = static::getPrepareMethodName();
        $prepareParams = [];
        if (method_exists(static::className(), $prepareMethodName)) {
            list($prepareParams, $config) = static::extractPrepareParams($prepareMethodName, $config);
        }

        /**
         * @var $interaction ActiveInteraction
         */
        $interaction = Yii::createObject($config);

        $interaction->runPrepare(array_values($prepareParams), $prepareMethodName);

        return $interaction;
    }

    protected function createNested($attribute, $params, $nestedAttribute, $additionalParamsForCreate = [])
    {
        $relation = null;
        if ($nested = $this->_nested[$attribute]) {
            if ($nested['relation'] == self::RELATION_HAS_MANY) {
                $relation = [];
                foreach ($params as $nestedData) {
                    $paramsForCreate = array_merge($additionalParamsForCreate, [$nestedAttribute => $nestedData]);
                    $relation[] = call_user_func([$nested['class'], 'create'], $paramsForCreate);
                }
            } else {
                $relation = call_user_func([$nested['class'], $params]);
            }
        }
        return $this->$attribute = $relation;
    }

//    protected function getNestedModel($class, )

    /**
     * This method will run on object initialize. It try to find a "prepareMethod" and if not simply run prepare()
     *
     * @param $prepareParams
     */
    protected function runPrepare($params, $method = null)
    {
        if(is_null($method)){
            $method = static::getPrepareMethodName();
            if(!method_exists($this, $method)){
                $method = 'prepare';
            }
        }
        if(method_exists($this, $method)){
            call_user_func_array([$this, $method], $params);
        }
    }

    protected static function extractPrepareParams($methodName, $prepareParams)
    {
        $r = new ReflectionMethod(static::className(), $methodName);
        $params = $r->getParameters();
        $methodParams = [];
        foreach ($params as $param) {
            $name = $param->getName();
            if(isset($prepareParams[$name])){
                $methodParams[$param->getName()] = $prepareParams[$name];
                unset($prepareParams[$param->getName()]);
            }
        }
        return [$methodParams, $prepareParams];
    }

    /**
     * Method to run an execution existing active interaction instance
     * The steps for interaction to be run: load, validate execute.
     * All this steps get before and after callbacks.
     * If it calls after create method and not get passed params it will returns $this
     *
     * @param $params
     * @return $this
     */
    public function runExecution($params)
    {
        if ($this->waitForLoad && empty($params)) {
            return $this;
        }

        $this->load($params);

        if ($this->validate()) {
            $this->result = $this->internalExecute();
        };

        return $this;
    }

    protected function internalExecute()
    {
        $result = null;
        if ($this->beforeExecute() !== false) {

            $result = $this->execute();
            $this->executed = true;
            $this->afterExecute();
            $this->isSuccess() ? $this->onSuccess() : $this->onErrors();
        };
        return $result;
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

    public function load($data, $formName = null)
    {
        if (($result = $this->beforeLoad()) !== false) {

            if (isset($data[0])) {
                $data = array_merge(array_shift($data), $data);
            }

            if (isset($data[$this->formName()])) {
                $requestParams = $data[$this->formName()];
                unset($data[$this->formName()]);
                $data = array_merge($data, $requestParams);
            }

            $result = parent::load($data, '');
            $this->loadNested($data);
            $this->afterLoad();
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

                foreach ($models as $model){
                    if ($model->validate()) {
                        $result[] = $model->internalExecute();
                    } else {
                        $this->nestedValid = false;
                    }
                }
            } else {
                $model = $this->$attribute;
                $model->runPrepare($params);
                if ($this->nestedValid = $this->validate()) {
                    $result = $model->internalExecute();
                }
            }
        }
        return $result;
    }

    protected function loadNested($data)
    {
        $this->interactionData = $data;
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


    public function isSuccess()
    {
        return !$this->hasErrors() && $this->nestedValid && $this->executed;
    }


    public function attributes()
    {
        return array_merge(array_keys($this->_attributes), parent::attributes());
    }

    public function init()
    {
        $this->_attributes = $this->getAttributesFromRules();
        $this->_attributes = array_merge($this->_attributes, $this->getNestedModels());
        parent::init();
    }

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

    public function __get($name)
    {
        if (array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        }
        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        if (isset($this->_attributes) && array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name] = $value;
        }
        return parent::__set($name, $value);
    }


    protected function getAttributesFromRules()
    {
        return $this->parseRules($this->rules());
    }

    protected function parseRules($rules)
    {
        $attributes = [];
        foreach ($rules as $rule) {
            $attr = reset($rule);
            if (!is_array($attr)) {
                $attr = [$attr => null];
            } else {
                $parsedAttributes = [];
                foreach ($attr as $attrName) {
                    $parsedAttributes[$attrName] = null;
                }
                $attr = $parsedAttributes;
            }

            $attributes = array_merge($attributes, $attr);
        }
        return $attributes;
    }


    protected static function getPrepareMethodName()
    {
        return 'prepareFor' . StringHelper::basename(static::className());

    }

    public function getResult()
    {
        return $this->result;
    }

    public function isValid()
    {
        return !$this->hasErrors();
    }

    protected function beforeExecute()
    {
        $this->trigger(static::EVENT_BEFORE_EXECUTE);
    }

    protected function afterExecute()
    {
        $this->trigger(static::EVENT_AFTER_EXECUTE);
    }

    protected function beforeLoad()
    {
        $this->trigger(static::EVENT_BEFORE_LOAD);
    }

    protected function afterLoad()
    {
        $this->trigger(static::EVENT_AFTER_LOAD);
    }

    protected function onSuccess()
    {
        $this->trigger(static::EVENT_ON_SUCCESS);
    }

    protected function onErrors()
    {
        $this->trigger(static::EVENT_ON_ERRORS);
    }

    protected function prepare($params)
    {
        return true;
    }


    protected function nested()
    {
        return [];
    }

}