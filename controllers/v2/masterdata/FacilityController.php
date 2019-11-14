<?php

namespace api\controllers\v2\masterdata;

use core\models\Facility;
use yii\filters\VerbFilter;

class FacilityController extends \yii\rest\Controller
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
                        'list' => ['GET'],
                    ],
                ],
            ]);
    }

    public function actionList()
    {
        $model = Facility::find()
            ->select('id, name')
            ->orderBy(['name' => SORT_ASC])
            ->asArray()->all();

        return $model;
    }
}