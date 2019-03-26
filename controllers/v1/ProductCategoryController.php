<?php

namespace api\controllers\v1;

use core\models\ProductCategory;
use Yii;
use yii\filters\VerbFilter;

class ProductCategoryController extends \yii\rest\Controller {
    
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
        $model = ProductCategory::find()
            ->select(['id', 'type', 'name'])
            ->andFilterWhere(['ilike', 'name', Yii::$app->request->get('keyword')])
            ->andWhere(['<>', 'type', 'Menu'])
            ->andWhere(['is_active' => true])
            ->orderBy(['name' => SORT_ASC])
            ->asArray()->all();
        
        $productCategory = [];
        
        foreach ($model as $dataProductCategory) {
            
            if ($dataProductCategory['type'] == 'General') {
                
                $productCategory['parent'][] = $dataProductCategory;
            } else {
                
                $productCategory['child'][] = $dataProductCategory;
            }
        }
        
        return array_merge(
            !empty($productCategory['parent']) ? $productCategory['parent'] : [], 
            !empty($productCategory['child']) ? $productCategory['child'] : []
        );
    }
}