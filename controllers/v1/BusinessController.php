<?php

namespace api\controllers\v1;

use core\models\Business;
use core\models\BusinessHour;
use core\models\User;
use Yii;
use yii\filters\VerbFilter;
use core\models\TransactionSession;
use core\models\BusinessHourAdditional;
use core\models\BusinessDelivery;
use core\models\BusinessPayment;

class BusinessController extends \yii\rest\Controller
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
                        'get-operational-hours' => ['POST'],
                        'get-branch' => ['POST'],
                        'get-finish-order' => ['POST'],
                        'get-on-progress-order' => ['POST'],
                        'get-open-status' => ['POST'],
                        'update-open-status' => ['POST'],
                        'update-operational-hours' => ['POST'],
                        'delivery-list' => ['GET'],
                        'payment-list' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionGetOperationalHours()
    {
        $result = [];
        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['business_id'])) {

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
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }

    public function actionGetBranch()
    {
        $result = [];
        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['user_id'])) {

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

                        $result['business'][$i]['is_open'] = false;

                        if ($dataBusinessContactPerson['business']['is_open']) {

                            if (!empty($dataBusinessContactPerson['business']['businessHours'])) {

                                $result['business'][$i]['is_open'] = $this->checkOpenStatus($dataBusinessContactPerson['business']['businessHours']);
                            }
                        }
                    }
                } else {

                    $result['message'] = 'User ID tidak valid';
                }
            } else {

                $result['message'] = 'User ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter user_id tidak boleh kosong';
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

    public function actionGetOpenStatus()
    {
        $result = [];
        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['business_id'])) {

            $modelBusiness = Business::find()
                ->joinWith(['businessHours.businessHourAdditionals'])
                ->andWhere(['business.id' => \Yii::$app->request->post()['business_id']])
                ->asArray()->one();

            $result['is_open'] = false;

            if (!empty($modelBusiness)) {

                $result['success'] = true;

                if ($modelBusiness['is_open']) {

                    if (!empty($modelBusiness['businessHours'])) {

                        $result['is_open'] = $this->checkOpenStatus($modelBusiness['businessHours']);
                    }
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }

    public function actionUpdateOpenStatus()
    {
        $result = [];
        $result['success'] = false;

        $post = \Yii::$app->request->post();

        if (!empty($post['business_id'])) {

            $modelBusiness = Business::findOne(['id' => $post['business_id']]);

            if (!empty($modelBusiness)) {

                if (!empty($post['is_open'])) {

                    $modelBusiness->is_open = strtolower($post['is_open']) == 'true' ? true : false;

                    if ($modelBusiness->save()) {

                        $result['success'] = true;
                        $result['message'] = 'Update Status buka/tutup berhasil';
                    } else {

                        $result['message'] = 'Update Status buka/tutup gagal, terdapat kesalahan saat menyimpan data';
                    }
                } else {

                    $result['message'] = 'Parameter is_open tidak boleh kosong';
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }

    public function actionUpdateOperationalHours()
    {
        $flag = false;

        $result = [];

        $result['success'] = false;
        $result['message'] = 'Update gagal, terjadi kesalahan saat menyimpan data';

        $post = \Yii::$app->request->post();

        if (!empty($post['day']) && !empty($post['business_id'])) {

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

                if (!empty($post['is_open'])) {

                    if ($post['is_open'] && strtolower($post['is_open']) == "true") {

                        if (!empty($post['hour'])) {

                            $postHour = json_decode($post['hour'], true);

                            if (!empty($modelBusinessHour->businessHourAdditionals)) {

                                foreach ($modelBusinessHour->businessHourAdditionals as $idx => $dataBusinessHourAdditional) {

                                    if ((count($postHour) - 1) < ($idx + 1)) {

                                        if (!($flag = BusinessHourAdditional::deleteAll(['id' => $dataBusinessHourAdditional->id]))) {

                                            break;
                                        }
                                    }
                                }
                            }

                            foreach ($postHour as $i => $hour) {

                                if ($i == 0) {

                                    $modelBusinessHour->open_at = $hour['open'];
                                    $modelBusinessHour->close_at = $hour['close'];
                                    $modelBusinessHour->is_open = true;

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
                        } else {

                            $result['message'] = 'Parameter hour tidak boleh kosong';
                        }
                    } else {

                        $modelBusinessHour->is_open = false;
                        $modelBusinessHour->open_at = null;
                        $modelBusinessHour->close_at = null;

                        if (($flag = $modelBusinessHour->save())) {

                            if (!empty($modelBusinessHour->businessHourAdditionals)) {

                                $flag = BusinessHourAdditional::deleteAll(['day' => $post['day'], 'business_hour_id' => $modelBusinessHour->id]);
                            }
                        }
                    }

                    if ($flag) {

                        $transaction->commit();

                        $result['success'] = true;
                        $result['message'] = 'Update jam operasional berhasil';
                    } else {

                        $transaction->rollback();
                    }
                } else {

                    $result['message'] = 'Parameter is_open tidak boleh kosong';
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter day & business_id tidak boleh kosong';
        }

        return $result;
    }

    public function actionDeliveryList($id)
    {
        $model = BusinessDelivery::find()
        ->select(['business_delivery.id', 'delivery_method.delivery_name', 'business_delivery.note', 'business_delivery.description', 'business_delivery.delivery_method_id'])
            ->joinWith([
                'deliveryMethod' => function ($query) {

                    $query->select(['delivery_method.id']);
                },
            ])
            ->andWhere(['business_delivery.business_id' => $id])
            ->andWhere(['business_delivery.is_active' => true])
            ->asArray()->all();

        return $model;
    }

    public function actionPaymentList($id)
    {
        $model = BusinessPayment::find()
            ->select(['business_payment.id', 'payment_method.payment_name', 'business_payment.note', 'business_payment.description', 'business_payment.payment_method_id'])
            ->joinWith([
                'paymentMethod' => function ($query) {

                    $query->select(['payment_method.id']);
                },
            ])
            ->andWhere(['business_payment.business_id' => $id])
            ->andWhere(['business_payment.is_active' => true])
            ->asArray()->all();

        return $model;
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

    private function checkOpenStatus($modelBusinessHour)
    {
        $isOpen = false;

        $days = \Yii::$app->params['days'];

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $now = \Yii::$app->formatter->asTime(time());

        \Yii::$app->formatter->timeZone = 'UTC';

        foreach ($modelBusinessHour as $dataBusinessHour) {

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

        return $isOpen;
    }
}