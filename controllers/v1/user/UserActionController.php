<?php

namespace api\controllers\v1\user;

use yii\filters\VerbFilter;
use core\models\TransactionSession;
use core\models\TransactionItem;
use core\models\TransactionSessionDelivery;

class UserActionController extends \yii\rest\Controller
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
                        'reorder' => ['POST'],
                        'get-order-driver' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionReorder()
    {
        $result = [];

        $post = \Yii::$app->request->post();

        $modelTransactionSession = TransactionSession::find()
            ->andWhere(['user_ordered' => $post['user_id']])
            ->andWhere(['status' => 'Open'])
            ->asArray()->one();

        if (!empty($modelTransactionSession)) {

            if ($modelTransactionSession['id'] == $post['id']) {

                $result['redirect'] = true;
                $result['business_id'] = $modelTransactionSession['business_id'];
            } else {

                $result['redirect'] = false;
                $result['message'] = 'Pesan Ulang Gagal' . "\r\n" . 'Silahkan selesaikan pesanan anda terlebih dahulu.' . "\r\n";
            }
        } else {

            $transaction = \Yii::$app->db->beginTransaction();

            $flag = false;

            $oldModelTransaction = TransactionSession::find()
                ->joinWith(['transactionItems'])
                ->andWhere(['transaction_session.id' => $post['id']])
                ->one();
            
            $totalPrice = 0;
            
            foreach ($oldModelTransaction->transactionItems as $dataTransactionItem) {
                
                $totalPrice += $dataTransactionItem->price * $dataTransactionItem->amount;
            }

            $modelTransactionSession = new TransactionSession();
            $modelTransactionSession->user_ordered = $oldModelTransaction->user_ordered;
            $modelTransactionSession->business_id = $oldModelTransaction->business_id;
            $modelTransactionSession->note = !empty($oldModelTransaction->note) ? $oldModelTransaction->note : null;
            $modelTransactionSession->total_price = $totalPrice;
            $modelTransactionSession->total_amount = $oldModelTransaction->total_amount;
            $modelTransactionSession->order_id = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6) . '_' . time();
            $modelTransactionSession->status = 'Open';

            if (($flag = $modelTransactionSession->save())) {

                foreach ($oldModelTransaction->transactionItems as $dataTransactionItem) {

                    $modelTransactionItem = new TransactionItem();
                    $modelTransactionItem->transaction_session_id = $modelTransactionSession->id;
                    $modelTransactionItem->business_product_id = $dataTransactionItem->business_product_id;
                    $modelTransactionItem->note = !empty($dataTransactionItem->note) ? $dataTransactionItem->note : null;
                    $modelTransactionItem->price = $dataTransactionItem->price;
                    $modelTransactionItem->amount = $dataTransactionItem->amount;

                    if (!($flag = $modelTransactionItem->save())) {

                        break;
                    }
                }
            }

            if ($flag) {

                $transaction->commit();

                $result['success'] = true;
                $result['business_id'] = $modelTransactionSession['business_id'];
            } else {

                $transaction->rollBack();

                $result['success'] = false;
                $result['error'] = $modelTransactionSession->getErrors();
                $result['message'] = 'Pesan Ulang Gagal' . "\r\n" . 'Silahkan ulangi kembali proses pemesanan anda' . "\r\n";
            }
        }

        return $result;
    }
    
    public function actionGetOrderDriver()
    {
        $result = [];
        
        $get = \Yii::$app->request->get();
        $result['success'] = false;
        
        if (!empty($get['transaction_session_id'])) {
        
            $modelTransactionSessionDelivery = TransactionSessionDelivery::find()
                ->joinWith([
                    'transactionSession',
                    'driver',
                    'driver.userPerson.person'
                ])
                ->andWhere(['transaction_session_id' => $get['transaction_session_id']])
                ->asArray()->one();
            
            $result['success'] = true;
            $result['status'] = $modelTransactionSessionDelivery['transactionSession']['status'];
            $result['delivery_fee'] = $modelTransactionSessionDelivery['total_delivery_fee'];
            $result['total_price'] = $modelTransactionSessionDelivery['transactionSession']['total_price'];
            $result['grand_total'] = $result['delivery_fee'] + $result['total_price'];
            $result['driver_fullname'] = $modelTransactionSessionDelivery['driver']['full_name'];
            $result['driver_photo'] = $modelTransactionSessionDelivery['driver']['image'];
            $result['driver_phone'] = $modelTransactionSessionDelivery['driver']['userPerson']['person']['phone'];
        } else {
            
            $result['message'] = 'transaction_session_id tidak ditemukan';
        }
        
        return $result;
    }
}