<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\imagine\Image;

class LoadImageController extends \yii\web\Controller {
    
    private $image;
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
                        'registry-business' => ['GET'],
                    ],
                ],
            ]);
    }
    
    public function actionRegistryBusiness($image, $w = null, $h = null)
    {
        $this->image = Yii::getAlias('@uploads') . '/img/registry_business/' . $image;
        
        if (!empty($w) || !empty($h)) {
            
            $this->image = Yii::getAlias('@uploads') . '/img/registry_business/'  . $w . 'x' . $h . $image;
            
            Image::thumbnail('@uploads' . '/img/registry_business/' . $image, $w, $h)
                ->save($this->image);
        }
        
        Yii::$app->getResponse()->getHeaders()
            ->set('Pragma', 'public')
            ->set('Expires', '0')
            ->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->set('Content-Transfer-Encoding', 'binary')
            ->set('Content-type', 'image/jpeg');
        
        try {
            
            Yii::$app->response->stream = fopen($this->image, 'r');
        } catch (\yii\base\ErrorException $e) {

            throw new NotFoundHttpException('The requested image does not exist.');
        }
        
        Yii::$app->response->format = Response::FORMAT_RAW;
        
        return Yii::$app->response->send();
    }
    
    public function actionUser($image, $w = null, $h = null)
    {
        $this->image = Yii::getAlias('@uploads') . '/img/user/' . $image;
        
        if (!empty($w) || !empty($h)) {
            
            $this->image = Yii::getAlias('@uploads') . '/img/user/'  . $w . 'x' . $h . $image;
            
            Image::thumbnail('@uploads' . '/img/user/' . $image, $w, $h)
            ->save($this->image);
        }
        
        Yii::$app->getResponse()->getHeaders()
        ->set('Pragma', 'public')
        ->set('Expires', '0')
        ->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
        ->set('Content-Transfer-Encoding', 'binary')
        ->set('Content-type', 'image/jpeg');
        
        try {
            
            Yii::$app->response->stream = fopen($this->image, 'r');
        } catch (\yii\base\ErrorException $e) {
            
            throw new NotFoundHttpException('The requested image does not exist.');
        }
        
        Yii::$app->response->format = Response::FORMAT_RAW;
        
        return Yii::$app->response->send();
    }
}