<?php

namespace api\controllers\v2\user;

use core\models\UserPostLove;
use core\models\UserPostMain;
use sycomponent\Tools;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;

class UserPostController extends \yii\rest\Controller
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
        'linksEnvelope' => 'links',
        'metaEnvelope' => 'meta',
    ];

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
                        'activity-list' => ['GET'],
                        'love-business-review' => ['POST'],
                        'upload-business-review-photo' => ['POST'],
                    ],
                ],
            ]);
    }

    public function actionActivityList()
    {
        $userId = \Yii::$app->request->get('user_id');

        $modelUserPostMain = UserPostMain::find()
            ->select([
                'user_post_main.id', 'user_post_main.text', 'user_post_main.love_value', 'user_post_main.created_at',
                'business.id as business_id', 'business.unique_name as business_unique_name', 'business.name as business_name',
                'user.id as user_id', 'user.full_name as user_full_name', 'user.image as user_image'
            ])
            ->joinWith([
                'business' => function ($query) {

                    $query->select(['business.id']);
                },
                'user' => function ($query) {

                    $query->select(['user.id']);
                },
                'userPostMains child' => function ($query) {

                    $query->select([
                            'child.id', 'child.parent_id', 'child.image'
                        ])
                        ->andOnCondition(['child.is_publish' => true])
                        ->andOnCondition(['child.type' => 'Photo']);
                },
                'userPostLoves' => function ($query) use ($userId) {

                    $query->select([
                            'user_post_love.id', 'user_post_love.user_post_main_id'
                        ])
                        ->andOnCondition(['user_post_love.user_id' => $userId])
                        ->andOnCondition(['user_post_love.is_active' => true]);
                },
                'userVotes'=> function ($query) {

                    $query->select([
                            'user_vote.id', 'user_vote.vote_value', 'user_vote.user_post_main_id'
                        ]);
                },
                'userPostComments'=> function ($query) {

                    $query->select([
                            'user_post_comment.id', 'user_post_comment.user_post_main_id'
                        ]);
                }
            ])
            ->andWhere(['user_post_main.parent_id' => null])
            ->andWhere(['user_post_main.is_publish' => true])
            ->andWhere(['user_post_main.type' => 'Review'])
            ->orderBy(['user_post_main.created_at' => SORT_DESC])
            ->distinct()
            ->asArray();

        $provider = new ActiveDataProvider([
            'query' => $modelUserPostMain,
        ]);

        return $provider;
    }

    public function actionLoveBusinessReview()
    {
        $post = \Yii::$app->request->post();

        $result = [];

        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;

        if (!empty($post['user_id'])) {

            $modelUserPostMain = UserPostMain::find()
                ->andWhere(['id' => $post['id']])
                ->andWhere(['is_publish' => true])
                ->one();

            if (!empty($modelUserPostMain)) {

                $modelUserPostLove = UserPostLove::find()
                    ->andWhere(['unique_id' => $post['id'] . '-' . $post['user_id']])
                    ->one();

                if (!empty($modelUserPostLove)) {

                    $modelUserPostLove->is_active = !$modelUserPostLove->is_active;
                } else {

                    $modelUserPostLove = new UserPostLove();

                    $modelUserPostLove->user_post_main_id = $post['id'];
                    $modelUserPostLove->user_id = $post['user_id'];
                    $modelUserPostLove->is_active = true;
                    $modelUserPostLove->unique_id = $post['id'] . '-' . $post['user_id'];
                }

                if (($flag = $modelUserPostLove->save())) {

                    if ($modelUserPostLove->is_active) {

                        $modelUserPostMain->love_value += 1;
                    } else {

                        $modelUserPostMain->love_value -= 1;
                    }

                    $flag = $modelUserPostMain->save();
                }
            }
        } else {

            $result['error']['user_id'] = ['empty'];
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;
            $result['is_active'] = $modelUserPostLove->is_active;
            $result['love_value'] = $modelUserPostMain->love_value;
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['message'] = 'Proses like gagal disimpan';
        }

        return $result;
    }

    public function actionUploadBusinessReviewPhoto()
    {
        $post = \Yii::$app->request->post();

        $flag = false;

        $result = [];
        $transaction = \Yii::$app->db->beginTransaction();

        if (!empty($post['user_id'] && !empty($post['business_id']))) {

            $modelUserPostMainPhoto = new UserPostMain();

            if (!empty($modelUserPostMainPhoto)) {

                $image = Tools::uploadFileWithoutModel('/img/user_post/', 'image', $modelUserPostMainPhoto->id, '', true);

                if (($flag = !empty($image))) {

                    $modelUserPostMainPhoto->unique_id = \Yii::$app->security->generateRandomString();
                    $modelUserPostMainPhoto->business_id = $post['business_id'];
                    $modelUserPostMainPhoto->user_id = $post['user_id'];
                    $modelUserPostMainPhoto->type = 'Photo';
                    $modelUserPostMainPhoto->text = $post['text'];
                    $modelUserPostMainPhoto->image = $image;
                    $modelUserPostMainPhoto->is_publish = true;
                    $modelUserPostMainPhoto->love_value = 0;

                    if (!($flag = $modelUserPostMainPhoto->save())) {

                        $result['message'] = $modelUserPostMainPhoto->getErrors();
                    }
                } else {

                    $result['message'] = 'Upload gambar gagal';
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Business ID tidak boleh kosong';
        }

        if ($flag) {

            $result['success'] = true;
            $result['message'] = 'Upload foto Berhasil';

            $transaction->commit();
        } else {

            $result['success'] = false;

            $transaction->rollBack();
        }

        return $result;
    }
}