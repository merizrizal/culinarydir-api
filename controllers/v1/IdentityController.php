<?php

namespace api\controllers\v1;

use Yii;
use yii\filters\VerbFilter;
use common\models\LoginForm;
use core\models\User;
use core\models\UserLevel;
use core\models\UserSocialMedia;
use core\models\UserPerson;
use core\models\Person;
use frontend\models\UserRegister;

class IdentityController extends \yii\rest\Controller {
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(
            [],
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'login' => ['POST'],
                        'login-socmed' => ['POST'],
                        'register' => ['POST']
                    ],
                ],
            ]);
    }
    
    public function actionLogin()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $model = new LoginForm();
        $model->login_id = $post['login_id'];
        $model->password = $post['password'];
        
        $flag = false;
        
        if (($flag = $model->login())) {
            
            $randomString = Yii::$app->security->generateRandomString();
            $randomStringHalf = substr($randomString, 16);
            
            $model->getUser()->login_token = substr($randomString, 0, 15) . $model->getUser()->id . $randomStringHalf . '_' . time();
            
            if (!($flag = $model->getUser()->save())) {
                
                $result['error'] = $model->getUser()->getErrors();
            }
        } else {
            
            $result['error'] = $model->getErrors();
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['message'] = 'Login Berhasil';
            $result['user'] = $model->getUser()->username;
            $result['login_token'] = $model->getUser()->login_token;
        } else {
            
            $result['success'] = false;
            $result['message'] = 'Login gagal';
        }
        
        return $result;
    }
    
    public function actionLoginSocmed()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $modelUser = User::find()
            ->joinWith(['userSocialMedia'])
            ->andWhere(['email' => $post['socmed_email']])
            ->one();
        
        if (empty($modelUser)) {
            
            $result['success'] = false;
            $result['message'] = 'Redirect ke halaman register';
        } else {
            
            $transaction = Yii::$app->db->beginTransaction();
            $flag = false;
            
            $modelUserSocialMedia = !empty($modelUser->userSocialMedia) ? $modelUser->userSocialMedia : new UserSocialMedia();
            
            if (strtolower($post['socmed']) === 'facebook') {
                
                if (empty($modelUserSocialMedia['facebook_id'])) {
                    
                    $modelUserSocialMedia->user_id = $modelUser->id;
                    $modelUserSocialMedia->facebook_id = $post['socmed_id'];
                    
                    if (!($flag = $modelUserSocialMedia->save())) {
                        
                        $result['error'] = $modelUserSocialMedia->getErrors();
                    }
                } else {
                    
                    $flag = ($modelUserSocialMedia->facebook_id === $post['socmed_id']);
                } 
            } else if (strtolower($post['socmed']) === 'google') {
                
                if (empty($modelUserSocialMedia['google_id'])) {
                    
                    $modelUserSocialMedia->user_id = $modelUser->id;
                    $modelUserSocialMedia->google_id = $post['socmed_id'];
                    
                    if (!($flag = $modelUserSocialMedia->save())) {
                        
                        $result['error'] = $modelUserSocialMedia->getErrors();
                    }
                } else {
                    
                    $flag = ($modelUserSocialMedia->google_id === $post['socmed_id']);
                }
            }
            
            if ($flag) {
                
                $model = new LoginForm();
                $model->useSocmed = true;
                $model->login_id = $post['socmed_email'];
                
                if (($flag = $model->login())) {
                    
                    $randomString = Yii::$app->security->generateRandomString();
                    $randomStringHalf = substr($randomString, 16);
                    
                    $model->getUser()->login_token = substr($randomString, 0, 15) . $model->getUser()->id . $randomStringHalf . '_' . time();
                    
                    if (!($flag = $model->getUser()->save())) {
                        
                        $result['error'] = $model->getUser()->getErrors();
                    }
                } else {
                    
                    $result['error'] = $model->getErrors();
                }
            } 
            
            if ($flag) {
                
                $transaction->commit();
                
                $result['success'] = true;
                $result['message'] = 'Login dengan ' . $result['socmed'] . ' berhasil';
                $result['socmed_email'] = $post['socmed_email'];
                $result['socmed_id'] = $post['socmed_id'];
                $result['socmed'] = $post['socmed'];
                $result['login_token'] = $model->getUser()->login_token;
            } else {
                
                $transaction->rollBack();
                
                $result['success'] = false;
                $result['message'] = 'Login gagal';
            }
        }
        
        return $result;
    }
    
    public function actionRegister()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $modelUserRegister = new UserRegister();
        $modelPerson = new Person();
        $modelUserSocialMedia = new UserSocialMedia();
        
        $userLevel = UserLevel::find()
            ->andWhere(['nama_level' => 'User'])
            ->asArray()->one();
        
        $transaction = Yii::$app->db->beginTransaction();
        $flag = false;
    
        $modelUserRegister->user_level_id = $userLevel['id'];
        $modelUserRegister->email = $post['email'];
        $modelUserRegister->username = $post['username'];
        $modelUserRegister->full_name = $post['first_name'] . ' ' . $post['last_name'];
        $modelUserRegister->password = $post['password'];
        $modelUserRegister->password_repeat = $post['password_repeat'];
        
        if (($flag = $modelUserRegister->validate())) {
            
            $modelUserRegister->setPassword($post['password']);
            $modelUserRegister->password_repeat = $modelUserRegister->password;
    
            if (($flag = $modelUserRegister->save())) {
                
                $modelPerson->first_name = $post['first_name'];
                $modelPerson->last_name = $post['last_name'];
                $modelPerson->email = $post['email'];
                $modelPerson->phone = $post['phone'];
                $modelPerson->city_id = $post['city_id'];
                
                if (($flag = $modelPerson->save())) {
                    
                    $modelUserPerson = new UserPerson();
                    
                    $modelUserPerson->user_id = $modelUserRegister->id;
                    $modelUserPerson->person_id = $modelPerson->id;
                    
                    if (($flag = $modelUserPerson->save())) {
                        
                        if (!empty($post['socmed_id']) && !empty($post['socmed'])) {
                            
                            $modelUserSocialMedia->user_id = $modelUserRegister->id;
                            
                            if (strtolower($post['socmed']) === 'google') {
                                
                                $modelUserSocialMedia->google_id = $post['socmed_id'];
                            } else if (strtolower($post['socmed']) === 'facebook') {
                                
                                $modelUserSocialMedia->facebook_id = $post['socmed_id'];
                            }
                            
                            if (($flag = $modelUserSocialMedia->save())) {
                                
                                Yii::$app->mailer->compose(['html' => 'register_confirmation'], [
                                    'email' => $post['email'],
                                    'full_name' => $post['first_name'] . ' ' . $post['last_name'],
                                    'socmed' => strtolower($post['socmed']) === 'google' ? 'Google' : 'Facebook',
                                ])
                                ->setFrom([Yii::$app->params['supportEmail'] => Yii::$app->name . ' Support'])
                                ->setTo($post['email'])
                                ->setSubject('Welcome to ' . Yii::$app->name)
                                ->send();
                            } else {
                                
                                $result['error'] = $modelUserSocialMedia->getErrors();
                            }
                        } else {
                            
                            $randomString = Yii::$app->security->generateRandomString();
                            $randomStringHalf = substr($randomString, 16);
                            $modelUserRegister->not_active = true;
                            $modelUserRegister->account_activation_token = substr($randomString, 0, 15) . $modelUserRegister->id . $randomStringHalf . '_' . time();
                            
                            if (($flag = $modelUserRegister->save())) {
                                
                                Yii::$app->mailer->compose(['html' => 'account_activation'], [
                                    'email' => $post['email'],
                                    'full_name' => $post['first_name'] . ' ' . $post['last_name'],
                                    'userToken' => $modelUserRegister->account_activation_token
                                ])
                                ->setFrom([Yii::$app->params['supportEmail'] => Yii::$app->name . ' Support'])
                                ->setTo($post['email'])
                                ->setSubject(Yii::$app->name . ' Account Activation')
                                ->send();
                            } else {
                                
                                $result['error'] = $modelUserRegister->getErrors();
                            }
                        }
                    } else {
                        
                        $result['error'] = $modelUserPerson->getErrors();
                    }
                } else {
                    
                    $result['error'] = $modelPerson->getErrors();
                }
            } else {
                
                $result['error'] = $modelUserRegister->getErrors();
            }
        } else {
            
            $result['error'] = $modelUserRegister->getErrors();
        }
        
        if ($flag) {
            
            $transaction->commit();
            
            $result['success'] = true;
        } else {
            
            $transaction->rollBack();
            
            $result['success'] = false;
        }
        
        return $result;
    }
}