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
                        'load-image' => ['GET'],
                    ],
                ],
            ]);
    }
    
    public function actionRegistryBusiness($image, $w = null, $h = null)
    {
        return $this->loadImage('registry_business', $image, $w, $h);
    }
    
    public function actionUser($image, $w = null, $h = null)
    {
        return $this->loadImage('user', $image, $w, $h);
    }
    
    public function actionUserPost($image, $w = null, $h = null)
    {
        return $this->loadImage('user_post', $image, $w, $h);
    }
    
    public function actionBusinessPromo($image, $w = null, $h = null)
    {
        return $this->loadImage('business_promo', $image, $w, $h);
    }
    
    public function actionPromo($image, $w = null, $h = null)
    {
        return $this->loadImage('promo', $image, $w, $h);
    }
    
    private function loadImage($directory, $image, $w = null, $h = null)
    {
        $this->image = Yii::getAlias('@uploads') . '/img/' . $directory . '/' . $image;
        
        if (!empty($w) || !empty($h)) {
            
            $this->image = Yii::getAlias('@uploads') . '/img/' . $directory . '/'  . $w . 'x' . $h . $image;
            
            Image::thumbnail('@uploads' . '/img/' . $directory . '/' . $image, $w, $h)
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