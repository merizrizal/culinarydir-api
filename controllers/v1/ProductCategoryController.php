<?php

namespace api\controllers\v1;

use core\models\ProductCategory;
use Yii;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;

class ProductCategoryController extends \yii\rest\Controller 
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
                        'list' => ['GET'],
                    ],
                ],
            ]);
    }
    
    public function actionList()
    {
        $modelProductCategory = ProductCategory::find()
            ->select([
                'id', 
                '(CASE 
                    WHEN type = \'General\' THEN \'A\'
                    ELSE \'B\'
                END) AS type', 
                'name'                
            ])
            ->andFilterWhere(['ilike', 'name', Yii::$app->request->get('keyword')])
            ->andWhere(['<>', 'type', 'Menu'])
            ->andWhere(['is_active' => true])
            ->orderBy(['type' => SORT_ASC, 'name' => SORT_ASC])
            ->asArray();
        
        $provider = new ActiveDataProvider([
            'query' => $modelProductCategory,
        ]);
        
        return $provider;
    }
}