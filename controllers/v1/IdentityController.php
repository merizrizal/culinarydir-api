<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;

class IdentityController extends \yii\rest\Controller {
    
    /**
     * @inheritdoc
     */
    public function behaviors() {
        
        return array_merge(
            [],
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'login' => ['post'],
                    ],
                ],
            ]);
    }
    
    public function actionLogin() {
        
        $post = Yii::$app->request->post();
        return [
            'isLogin' => 'asdasdasdasdasdasd ' . $post['email'] . ' ' . $post['password'],
        ];
    }
}