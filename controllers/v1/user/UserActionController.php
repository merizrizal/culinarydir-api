<?php

namespace api\controllers\v1\user;

use yii\filters\VerbFilter;
use core\models\TransactionSession;
use core\models\TransactionItem;

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

            $modelTransactionSession = new TransactionSession();
            $modelTransactionSession->user_ordered = $oldModelTransaction->user_ordered;
            $modelTransactionSession->business_id = $oldModelTransaction->business_id;
            $modelTransactionSession->note = !empty($oldModelTransaction->note) ? $oldModelTransaction->note : null;
            $modelTransactionSession->total_price = $oldModelTransaction->total_price;
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
}