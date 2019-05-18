<?php

namespace api\controllers\v1\transaction;

use core\models\PromoItem;
use core\models\TransactionItem;
use core\models\TransactionSession;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use function yii\i18n\Formatter\asDate as time;
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
                        'set-item-amount' => ['POST']
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

            $flag = $modelTransactionItem->delete() && $modelTransactionSession->delete();
        } else {

            $flag = $modelTransactionItem->delete() && $modelTransactionSession->save();
        }

        $result = [];

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
}