<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use core\models\TransactionSession;

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
                        'get-order-header' => ['POST']
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
}