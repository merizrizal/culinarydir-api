<?php

namespace api\controllers\v1;

use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use core\models\Business;
use core\models\BusinessHour;
use core\models\BusinessProductCategory;
use core\models\BusinessPromo;
use core\models\Promo;
use core\models\RatingComponent;
use core\models\TransactionSession;
use core\models\UserPostMain;
use core\models\UserLove;
use core\models\UserVisit;
use frontend\models\Post;

class PageController extends \yii\rest\Controller
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
                        'news-promo' => ['GET'],
                        'business-detail' => ['GET'],
                        'business-product-category' => ['GET'],
                        'business-promo' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionNewsPromo()
    {
        $data = [];

        $data[0]['image'] = \Yii::$app->params['endPointLoadImage'] . 'load-image?image=sudah-mendata.jpg';

        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $modelPromo = Promo::find()
            ->andWhere(['not_active' => false])
            ->andWhere(['OR', ['>=', 'date_end', \Yii::$app->formatter->asDate(time())], ['date_end' => null]])
            ->orderBy(['created_at' => SORT_DESC])
            ->asArray()->all();

        \Yii::$app->formatter->timeZone = 'UTC';

        foreach ($modelPromo as $i => $dataPromo) {

            $data[$i + 1]['image'] = \Yii::$app->params['endPointLoadImage'] . 'promo?image=' . $dataPromo['image'];
        }

        return $data;
    }

    public function actionBusinessDetail($id, $userId = null)
    {
        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $data = [];

        $data['business'] = Business::find()
            ->select([
                'business.id', 'business.name', 'business.unique_name', 'business.is_active', 'business.membership_type_id',
                'business.phone1', 'business.phone2', 'business.phone3', 'business_detail.price_min', 'business_detail.price_max',
                'business.about', 'business_detail.voters', 'business_detail.vote_value', 'business_detail.vote_points',
                'business_detail.love_value', 'business_detail.visit_value', 'business_location.address_type',
                'business_location.address', 'business_location.coordinate', 'city.name as city_name',
                'district.name as district_name', 'village.name as village_name'
            ])
            ->joinWith([
                'businessCategories' => function ($query) {

                    $query->select(['business_category.category_id', 'business_category.business_id'])
                        ->andOnCondition(['business_category.is_active' => true]);
                },
                'businessCategories.category' => function ($query) {

                    $query->select(['category.id', 'category.name']);
                },
                'businessFacilities' => function ($query) {

                    $query->select(['business_facility.facility_id', 'business_facility.business_id'])
                        ->andOnCondition(['business_facility.is_active' => true]);
                },
                'businessFacilities.facility' => function ($query) {

                    $query->select(['facility.id', 'facility.name']);
                },
                'businessImages' => function ($query) {

                    $query->select(['business_image.business_id', 'business_image.image'])
                        ->andOnCondition(['business_image.is_primary' => true]);
                },
                'businessLocation' => function ($query) {

                    $query->select(['business_location.business_id', 'business_location.city_id', 'business_location.district_id', 'business_location.village_id']);
                },
                'businessLocation.city' => function ($query) {

                    $query->select(['city.id']);
                },
                'businessLocation.district' => function ($query) {

                    $query->select(['district.id']);
                },
                'businessLocation.village' => function ($query) {

                    $query->select(['village.id']);
                },
                'businessDetail' => function ($query) {

                    $query->select(['business_detail.business_id']);
                },
                'businessDetailVotes' => function ($query) {

                    $query->select(['business_detail_vote.business_id', 'business_detail_vote.rating_component_id', 'business_detail_vote.vote_value']);
                },
                'businessDetailVotes.ratingComponent' => function ($query) {

                    $query->select(['rating_component.id', 'rating_component.name'])
                        ->andOnCondition(['rating_component.is_active' => true]);
                },
                'membershipType' => function ($query) {

                    $query->select(['membership_type.id'])
                        ->andOnCondition(['membership_type.is_active' => true]);
                },
                'membershipType.membershipTypeProductServices' => function ($query) {

                    $query->select(['membership_type_product_service.product_service_id', 'membership_type_product_service.membership_type_id'])
                        ->andOnCondition(['membership_type_product_service.not_active' => false]);
                },
                'membershipType.membershipTypeProductServices.productService' => function ($query) {

                    $query->select(['product_service.name', 'product_service.code_name'])
                        ->andOnCondition(['product_service.code_name' => 'order-online'])
                        ->andOnCondition(['product_service.not_active' => false]);
                }
            ])
            ->andWhere(['business.id' => $id])
            ->cache(60)
            ->asArray()->one();

        $data['business']['userLoves'] = UserLove::find()
            ->select(['business_id'])
            ->andWhere(['business_id' => $id])
            ->andWhere(['user_id' => !empty($userId) ? $userId : null])
            ->andWhere(['is_active' => true])
            ->asArray()->all();

        $data['business']['userVisits'] = UserVisit::find()
            ->select(['business_id'])
            ->andWhere(['business_id' => $id])
            ->andWhere(['user_id' => !empty($userId) ? $userId : null])
            ->andWhere(['is_active' => true])
            ->asArray()->all();

        $data['business']['businessProductCategories'] = BusinessProductCategory::find()
            ->select(['business_product_category.product_category_id', 'business_product_category.business_id'])
            ->joinWith([
                'productCategory' => function ($query) {

                    $query->select(['product_category.id', 'product_category.name']);
                }
            ])
            ->andWhere(['business_product_category.business_id' => $data['business']['id']])
            ->andWhere(['business_product_category.is_active' => true])
            ->andWhere(['<>', 'product_category.type', 'Menu'])
            ->cache(60)
            ->asArray()->all();

        $data['business']['businessHours'] = BusinessHour::find()
            ->select([
                'business_hour.id', 'business_hour.business_id',
                'to_char(business_hour.open_at, \'HH24:MI\') as open_at', 'to_char(business_hour.close_at, \'HH24:MI\') as close_at',
                'business_hour.day', 'business_hour.is_open'
            ])
            ->joinWith([
                'businessHourAdditionals' => function($query) {

                    $query->select([
                        'business_hour_additional.business_hour_id',
                        'to_char(business_hour_additional.open_at, \'HH24:MI\') as open_at',
                        'to_char(business_hour_additional.close_at, \'HH24:MI\') as close_at',
                        'business_hour_additional.is_open',
                        'business_hour_additional.day'
                    ]);
                }
            ])
            ->andWhere(['business_hour.business_id' => $data['business']['id']])
            ->andWhere(['business_hour.is_open' => true])
            ->orderBy(['business_hour.day' => SORT_ASC])
            ->cache(60)
            ->asArray()->all();

        $days = \Yii::$app->params['days'];
        $now = \Yii::$app->formatter->asTime(time());
        $isOpen = false;

        foreach ($data['business']['businessHours'] as $dataBusinessHour) {

            $day = $days[$dataBusinessHour['day'] - 1];

            if (date('l') == $day) {

                $isOpen = $now >= $dataBusinessHour['open_at'] && $now <= $dataBusinessHour['close_at'];
                $openStatusMessage = " hingga " . \Yii::$app->formatter->asTime($dataBusinessHour['close_at'], 'HH:mm') . " hari ini";
            }

            if (!empty($dataBusinessHour['businessHourAdditionals']) && !$isOpen) {

                foreach ($dataBusinessHour['businessHourAdditionals'] as $dataBusinessHourAdditional) {

                    if (date('l') == $day) {

                        $isOpen = $now >= $dataBusinessHourAdditional['open_at'] && $now <= $dataBusinessHourAdditional['close_at'];
                        $openStatusMessage = " hingga " . \Yii::$app->formatter->asTime($dataBusinessHourAdditional['close_at'], 'HH:mm') . " hari ini";
                    }
                }
            }
        }

        $data['business']['isOpenNow'] = $isOpen;
        $data['business']['openStatusMessage'] = $openStatusMessage;

        $data['business']['isOrderOnline'] = false;

        if (empty($data['business'])) {

            throw new NotFoundHttpException('The requested page does not exist.');
        } else {

            if (!empty($data['business']['membershipType']['membershipTypeProductServices'])) {

                foreach ($data['business']['membershipType']['membershipTypeProductServices'] as $membershipTypeProductService) {

                    if ($data['business']['isOrderOnline'] = !empty($membershipTypeProductService['productService'])) {

                        break;
                    }
                }
            }
        }

        $data['business']['userPostMains'] = UserPostMain::find()
            ->joinWith([
                'user',
                'userPostMains child' => function ($query) {

                    $query->andOnCondition(['child.is_publish' => true])
                        ->andOnCondition(['child.type' => 'Photo'])
                        ->orderBy(['child.created_at' => SORT_ASC]);
                },
                'userVotes' => function ($query) {

                    $query->orderBy(['rating_component_id' => SORT_ASC]);
                },
                'userVotes.ratingComponent' => function ($query) {

                    $query->andOnCondition(['rating_component.is_active' => true]);
                },
                'userPostLoves' => function ($query) use ($userId) {

                    $query->andOnCondition(['user_post_love.user_id' => !empty($userId) ? $userId : null])
                        ->andOnCondition(['user_post_love.is_active' => true]);
                },
                'userPostComments',
                'userPostComments.user user_comment'
            ])
            ->andWhere(['user_post_main.parent_id' => null])
            ->andWhere(['user_post_main.business_id' => $id])
            ->andWhere(['user_post_main.user_id' => !empty($userId) ? $userId : null])
            ->andWhere(['user_post_main.type' => 'Review'])
            ->andWhere(['user_post_main.is_publish' => true])
            ->cache(60)
            ->asArray()->one();

        $data['ratingComponent'] = RatingComponent::find()
            ->where(['is_active' => true])
            ->orderBy(['order' => SORT_ASC])
            ->asArray()->all();

        $data['business']['transactionSession'] = TransactionSession::find()
            ->joinWith(['business'])
            ->andWhere(['transaction_session.user_ordered' => !empty($userId) ? $userId : null])
            ->andWhere(['transaction_session.status' => 'Open'])
            ->cache(60)
            ->asArray()->one();

        if (!empty($data['business']['userPostMains']['userVotes'])) {

            $ratingComponentValue = [];
            $totalVoteValue = 0;

            foreach ($data['business']['userPostMains']['userVotes'] as $dataUserVote) {

                if (!empty($dataUserVote['ratingComponent'])) {

                    $totalVoteValue += $dataUserVote['vote_value'];

                    $ratingComponentValue[$dataUserVote['rating_component_id']] = $dataUserVote['vote_value'];
                }
            }

            $overallValue = !empty($totalVoteValue) && !empty($ratingComponentValue) ? ($totalVoteValue / count($ratingComponentValue)) : 0;

            $data['dataUserVoteReview'] = [
                'overallValue' => $overallValue,
                'ratingComponentValue' => $ratingComponentValue
            ];
        }

        $modelPost = new Post();

        if (!empty($data['business']['userPostMains'])) {

            $modelPost->text = $data['business']['userPostMains']['text'];
        }

        $data['post'] = $modelPost->toArray();

        $data['businessWhatsapp'] = !empty($data['business']['phone3']) ? 'https://api.whatsapp.com/send?phone=62' . substr(str_replace('-', '', $data['business']['phone3']), 1) : null;

        \Yii::$app->formatter->timeZone = 'UTC';

        return $data;
    }

    public function actionBusinessProductCategory($id)
    {
        $modelBusinessProductCategory = BusinessProductCategory::find()
            ->select(['business_product_category.id', 'business_product_category.product_category_id', 'product_category.name'])
            ->joinWith([
                'productCategory' => function($query) {

                    $query->select(['product_category.id']);
                },
                'businessProducts' => function($query) {

                    $query->select([
                        'business_product.name', 'business_product.description', 'business_product.price',
                        'business_product.is_available', 'business_product.business_product_category_id',
                        'business_product.business_id'
                    ])
                    ->andOnCondition(['business_product.not_active' => false]);
                }
            ])
            ->andWhere(['business_product_category.business_id' => $id])
            ->asArray()->all();

        return $modelBusinessProductCategory;
    }

    public function actionBusinessPromo($id)
    {
        $modelBusinessPromo = BusinessPromo::find()
            ->select([
                'business_promo.title', 'business_promo.short_description', 'business_promo.business_id',
                'business_promo.image', 'business_promo.date_start', 'business_promo.date_end'
            ])
            ->andWhere(['>=', 'business_promo.date_end', \Yii::$app->formatter->asDate(time())])
            ->andWhere(['business_promo.not_active' => false])
            ->andWhere(['business_promo.business_id' => $id])
            ->asArray()->all();

        if (!empty($modelBusinessPromo)) {

            foreach ($modelBusinessPromo as $i => $dataBusinessPromo) {

                $modelBusinessPromo[$i]['date_start'] = \Yii::$app->formatter->asDate($dataBusinessPromo['date_start'], 'medium');
                $modelBusinessPromo[$i]['date_end'] = \Yii::$app->formatter->asDate($dataBusinessPromo['date_end'], 'medium');
            }
        }

        return $modelBusinessPromo;
    }
}

