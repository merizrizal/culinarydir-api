<?php

namespace api\controllers\v1;

use core\models\User;
use Yii;
use yii\filters\VerbFilter;

class BusinessController extends \yii\rest\Controller {

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
                        'get-branch' => ['POST'],
                        'get-operational-hours' => ['POST']
                    ],
                ],
            ]);
    }

    public function actionGetOperationalHours()
    {


    }

    public function actionGetBranch()
    {
        $result = [];

        $post = Yii::$app->request->post();

        $model = User::find()
            ->joinWith([
                'userPerson.person.businessContactPeople.business.businessLocation'
            ])
            ->andWhere(['user.id' => $post['id']])
            ->asArray()->one();

        if (!empty($model)) {

            if (!empty($model['userPerson']['person']['businessContactPeople'])) {

                foreach ($model['userPerson']['person']['businessContactPeople'] as $i => $dataBusinessContactPerson) {

                    $result['business'][$i]['name'] = $dataBusinessContactPerson['business']['name'];
                    $result['business'][$i]['phone'] = $dataBusinessContactPerson['business']['phone3'];
                    $result['business'][$i]['email'] = $dataBusinessContactPerson['business']['email'];
                    $result['business'][$i]['address'] = $dataBusinessContactPerson['business']['businessLocation']['address'];
                }
            } else {

                $result['message'] = 'User ID tidak valid';
            }
        } else {

            $result['message'] = 'User ID tidak ditemukan';
        }

        return $result;
    }
}