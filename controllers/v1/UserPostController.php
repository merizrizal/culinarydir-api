<?php

namespace api\controllers\v1;

use core\models\UserPostMain;
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
                    ],
                ],
            ]);
    }

    public function actionActivityList()
    {
        $userId = Yii::$app->request->get('user_id');

        $modelUserPostMain = UserPostMain::find()
            ->select([
                'user_post_main.id', 'user_post_main.text', 'user_post_main.love_value', 'user_post_main.created_at',
                'business.id as business_id', 'business.name as business_name',
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
                        ])->andOnCondition([
                            'child.is_publish' => true,
                            'child.type' => 'Photo'
                        ]);
                },
                'userPostLoves' => function ($query) use ($userId) {

                    $query->select([
                            'user_post_love.id', 'user_post_love.user_post_main_id'
                        ])->andOnCondition([
                            'user_post_love.user_id' => $userId,
                            'user_post_love.is_active' => true
                        ]);
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
}