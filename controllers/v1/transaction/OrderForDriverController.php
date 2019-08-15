<?php

namespace api\controllers\v1\transaction;

use core\models\TransactionCanceledByDriver;
use core\models\TransactionSession;
use core\models\TransactionSessionDelivery;
use core\models\User;
use sycomponent\Tools;
use yii\filters\VerbFilter;

class OrderForDriverController extends \yii\rest\Controller
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
                        'new-order' => ['POST'],
                        'driver-not-found' => ['POST'],
                        'get-list-order-by-driver' => ['POST']
                    ],
                ],
            ]);
    }

    public function actionGetOrderDetail()
    {
        $result = [];

        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['order_id'])) {

            $modelTransactionSession = TransactionSession::find()
                ->select(['transaction_session.id', 'transaction_session.total_price', 'transaction_session.total_amount', 'transaction_session.user_ordered'])
                ->joinWith([
                    'transactionItems' => function ($query) {

                        $query->orderBy(['transaction_item.created_at' => SORT_ASC]);
                    },
                    'transactionItems.businessProduct',
                    'userOrdered',
                    'userOrdered.userPerson.person',
                    'transactionSessionDelivery'
                ])
                ->andWhere(['ilike', 'order_id', \Yii::$app->request->post()['order_id'] . '_'])
                ->asArray()->one();

            $modelUserDriver = User::find()
                ->joinWith(['userPerson.person'])
                ->andWhere(['user.id' => $modelTransactionSession['transactionSessionDelivery']['driver_id']])
                ->asArray()->one();

            if (!empty($modelTransactionSession)) {

                $result['detail'] = [];

                foreach ($modelTransactionSession['transactionItems'] as $i => $dataTransactionItem) {

                    $result['detail'][$i] = [];

                    $result['detail'][$i]['menu'] = $dataTransactionItem['businessProduct']['name'];
                    $result['detail'][$i]['price'] = $dataTransactionItem['price'];
                    $result['detail'][$i]['amount'] = $dataTransactionItem['amount'];
                    $result['detail'][$i]['note'] = $dataTransactionItem['note'];
                    $result['detail'][$i]['total'] = $dataTransactionItem['price'] * $dataTransactionItem['amount'];
                }

                $result['success'] = true;
                $result['total_price'] = $modelTransactionSession['total_price'];
                $result['total_amount'] = $modelTransactionSession['total_amount'];
                $result['customer_name'] = $modelTransactionSession['userOrdered']['full_name'];
                $result['customer_phone'] = $modelTransactionSession['userOrdered']['userPerson']['person']['phone'];
                $result['driver_name'] = $modelUserDriver['full_name'];
                $result['driver_phone'] = $modelUserDriver['userPerson']['person']['phone'];

                if (!empty($modelTransactionSession['transactionSessionDelivery'])) {

                    $result['total_delivery_fee'] = $modelTransactionSession['transactionSessionDelivery']['total_delivery_fee'];
                }
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

        $transaction = \Yii::$app->db->beginTransaction();

        if (!empty(($post = \Yii::$app->request->post())) && !empty($post['order_id'])) {

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

        $transaction = \Yii::$app->db->beginTransaction();

        if (!empty(($post = \Yii::$app->request->post()))) {

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

        $transaction = \Yii::$app->db->beginTransaction();

        if (!empty(($post = \Yii::$app->request->post()))) {

            if (!empty($post['order_id'])) {

                $modelTransactionSession = TransactionSession::find()
                    ->andWhere(['ilike', 'order_id', $post['order_id'] . '_'])
                    ->andWhere(['status' => 'New'])
                    ->one();

                if (!empty($modelTransactionSession)) {

                    if (!empty($post['driver_user_id'])) {

                        $modelTransactionSessionDelivery = TransactionSessionDelivery::find()
                            ->andWhere(['transaction_session_id' => $modelTransactionSession->id])
                            ->one();

                        if (empty($modelTransactionSessionDelivery)) {

                            $modelTransactionSessionDelivery = new TransactionSessionDelivery();
                            $modelTransactionSessionDelivery->transaction_session_id = $modelTransactionSession->id;
                            $modelTransactionSessionDelivery->total_distance = $post['distance'];
                            $modelTransactionSessionDelivery->total_delivery_fee = $post['delivery_fee'];
                        }

                        $modelTransactionSessionDelivery->driver_id = $post['driver_user_id'];

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

        $transaction = \Yii::$app->db->beginTransaction();

        if (!empty(($post = \Yii::$app->request->post()))) {

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

        if (!empty(\Yii::$app->request->post()['order_id'])) {

            $result['success'] = $this->updateStatusOrder(\Yii::$app->request->post()['order_id'], 'Send-Order');
        }

        return $result;
    }

    public function actionFinishOrder()
    {
        $result = [];

        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['order_id'])) {

            $result['success'] = $this->updateStatusOrder(\Yii::$app->request->post()['order_id'], 'Finish');
        }

        return $result;
    }

    public function actionNewOrder()
    {
        $result = [];

        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['order_id'])) {

            $result['success'] = $this->updateStatusOrder(\Yii::$app->request->post()['order_id'], 'New');
        }

        return $result;
    }

    public function actionDriverNotFound()
    {
        $result = [];

        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['order_id'])) {

            $result['success'] = $this->updateStatusOrder(\Yii::$app->request->post()['order_id'], 'Cancel');
        }

        return $result;
    }

    public function actionGetListOrderByDriver()
    {
        $result = [];
        $result['success'] = false;

        $post = \Yii::$app->request->post();

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        if (!empty($post['order_date']) && !empty($post['driver_id'])) {

            $modelTransactionSession = TransactionSession::find()
                ->joinWith([
                    'business',
                    'transactionSessionDelivery',
                    'transactionCanceledByDrivers' => function ($query) use ($post) {

                        $query->andOnCondition(['transaction_canceled_by_driver.driver_id' => $post['driver_id']]);
                    }
                ])
                ->andWhere(['date(transaction_session.created_at)' => $post['order_date']])
                ->andWhere(['transaction_session_delivery.driver_id' => $post['driver_id']])
                ->andWhere(['transaction_session.status' => ['Finish', 'Cancel']])
                ->orderBy(['transaction_session.created_at' => SORT_DESC])
                ->asArray()->all();

            if (!empty($modelTransactionSession)) {

                $result['success'] = true;
                $result['total_income'] = 0;
                $result['total_order'] = 0;

                $result['order'] = [];

                foreach ($modelTransactionSession as $dataTransactionSession) {

                    if ($dataTransactionSession['status'] == 'Finish') {

                        $result['total_income'] += $dataTransactionSession['transactionSessionDelivery']['total_delivery_fee'];
                        $result['total_order'] += 1;

                        array_push($result['order'], [
                            'status' =>  $dataTransactionSession['status'],
                            'delivery_fee' => $dataTransactionSession['transactionSessionDelivery']['total_delivery_fee'],
                            'transaction_time' => \Yii::$app->formatter->asTime($dataTransactionSession['updated_at'], 'HH:mm'),
                            'business_name' => $dataTransactionSession['business']['name'],
                            'order_id' => substr($dataTransactionSession['order_id'], 0, 6)
                        ]);
                    } else {

                        foreach ($dataTransactionSession['transactionCanceledByDrivers'] as $dataTransactionCanceled) {

                            array_push($result['order'], [
                                'status' => $dataTransactionSession['status'],
                                'delivery_fee' => 0,
                                'transaction_time' => \Yii::$app->formatter->asTime($dataTransactionCanceled['created_at'], 'HH:mm'),
                                'business_name' => $dataTransactionSession['business']['name'],
                                'order_id' => substr($dataTransactionSession['order_id'], 0, 6)
                            ]);
                        }
                    }
                }
            } else {

                $result['message'] = 'Transaksi tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter order_date & order_id tidak boleh kosong';
        }

        \Yii::$app->formatter->timeZone = 'UTC';

        return $result;
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
