<?php

namespace api\controllers\v1;

use core\models\Category;
use yii\filters\VerbFilter;

class CategoryController extends \yii\rest\Controller {

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

        $model = Category::find()
            ->select('id, name')
            ->orderBy(['name' => SORT_ASC])
            ->asArray()->all();

        return $model;
    }
}