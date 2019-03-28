<?php

namespace api\controllers\v1;

use common\models\LoginForm;
use core\models\Person;
use core\models\User;
use core\models\UserLevel;
use core\models\UserPerson;
use core\models\UserSocialMedia;
use frontend\models\RequestResetPassword;
use frontend\models\ResetPassword;
use frontend\models\UserRegister;
use Yii;
use yii\filters\VerbFilter;

class IdentityController extends \yii\rest\Controller {
    
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
                        'login' => ['POST'],
                        'login-socmed' => ['POST'],
                        'register' => ['POST'],
                        'request-reset-password-token' => ['POST'],
                        'token-verification' => ['POST'],
                        'reset-password' => ['POST']
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
            $result['username'] = $model->getUser()->username;
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
            $result['message'] = 'Email belum terdaftar. Silakan registrasi terlebih dahulu.';
            $result['action'] = 'register';
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
                    
                    if (!($flag = ($modelUserSocialMedia->facebook_id === $post['socmed_id']))) {
                        
                        $result['action'] = 'register';
                    }
                }
            } else if (strtolower($post['socmed']) === 'google') {
                
                if (empty($modelUserSocialMedia['google_id'])) {
                    
                    $modelUserSocialMedia->user_id = $modelUser->id;
                    $modelUserSocialMedia->google_id = $post['socmed_id'];
                    
                    if (!($flag = $modelUserSocialMedia->save())) {
                        
                        $result['error'] = $modelUserSocialMedia->getErrors();
                    }
                } else {
                    
                    if (!($flag = ($modelUserSocialMedia->google_id === $post['socmed_id']))) {
                        
                        $result['action'] = 'register';
                    }
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
                $result['message'] = 'Login dengan ' . $post['socmed'] . ' berhasil';
                $result['socmed_email'] = $post['socmed_email'];
                $result['socmed_id'] = $post['socmed_id'];
                $result['socmed'] = $post['socmed'];
                $result['login_token'] = $model->getUser()->login_token;
            } else {
                
                $transaction->rollBack();
                
                $result['success'] = false;
                $result['message'] = 'Login gagal. Akun login anda tidak terdaftar.';
            }
        }
        
        return $result;
    }
    
    public function actionRegister()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $transaction = Yii::$app->db->beginTransaction();
        $flag = false;
        
        $userLevel = UserLevel::find()
            ->andWhere(['nama_level' => 'User'])
            ->asArray()->one();
    
        $modelUserRegister = new UserRegister();
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
                
                $modelPerson = new Person();
                $modelPerson->first_name = $post['first_name'];
                $modelPerson->last_name = $post['last_name'];
                $modelPerson->email = $post['email'];
                $modelPerson->phone = !empty($post['phone']) ? $post['phone'] : null;
                $modelPerson->city_id = !empty($post['city_id']) ? $post['city_id'] : null;
                
                if (($flag = $modelPerson->save())) {
                    
                    $modelUserPerson = new UserPerson();
                    
                    $modelUserPerson->user_id = $modelUserRegister->id;
                    $modelUserPerson->person_id = $modelPerson->id;
                    
                    if (($flag = $modelUserPerson->save())) {
                        
                        if (!empty($post['socmed_id']) && !empty($post['socmed'])) {
                            
                            $modelUserSocialMedia = new UserSocialMedia();
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
                                    'isFromApi' => true
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
                                    'userToken' => $modelUserRegister->account_activation_token,
                                    'isFromApi' => true
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
    
    public function actionRequestResetPasswordToken()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $flag = false;
        
        $model = new RequestResetPassword();
        $model->email = $post['email'];
        $model->isRequestToken = true;
        
        if (($flag = $model->validate())) {
            
            if (($flag = $model->sendEmail(true))) {
                
                $result['message'] = Yii::t('app', 'We have sent a verification code to') . ' ' . $model->email;
            } else {
                
                $result['message'] = Yii::t('app', 'An error has occurred while requesting password reset');
            }
        } else {
            
            $result['error'] = $model->getErrors();
        }
        
        if ($flag) {
            
            $result['success'] = true;
            $result['email'] = $model->email;
        } else {
            
            $result['success'] = false;
        }
        
        return $result;
    }
    
    public function actionTokenVerification()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $model = new RequestResetPassword();
        $model->email = $post['email'];
        $model->verificationCode = $post['verification_code'];
        
        if ($model->validate()) {
            
            $result['success'] = true;
            $result['email'] = $model->email;
            $result['token'] = $model->token;
        } else {
            
            $result['success'] = false;
            $result['error'] = $model->getErrors();
        }
        
        return $result;
    }
    
    public function actionResetPassword()
    {
        $post = Yii::$app->request->post();
        
        $result = [];
        
        $model = new ResetPassword();
        $model->email = $post['email'];
        $model->token = $post['token'];
        $model->password = $post['password'];
        
        if ($model->validate() && $model->resetPassword()) {
            
            $result['success'] = true;
            $result['message'] = 'Password baru berhasil disimpan';
            $result['username'] = $model->username;
        } else {
            
            $result['success'] = false;
            $result['error'] = $model->getErrors();
        }
        
        return $result;
    }
}