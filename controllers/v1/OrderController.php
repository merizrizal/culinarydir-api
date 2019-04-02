<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use core\models\TransactionSession;
use core\models\TransactionItem;

class OrderController extends \yii\rest\Controller
{
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'get-order-header' => ['POST'],
                        'get-order-detail' => ['POST']
                    ],
                ],
            ]);
    }
    
    public function actionGetOrderHeader()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $modelTransactionSession = TransactionSession::find()
            ->andFilterWhere(['ilike', 'order_id', $post['order_id'] . '_'])
            ->andFilterWhere(['driver_username' => $post['driver_username']])
            ->asArray()->all();
        
        if (!empty($modelTransactionSession)) {
            
            $result['success'] = true;
            
            foreach ($modelTransactionSession as $i => $dataTransactionSession) {
                
                $result[$i] = [];
                
                $result[$i]['user_ordered'] = $dataTransactionSession['user_ordered'];
                $result[$i]['business_id'] = $dataTransactionSession['business_id'];
                $result[$i]['note'] = $dataTransactionSession['note'];
                $result[$i]['total_price'] = $dataTransactionSession['total_price'];
                $result[$i]['total_amount'] = $dataTransactionSession['total_amount'];
                $result[$i]['total_distance'] = $dataTransactionSession['total_distance'];
                $result[$i]['total_delivery_fee'] = $dataTransactionSession['total_delivery_fee'];
                $result[$i]['order_status'] = $dataTransactionSession['order_status'];
            }
        } else {
            
            $result['success'] = false;
            $result['message'] = 'Order Header tidak ditemukan';
        }
        
        return $result;
    }
    
    public function actionGetOrderDetail()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'transactionItems',
                'transactionItems.businessProduct'
            ])
            ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
            ->asArray()->one();
        
        if (!empty($modelTransactionSession)) {
            
            $result['success'] = true;
            
            foreach ($modelTransactionSession['transactionItems'] as $i => $dataTransactionItem) {
                
                $result[$i] = [];
                
                $result[$i]['menu'] = $dataTransactionItem['businessProduct']['name'];
                $result[$i]['note'] = $dataTransactionItem['note'];
                $result[$i]['price'] = $dataTransactionItem['price'];
                $result[$i]['amount'] = $dataTransactionItem['amount'];
            }
        } else {
            
            $result['success'] = false;
            $result['message'] = 'Order Detail tidak ditemukan';
        }
            
        return $result;
    }
}