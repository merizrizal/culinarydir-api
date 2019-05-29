<?php

namespace api\controllers\v1;

use core\models\Promo;
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

        $data[0]['image'] = \Yii::$app->params['endPointLoadImage'] . 'load-image?image=sudah-mendata.jpg';

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelPromo = Promo::find()
            ->andWhere(['not_active' => false])
            ->andWhere(['OR', ['>=', 'date_end', \Yii::$app->formatter->asDate(time())], ['date_end' => null]])
            ->orderBy(['created_at' => SORT_DESC])
            ->asArray()->all();

        \Yii::$app->formatter->timeZone = 'UTC';

        foreach ($modelPromo as $i => $dataPromo) {

            $data[$i + 1]['image'] = \Yii::$app->params['endPointLoadImage'] . 'promo?image=' . $dataPromo['image'];
        }

        return $data;
    }
}

