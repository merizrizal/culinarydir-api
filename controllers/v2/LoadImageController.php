<?php

namespace api\controllers\v2;

use yii\filters\VerbFilter;
use yii\imagine\Image;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class LoadImageController extends \yii\web\Controller
{
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
                        'user' => ['GET'],
                        'user-post' => ['GET'],
                        'business-promo' => ['GET'],
                        'promo' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionRegistryBusiness($image, $w = null, $h = null)
    {
        return $this->loadImage('registry_business/', $image, $w, $h);
    }

    public function actionUser($image, $w = null, $h = null)
    {
        return $this->loadImage('user/', $image, $w, $h, 'default-avatar.png');
    }

    public function actionUserPost($image, $w = null, $h = null)
    {
        return $this->loadImage('user_post/', $image, $w, $h);
    }

    public function actionBusinessPromo($image, $w = null, $h = null)
    {
        return $this->loadImage('business_promo/', $image, $w, $h);
    }

    public function actionPromo($image, $w = null, $h = null)
    {
        return $this->loadImage('promo/', $image, $w, $h);
    }

    public function actionLoadImage($image, $w = null, $h = null)
    {
        return $this->loadImage('', $image, $w, $h);
    }

    private function loadImage($directory, $image, $w = null, $h = null, $defaultImage = 'image-no-available.jpg')
    {
        if (empty($image)) {

            return $this->loadImage('', $defaultImage, $w, $h);
        }

        try {

            $this->image = \Yii::getAlias('@uploads') . '/img/' . $directory . $image;

            if (!empty($w) || !empty($h)) {

                $this->image = \Yii::getAlias('@uploads') . '/img/' . $directory . $w . 'x' . $h . $image;

                Image::thumbnail('@uploads' . '/img/' . $directory . $image, $w, $h)
                    ->save($this->image);
            }
        } catch (\Imagine\Exception\InvalidArgumentException $e) {

            return $this->loadImage('', $defaultImage, $w, $h);
        } catch (\Imagine\Exception\Exception $e) {

            return $this->loadImage('', $defaultImage, $w, $h);
        } catch (\Exception $e) {

            return $this->loadImage('', $defaultImage, $w, $h);
        }

        \Yii::$app->formatter->locale = 'en_US';

        \Yii::$app->getResponse()->getHeaders()
            ->set('Pragma', 'public')
            ->set('Expires', \Yii::$app->formatter->asDatetime((time() + 60 * 60 * 24 * 60), 'EEE, dd MMM yyyy HH:mm:ss O'))
            ->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->set('Content-Transfer-Encoding', 'binary')
            ->set('Content-Type', 'image/jpeg');

        try {

            \Yii::$app->response->stream = fopen($this->image, 'r');
        } catch (\yii\base\ErrorException $e) {

            throw new NotFoundHttpException('The requested image does not exist.');
        }

        \Yii::$app->response->format = Response::FORMAT_RAW;

        return \Yii::$app->response->send();
    }
}