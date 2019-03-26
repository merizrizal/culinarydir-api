<?php

namespace api\controllers\v1;

use core\models\Business;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;

class SearchResultController extends \yii\rest\Controller {
    
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
        $provider = null;
        
        if (Yii::$app->request->get('search_type') == 'favorite' || Yii::$app->request->get('search_type') == 'online-order') {
        
            $modelBusiness = Business::find()
                ->select(['business.id', 'business.name', 'business.unique_name', 'business.is_active', 'business.membership_type_id', 'business_image.image'])
                ->joinWith([
                    
                    'businessCategories' => function ($query) {
                    
                        $query->select(['business_category.category_id', 'business_category.business_id'])
                            ->andOnCondition(['business_category.is_active' => true]);
                    },
                    'businessCategories.category' => function ($query) {
                        
                        $query->select(['category.id', 'category.name']);
                    },
                    'businessImages' => function ($query) {
                    
                        $query->select(['business_image.business_id' , 'business_image.image'])
                            ->andOnCondition(['type' => 'Profile']);
                    },
                    'businessLocation' => function ($query) {
                    
                        $query->select([
                            'business_location.business_id', 'business_location.address_type', 'business_location.address', 
                            'business_location.city_id', 'business_location.coordinate'
                        ]);
                    },
                    'businessLocation.city' => function ($query) {
                    
                        $query->select(['city.id', 'city.name']);
                    },
                    'businessProductCategories' => function ($query) {
                    
                        $query->select(['business_product_category.business_id', 'business_product_category.product_category_id'])
                            ->andOnCondition(['business_product_category.is_active' => true]);
                    },
                    'businessProductCategories.productCategory' => function ($query) {
                    
                        $query->select(['product_category.id', 'product_category.name'])
                            ->andOnCondition(['<>', 'product_category.type', 'Menu']);
                    },
                    'businessDetail' => function ($query) {
                    
                        $query->select([
                            'business_detail.business_id', 'business_detail.price_min', 'business_detail.price_max', 
                            'business_detail.voters', 'business_detail.vote_value', 'business_detail.vote_points'
                        ]);
                    },
                    'userLoves' => function ($query) {
                    
                        $query->select(['user_love.user_id', 'user_love.business_id'])
                            ->andOnCondition([
                                'user_love.user_id' => Yii::$app->request->get('user_id'),
                                'user_love.is_active' => true
                            ]);
                    },
                    'membershipType'  => function ($query) {
                    
                        $query->select(['membership_type.id']);
                    },
                    'membershipType.membershipTypeProductServices' => function ($query) {
                    
                        $query->select(['membership_type_product_service.product_service_id', 'membership_type_product_service.membership_type_id']);
                    },
                    'membershipType.membershipTypeProductServices.productService' => function ($query) {
                    
                        $query->select(['product_service.id', 'product_service.code_name']);
                    },
                ])
                ->andWhere(['membership_type.as_archive' => false])
                ->andFilterWhere(['business_location.city_id' => Yii::$app->request->get('city_id')])
                ->andFilterWhere(['lower(city.name)' => str_replace('-', ' ', Yii::$app->request->get('city'))])
                ->andFilterWhere([
                    'OR', 
                    ['ilike', 'business.name', Yii::$app->request->get('keyword')],
                    ['ilike', 'product_category.name', Yii::$app->request->get('keyword')],
                    ['ilike', 'business_location.address', Yii::$app->request->get('keyword')]
                ])
                ->andFilterWhere(['business_product_category.product_category_id' => Yii::$app->request->get('product_category_id')]);
                
            if (Yii::$app->request->get('search_type') == 'favorite') {
                                
            } else if (Yii::$app->request->get('search_type') == 'online-order') {
                
                $modelBusiness = $modelBusiness->andFilterWhere(['product_service.code_name' => 'order-online']);
            }
                
            $modelBusiness = $modelBusiness->orderBy(['business.id' => SORT_DESC])
                ->distinct()
                ->asArray();
                
            $provider = new ActiveDataProvider([
                'query' => $modelBusiness,
            ]);
        }
        
        return $provider;
    }
}