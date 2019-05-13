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
                        'checkout' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionCheckout()
    {
        $userId = \Yii::$app->request->get('user_id');

        $return = [];

        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'business',
                'business.businessDeliveries' => function ($query) {

                    $query->andOnCondition(['business_delivery.is_active' => true]);
                },
                'business.businessPayments' => function ($query) {

                    $query->andOnCondition(['business_payment.is_active' => true]);
                },
                'transactionItems' => function ($query) {

                    $query->orderBy(['transaction_item.created_at' => SORT_ASC]);
                },
                'transactionItems.businessProduct'
            ])
            ->andWhere(['transaction_session.user_ordered' => $userId])
            ->andWhere(['transaction_session.is_closed' => false])
            ->asArray()->one();

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $promoItemClaimed = PromoItem::find()
            ->joinWith([
                'userPromoItem',
                'promo'
            ])
            ->andWhere(['promo_item.not_active' => false])
            ->andWhere(['promo_item.business_claimed' => null])
            ->andWhere(['>=', 'promo.date_end', \Yii::$app->formatter->asDate(time())])
            ->andWhere(['promo.not_active' => false])
            ->andWhere(['user_promo_item.user_id' => $userId])
            ->asArray()->all();

        \Yii::$app->formatter->timeZone = 'UTC';

        $return['transactionSession'] = $modelTransactionSession;
        $return['promoItem'] = $promoItemClaimed;

        return $return;
    }
}