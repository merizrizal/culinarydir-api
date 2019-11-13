<?php

namespace api\controllers\v2\user;

use core\models\BusinessDetail;
use core\models\BusinessDetailVote;
use core\models\UserLove;
use core\models\UserPostMain;
use core\models\UserReport;
use core\models\UserPost;
use core\models\UserVote;
use sycomponent\Tools;
use yii\filters\VerbFilter;
use yii\web\Response;
use frontend\models\Post;

class ActionController extends \yii\rest\Controller
{
    /**
     *
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
                        'love-business' => ['POST'],
                        'submit-report' => ['POST'],
                        'submit-review' => ['POST']
                    ]
                ]
            ]);
    }

    public function actionLoveBusiness()
    {
        if (!empty(($post = \Yii::$app->request->post()))) {

            $transaction = \Yii::$app->db->beginTransaction();
            $flag = false;

            $modelUserLove = UserLove::find()
                ->andWhere(['unique_id' => $post['user_id'] . '-' . $post['business_id']])
                ->one();

            if (!empty($modelUserLove)) {

                $modelUserLove->is_active = !$modelUserLove->is_active;
            } else {

                $modelUserLove = new UserLove();

                $modelUserLove->business_id = $post['business_id'];
                $modelUserLove->user_id = $post['user_id'];
                $modelUserLove->is_active = true;
                $modelUserLove->unique_id = $post['user_id'] . '-' . $post['business_id'];
            }

            if (($flag = $modelUserLove->save())) {

                $modelBusinessDetail = BusinessDetail::find()
                    ->where(['business_id' => $post['business_id']])
                    ->one();

                if ($modelUserLove->is_active) {

                    $modelBusinessDetail->love_value += 1;
                } else {

                    $modelBusinessDetail->love_value -= 1;
                }

                $flag = $modelBusinessDetail->save();
            }

            $result = [];

            if ($flag) {

                $transaction->commit();

                $result['success'] = true;
                $result['is_active'] = $modelUserLove->is_active;
                $result['love_value'] = $modelBusinessDetail->love_value;
            } else {

                $transaction->rollBack();

                $result['success'] = false;
                $result['message'] = 'Proses love gagal disimpan';
            }

            \Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        }
    }

    public function actionSubmitReport()
    {
        if (!empty(($post = \Yii::$app->request->post()))) {

            $modelUserReport = new UserReport();

            $modelUserReport->business_id = $post['business_id'];
            $modelUserReport->user_id = $post['user_id'];
            $modelUserReport->report_status = $post['UserReport']['report_status'];
            $modelUserReport->text = $post['UserReport']['text'];

            $result = [];

            if ($modelUserReport->save()) {

                $result['success'] = true;
                $result['message'] = 'Report Anda berhasil disimpan.';
            } else {

                $result['success'] = false;
                $result['message'] = 'Report Anda gagal disimpan.';
            }

            \Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        }
    }

    public function actionSubmitReview()
    {
        if (!empty(($post = \Yii::$app->request->post()))) {

            $modelUserPostMain = UserPostMain::find()
                ->joinWith(['userPostComments'])
                ->andWhere(['unique_id' => $post['business_id'] . '-' . $post['user_id']])
                ->one();

            if (!empty($modelUserPostMain)) {

                \Yii::$app->response->format = Response::FORMAT_JSON;
                return $this->updateReview($post, $modelUserPostMain);
            } else {

                \Yii::$app->response->format = Response::FORMAT_JSON;
                return $this->createReview($post);
            }
        }
    }

    private function createReview($post)
    {
        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;
        $result = [];

        $modelUserPostMain = new UserPostMain();
        $modelPost = new Post();

        $modelUserPostMain->unique_id = $post['business_id'] . '-' . $post['user_id'];
        $modelUserPostMain->business_id = $post['business_id'];
        $modelUserPostMain->user_id = $post['user_id'];
        $modelUserPostMain->type = 'Review';
        $modelUserPostMain->text = preg_replace("/\r\n/", "", $post['Post']['review']['text']);
        $modelUserPostMain->is_publish = true;
        $modelUserPostMain->love_value = 0;

        if (($flag = $modelUserPostMain->save())) {

            $modelUserPost = new UserPost();

            $modelUserPost->business_id = $modelUserPostMain->business_id;
            $modelUserPost->type = $modelUserPostMain->type;
            $modelUserPost->user_id = $modelUserPostMain->user_id;
            $modelUserPost->text = $modelUserPostMain->text;
            $modelUserPost->is_publish = $modelUserPostMain->is_publish;
            $modelUserPost->love_value = $modelUserPostMain->love_value;
            $modelUserPost->user_post_main_id = $modelUserPostMain->id;

            if (($flag = $modelUserPost->save())) {

                $maxFiles = 0;

                foreach ($modelPost->getValidators() as $postValidator) {

                    if (!empty($postValidator->maxFiles)) {

                        $maxFiles = $postValidator->maxFiles;
                    }
                }

                $images = Tools::uploadFilesWithoutModel('/img/user_post/', 'image', $modelUserPostMain->id, '', true);

                $dataUserPostMainPhoto = [];

                if (count($images) <= $maxFiles) {

                    foreach ($images as $photoIndex => $image) {

                        $modelUserPostMainPhoto = new UserPostMain();

                        $modelUserPostMainPhoto->parent_id = $modelUserPostMain->id;
                        $modelUserPostMainPhoto->unique_id = \Yii::$app->security->generateRandomString() . $photoIndex;
                        $modelUserPostMainPhoto->business_id = $post['business_id'];
                        $modelUserPostMainPhoto->user_id = $post['user_id'];
                        $modelUserPostMainPhoto->type = 'Photo';
                        $modelUserPostMainPhoto->image = $image;
                        $modelUserPostMainPhoto->is_publish = true;
                        $modelUserPostMainPhoto->love_value = 0;

                        if (($flag = $modelUserPostMainPhoto->save())) {

                            $modelUserPostMainPhoto->image = \Yii::$app->params['endPointLoadImage'] . 'user-post?image=' . $modelUserPostMainPhoto->image . '&w=72&h=72';

                            array_push($dataUserPostMainPhoto, $modelUserPostMainPhoto->toArray());

                            $modelUserPostPhoto = new UserPost();

                            $modelUserPostPhoto->parent_id = $modelUserPost->id;
                            $modelUserPostPhoto->business_id = $modelUserPostMainPhoto->business_id;
                            $modelUserPostPhoto->type = $modelUserPostMainPhoto->type;
                            $modelUserPostPhoto->user_id = $modelUserPostMainPhoto->user_id;
                            $modelUserPostPhoto->image = $modelUserPostMainPhoto->image;
                            $modelUserPostPhoto->is_publish = $modelUserPostMainPhoto->is_publish;
                            $modelUserPostPhoto->love_value = $modelUserPostMainPhoto->love_value;

                            if (!($flag = $modelUserPostPhoto->save())) {

                                break;
                            }
                        } else {

                            break;
                        }
                    }
                } else {

                    $flag = false;

                    $result['errorPhoto'] = \Yii::t('app', 'You can upload up to {limit} photos', ['limit' => $maxFiles]);
                }
            }
        }

        if ($flag) {

            foreach ($post['Post']['review']['rating'] as $ratingComponentId => $voteValue) {

                $modelUserVote = new UserVote();

                $modelUserVote->rating_component_id = $ratingComponentId;
                $modelUserVote->vote_value = $voteValue;
                $modelUserVote->user_post_main_id = $modelUserPostMain->id;

                if (!($flag = $modelUserVote->save())) {

                    $result['errorVote'] = $modelUserVote->getErrors();
                    break;
                } else {

                    if ($voteValue == 0) {

                        $flag = false;
                        $result['errorVote'] = $modelUserVote->getErrors();

                        break;
                    }
                }
            }
        }

        if ($flag) {

            $modelBusinessDetail = BusinessDetail::find()
                ->joinWith(['business'])
                ->andWhere(['business_id' => $modelUserPostMain->business_id])
                ->one();

            foreach ($post['Post']['review']['rating'] as $votePoint) {

                $modelBusinessDetail->total_vote_points += $votePoint;
            }

            $modelBusinessDetail->voters += 1;
            $modelBusinessDetail->vote_points = $modelBusinessDetail->total_vote_points / count($post['Post']['review']['rating']);
            $modelBusinessDetail->vote_value = $modelBusinessDetail->vote_points / $modelBusinessDetail->voters;

            $flag = $modelBusinessDetail->save();
        }

        if ($flag) {

            foreach ($post['Post']['review']['rating'] as $ratingComponentId => $votePoint) {

                $modelBusinessDetailVote = BusinessDetailVote::find()
                    ->andWhere(['business_id' => $modelUserPostMain->business_id])
                    ->andWhere(['rating_component_id' => $ratingComponentId])
                    ->one();

                if (empty($modelBusinessDetailVote)) {

                    $modelBusinessDetailVote = new BusinessDetailVote();

                    $modelBusinessDetailVote->business_id = $post['business_id'];
                    $modelBusinessDetailVote->rating_component_id = $ratingComponentId;
                }

                $modelBusinessDetailVote->total_vote_points += $votePoint;
                $modelBusinessDetailVote->vote_value = $modelBusinessDetailVote->total_vote_points / $modelBusinessDetail->voters;

                if (!($flag = $modelBusinessDetailVote->save())) {

                    break;
                }
            }
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;
            $result['message'] = 'Review anda berhasil disimpan';
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['message'] = 'Review anda gagal disimpan.';
        }

        return $result;
    }

    private function updateReview($post, $modelUserPostMain = [])
    {
        $transaction = \Yii::$app->db->beginTransaction();
        $flag = false;
        $result = [];

        $isUpdate = $modelUserPostMain->is_publish;

        $modelPost = new Post();

        $modelUserPostMain->text = preg_replace("/\r\n/", " ", $post['Post']['review']['text']);
        $modelUserPostMain->is_publish = true;
        $modelUserPostMain->created_at = \Yii::$app->formatter->asDatetime(time());

        if (($flag = $modelUserPostMain->save())) {

            $modelUserPost = new UserPost();

            $modelUserPost->business_id = $modelUserPostMain->business_id;
            $modelUserPost->type = $modelUserPostMain->type;
            $modelUserPost->user_id = $modelUserPostMain->user_id;
            $modelUserPost->text = $modelUserPostMain->text;
            $modelUserPost->is_publish = $modelUserPostMain->is_publish;
            $modelUserPost->love_value = $modelUserPostMain->love_value;
            $modelUserPost->user_post_main_id = $modelUserPostMain->id;

            if (($flag = $modelUserPost->save())) {

                $maxFiles = 0;

                foreach ($modelPost->getValidators() as $postValidator) {

                    if (!empty($postValidator->maxFiles)) {

                        $maxFiles = $postValidator->maxFiles;
                    }
                }

                $images = Tools::uploadFilesWithoutModel('/img/user_post/', 'image', $modelUserPostMain->id, '', true);

                $dataUserPostMainPhoto = [];

                if (count($images) <= $maxFiles) {

                    foreach ($images as $photoIndex => $image) {

                        $modelUserPostMainPhoto = new UserPostMain();

                        $modelUserPostMainPhoto->parent_id = $modelUserPostMain->id;
                        $modelUserPostMainPhoto->unique_id = \Yii::$app->security->generateRandomString() . $photoIndex;
                        $modelUserPostMainPhoto->business_id = $post['business_id'];
                        $modelUserPostMainPhoto->user_id = $post['user_id'];
                        $modelUserPostMainPhoto->type = 'Photo';
                        $modelUserPostMainPhoto->image = $image;
                        $modelUserPostMainPhoto->is_publish = true;
                        $modelUserPostMainPhoto->love_value = 0;

                        if (($flag = $modelUserPostMainPhoto->save())) {

                            $modelUserPostMainPhoto->image = \Yii::$app->params['endPointLoadImage'] . 'user-post?image=' . $modelUserPostMainPhoto->image . '&w=72&h=72';

                            array_push($dataUserPostMainPhoto, $modelUserPostMainPhoto->toArray());

                            $modelUserPostPhoto = new UserPost();

                            $modelUserPostPhoto->parent_id = $modelUserPost->id;
                            $modelUserPostPhoto->business_id = $modelUserPostMainPhoto->business_id;
                            $modelUserPostPhoto->type = $modelUserPostMainPhoto->type;
                            $modelUserPostPhoto->user_id = $modelUserPostMainPhoto->user_id;
                            $modelUserPostPhoto->image = $modelUserPostMainPhoto->image;
                            $modelUserPostPhoto->is_publish = $modelUserPostMainPhoto->is_publish;
                            $modelUserPostPhoto->love_value = $modelUserPostMainPhoto->love_value;

                            if (!($flag = $modelUserPostPhoto->save())) {

                                break;
                            }
                        } else {

                            break;
                        }
                    }
                } else {

                    $flag = false;

                    $result['errorPhoto'] = \Yii::t('app', 'You can upload up to {limit} photos', ['limit' => $maxFiles]);
                }

                if ($flag && !empty($post['ImageReviewDelete'])) {

                    $modelUserPhoto = UserPostMain::findAll(['parent_id' => $modelUserPostMain->id]);

                    foreach ($modelUserPhoto as $dataUserPhoto) {

                        foreach ($post['ImageReviewDelete'] as $deletedPhotoId) {

                            if ($deletedPhotoId == $dataUserPhoto->id) {

                                $dataUserPhoto->is_publish = false;

                                if (!($flag = $dataUserPhoto->save())) {

                                    break 2;
                                } else {

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($flag) {

            $prevUserVote = [];
            $prevUserVoteTotal = 0;

            foreach ($post['Post']['review']['rating'] as $ratingComponentId => $voteValue) {

                $modelUserVote = UserVote::find()
                    ->andWhere(['user_post_main_id' => $modelUserPostMain->id])
                    ->andWhere(['rating_component_id' => $ratingComponentId])
                    ->one();

                $prevUserVote[$ratingComponentId] = $modelUserVote->vote_value;
                $prevUserVoteTotal += $modelUserVote->vote_value;

                $modelUserVote->vote_value = $voteValue;

                if (!($flag = $modelUserVote->save())) {

                    $result['errorVote'] = $modelUserVote->getErrors();
                    break;
                } else {

                    if ($voteValue == 0) {

                        $flag = false;
                        $result['errorVote'] = $modelUserVote->getErrors();

                        break;
                    }
                }
            }
        }

        if ($flag) {

            $modelBusinessDetail = BusinessDetail::find()
                ->joinWith(['business'])
                ->andWhere(['business_id' => $post['business_id']])
                ->one();

            $modelBusinessDetail->total_vote_points -= $prevUserVoteTotal;

            foreach ($post['Post']['review']['rating'] as $votePoint) {

                $modelBusinessDetail->total_vote_points += $votePoint;
            }

            $modelBusinessDetail->voters = (!$isUpdate) ? $modelBusinessDetail->voters + 1 : $modelBusinessDetail->voters;
            $modelBusinessDetail->vote_points = $modelBusinessDetail->total_vote_points / count($post['Post']['review']['rating']);
            $modelBusinessDetail->vote_value = $modelBusinessDetail->vote_points / $modelBusinessDetail->voters;

            $flag = $modelBusinessDetail->save();
        }

        if ($flag) {

            foreach ($post['Post']['review']['rating'] as $ratingComponentId => $votePoint) {

                $modelBusinessDetailVote = BusinessDetailVote::find()
                    ->andWhere(['business_id' => $post['business_id']])
                    ->andWhere(['rating_component_id' => $ratingComponentId])
                    ->one();

                $modelBusinessDetailVote->total_vote_points -= $prevUserVote[$ratingComponentId];

                $modelBusinessDetailVote->total_vote_points += $votePoint;
                $modelBusinessDetailVote->vote_value = $modelBusinessDetailVote->total_vote_points / $modelBusinessDetail->voters;

                if (!($flag = $modelBusinessDetailVote->save())) {

                    break;
                }
            }
        }

        if ($flag) {

            $transaction->commit();

            $result['success'] = true;
            $result['message'] = 'Review anda berhasil disimpan.';
        } else {

            $transaction->rollBack();

            $result['success'] = false;
            $result['message'] = 'Review anda gagal disimpan.';
        }

        return $result;
    }
}