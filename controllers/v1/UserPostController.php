<?php

namespace api\controllers\v1;

use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use core\models\UserPostMain;

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
    
    public function actionActivityList($userId)
    {
        $modelUserPostMain = UserPostMain::find()
            ->joinWith([
                'business',
                'business.businessLocation',
                'business.businessLocation.city',
                'user',
                'userPostMains child' => function ($query) {
            
                    $query->andOnCondition(['child.is_publish' => true]);
                },
                'userPostLoves' => function ($query) use ($userId) {
            
                    $query->andOnCondition([
                        'user_post_love.user_id' => $userId,
                        'user_post_love.is_active' => true
                    ]);
                },
                'userVotes',
                'userPostComments'
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