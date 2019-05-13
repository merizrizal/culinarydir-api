<?php

namespace api\controllers\v1;

use core\models\Business;
use core\models\BusinessHour;
use core\models\User;
use Yii;
use yii\filters\VerbFilter;
use core\models\TransactionSession;
use core\models\BusinessHourAdditional;

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
                        'get-on-progress-order' => ['POST'],
                        'update-open-status' => ['POST'],
                        'update-operational-hours' => ['POST']
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
            ->andWhere(['business.id' => \Yii::$app->request->post()['business_id']])
            ->asArray()->one();

        if (!empty($modelBusiness)) {

            if (!empty($modelBusiness['businessHours'])) {

                $result['success'] = true;

                foreach ($modelBusiness['businessHours'] as $i => $dataBusinessHour) {

                    $day = \Yii::t('app', \Yii::$app->params['days'][$dataBusinessHour['day'] - 1]);

                    $result['schedule'][$i]['day_id'] = $dataBusinessHour['day'];
                    $result['schedule'][$i]['day'] = $day;
                    $result['schedule'][$i]['is_open'] = $dataBusinessHour['is_open'];
                    $result['schedule'][$i]['hour'] = [];

                    array_push($result['schedule'][$i]['hour'], [
                        'open' => Yii::$app->formatter->asTime($dataBusinessHour['open_at'], 'HH:mm'),
                        'close' => Yii::$app->formatter->asTime($dataBusinessHour['close_at'], 'HH:mm')
                    ]);

                    if (!empty($dataBusinessHour['businessHourAdditionals'])) {

                        foreach ($dataBusinessHour['businessHourAdditionals'] as $dataBusinessHourAdditional) {

                            array_push($result['schedule'][$i]['hour'], [
                                'open' => Yii::$app->formatter->asTime($dataBusinessHourAdditional['open_at'], 'HH:mm'),
                                'close' => Yii::$app->formatter->asTime($dataBusinessHourAdditional['close_at'], 'HH:mm')
                            ]);
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
            ->andWhere(['user.id' => \Yii::$app->request->post()['user_id']])
            ->asArray()->one();

        if (!empty($model)) {

            if (!empty($model['userPerson']['person']['businessContactPeople'])) {

                $result['success'] = true;

                foreach ($model['userPerson']['person']['businessContactPeople'] as $i => $dataBusinessContactPerson) {

                    $result['business'][$i]['id'] = $dataBusinessContactPerson['business_id'];
                    $result['business'][$i]['name'] = $dataBusinessContactPerson['business']['name'];
                    $result['business'][$i]['phone'] = $dataBusinessContactPerson['business']['phone3'];
                    $result['business'][$i]['email'] = $dataBusinessContactPerson['business']['email'];
                    $result['business'][$i]['address'] = $dataBusinessContactPerson['business']['businessLocation']['address'];

                    if (!empty($dataBusinessContactPerson['business']['businessHours'])) {

                        foreach ($dataBusinessContactPerson['business']['businessHours'] as $dataBusinessHour) {

                            $day = $days[$dataBusinessHour['day'] - 1];

                            if (date('l') == $day && $dataBusinessHour['is_open']) {

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
        return $this->getTodaysOrder('finish');
    }

    public function actionGetOnProgressOrder()
    {
        return $this->getTodaysOrder('on-progress');
    }

    public function actionUpdateOpenStatus()
    {
        $result = [];
        $flag = false;
        $result['success'] = false;

        $modelBusinessHour = BusinessHour::find()
            ->joinWith(['businessHourAdditionals'])
            ->andWhere(['business_hour.business_id' => \Yii::$app->request->post()['business_id']])
            ->all();

        if (!empty($modelBusinessHour)) {

            $transaction = \Yii::$app->db->beginTransaction();

            foreach ($modelBusinessHour as $dataBusinessHour) {

                $dataBusinessHour->is_open = \Yii::$app->request->post()['is_open'];

                if (!($flag = $dataBusinessHour->save())) {

                    break;
                } else {

                    if (!empty($dataBusinessHour->businessHourAdditionals)) {

                        foreach ($dataBusinessHour->businessHourAdditionals as $dataBusinessHourAdditional) {

                            $dataBusinessHourAdditional->is_open = $dataBusinessHour->is_open;

                            if (!($flag = $dataBusinessHourAdditional->save())) {

                                break 2;
                            }
                        }
                    }
                }
            }

            if ($flag) {

                $transaction->commit();

                $result['success'] = true;
                $result['message'] = 'Update Status buka/tutup berhasil';
            } else {

                $transaction->rollback();

                $result['message'] = 'Update Status buka/tutup gagal, terdapat kesalahan saat menyimpan data';
            }
        } else {

            $result['message'] = 'Business ID tidak ditemukan';
        }

        return $result;
    }

    public function actionUpdateOperationalHours()
    {
        $result = [];
        $flag = false;
        $result['success'] = false;

        $post = \Yii::$app->request->post();

        $modelBusinessHour = BusinessHour::find()
            ->joinWith([
                'businessHourAdditionals' => function ($query) use ($post) {

                    $query->andOnCondition(['business_hour_additional.day' => $post['day']]);
                }
            ])
            ->andWhere(['business_hour.business_id' => $post['business_id']])
            ->andWhere(['business_hour.day' => $post['day']])
            ->one();

        if (!empty($modelBusinessHour)) {

            $transaction = Yii::$app->db->beginTransaction();

            if (!empty($post['hour'])) {

                if (!empty($modelBusinessHour->businessHourAdditionals)) {

                    foreach ($modelBusinessHour->businessHourAdditionals as $idx => $dataBusinessHourAdditional) {

                        if ((count($post['hour']) - 1) < ($idx + 1)) {

                            if (!($flag = BusinessHourAdditional::deleteAll(['id' => $dataBusinessHourAdditional->id]))) {

                                break;
                            }
                        }
                    }
                }

                foreach ($post['hour'] as $i => $hour) {

                    if ($i == 0) {

                        $modelBusinessHour->open_at = $hour['open'];
                        $modelBusinessHour->close_at = $hour['close'];

                        if (!($flag = $modelBusinessHour->save())) {

                            break;
                        }
                    } else {

                        if (!empty(($modelBusinessHour->businessHourAdditionals[$i - 1]))) {

                            $hourAdditional = $modelBusinessHour->businessHourAdditionals[$i - 1];

                            $hourAdditional->open_at = $hour['open'];
                            $hourAdditional->close_at = $hour['close'];

                            if (!($flag = $hourAdditional->save())) {

                                break;
                            }
                        } else {

                            $newModelBusinessHourAdditional = new BusinessHourAdditional();
                            $newModelBusinessHourAdditional->unique_id = $modelBusinessHour->id . '-' . $post['day'] . '-' . $i;
                            $newModelBusinessHourAdditional->business_hour_id = $modelBusinessHour->id;
                            $newModelBusinessHourAdditional->is_open = true;
                            $newModelBusinessHourAdditional->day = $post['day'];
                            $newModelBusinessHourAdditional->open_at = $hour['open'];
                            $newModelBusinessHourAdditional->close_at = $hour['close'];

                            if (!($flag = $newModelBusinessHourAdditional->save())) {

                                break;
                            }
                        }
                    }
                }

                if ($flag) {

                    $transaction->commit();

                    $result['success'] = true;
                    $result['message'] = 'Update jam operasional berhasil';
                } else {

                    $transaction->rollback();

                    $result['message'] = 'Update gagal, terjadi kesalahan saat menyimpan data';
                }
            } else {

                $result['message'] = 'Parameter hour tidak ada';
            }
        } else {

            $result['message'] = 'Business ID tidak ditemukan';
        }

        return $result;
    }

    private function getTodaysOrder($type)
    {
        $result = [];
        $result['success'] = false;

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelTransactionSession = TransactionSession::find()
            ->joinWith([
                'transactionSessionDelivery.driver',
                'transactionItems.businessProduct',
            ])
            ->andWhere(['date(transaction_session.created_at)' => \Yii::$app->formatter->asDate(time())]);

        \Yii::$app->formatter->timeZone = 'UTC';

        if ($type == 'finish') {

            $modelTransactionSession = $modelTransactionSession->andWhere(['status' => ['Finish', 'Upload-Receipt', 'Send-Order', 'Confirm-Price']]);
        } else {

            $modelTransactionSession = $modelTransactionSession->andWhere(['status' => 'Take-Order']);
        }

        $modelTransactionSession = $modelTransactionSession->asArray()->all();

        if (!empty($modelTransactionSession)) {

            $result['success'] = true;

            foreach ($modelTransactionSession as $i => $dataTransactionSession) {

                $result['transaction'][$i]['order_id'] = substr($dataTransactionSession['order_id'], 0, 6);

                if (!empty($dataTransactionSession['transactionSessionDelivery'])) {

                    $result['transaction'][$i]['driver_name'] = $dataTransactionSession['transactionSessionDelivery']['driver']['full_name'];

                    if ($type == 'finish') {

                        $result['transaction'][$i]['upload_receipt_time'] = $dataTransactionSession['transactionSessionDelivery']['updated_at'];
                    } else {

                        $result['transaction'][$i]['take_order_time'] = $dataTransactionSession['transactionSessionDelivery']['created_at'];
                    }
                }

                $result['transaction'][$i]['menu_format'] = '';

                foreach ($dataTransactionSession['transactionItems'] as $dataTransactionItem) {

                    $result['transaction'][$i]['menu_format'] .= $dataTransactionItem['businessProduct']['name'] . '(' . $dataTransactionItem['amount'] . '), ';
                }

                $result['transaction'][$i]['menu_format'] = trim($result['transaction'][$i]['menu_format'], ', ');
            }
        } else {

            $result['message'] = $type == 'finish' ? 'Tidak ada transaksi yang selesai hari ini' : 'Tidak ada transaksi on progress hari ini';
        }

        return $result;
    }
}