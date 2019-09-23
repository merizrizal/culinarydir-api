<?php
namespace api\controllers;


use yii\filters\VerbFilter;
use yii\web\HttpException;

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

        $exception = new class extends HttpException {

            public function __construct()
            {
                parent::__construct(404, 'Sedang maintenance');
            }

            public function getName()
            {

                return $this->message;
            }
        };

        throw $exception;
    }
}