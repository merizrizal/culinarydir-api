<?php

namespace api\controllers\v1;

use yii\filters\VerbFilter;

class PageController extends \yii\rest\Controller
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
                        'news-promo' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionNewsPromo()
    {
        $data = [];

        $data[]['image'] = \Yii::$app->params['endPointLoadImage'] . 'load-image?image=sudah-mendata.jpg&w=875&h=385';

        return $data;
    }
}

