<?php

namespace api\controllers\v2;

use core\models\BusinessDelivery;
use core\models\BusinessImage;
use core\models\BusinessPayment;
use yii\filters\VerbFilter;

class BusinessController extends \yii\rest\Controller
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
                        'delivery-list' => ['GET'],
                        'payment-list' => ['GET'],
                        'album-list' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionDeliveryList($id)
    {
        $model = BusinessDelivery::find()
            ->select([
                'business_delivery.id', 'delivery_method.delivery_name', 'business_delivery.note', 'business_delivery.description',
                'business_delivery.delivery_method_id', 'delivery_method.is_special'
            ])
            ->joinWith([
                'deliveryMethod' => function ($query) {

                    $query->select(['delivery_method.id']);
                },
            ])
            ->andWhere(['business_delivery.business_id' => $id])
            ->andWhere(['business_delivery.is_active' => true])
            ->asArray()->all();

        return $model;
    }

    public function actionPaymentList($id)
    {
        $model = BusinessPayment::find()
            ->select(['business_payment.id', 'payment_method.payment_name', 'business_payment.note', 'business_payment.description', 'business_payment.payment_method_id'])
            ->joinWith([
                'paymentMethod' => function ($query) {

                    $query->select(['payment_method.id']);
                },
            ])
            ->andWhere(['business_payment.business_id' => $id])
            ->andWhere(['business_payment.is_active' => true])
            ->asArray()->all();

        return $model;
    }

    public function actionAlbumList($id)
    {
        $model = BusinessImage::find()
            ->select(['business_image.id', 'business_image.business_id', 'business_image.image', 'business_image.type', 'business_image.is_primary', 'business_image.category', 'business_image.order' ])
            ->andWhere(['business_id' => $id])
            ->asArray()->all();

        return $model;
    }
}