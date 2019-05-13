<?php

namespace api\controllers\v1\transaction;

use core\models\PromoItem;
use core\models\TransactionSession;
use yii\filters\VerbFilter;

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
                        'user-promo-item-list' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionTransactionItemList($user)
    {
        $modelTransactionSession = TransactionSession::find()
            ->select([
                'transaction_session.id', 'transaction_session.user_ordered', 'transaction_session.business_id',
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
            ->andWhere(['transaction_session.is_closed' => false])
            ->asArray()->one();

        return $modelTransactionSession;
    }

    public function actionUserPromoItemList($user)
    {
        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelPromoItem = PromoItem::find()
            ->select([
                'promo_item.id', 'promo_item.promo_id', 'promo_item.amount'
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
}