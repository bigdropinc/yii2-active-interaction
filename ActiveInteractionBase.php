<?php
/**
 * Created by PhpStorm.
 * User: vadim
 * Date: 18.06.17
 * Time: 19:20
 */

namespace bigdropinc\interactions;

use bigdropinc\interactions\errors\InteractionInvalidError;
use Yii;
use yii\base\Model;
use yii\helpers\StringHelper;


abstract class ActiveInteractionBase extends Model implements InteractionEventsInterface
{

    use InteractionEventsTrait;

    public $waitForRunParams = true;

    protected $result, $executed = false, $_attributes, $isPrepareWasRun = false;

    /**
     * @param $params
     * @return $this
     */
    function __invoke($params = [])
    {
        $this->runPrepare($params);

        return $this;
    }

    /**
     * This method will run on object initialize. It try to find a "prepareMethod" and if not simply run prepare()
     *
     * @param $prepareParams
     */
    protected function runPrepare($params, $method = null)
    {
        if($this->isPrepareWasRun){
            return false;
        }

        if (is_null($method)) {
            $method = static::getPrepareMethodName();
            if (!method_exists($this, $method)) {
                $method = 'prepare';
                return call_user_func([$this, $method], $params);
            }
        }
        if (method_exists($this, $method)) {
            call_user_func_array([$this, $method], $params);
        }

        return $this->isPrepareWasRun = true;
    }

    protected static function getPrepareMethodName()
    {
        return 'prepareFor' . StringHelper::basename(static::className());

    }

    public function forceRun($params)
    {
        $this->run($params);
        if ($this->hasErrors()) {
            throw new InteractionInvalidError($this);
        }
        return $this;
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
    public function run($params)
    {
        if ($this->waitForRunParams && empty($params)) {
            return $this;
        }

        $this->runPrepare([]);

        $this->load($params);

        if ($this->validate()) {
            $this->result = $this->internalExecute();
        };

        return $this;
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

            $result = $this->loadInternal($data);
            $this->afterLoad();
        }
        return $result;
    }

    protected function loadInternal($data)
    {
        return parent::load($data, '');
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

    /**
     * Realisation of your business logic
     *
     * @return mixed
     */
    abstract protected function execute();

    public function isSuccess()
    {
        return !$this->hasErrors() && $this->executed;
    }

    public function attributes()
    {
        return array_keys($this->_attributes);
    }

    public function init()
    {
        $this->initAttributes();
        parent::init();
    }

    protected function initAttributes()
    {
        $this->_attributes = $this->getAttributesFromRules();
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

    public function getResult()
    {
        return $this->result;
    }

    public function isValid()
    {
        return !$this->hasErrors();
    }

    protected function prepare($params)
    {
        return true;
    }

}