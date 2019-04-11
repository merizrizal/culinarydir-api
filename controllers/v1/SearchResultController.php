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
                ->select([
                    'business.id', 'business.name', 'business.unique_name', 'business.is_active', 'business.membership_type_id',
                    'business_detail.business_id', 'business_detail.price_min', 'business_detail.price_max',
                    'business_detail.voters', 'business_detail.vote_value', 'business_detail.vote_points', 'business_detail.love_value',
                    'business_location.address_type', 'business_location.address',
                    'business_location.city_id', 'business_location.coordinate',
                    'city.name as city_name'
                ])
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
                            'business_detail.voters', 'business_detail.vote_value', 'business_detail.vote_points', 'business_detail.love_value'
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
                ->andFilterWhere([
                    'OR', 
                    ['ilike', 'business.name', Yii::$app->request->get('keyword')],
                    ['ilike', 'product_category.name', Yii::$app->request->get('keyword')],
                    ['ilike', 'business_location.address', Yii::$app->request->get('keyword')]
                ])
                ->andFilterWhere(['business_product_category.product_category_id' => Yii::$app->request->get('product_category_id')]);
                
            if (Yii::$app->request->get('search_type') == 'favorite') {
                
                $modelBusiness = $modelBusiness->andFilterWhere(['business_category.category_id' => Yii::$app->request->get('category_id')]);
            } else if (Yii::$app->request->get('search_type') == 'online-order') {
                
                $modelBusiness = $modelBusiness->andFilterWhere(['product_service.code_name' => 'order-online']);
            }
            
            if (!empty(Yii::$app->request->get('coordinate_lat')) && !empty(Yii::$app->request->get('coordinate_lng')) && !empty(Yii::$app->request->get('radius_map'))) {
                
                $latitude = Yii::$app->request->get('coordinate_lat');
                $longitude = Yii::$app->request->get('coordinate_lng');
                $radius = Yii::$app->request->get('radius_map');
                
                $modelBusiness = $modelBusiness->andWhere('(acos(sin(radians(split_part("business_location"."coordinate" , \',\', 1)::double precision)) * sin(radians(' . $latitude . ')) + cos(radians(split_part("business_location"."coordinate" , \',\', 1)::double precision)) * cos(radians(' . $latitude . ')) * cos(radians(split_part("business_location"."coordinate" , \',\', 2)::double precision) - radians(' . $longitude . '))) * 6356 * 1000) <= ' . $radius);
            }
            
            if (!empty(Yii::$app->request->get('price_min')) || !empty(Yii::$app->request->get('price_max'))) {
                
                $modelBusiness = $modelBusiness->andFilterWhere([
                    'OR',
                    '(' . Yii::$app->request->get('price_min') . ' >= "business_detail"."price_min" AND ' . Yii::$app->request->get('price_min') . ' <= "business_detail"."price_max")',
                    '(' . Yii::$app->request->get('price_max') . ' >= "business_detail"."price_min" AND ' . Yii::$app->request->get('price_max') . ' <= "business_detail"."price_max")',
                    '("business_detail"."price_min" >= ' . Yii::$app->request->get('price_min') . ' AND "business_detail"."price_min" <= ' . Yii::$app->request->get('price_max') . ')',
                    '("business_detail"."price_max" >= ' . Yii::$app->request->get('price_min') . ' AND "business_detail"."price_max" <= ' . Yii::$app->request->get('price_max') . ')',
                ]);
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