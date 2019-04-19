<?php

namespace api\controllers\v1;

use core\models\City;
use yii\filters\VerbFilter;

class CityController extends \yii\rest\Controller {

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
                        'list' => ['GET'],
                    ],
                ],
            ]);
    }

    public function actionList()
    {

        $model = City::find()
            ->select('id, name')
            ->orderBy(['name' => SORT_ASC])
            ->asArray()->all();

        return $model;
    }
}