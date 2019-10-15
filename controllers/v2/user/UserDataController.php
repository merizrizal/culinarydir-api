<?php

namespace api\controllers\v2\user;

use core\models\TransactionSession;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;

class UserDataController extends \yii\rest\Controller
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
        'linksEnvelope' => 'links',
        'metaEnvelope' => 'meta',
    ];

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
                        'order-history-list' => ['GET'],
                    ],
                ],
            ]);
    }

    public function actionOrderHistoryList($user_id)
    {
        $modelTransactionSession = TransactionSession::find()
            ->select([
                'transaction_session.id', 'transaction_session.status', 'transaction_session.created_at', 'transaction_session.total_price',
                'transaction_session.discount_value', 'transaction_session_delivery.total_delivery_fee', 'business.id as business_id',
                'business.name as business_name', 'business_location.address as business_address', 'business_location.address_type as business_address_type',
                'business_image.image as business_image'
            ])
            ->joinWith([
                'business' => function ($query) {

                    $query->select(['business.id']);
                },
                'business.businessImages' => function ($query) {

                    $query->select(['business_image.business_id'])
                        ->andOnCondition(['business_image.type' => 'Profile'])
                        ->andOnCondition(['business_image.is_primary' => true]);
                },
                'business.businessLocation' => function ($query) {

                    $query->select(['business_location.business_id']);
                },
                'transactionSessionDelivery' => function ($query) {

                    $query->select(['transaction_session_delivery.transaction_session_id']);
                }
            ])
            ->andWhere(['transaction_session.user_ordered' => $user_id])
            ->orderBy(['transaction_session.created_at' => SORT_DESC])
            ->distinct()
            ->asArray();

        $provider = new ActiveDataProvider([
            'query' => $modelTransactionSession,
        ]);

        return $provider;
    }
}