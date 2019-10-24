<?php

namespace api\controllers\v2\user;

use core\models\BusinessDetail;
use core\models\UserLove;
use core\models\UserReport;
use yii\filters\VerbFilter;
use yii\web\Response;

class ActionController extends \yii\rest\Controller
{
    /**
     *
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
                        'love-business' => ['POST'],
                        'submit-report' => ['POST']
                    ]
                ]
            ]);
    }

    public function actionLoveBusiness()
    {
        if (!empty(($post = \Yii::$app->request->post()))) {

            $transaction = \Yii::$app->db->beginTransaction();
            $flag = false;

            $modelUserLove = UserLove::find()
                ->andWhere(['unique_id' => $post['user_id'] . '-' . $post['business_id']])
                ->one();

            if (!empty($modelUserLove)) {

                $modelUserLove->is_active = !$modelUserLove->is_active;
            } else {

                $modelUserLove = new UserLove();

                $modelUserLove->business_id = $post['business_id'];
                $modelUserLove->user_id = $post['user_id'];
                $modelUserLove->is_active = true;
                $modelUserLove->unique_id = $post['user_id'] . '-' . $post['business_id'];
            }

            if (($flag = $modelUserLove->save())) {

                $modelBusinessDetail = BusinessDetail::find()
                    ->where(['business_id' => $post['business_id']])
                    ->one();

                if ($modelUserLove->is_active) {

                    $modelBusinessDetail->love_value += 1;
                } else {

                    $modelBusinessDetail->love_value -= 1;
                }

                $flag = $modelBusinessDetail->save();
            }

            $result = [];

            if ($flag) {

                $transaction->commit();

                $result['success'] = true;
                $result['is_active'] = $modelUserLove->is_active;
                $result['love_value'] = $modelBusinessDetail->love_value;
            } else {

                $transaction->rollBack();

                $result['success'] = false;
                $result['message'] = 'Proses love gagal disimpan';
            }

            \Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        }
    }

    public function actionSubmitReport()
    {
        if (!empty(($post = \Yii::$app->request->post()))) {

            $modelUserReport = new UserReport();

            $modelUserReport->business_id = $post['business_id'];
            $modelUserReport->user_id = $post['user_id'];
            $modelUserReport->report_status = $post['UserReport']['report_status'];
            $modelUserReport->text = $post['UserReport']['text'];

            $result = [];

            if ($modelUserReport->save()) {

                $result['success'] = true;
                $result['icon'] = 'aicon aicon-icon-tick-in-circle';
                $result['title'] = 'Report Berhasil';
                $result['message'] = 'Report Anda berhasil disimpan.';
                $result['type'] = 'success';
            } else {

                $result['success'] = false;
                $result['icon'] = 'aicon aicon-icon-info';
                $result['title'] = 'Report Gagal';
                $result['message'] = 'Report Anda gagal disimpan.';
                $result['type'] = 'danger';
            }

            \Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        }
    }
}