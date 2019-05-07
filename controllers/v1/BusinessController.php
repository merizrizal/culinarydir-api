<?php

namespace api\controllers\v1;

use core\models\Business;
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
        $result = [];

        $post = Yii::$app->request->post();

        $modelBusiness = Business::find()
            ->joinWith([
                'businessHours' => function ($query) {

                    $query->orderBy(['business_hour.day' => SORT_ASC]);
                },
                'businessHours.businessHourAdditionals'
            ])
            ->andWhere(['business.id' => $post['business_id']])
            ->asArray()->one();

        if (!empty($modelBusiness)) {

            if (!empty($modelBusiness['businessHours'])) {

                foreach ($modelBusiness['businessHours'] as $i => $dataBusinessHour) {

                    $day = Yii::t('app', Yii::$app->params['days'][$i]);

                    $result[$day]['is_open'] = $dataBusinessHour['is_open'];
                    $result[$day]['hour'][0]['open'] = $dataBusinessHour['open_at'];
                    $result[$day]['hour'][0]['close'] = $dataBusinessHour['close_at'];

                    if (!empty($dataBusinessHour['businessHourAdditionals'])) {

                        foreach ($dataBusinessHour['businessHourAdditionals'] as $i => $dataBusinessHourAdditional) {

                            $result[$day]['hour'][$i + 1]['open'] = $dataBusinessHourAdditional['open_at'];
                            $result[$day]['hour'][$i + 1]['close'] = $dataBusinessHourAdditional['close_at'];
                        }
                    }
                }
            } else {

                $result['message'] = 'Tidak ada jam operasional';
            }
        } else {

            $result['message'] = 'Business ID tidak ditemukan';
        }

        return $result;
    }

    public function actionGetBranch()
    {
        $result = [];

        $model = User::find()
            ->joinWith([
                'userPerson.person.businessContactPeople.business.businessLocation'
            ])
            ->andWhere(['user.id' => Yii::$app->request->post()['user_id']])
            ->asArray()->one();

        if (!empty($model)) {

            if (!empty($model['userPerson']['person']['businessContactPeople'])) {

                foreach ($model['userPerson']['person']['businessContactPeople'] as $i => $dataBusinessContactPerson) {

                    $result['business'][$i]['id'] = $dataBusinessContactPerson['business_id'];
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