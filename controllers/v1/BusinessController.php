<?php

namespace api\controllers\v1;

use core\models\BusinessDelivery;
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
                        'payment-list' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionDeliveryList($id)
    {
        $model = BusinessDelivery::find()
            ->select(['id', 'note', 'description'])
            ->andWhere(['business_id' => $id])
            ->asArray()->all();

        return $model;
    }

    public function actionPaymentList($id)
    {
        $model = BusinessPayment::find()
        ->select(['id', 'note', 'description'])
        ->andWhere(['business_id' => $id])
        ->asArray()->all();

        return $model;
    }
}