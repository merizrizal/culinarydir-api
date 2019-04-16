<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use Faker\Factory;
use core\models\TransactionSession;
use core\models\TransactionCanceledByDriver;
use core\models\TransactionSessionDelivery;
use sycomponent\Tools;

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
                
                $result['message'] = 'Order Detail tidak ditemukan';
            }
        } else {
            
            $result['message'] = 'Order ID tidak boleh kosong';
        }
            
        return $result;
    }
    
    public function actionUploadReceipt()
    {
        $flag = false;
        
        $result = [];
        
        $transaction = Yii::$app->db->beginTransaction();
        
        if (!empty(($post = Yii::$app->request->post())) && !empty($post['order_id'])) {
            
            $modelTransactionSession = TransactionSession::find()
                ->joinWith(['transactionSessionDelivery'])
                ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                ->one();
            
            if (!empty($modelTransactionSession->transactionSessionDelivery)) {
                
                $image = Tools::uploadFileWithoutModel('/img/transaction_session/', 'image', $post['order_id'], '-AD');
                
                if (($flag = !empty($image))) {
                    
                    $modelTransactionSessionDelivery = $modelTransactionSession->transactionSessionDelivery;
                    $modelTransactionSessionDelivery->image = $image;
                    
                    if (($flag = $modelTransactionSessionDelivery->save())) {
                        
                        if (!($flag = $this->updateStatusOrder($modelTransactionSession, 'Upload-Receipt'))) {
                            
                            $result['message'] = 'Gagal Update Status Order';
                        }
                    } else {
                        
                        $result['error'] = $modelTransactionSessionDelivery->getErrors();
                    }
                } else {
                    
                    $result['message'] = 'Gagal Upload Resi';
                }
            } else {
                
                $result['message'] = 'Order ID tidak ditemukan';
            }
        } else {
            
            $result['message'] = 'Order ID tidak boleh kosong';
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['message'] = 'Upload Resi Berhasil';
            
            $transaction->commit();
        } else {
            
            $result['success'] = false;
            
            $transaction->rollBack();
        }
        
        return $result;
    }
    
    public function actionConfirmPrice()
    {
        $flag = false;
        
        $result = [];
        
        $transaction = Yii::$app->db->beginTransaction();
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            if (!empty($post['order_id'])) {
            
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                if (!empty($modelTransactionSession)) {
                    
                    if (!empty($post['total_price'])) {
                    
                        $modelTransactionSession->total_price = $post['total_price'];
    
                        if (($flag = $modelTransactionSession->save())) {
                            
                            if (!($flag = $this->updateStatusOrder($modelTransactionSession, 'Confirm-Price'))) {
                                
                                $result['message'] = 'Gagal Update Status Order';
                            }
                        } else {
                            
                            $result['message'] = 'Konfirmasi Harga Gagal';
                            $result['error'] = $modelTransactionSession->getErrors();
                        }
                    } else {
                        
                        $result['message'] = 'Total price tidak boleh kosong';
                    }
                } else {
                    
                    $result['message'] = 'Order ID tidak ditemukan';
                }
            } else {
                
                $result['message'] = 'Order ID tidak boleh kosong';
            }
        } else {
            
            $result['message'] = 'Parameter yang dibutuhkan tidak boleh kosong';
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['message'] = 'Konfirmasi Harga Berhasil';
            
            $transaction->commit();
        } else {
            
            $result['success'] = false;
            
            $transaction->rollBack();
        }
        
        return $result;
    }
    
    public function actionTakeOrder()
    {
        $flag = false;
        
        $result = [];
        
        $transaction = Yii::$app->db->beginTransaction();
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            if (!empty($post['order_id'])) {
                
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                if (!empty($modelTransactionSession)) {
                    
                    if (!empty($post['driver_user_id'])) {
                        
                        $faker = Factory::create();
                        
                        $modelTransactionSessionDelivery = new TransactionSessionDelivery();
                        $modelTransactionSessionDelivery->transaction_session_id = $modelTransactionSession->id;
                        $modelTransactionSessionDelivery->driver_id = $post['driver_user_id'];
                        $modelTransactionSessionDelivery->total_distance = $faker->randomNumber(2);
                        $modelTransactionSessionDelivery->total_delivery_fee = $faker->randomNumber(6);
                        
                        if (($flag = $modelTransactionSessionDelivery->save())) {
                            
                            if (!($flag = $this->updateStatusOrder($modelTransactionSession, 'Take-Order'))) {
                                
                                $result['message'] = 'Gagal Update Status Order';
                            }
                        } else {
                            
                            $result['message'] = 'Ambil Pesanan Gagal';
                            $result['error'] = $modelTransactionSessionDelivery->getErrors();
                        }
                    } else {
                        
                        $result['message'] = 'ID Driver tidak boleh kosong';
                    }
                } else {
                    
                    $result['message'] = 'Order ID tidak ditemukan';
                }
            } else {
                
                $result['message'] = 'Order ID tidak boleh kosong';
            }
        } else {
            
            $result['message'] = 'Parameter yang dibutuhkan tidak boleh kosong';
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['message'] = 'Ambil Pesanan Berhasil';
            
            $transaction->commit();
        } else {
            
            $result['success'] = false;
            
            $transaction->rollBack();
        }
        
        return $result;
    }
    
    public function actionCancelOrder()
    {
        $flag = false;
        
        $result = [];
        
        $transaction = Yii::$app->db->beginTransaction();
        
        if (!empty(($post = Yii::$app->request->post()))) {
            
            if (!empty($post['order_id'])) {
                
                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->one();
                
                if (!empty($modelTransactionSession)) {
                    
                    if (!empty($post['driver_user_id'])) {
                    
                        $modelTransactionCanceledByDriver = new TransactionCanceledByDriver();
                        
                        $modelTransactionCanceledByDriver->transaction_session_id = $modelTransactionSession->id;
                        $modelTransactionCanceledByDriver->order_id = $modelTransactionSession->order_id;
                        $modelTransactionCanceledByDriver->driver_id = $post['driver_user_id'];
                        
                        if (($flag = $modelTransactionCanceledByDriver->save())) {
                            
                            if (!($flag = $this->updateStatusOrder($modelTransactionSession, 'Cancel'))) {
                                
                                $result['message'] = 'Gagal Update Status Order';
                            }
                        } else {
                            
                            $result['message'] = 'Cancel Order Gagal';
                            $result['error'] = $modelTransactionCanceledByDriver->getErrors();
                        }
                    } else {
                        
                        $result['message'] = 'ID Driver tidak boleh kosong';
                    }
                } else {
                    
                    $result['message'] = 'Order ID tidak ditemukan';
                }
            } else {
                
                $result['message'] = 'Order ID kosong';
            }
        } else {
            
            $result['message'] = 'Parameter yang dibutuhkan tidak ada';
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['message'] = 'Cancel Order Berhasil';
            
            $transaction->commit();
        } else {
            
            $result['success'] = false;
            
            $transaction->rollBack();
        }
        
        return $result;
    }
    
    public function actionSendOrder()
    {
        $result = [];
        
        $result['success'] = false;
        
        if (!empty(Yii::$app->request->post()['order_id'])) {
        
            $result['success'] = $this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Send-Order');
        }
        
        return $result;
    }
    
    public function actionFinishOrder()
    {
        $result = [];
        
        $result['success'] = false;
        
        if (!empty(Yii::$app->request->post()['order_id'])) {
            
            $result['success'] = $this->updateStatusOrder(Yii::$app->request->post()['order_id'], 'Finish');
        }
        
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
