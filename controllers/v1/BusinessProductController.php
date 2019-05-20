<?php

namespace api\controllers\v1;

use yii\filters\VerbFilter;
use core\models\BusinessProduct;
use core\models\BusinessProductCategory;

class BusinessProductController extends \yii\rest\Controller
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
                        'get-active-menu' => ['POST'],
                        'get-not-active-menu' => ['POST'],
                        'get-active-category' => ['POST'],
                        'get-not-active-category' => ['POST'],
                        'create-menu' => ['POST'],
                        'update-menu' => ['POST']
                    ],
                ],
            ]);
    }

    public function actionGetActiveMenu()
    {
        return $this->getMenuList(false);
    }

    public function actionGetNotActiveMenu()
    {
        return $this->getMenuList(true);
    }

    public function actionGetActiveCategory()
    {
        return $this->getCategoryList(true);
    }

    public function actionGetNotActiveCategory()
    {
        return $this->getCategoryList(false);
    }

    public function actionCreateMenu()
    {
        $result = [];
        $result['success'] = false;

        $post = \Yii::$app->request->post();

        if (!empty($post['business_id'])) {

            $newModelBusinessProduct = new BusinessProduct();

            $newModelBusinessProduct->name = $post['product_name'];
            $newModelBusinessProduct->description = $post['product_description'];
            $newModelBusinessProduct->price = $post['product_price'];
            $newModelBusinessProduct->not_active = strtolower($post['not_active']) == 'true' ? true : false;
            $newModelBusinessProduct->business_id = $post['business_id'];
            $newModelBusinessProduct->business_product_category_id = $post['business_product_category_id'];

            if ($newModelBusinessProduct->save()) {

                $result['success'] = true;
                $result['message'] = 'Menu berhasil dibuat';
            } else {

                $result['message'] = 'Menu gagal dibuat';
            }
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }

    public function actionUpdateMenu()
    {
        $result = [];
        $result['success'] = false;

        $post = \Yii::$app->request->post();

        if (!empty($post['business_product_id'])) {

            $modelBusinessProduct = BusinessProduct::findOne(['business_product.id' => $post['business_product_id']]);

            $modelBusinessProduct->name = $post['product_name'];
            $modelBusinessProduct->description = $post['product_description'];
            $modelBusinessProduct->price = $post['product_price'];
            $modelBusinessProduct->business_product_category_id = $post['business_product_category_id'];

            if ($modelBusinessProduct->save()) {

                $result['success'] = true;
                $result['message'] = 'Menu berhasil diupdate';
            } else {

                $result['message'] = 'Menu gagal diupdate';
            }
        } else {

            $result['message'] = 'Parameter business_product_id tidak boleh kosong';
        }

        return $result;
    }

    private function getMenuList($not_active)
    {
        $result = [];
        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['business_id'])) {

            $modelBusinessProduct = BusinessProduct::find()
                ->select([
                    'business_product.id',
                    'business_product.name',
                    'business_product.description',
                    'business_product.price',
                    'business_product.order',
                    'business_product.business_product_category_id',
                    'product_category.name as category_name'
                ])
                ->joinWith(['businessProductCategory.productCategory'])
                ->andWhere(['business_product.business_id' => \Yii::$app->request->post()['business_id']])
                ->andWhere(['business_product.not_active' => $not_active])
                ->andWhere(['business_product_category.is_active' => true])
                ->andWhere(['product_category.is_active' => true])
                ->orderBy(['business_product.order' => SORT_ASC])
                ->asArray()->all();

            if (!empty($modelBusinessProduct)) {

                $result['success'] = true;

                foreach ($modelBusinessProduct as $i => $dataBusinessProduct) {

                    $result['menu'][$i]['id'] = $dataBusinessProduct['id'];
                    $result['menu'][$i]['name'] = $dataBusinessProduct['name'];
                    $result['menu'][$i]['description'] = $dataBusinessProduct['description'];
                    $result['menu'][$i]['price'] = $dataBusinessProduct['price'];
                    $result['menu'][$i]['category'] = $dataBusinessProduct['category_name'];
                    $result['menu'][$i]['order'] = $dataBusinessProduct['order'];
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }

    private function getCategoryList($is_active)
    {
        $result = [];
        $result['success'] = false;

        if (!empty(\Yii::$app->request->post()['business_id'])) {

            $modelBusinessProductCategory = BusinessProductCategory::find()
                ->select(['business_product_category.id', 'product_category.name', 'business_product_category.order', 'business_product_category.product_category_id'])
                ->joinWith(['productCategory'])
                ->andWhere(['business_product_category.business_id' => \Yii::$app->request->post()['business_id']])
                ->andWhere(['business_product_category.is_active' => $is_active])
                ->andWhere(['OR', ['product_category.type' => 'Menu'], ['product_category.type' => 'Specific-Menu']])
                ->andWhere(['product_category.is_active' => true])
                ->orderBy(['business_product_category.order' => SORT_ASC])
                ->asArray()->all();

            if (!empty($modelBusinessProductCategory)) {

                $result['success'] = true;

                foreach ($modelBusinessProductCategory as $i => $dataBusinessProductCategory) {

                    $result['category'][$i]['id'] = $dataBusinessProductCategory['id'];
                    $result['category'][$i]['name'] = $dataBusinessProductCategory['productCategory']['name'];
                    $result['category'][$i]['order'] = $dataBusinessProductCategory['order'];
                }
            } else {

                $result['message'] = 'Business ID tidak ditemukan';
            }
        } else {

            $result['message'] = 'Parameter business_id tidak boleh kosong';
        }

        return $result;
    }
}