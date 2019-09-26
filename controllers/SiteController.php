<?php
namespace api\controllers;


use yii\filters\VerbFilter;

/**
 * Site controller
 */
class SiteController extends \yii\rest\Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(
            [],
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [

                    ],
                ],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $this->layout = 'zero';

        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
                'view' => 'error',
            ],
        ];
    }

    public function actionIndex() {

        return 1;
    }

    public function actionMaintenance() {

        return [
            'success' => false,
            'message' => 'Sedang maintenance',
            'error' => [
                'message' => 'Sedang maintenance'
            ]
        ];
    }
}