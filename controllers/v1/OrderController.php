<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use Faker\Factory;
use core\models\TransactionSession;
use core\models\TransactionCanceledByDriver;
use core\models\TransactionSessionDelivery;

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
                        'get-order-detail' => ['POST'],
                        'upload-receipt' => ['POST'],
                        'confirm-price' => ['POST'],
                        'take-order' => ['POST'],
                        'cancel-order' => ['POST'],
                        'send-order' => ['POST'],
                        'finish-order' => ['POST'],
                        'calculate-delivery-fee' => ['POST']
                    ],
                ],
            ]);
    }
    
    public function actionGetOrderDetail()
    {
        $result = [];
        
        $result['success'] = false;
        
        if (!empty(Yii::$app->request->post()['order_id'])) {
        
            $modelTransactionSession = TransactionSession::find()
                ->joinWith([
                    'transactionItems',
                    'transactionItems.businessProduct'
                ])
                ->andWhere(['ilike', 'order_id', Yii::$app->request->post()['order_id'] . '_'])
                ->asArray()->one();
            
            if (!empty($modelTransactionSession)) {
                
                $result['detail'] = [];
                
                foreach ($modelTransactionSession['transactionItems'] as $i => $dataTransactionItem) {
                    
                    $result['detail'][$i] = [];
                    
                    $result['detail'][$i]['menu'] = $dataTransactionItem['businessProduct']['name'];
                    $result['detail'][$i]['price'] = $dataTransactionItem['price'];
                    $result['detail'][$i]['amount'] = $dataTransactionItem['amount'];
                    $result['detail'][$i]['note'] = $dataTransactionItem['note'];
                }
                
                $result['success'] = true;
                $result['total_price'] = $modelTransactionSession['total_price'];
                $result['total_amount'] = $modelTransactionSession['total_amount'];
            } else {
                
                $result['message'] = 'Parameter order_id tidak boleh kosong';
            }
        } else {
            
            $result['message'] = 'Order Detail tidak ditemukan';
        }
            
        return $result;
    }
    
    public function actionUploadReceipt()
    {
        $flag = false;
        
        $result = [];
        
        $result['success'] = false;
        $result['message'] = 'Order ID tidak ada';
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            $modelTransactionSession = TransactionSession::find()
                ->joinWith(['transactionSessionDelivery'])
                ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                ->one();
            
            $file = UploadedFile::getInstanceByName('image');
            
            $result['message'] = 'Gagal Upload Resi';
            
            if (!empty($modelTransactionSession->transactionSessionDelivery) && $file) {
                
                $transaction = Yii::$app->db->beginTransaction();
                
                $fileName = 'AD-' . $post['order_id'] . '.' . $file->extension;
                
                if (($flag = $file->saveAs(Yii::getAlias('@uploads') . '/img/transaction_session/' . $fileName))) {
                    
                    $modelTransactionSessionDelivery = $modelTransactionSession->transactionSessionDelivery;
                    $modelTransactionSessionDelivery->image = $fileName;
                    
                    $flag = $modelTransactionSessionDelivery->save();
                    
                    if ($flag) {
                        
                        if ($this->updateStatusOrder($modelTransactionSession, 'Upload-Receipt')) {
                            
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
        }
        
        return $result;
    }
    
    public function actionConfirmPrice()
    {
        $flag = false;
        
        $result = [];
        
        $result['success'] = false;
        $result['message'] = 'Order ID tidak ditemukan';
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            if (!empty($post['order_id'])) {
            
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                if (!empty($modelTransactionSession)) {
                    
                    $transaction = Yii::$app->db->beginTransaction();
                    
                    $modelTransactionSession->total_price = !empty($post['total_price']) ? $post['total_price'] : $modelTransactionSession->total_price;
                    $flag = $modelTransactionSession->save();
                    
                    $result['message'] = 'Konfirmasi Harga Gagal';
                    
                    if ($flag) {
                        
                        if ($this->updateStatusOrder($modelTransactionSession, 'Confirm-Price')) {
                            
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
                }
            }
        }
        
        return $result;
    }
    
    public function actionTakeOrder()
    {
        $flag = false;
        
        $result = [];
        
        $result['success'] = false;
        $result['message'] = 'Order ID dan ID driver tidak ditemukan';
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            $result['message'] = 'Order ID tidak ditemukan';
            
            if (!empty($post['order_id'])) {
                
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                if (!empty($modelTransactionSession)) {
                    
                    $transaction = Yii::$app->db->beginTransaction();
                    
                    if (!empty($post['driver_user_id'])) {
                        
                        $faker = Factory::create();
                        
                        $modelTransactionSessionDelivery = new TransactionSessionDelivery();
                        $modelTransactionSessionDelivery->transaction_session_id = $modelTransactionSession->id;
                        $modelTransactionSessionDelivery->driver_id = $post['driver_user_id'];
                        $modelTransactionSessionDelivery->total_distance = $faker->randomNumber(2);
                        $modelTransactionSessionDelivery->total_delivery_fee = $faker->randomNumber(6);
                        
                        $flag = $modelTransactionSessionDelivery->save();
                        
                        $result['message'] = 'Ambil Pesanan Gagal';
                        
                        if ($flag) {
                            
                            if ($this->updateStatusOrder($modelTransactionSession, 'Take-Order')) {
                                
                                $result['success'] = true;
                                $result['message'] = 'Ambil Pesanan Berhasil';
                                
                                $transaction->commit();
                            } else {
                                
                                $transaction->rollBack();
                            }
                        } else {
                            
                            $result['error'] = $modelTransactionSessionDelivery->getErrors();
                            
                            $transaction->rollBack();
                        }
                    } else {
                        
                        $result['message'] = 'ID driver kosong';
                    }
                }
            }
        }
        
        return $result;
    }
    
    public function actionCancelOrder()
    {
        $flag = false;
        
        $result = [];
        $result['success'] = false;
        $result['message'] = 'Order ID dan ID driver tidak ditemukan';
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            $transaction = Yii::$app->db->beginTransaction();
            
            if (!empty($post['order_id']) && !empty($post['driver_user_id'])) {
                
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                $result['message'] = 'Order ID tidak ditemukan';
                    
                if (!empty($modelTransactionSession)) {
                        
                    $modelTransactionCanceledByDriver = new TransactionCanceledByDriver();
                    
                    $modelTransactionCanceledByDriver->transaction_session_id = $modelTransactionSession->id;
                    $modelTransactionCanceledByDriver->order_id = $modelTransactionSession->order_id;
                    $modelTransactionCanceledByDriver->driver_id = $post['driver_user_id'];
                    
                    $flag = $modelTransactionCanceledByDriver->save();
                    
                    $result['message'] = 'Cancel Order Gagal';
                    
                    if ($flag) {
                        
                        if ($this->updateStatusOrder($modelTransactionSession, 'Cancel')) {
                            
                            $result['success'] = true;
                            $result['message'] = 'Cancel Order Berhasil';
                            
                            $transaction->commit();
                        } else {
                            
                            $transaction->rollback();
                        }
                    } else {
                        
                        $result['error'] = $modelTransactionCanceledByDriver->getErrors();
                        
                        $transaction->rollBack();
                    }
                }
            }
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
        if (!empty($orderId)) {
        
            if (is_string($orderId)) {
            
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $orderId . '_'])
                    ->one();
            } else {
                
                $modelTransactionSession = $orderId;
            }
            
            if (!empty($modelTransactionSession)) {
                
                $modelTransactionSession->status = $status;
                
                return $modelTransactionSession->save();
            }
        }
        
        return false;
    }
}
