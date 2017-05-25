<?php
/**
 * Created by PhpStorm.
 * User: vadim
 * Date: 25.05.17
 * Time: 22:30
 */

namespace bigdropinc\interactions\behaviors;


use bigdropinc\interactions\ActiveInteraction;
use yii\base\Behavior;
use yii\db\Transaction;

class TransactionWrapBehavior extends Behavior
{
    public $transactionIsolationLevel;
    /**
     * @var Transaction
     */
    private $transaction;

    public function events()
    {
        return [
            ActiveInteraction::EVENT_BEFORE_EXECUTE => 'initTransaction',
            ActiveInteraction::EVENT_ON_SUCCESS => 'commitTransaction',
            ActiveInteraction::EVENT_ON_ERRORS => 'rollBackTransaction'
        ];
    }

    public function initTransaction()
    {
        $this->transaction = \Yii::$app->db->beginTransaction($this->transactionIsolationLevel);
    }

    public function commitTransaction()
    {
        $this->transaction->commit();
    }

    public function rollBackTransaction()
    {
        $this->transaction->rollBack();
    }
}