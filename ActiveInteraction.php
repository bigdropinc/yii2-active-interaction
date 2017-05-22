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

    const EVENT_BEFORE_LOAD = 'beforeLoad';
    const EVENT_AFTER_LOAD = 'afterLoad';
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE = 'afterExecute';

    public $waitForLoad;

    protected $result, $executed = false;

    private $_attributes;

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
            if($this->beforeExecute() !== false){

                $this->result = $this->execute();

                $this->executed = true;
                $this->afterExecute();
            };
        };

        return $this;
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
            $this->afterLoad();
        }
        return $result;
    }


    public function isExecuted()
    {
        return $this->executed && !$this->hasErrors();
    }


    public function attributes()
    {
        return array_merge( array_keys($this->_attributes), parent::attributes() );
    }

    public final function init()
    {
        $this->_attributes = [];
        foreach($this->getAttributesFromRules() as $attribute)
        {
            $this->_attributes[$attribute] = null;
        }
        parent::init();
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
        $rules = $this->rules();
        $attributes = [];
        foreach ($rules as $rule){
            $attr = reset($rule);
            if(!is_array($attr)){
                $attr = [$attr];
            }
            $attributes = array_merge($attributes, $attr);
        }
        return array_unique($attributes);
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

}