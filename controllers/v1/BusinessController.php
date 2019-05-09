<?php

namespace api\controllers\v1;

use core\models\Business;
use core\models\User;
use Yii;
use yii\filters\VerbFilter;
use core\models\TransactionSession;

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
                        'get-operational-hours' => ['POST'],
                        'get-branch' => ['POST'],
                        'get-finish-order' => ['POST'],
                        'get-on-progress-order' => ['POST']
                    ],
                ],
            ]);
    }

    public function actionGetOperationalHours()
    {
        $result = [];

        $modelBusiness = Business::find()
            ->joinWith([
                'businessHours' => function ($query) {

                    $query->orderBy(['business_hour.day' => SORT_ASC]);
                },
                'businessHours.businessHourAdditionals'
            ])
            ->andWhere(['business.id' => Yii::$app->request->post()['business_id']])
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

        $days = \Yii::$app->params['days'];
        $isOpen = false;

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $now = \Yii::$app->formatter->asTime(time());

        \Yii::$app->formatter->timeZone = 'UTC';

        $model = User::find()
            ->joinWith([
                'userPerson.person.businessContactPeople.business.businessLocation',
                'userPerson.person.businessContactPeople.business.businessHours.businessHourAdditionals'
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

                    if (!empty($dataBusinessContactPerson['business']['businessHours'])) {

                        foreach ($dataBusinessContactPerson['business']['businessHours'] as $dataBusinessHour) {

                            $day = $days[$dataBusinessHour['day'] - 1];

                            if (date('l') == $day) {

                                $isOpen = $now >= $dataBusinessHour['open_at'] && $now <= $dataBusinessHour['close_at'];

                                if (!$isOpen && !empty($dataBusinessHour['businessHourAdditionals'])) {

                                    foreach ($dataBusinessHour['businessHourAdditionals'] as $dataBusinessHourAdditional) {

                                        $isOpen = $now >= $dataBusinessHourAdditional['open_at'] && $now <= $dataBusinessHourAdditional['close_at'];

                                        if ($isOpen) {

                                            break 2;
                                        }
                                    }
                                } else {

                                    break;
                                }
                            } else {

                                $isOpen = false;
                            }
                        }

                        $result['business'][$i]['is_open'] = $isOpen;
                    }
                }
            } else {

                $result['message'] = 'User ID tidak valid';
            }
        } else {

            $result['message'] = 'User ID tidak ditemukan';
        }

        return $result;
    }

    public function actionGetFinishOrder()
    {
        $result = [];

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelTransactionSession = TransactionSession::find()
            ->andWhere(['created_at' => \Yii::$app->formatter->asDate(time())])
            ->asArray()->all();

        \Yii::$app->formatter->timeZone = 'UTC';

        return $modelTransactionSession;
    }

    public function actionGetOnProgressOrder()
    {

    }
}