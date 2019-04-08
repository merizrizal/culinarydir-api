<?php

namespace api\controllers\v1;

use Yii;
use frontend\components\AddressType;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use core\models\TransactionSession;
use core\models\TransactionCanceled;

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
                        'get-order-detail' => ['POST'],
                        'upload-receipt' => ['POST'],
                        'confirm-price' => ['POST'],
                        'take-order' => ['POST'],
                        'cancel-order' => ['POST'],
                        'send-order' => ['POST'],
                        'finish-order' => ['POST'],
                        'calculate-delivery-fee' => ['POST'],
                        'update-driver-position' => ['POST']
                    ],
                ],
            ]);
    }
    
    public function actionGetOrderHeader()
    {
        $post = Yii::$app->request->post();
        
        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'business',
                'business.businessLocation',
                'userOrdered',
                'userOrdered.userPerson.person'
            ])
            ->andFilterWhere(['ilike', 'order_id', $post['order_id'] . '_'])
            ->andFilterWhere(['driver_username' => $post['driver_username']])
            ->asArray()->all();
        
        $result = [];
        
        if (!empty($modelTransactionSession)) {
            
            $result['success'] = true;
            
            foreach ($modelTransactionSession as $i => $dataTransactionSession) {
                
                $result[$i] = [];
                
                $result[$i]['customer_id'] = $dataTransactionSession['user_ordered'];
                $result[$i]['customer_name'] = $dataTransactionSession['userOrdered']['full_name'];
                $result[$i]['customer_username'] = $dataTransactionSession['userOrdered']['username'];
                $result[$i]['customer_phone'] = $dataTransactionSession['userOrdered']['userPerson']['person']['phone'];
                $result[$i]['customer_address'] = $dataTransactionSession['userOrdered']['userPerson']['person']['address'];
                
                $result[$i]['business_id'] = $dataTransactionSession['business_id'];
                $result[$i]['business_name'] = $dataTransactionSession['business']['name'];
                $result[$i]['business_phone'] = $dataTransactionSession['business']['phone3'];
                $result[$i]['business_location'] = $dataTransactionSession['business']['businessLocation']['coordinate'];
                $result[$i]['business_address'] = 
                    AddressType::widget([
                        'businessLocation' => $dataTransactionSession['business']['businessLocation'],
                        'showDetail' => false
                    ]);
                
                $result['order_id'] = substr($dataTransactionSession['order_id'], 0, 6);
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
        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'transactionItems',
                'transactionItems.businessProduct'
            ])
            ->andWhere(['ilike', 'order_id', Yii::$app->request->post()['order_id'] . '_'])
            ->asArray()->one();
        
        $result = [];
        
        if (!empty($modelTransactionSession)) {
            
            foreach ($modelTransactionSession['transactionItems'] as $i => $dataTransactionItem) {
                
                $result[$i] = [];
                
                $result[$i]['menu'] = $dataTransactionItem['businessProduct']['name'];
                $result[$i]['price'] = $dataTransactionItem['price'];
                $result[$i]['amount'] = $dataTransactionItem['amount'];
                $result[$i]['note'] = $dataTransactionItem['note'];
            }
            
            $result['total_price'] = $modelTransactionSession['total_price'];
            $result['total_amount'] = $modelTransactionSession['total_amount'];
            $result['success'] = true;
        } else {
            
            $result['success'] = false;
            $result['message'] = 'Order Detail tidak ditemukan';
        }
            
        return $result;
    }
    
    public function actionUploadReceipt()
    {
        $post = Yii::$app->request->post();
        $flag = false;
        
        $modelTransactionSession = TransactionSession::find()
            ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
            ->one();
            
        $file = UploadedFile::getInstanceByName('image');
        
        $result = [];
        
        $result['success'] = false;
        $result['message'] = 'Gagal Upload Resi';
        
        if (!empty($modelTransactionSession) && $file) {
            
            $transaction = Yii::$app->db->beginTransaction();
            
            $fileName = 'AD-' . $post['order_id'] . '.' . $file->extension;
            
            if (($flag = $file->saveAs(Yii::getAlias('@uploads') . '/img/transaction_session/' . $fileName))) {
                
                $modelTransactionSession->image = $fileName;
                $flag = $modelTransactionSession->save();
                
                if ($flag) {
                    
                    if ($this->updateStatusOrder($post['order_id'], 'Upload Receipt')) {
                        
                        $result['success'] = true;
                        $result['message'] = 'Upload Resi Berhasil';
                        
                        $transaction->commit();
                    } else {
                        
                        $transaction->rollBack();
                    }
                } else {
                    
                    $result['error'] = $modelTransactionSession->getErrors();
                    
                    $transaction->rollBack();
                }
            }
        }
        
        return $result;
    }
    
    public function actionConfirmPrice()
    {
        $post = Yii::$app->request->post();
        $flag = false;
        
        $modelTransactionSession = TransactionSession::find()
            ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
            ->one();
        
        $result = [];
        
        $result['success'] = false;
        $result['message'] = 'Konfirmasi Harga Gagal';
        
        if (!empty($modelTransactionSession)) {
            
            $transaction = Yii::$app->db->beginTransaction();
            
            $modelTransactionSession->total_price = !empty($post['total_price']) ? $post['total_price'] : $modelTransactionSession->total_price;
            $flag = $modelTransactionSession->save();
            
            if ($flag) {
                
                if ($this->updateStatusOrder($post['order_id'], 'Confirm Price')) {
                    
                    $result['success'] = true;
                    $result['message'] = 'Konfirmasi Harga Berhasil';
                    
                    $transaction->commit();
                } else {
                    
                    $transaction->rollBack();
                }
            } else {
                
                $result['error'] = $modelTransactionSession->getErrors();
                
                $transaction->rollBack();
            }
        } else {
            
            $result['message'] = 'Order ID tidak ditemukan';
        }
        
        return $result;
    }
    
    public function actionTakeOrder()
    {
        $result = [];
        $result['success'] = $this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Take Order');
        
        return $result;
    }
    
    public function actionCancelOrder()
    {
        $post = Yii::$app->request->post();
        
        $flag = false;
        $transaction = Yii::$app->db->beginTransaction();
        
        $modelTransactionCanceled = TransactionCanceled::findOne(['transaction_session_order_id' => $post['order_id']]);
        
        $result = [];
        
        if (!empty($modelTransactionCanceled)) {
            
            $modelTransactionCanceled->driver_username = $post['driver_username'];
        } else {
            
            $newModelTransactionCanceled = new TransactionCanceled();
            
            $newModelTransactionCanceled->transaction_session_order_id = $post['order_id'];
            $newModelTransactionCanceled->driver_username = $post['driver_username'];
        }
        
        $flag = $newModelTransactionCanceled->save();
        
        if ($flag) {
            
            if ($this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Cancel')) {
                
                $result['success'] = true;
                
                $transaction->commit();
            } else {
                
                $result['success'] = false;
                
                $transaction->rollback();
            }
        } else {
            
            $transaction->rollBack();
        }
        
        return $result;
    }
    
    public function actionSendOrder()
    {
        $result = [];
        $result['success'] = $this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Send Order');
        
        return $result;
    }
    
    public function actionFinishOrder()
    {
        $result = [];
        $result['success'] = $this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Finish');
        
        return $result;
    }
    
    public function actionCalculateDeliveryFee()
    {
        
    }
    
    private function updateStatusOrder($orderId, $status)
    {
        $modelTransactionSession = TransactionSession::find()
            ->andWhere(['ilike', 'order_id', $orderId . '_'])
            ->one();
        
        $success = false;
        
        if (!empty($modelTransactionSession)) {
            
            $modelTransactionSession->order_status = $status;
            
            if ($modelTransactionSession->save()) {
                
                $success = true;
            }
        }
        
        return $success;
    }
}