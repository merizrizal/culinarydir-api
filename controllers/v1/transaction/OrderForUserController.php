<?php

namespace api\controllers\v1\transaction;

use core\models\PromoItem;
use core\models\TransactionItem;
use core\models\TransactionSession;
use core\models\TransactionSessionOrder;
use frontend\components\AddressType;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;


class OrderForUserController extends \yii\rest\Controller
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
                        'transaction-item-list' => ['GET'],
                        'user-promo-item-list' => ['GET'],
                        'set-item-amount' => ['POST'],
                        'remove-item' => ['POST'],
                        'set-notes' => ['POST'],
                        'calculate-delivery-fee' => ['POST'],
                        'order-checkout' => ['POST'],
                        'cancel-order' => ['POST']
                    ],
                ],
            ]);
    }

    public function actionTransactionItemList($user)
    {
        $modelTransactionSession = TransactionSession::find()
            ->select([
                'transaction_session.id', 'transaction_session.user_ordered', 'transaction_session.business_id', 'transaction_session.total_price',
            ])
            ->joinWith([
                'business' => function ($query) {

                    $query->select([
                            'business.id', 'business.name'
                        ]);
                },
                'business.businessLocation' => function ($query) {

                    $query->select([
                            'business_location.address_type', 'business_location.address', 'business_location.coordinate'
                        ]);
                },
                'transactionItems' => function ($query) {

                    $query->select([
                            'transaction_item.id', 'transaction_item.transaction_session_id', 'transaction_item.note', 'transaction_item.price',
                            'transaction_item.amount', 'transaction_item.business_product_id'
                        ])
                        ->orderBy(['transaction_item.created_at' => SORT_ASC]);
                },
                'transactionItems.businessProduct' => function ($query) {

                    $query->select([
                            'business_product.id', 'business_product.name', 'business_product.description', 'business_product.price'
                        ]);
                }
            ])
            ->andWhere(['transaction_session.user_ordered' => $user])
            ->andWhere(['transaction_session.status' => 'Open'])
            ->asArray()->one();

        return $modelTransactionSession;
    }

    public function actionUserPromoItemList($user)
    {
        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelPromoItem = PromoItem::find()
            ->select([
                'promo_item.id', 'substr(promo_item.id, 1, 6) as code', 'promo_item.promo_id', 'promo_item.amount'
            ])
            ->joinWith([
                'userPromoItem' => function ($query) {

                    $query->select(['user_promo_item.promo_item_id']);
                },
                'promo' => function ($query) {

                    $query->select(['promo.id']);
                }
            ])
            ->andWhere(['promo_item.not_active' => false])
            ->andWhere(['promo_item.business_claimed' => null])
            ->andWhere(['>=', 'promo.date_end', \Yii::$app->formatter->asDate(time())])
            ->andWhere(['promo.not_active' => false])
            ->andWhere(['user_promo_item.user_id' => $user])
            ->asArray()->all();

        \Yii::$app->formatter->timeZone = 'UTC';

        return $modelPromoItem;
    }

    public function actionSetItemAmount()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $modelTransactionItem = TransactionItem::find()
            ->joinWith(['transactionSession'])
            ->andWhere(['transaction_item.id' => !empty($post['id']) ? $post['id'] : null])
            ->one();

        if (empty($modelTransactionItem)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;

        $amountPrior = $modelTransactionItem->amount;
        $modelTransactionItem->amount = $post['amount'];
        $totalAmount = $post['amount'] - $amountPrior;

        if (($flag = $modelTransactionItem->save())) {

            $modelTransactionSession = $modelTransactionItem->transactionSession;
            $modelTransactionSession->total_amount += $totalAmount;
            $modelTransactionSession->total_price += $modelTransactionItem->price * $totalAmount;

            if (!($flag = $modelTransactionSession->save())) {

                $result['error'] = $modelTransactionSession->getErrors();
            }
        } else {

            $result['error'] = $modelTransactionItem->getErrors();
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;
            $result['amount'] = $modelTransactionItem->amount;
            $result['total_price'] = $modelTransactionSession->total_price;
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['amount'] = $amountPrior;
        }

        return $result;
    }

    public function actionRemoveItem()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $modelTransactionItem = TransactionItem::find()
            ->joinWith(['transactionSession'])
            ->andWhere(['transaction_item.id' => !empty($post['id']) ? $post['id'] : null])
            ->one();

        if (empty($modelTransactionItem)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;

        $modelTransactionSession = $modelTransactionItem->transactionSession;
        $modelTransactionSession->total_amount -= $modelTransactionItem->amount;
        $modelTransactionSession->total_price -= $modelTransactionItem->price * $modelTransactionItem->amount;

        if ($modelTransactionSession->total_amount == 0) {

            $result['total_price'] = 0;

            $flag = $modelTransactionItem->delete() && $modelTransactionSession->delete();
        } else {

            $result['total_price'] = $modelTransactionSession->total_price;

            $flag = $modelTransactionItem->delete() && $modelTransactionSession->save();
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['error'] = ArrayHelper::merge($modelTransactionItem->getErrors(), $modelTransactionSession->getErrors());
        }

        return $result;
    }

    public function actionSetNotes()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $modelTransactionItem = TransactionItem::find()
            ->andWhere(['transaction_item.id' => !empty($post['id']) ? $post['id'] : null])
            ->one();

        if (empty($modelTransactionItem)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $modelTransactionItem->note = !empty($post['note']) ? $post['note'] : null;

        if ($modelTransactionItem->save()) {

            $result['success'] = true;
        } else {

            $result['success'] = false;
            $result['error'] = $modelTransactionItem->getErrors();
        }

        return $result;
    }

    public function actionCalculateDeliveryFee() {

        $post = \Yii::$app->request->post();

        $result = [];

        $result['delivery_fee'] = round($post['distance'] / 1000) * 2500;

        return $result;
    }

    public function actionOrderCheckout()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $modelTransactionSessionOrder = new TransactionSessionOrder();

        $modelPromoItem = new PromoItem();

        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'transactionItems.businessProduct',
                'business',
                'business.businessLocation',
                'userOrdered',
                'userOrdered.userPerson.person'
            ])
            ->andWhere(['transaction_session.user_ordered' => $post['user_id']])
            ->andWhere(['transaction_session.status' => 'Open'])
            ->one();

        if (empty($modelTransactionSession)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;

        $modelTransactionSessionOrder->transaction_session_id = $modelTransactionSession->id;
        $modelTransactionSessionOrder->business_delivery_id = !empty($post['business_delivery_id']) ? $post['business_delivery_id'] : null;
        $modelTransactionSessionOrder->business_payment_id = !empty($post['business_payment_id']) ? $post['business_payment_id'] : null;
        $modelTransactionSessionOrder->destination_coordinate = $post['location'];

        if (($flag = $modelTransactionSessionOrder->save())) {

            $modelTransactionSession->promo_item_id = !empty($post['promo_item_id']) ? $post['promo_item_id'] : null;
            $modelTransactionSession->note = !empty($post['note']) ? $post['note'] : null;
            $modelTransactionSession->status = 'New';

            if (!empty($modelTransactionSession->promo_item_id)) {

                $modelPromoItem = PromoItem::find()
                    ->joinWith(['userPromoItem'])
                    ->andWhere(['promo_item.id' => $modelTransactionSession->promo_item_id])
                    ->andWhere(['promo_item.business_claimed' => null])
                    ->andWhere(['promo_item.not_active' => false])
                    ->andWhere(['user_promo_item.user_id' => $post['user_id']])
                    ->one();

                $modelPromoItem->business_claimed = $modelTransactionSession->business_id;
                $modelPromoItem->not_active = true;

                if (($flag = $modelPromoItem->save())) {

                    $modelTransactionSession->promo_item_id = $modelPromoItem->id;
                    $modelTransactionSession->discount_value = $modelPromoItem->amount;
                    $modelTransactionSession->discount_type = 'Amount';
                }
            }

            $flag = $modelTransactionSession->save();
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;

            $result['order'] = [];
            $result['order']['header']['customer_id'] = $modelTransactionSession->userOrdered->id;
            $result['order']['header']['customer_name'] = $modelTransactionSession->userOrdered->full_name;
            $result['order']['header']['customer_username'] = $modelTransactionSession->userOrdered->username;
            $result['order']['header']['customer_phone'] = $modelTransactionSession->userOrdered->userPerson->person->phone;
            $result['order']['header']['customer_location'] = $modelTransactionSessionOrder->destination_coordinate;
            $result['order']['header']['customer_address'] = $post['address'];
            $result['order']['header']['customer_delivery_note'] = $modelTransactionSession->note;

            $result['order']['header']['business_id'] = $modelTransactionSession->business_id;
            $result['order']['header']['business_name'] = $modelTransactionSession->business->name;
            $result['order']['header']['business_phone'] = $modelTransactionSession->business->phone3;
            $result['order']['header']['business_location'] = $modelTransactionSession->business->businessLocation->coordinate;
            $result['order']['header']['business_address'] = AddressType::widget([
                'businessLocation' => $modelTransactionSession->business->businessLocation,
                'showDetail' => false
            ]);

            $result['order']['header']['order_id'] = substr($modelTransactionSession->order_id, 0, 6);
            $result['order']['header']['note'] = $modelTransactionSession->note;
            $result['order']['header']['total_price'] = $modelTransactionSession->total_price;
            $result['order']['header']['total_amount'] = $modelTransactionSession->total_amount;
            $result['order']['header']['distance'] = $post['distance'];
            $result['order']['header']['delivery_fee'] = $post['delivery_fee'];
            $result['order']['header']['order_status'] = $modelTransactionSession->status;

            foreach ($modelTransactionSession->transactionItems as $i => $dataTransactionItem) {

                $result['order']['detail'][$i] = [];
                $result['order']['detail'][$i]['menu'] = $dataTransactionItem->businessProduct->name;
                $result['order']['detail'][$i]['price'] = $dataTransactionItem->price;
                $result['order']['detail'][$i]['amount'] = $dataTransactionItem->amount;
                $result['order']['detail'][$i]['note'] = $dataTransactionItem->note;
            }

//             if ($post['business_delivery_special'] === 'true') {

//                 $client = new \ElephantIO\Client(new \ElephantIO\Engine\SocketIO\Version2X(\Yii::$app->params['socketIOServiceAddress']));
//                 $client->initialize();
//                 $client->emit('broadcast', $result['order']);
//                 $client->close();
//             }
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['error'] = ArrayHelper::merge($modelTransactionSession->getErrors(), $modelTransactionSessionOrder->getErrors(), $modelPromoItem->getErrors());
        }

        return $result;
    }

    public function actionCancelOrder()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $modelTransactionItem = TransactionItem::find()
            ->joinWith(['transactionSession'])
            ->andWhere(['transaction_item.transaction_session_id' => !empty($post['id']) ? $post['id'] : null])
            ->all();

        if (empty($modelTransactionItem)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;

        $modelTransactionSession = $modelTransactionItem[0]->transactionSession;

        foreach ($modelTransactionItem as $dataTransactionItem) {

            if (!($flag = $dataTransactionItem->delete())) {

                break;
            }
        }

        if ($flag) {

            $flag = $modelTransactionSession->delete();
        }

        if ($flag) {

            $result['success'] = true;
            $result['message'] = 'Pesanan telah dibatalkan';

            $transaction->commit();
        } else {

            $result['success'] = false;
            $result['message'] = 'Pesanan gagal dibatalkan';
            $result['error'] = ArrayHelper::merge($modelTransactionItem->getErrors(), $modelTransactionSession->getErrors());

            $transaction->rollback();
        }

        return $result;
    }
}