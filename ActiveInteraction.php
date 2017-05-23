<?php
namespace bigdropinc\interactions;

use backend\interactions\errors\InteractionInvalidError;
use bigdropinc\take\exceptions\RecordInvalidException;
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

    public $waitForLoad;

    protected $result, $executed = false, $_nested = [];

    protected $_attributes;

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
        if(isset($params['config'])){
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
        if($interaction->hasErrors()){
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
     * @param array $config
     * @return ActiveInteraction
     */
    public static final function create($config = [])
    {
        $params = [];
        if(isset($config['params'])){
            $params = $config['params'];
            unset($config['params']);
        }

        if(!isset($config['waitForLoad'])){
            $config['waitForLoad'] = true;
        }
        $config['class'] = static::class;
        /**
         * @var $interaction ActiveInteraction
         */
        $interaction = Yii::createObject($config);

        if(!empty($params)){
            $interaction->setAttributes($params);
        }
        $interaction->runPrepare($params);

        return $interaction;
    }

    /**
     * This method will run on object initialize. It try to find a "prepareMethod" and if not simply run prepare()
     *
     * @param $prepareParams
     * @return bool|mixed
     */
    protected function runPrepare($prepareParams)
    {
        $prepareMethodName = $this->getPrepareMethodName();
        if(method_exists(static::className(), $prepareMethodName)){
            return call_user_func_array([$this, $prepareMethodName], $prepareParams);
        } else {
            return $this->prepare($prepareParams);
        }
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
        if($this->waitForLoad && empty($params)){
            return $this;
        }

        $this->load($params);

        if($this->validate()){
            $this->result = $this->internalExecute();
        };

        return $this;
    }

    protected function internalExecute()
    {
        $result = null;
        if($this->beforeExecute() !== false){

            $result = $this->execute();

            $this->executed = true;
            $this->afterExecute();
        };
        return $result;
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        $result = parent::validate($attributeNames, $clearErrors);
        $nestedResult = true;
        if($result){
            $nestedResult = $this->validateNested();
        }

        return $result && $nestedResult;
    }

    protected function validateNested()
    {
        $result = true;
        foreach ($this->_nested as $attribute => $nested){
            if($nested['relation'] == self::RELATION_HAS_MANY){
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
        if(($result = $this->beforeLoad()) !== false){

            if(isset($data[0])){
                $data = array_merge(array_shift($data), $data) ;
            }

            if(isset($data[$this->formName()])){
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

    protected function executeNested($attribute)
    {
        $result = null;
        if($nested = $this->_nested[$attribute]){
            if($nested['relation'] == self::RELATION_HAS_MANY){
                $models = $this->$attribute;
                $result = [];
                foreach ($models as $model){
                    $result[] = $model->internalExecute();
                }
            } else {
                $model = $this->$attribute;
                $result = $model->internalExecute();
            }
        }
        return $result;
    }

    protected function loadNested($data)
    {
        foreach ($this->_nested as $attribute => $nested){

            if($nested['relation'] == self::RELATION_HAS_MANY){
                $models = $this->$attribute;
                $model = reset($models);
                $formName = $model->formName();

                if(isset($data[$formName])){
                    $fieldsCount = count($data[$formName]);
                    $models = [];
                    for($i = 0; $i < $fieldsCount; $i++){
                        $models[] = call_user_func([$nested['class'], 'create']);
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



    public function isExecuted()
    {
        return $this->executed && !$this->hasErrors();
    }


    public function attributes()
    {
        return array_merge( array_keys($this->_attributes), parent::attributes() );
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
        foreach ($this->nested() as $nestedModel){
            $attribute = $nestedModel[0];
            $model = $nestedModel[1];
            $object = call_user_func([$model, 'create'], []);
            if($nestedModel['relation'] == self::RELATION_HAS_MANY){
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
        if(array_key_exists($name, $this->_attributes)){
            return $this->_attributes[$name];
        }
        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        if(isset($this->_attributes) && array_key_exists($name, $this->_attributes)){
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
        foreach ($rules as $rule){
            $attr = reset($rule);
            if(!is_array($attr)){
                $attr = [$attr => null];
            } else {
                $parsedAttributes = [];
                foreach ($attr as $attrName){
                    $parsedAttributes[$attrName] = null;
                }
                $attr = $parsedAttributes;
            }

            $attributes = array_merge($attributes, $attr);
        }
        return $attributes;
    }


    protected function getPrepareMethodName()
    {
        return 'prepareFor'.StringHelper::basename(static::className());

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

    protected function prepare($params)
    {
        return true;
    }


    protected function nested()
    {
        return [];
    }

}