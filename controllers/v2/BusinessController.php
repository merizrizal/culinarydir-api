<?php

namespace api\controllers\v2;

use core\models\Business;
use core\models\BusinessDelivery;
use core\models\BusinessHour;
use core\models\BusinessImage;
use core\models\BusinessPayment;
use core\models\BusinessProductCategory;
use core\models\BusinessPromo;
use core\models\Promo;
use core\models\RatingComponent;
use core\models\TransactionSession;
use core\models\UserLove;
use core\models\UserPostMain;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class BusinessController extends \yii\rest\Controller
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
        'linksEnvelope' => 'links',
        'metaEnvelope' => 'meta',
    ];

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
                        'delivery-list' => ['GET'],
                        'payment-list' => ['GET'],
                        'album-list' => ['GET'],
                        'count-category-album' => ['GET'],
                        'news-promo' => ['GET'],
                        'business-detail' => ['GET'],
                        'business-product-category' => ['GET'],
                        'business-promo' => ['GET'],
                        'my-review' => ['GET'],
                        'all-review' => ['GET'],
                        'count-menu-order' => ['GET']
                    ],
                ],
            ]);
    }

    public function actionDeliveryList($id)
    {
        $model = BusinessDelivery::find()
            ->select([
                'business_delivery.id', 'delivery_method.delivery_name', 'business_delivery.note', 'business_delivery.description',
                'business_delivery.delivery_method_id', 'delivery_method.is_special'
            ])
            ->joinWith([
                'deliveryMethod' => function ($query) {

                    $query->select(['delivery_method.id']);
                },
            ])
            ->andWhere(['business_delivery.business_id' => $id])
            ->andWhere(['business_delivery.is_active' => true])
            ->asArray()->all();

        return $model;
    }

    public function actionPaymentList($id)
    {
        $model = BusinessPayment::find()
            ->select(['business_payment.id', 'payment_method.payment_name', 'business_payment.note', 'business_payment.description', 'business_payment.payment_method_id'])
            ->joinWith([
                'paymentMethod' => function ($query) {

                    $query->select(['payment_method.id']);
                },
            ])
            ->andWhere(['business_payment.business_id' => $id])
            ->andWhere(['business_payment.is_active' => true])
            ->asArray()->all();

        return $model;
    }

    public function actionAlbumList($businessId, $imageType = null)
    {
        $provider = null;

        $modelBusinessImage = BusinessImage::find()
            ->select(['id', 'business_id', 'image', 'CONCAT(category) AS image_type', 'created_at'])
            ->andWhere(['business_id' => $businessId]);

        $modelUserPostMain = UserPostMain::find()
            ->select(['id', 'business_id', 'image', 'CONCAT(\'User Post\') AS image_type', 'created_at'])
            ->andWhere(['business_id' => $businessId])
            ->andWhere(['type' => 'Photo']);

        $model = (new \yii\db\Query())
            ->from(['image_business_user' => $modelBusinessImage->union($modelUserPostMain)])
            ->andFilterWhere(['image_type' => $imageType])
            ->orderBy(['created_at' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $model,
        ]);

        return $provider;
    }

    public function actionCountCategoryAlbum($businessId) {

        $modelBusinessImage = BusinessImage::find()
            ->select(['COUNT(id) AS count', 'CONCAT(category) as image_type'])
            ->andWhere(['business_id' => $businessId])
            ->groupBy('CONCAT(category)');

        $modelUserPostMain = UserPostMain::find()
            ->select(['COUNT(id) AS count', 'CONCAT(\'User Post\') as image_type'])
            ->andWhere(['business_id' => $businessId])
            ->andWhere(['type' => 'Photo'])
            ->groupBy('CONCAT(\'User Post\')');

        $model = (new \yii\db\Query())
            ->from(['count_image_business_user' => $modelBusinessImage->union($modelUserPostMain)])
            ->orderBy(['image_type' => SORT_ASC])
            ->all();

        return $model;
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

    public function actionBusinessDetail($businessId, $userId = null)
    {
        \Yii::$app->formatter->timeZone = 'Asia/Jakarta';

        $data = [];

        $data = Business::find()
            ->select([
                'business.id', 'business.name', 'business.membership_type_id',
                'business.phone1', 'business.phone2', 'business.phone3', 'business_detail.price_min', 'business_detail.price_max',
                'business.about', 'business_detail.voters', 'business_detail.vote_value', 'business_detail.vote_points',
                'business_detail.love_value', 'business_detail.note_business_hour', 'business_location.address_type',
                'business_location.address', 'business_location.coordinate', 'city.name as city_name',
                'district.name as district_name', 'village.name as village_name'
            ])
            ->joinWith([
                'businessFacilities' => function ($query) {

                    $query->andOnCondition(['business_facility.is_active' => true]);
                },
                'businessFacilities.facility',
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

                    $query->select([
                        'business_detail_vote.business_id', 'business_detail_vote.rating_component_id',
                        'business_detail_vote.vote_value', 'rating_component.name'
                    ])
                    ->joinWith([
                        'ratingComponent' => function ($query) {

                            $query->select(['rating_component.id'])
                                ->andOnCondition(['rating_component.is_active' => true]);
                        }
                    ])
                    ->orderBy(['rating_component.order' => SORT_ASC]);
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
            ->andWhere(['business.id' => $businessId])
            ->asArray()->one();

        $data['businessProductCategories'] = BusinessProductCategory::find()
            ->select(['business_product_category.product_category_id', 'business_product_category.business_id', 'product_category.name'])
            ->joinWith([
                'productCategory' => function ($query) {

                    $query->select(['product_category.id']);
                }
            ])
            ->andWhere(['business_product_category.business_id' => $data['id']])
            ->andWhere(['business_product_category.is_active' => true])
            ->andWhere(['<>', 'product_category.type', 'Menu'])
            ->cache(60)
            ->asArray()->all();

        $businessFacilityList = "";
        $businessProductCategoryList = "";

        foreach ($data['businessFacilities'] as $dataBusinessFacility) {

            $businessFacilityList .= $dataBusinessFacility['facility']['name'] . ", ";
        }

        foreach ($data['businessProductCategories'] as $dataBusinessProductCategory) {

            $businessProductCategoryList .= $dataBusinessProductCategory['name'] . ", ";
        }

        $data['business_facility'] = trim($businessFacilityList, ", ");
        $data['business_product_category'] = trim($businessProductCategoryList, ", ");

        $data['userLoves'] = UserLove::find()
            ->select(['business_id'])
            ->andWhere(['business_id' => $businessId])
            ->andWhere(['user_id' => !empty($userId) ? $userId : null])
            ->andWhere(['is_active' => true])
            ->asArray()->all();

        $data['businessHours'] = BusinessHour::find()
            ->select([
                'business_hour.id', 'business_hour.business_id',
                'to_char(business_hour.open_at, \'HH24:MI\') as open_at', 'to_char(business_hour.close_at, \'HH24:MI\') as close_at',
                'business_hour.day', 'business_hour.is_open'
            ])
            ->joinWith([
                'businessHourAdditionals' => function ($query) {

                    $query->select([
                        'business_hour_additional.business_hour_id',
                        'to_char(business_hour_additional.open_at, \'HH24:MI\') as open_at',
                        'to_char(business_hour_additional.close_at, \'HH24:MI\') as close_at',
                    ]);
                }
            ])
            ->andWhere(['business_hour.business_id' => $data['id']])
            ->andWhere(['business_hour.is_open' => true])
            ->orderBy(['business_hour.day' => SORT_ASC])
            ->cache(60)
            ->asArray()->all();

        $days = \Yii::$app->params['days'];
        $now = \Yii::$app->formatter->asTime(time());

        $todayHour = \Yii::t('app', 'Closed');
        $isOpen = false;
        $openStatusMessage = "";

        foreach ($data['businessHours'] as $i => $dataBusinessHour) {

            $day = $days[$dataBusinessHour['day'] - 1];

            $data['businessHours'][$i]['day'] = \Yii::t('app', $day);

            if (date('l') == $day) {

                if (($dataBusinessHour['open_at'] == '00:00') && ($dataBusinessHour['close_at'] == '24:00')) {

                    $todayHour = \Yii::t('app', '24 Hours');
                } else {

                    $todayHour = $dataBusinessHour['open_at'] . ' - ' . $dataBusinessHour['close_at'];
                }

                if (($isOpen = $now >= $dataBusinessHour['open_at'] && $now <= $dataBusinessHour['close_at'])) {

                    $openStatusMessage = " hingga " . \Yii::$app->formatter->asTime($dataBusinessHour['close_at'], 'HH:mm') . " hari ini";
                } else {

                    if (!empty($dataBusinessHour['businessHourAdditionals'])) {

                        foreach ($dataBusinessHour['businessHourAdditionals'] as $dataBusinessHourAdditional) {

                            if (date('l') == $day) {

                                if (($isOpen = $now >= $dataBusinessHourAdditional['open_at'] && $now <= $dataBusinessHourAdditional['close_at'])) {

                                    $openStatusMessage = " hingga " . \Yii::$app->formatter->asTime($dataBusinessHourAdditional['close_at'], 'HH:mm') . " hari ini";
                                } else {

                                    $openStatusMessage = " buka lagi jam " . \Yii::$app->formatter->asTime($dataBusinessHourAdditional['open_at'], 'HH:mm');
                                }

                                $todayHour .= "\n" . $dataBusinessHourAdditional['open_at'] . ' - ' . $dataBusinessHourAdditional['close_at'];
                            }
                        }
                    }
                }
            }
        }

        $data['openStatus']['is_open_now'] = $isOpen;
        $data['openStatus']['open_status_message'] = $openStatusMessage;
        $data['today_hour'] = $todayHour;

        $data['is_order_online'] = false;

        if (empty($data)) {

            throw new NotFoundHttpException('The requested page does not exist.');
        } else {

            if (!empty($data['membershipType']['membershipTypeProductServices'])) {

                foreach ($data['membershipType']['membershipTypeProductServices'] as $membershipTypeProductService) {

                    if (($data['is_order_online'] = !empty($membershipTypeProductService['productService']))) {

                        break;
                    }
                }
            }
        }

        $data['business_whatsapp'] = !empty($data['phone3']) ? 'https://api.whatsapp.com/send?phone=62' . substr(str_replace('-', '', $data['phone3']), 1) : null;

        if (empty($data['businessDetailVotes'])) {

            $modelRatingComponent = RatingComponent::find()
                ->andWhere(['is_active' => true])
                ->orderBy(['order' => SORT_ASC])
                ->asArray()->all();

            foreach ($modelRatingComponent as $i => $dataRatingComponent) {

                $data['businessDetailVotes'][$i]['vote_value'] = 0;
                $data['businessDetailVotes'][$i]['name'] = $dataRatingComponent['name'];
            }
        }

        \Yii::$app->formatter->timeZone = 'UTC';

        return $data;
    }

    public function actionBusinessProductCategory($businessId, $userId = null)
    {
        $result = [];

        $modelBusinessProductCategory = BusinessProductCategory::find()
            ->select(['business_product_category.id', 'business_product_category.product_category_id', 'product_category.name'])
            ->joinWith([
                'productCategory' => function ($query) {

                    $query->select(['product_category.id', 'product_category.type']);
                },
                'businessProducts' => function ($query) {

                    $query->select([
                        'business_product.id', 'business_product.name', 'business_product.description',
                        'business_product.price', 'business_product.is_available', 'business_product.business_product_category_id',
                        'business_product.business_id'
                    ])
                    ->andOnCondition(['business_product.not_active' => false]);
                }
            ])
            ->andWhere(['business_product_category.business_id' => $businessId])
            ->andWhere(['OR', ['product_category.type' => 'Menu'], ['product_category.type' => 'Specific-Menu']])
            ->asArray()->all();

        $modelTransactionSession = TransactionSession::find()
            ->select(['transaction_session.id'])
            ->joinWith(['transactionItems'])
            ->andWhere(['transaction_session.user_ordered' => $userId])
            ->andWhere(['transaction_session.status' => 'Open'])
            ->asArray()->one();

        foreach ($modelBusinessProductCategory as $i => $dataBusinessProductCategory) {

            $result[$i]['name'] = $dataBusinessProductCategory['name'];

            foreach ($dataBusinessProductCategory['businessProducts'] as $j => $dataBusinessProduct) {

                $result[$i]['businessProducts'][$j]['id'] = $dataBusinessProduct['id'];
                $result[$i]['businessProducts'][$j]['name'] = $dataBusinessProduct['name'];
                $result[$i]['businessProducts'][$j]['description'] = $dataBusinessProduct['description'];
                $result[$i]['businessProducts'][$j]['price'] = $dataBusinessProduct['price'];
                $result[$i]['businessProducts'][$j]['is_available'] = $dataBusinessProduct['is_available'];
                $result[$i]['businessProducts'][$j]['business_id'] = $dataBusinessProduct['business_id'];

                if (!empty($modelTransactionSession)) {

                    foreach ($modelTransactionSession['transactionItems'] as $dataTransactionItem) {

                        if ($dataBusinessProduct['id'] == $dataTransactionItem['business_product_id']) {

                            $result[$i]['businessProducts'][$j]['transactionItem']['id'] = $dataTransactionItem['id'];
                            $result[$i]['businessProducts'][$j]['transactionItem']['note'] = $dataTransactionItem['note'];
                            $result[$i]['businessProducts'][$j]['transactionItem']['price'] = $dataTransactionItem['price'];
                            $result[$i]['businessProducts'][$j]['transactionItem']['amount'] = $dataTransactionItem['amount'];

                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function actionBusinessPromo($businessId)
    {
        $modelBusinessPromo = BusinessPromo::find()
            ->select([
                'business_promo.title', 'business_promo.short_description', 'business_promo.business_id',
                'business_promo.image', 'business_promo.date_start', 'business_promo.date_end'
            ])
            ->andWhere(['>=', 'business_promo.date_end', \Yii::$app->formatter->asDate(time())])
            ->andWhere(['business_promo.not_active' => false])
            ->andWhere(['business_promo.business_id' => $businessId])
            ->asArray()->all();

        if (!empty($modelBusinessPromo)) {

            foreach ($modelBusinessPromo as $i => $dataBusinessPromo) {

                $modelBusinessPromo[$i]['date_start'] = \Yii::$app->formatter->asDate($dataBusinessPromo['date_start'], 'medium');
                $modelBusinessPromo[$i]['date_end'] = \Yii::$app->formatter->asDate($dataBusinessPromo['date_end'], 'medium');
            }
        }

        return $modelBusinessPromo;
    }

    public function actionMyReview($businessId, $userId)
    {
        $modelUserPostMain = UserPostMain::find()
            ->select([
                'user_post_main.id', 'user_post_main.user_id', 'user.image as user_image', 'user.full_name',
                'user_post_main.text', 'user_post_main.love_value', 'user_post_main.created_at'
            ])
            ->joinWith([
                'user' => function ($query) {

                    $query->select(['user.id']);
                },
                'user.userPosts' => function ($query) {

                    $query->select(['user_post.id', 'user_post.user_id']);
                },
                'userPostMains child' => function ($query) {

                    $query->select(['child.parent_id', 'child.image'])
                        ->andOnCondition(['child.is_publish' => true])
                        ->andOnCondition(['child.type' => 'Photo']);
                },
                'userVotes' => function ($query) {

                    $query->select(['rating_component.name', 'user_vote.vote_value', 'user_vote.rating_component_id', 'user_vote.user_post_main_id'])
                        ->joinWith([
                            'ratingComponent' => function ($query) {

                                $query->select(['rating_component.id'])
                                    ->andOnCondition(['rating_component.is_active' => true]);
                            }
                        ]);
                },
                'userPostLoves' => function ($query) {

                    $query->select(['user_post_love.id', 'user_post_love.user_post_main_id'])
                        ->andOnCondition(['user_post_love.is_active' => true]);
                },
                'userPostComments' => function ($query) {

                    $query->select([
                        'user_post_comment.text', 'user_post_comment.user_post_main_id', 'user_post_comment.user_id',
                        'user_post_comment.created_at', 'user_comment.full_name', 'user_comment.image as user_image'
                    ])
                    ->joinWith([
                        'user user_comment' => function ($query) {

                            $query->select(['user_comment.id']);
                        }
                    ]);
                }
            ])
            ->andWhere(['user_post_main.parent_id' => null])
            ->andWhere(['user_post_main.business_id' => $businessId])
            ->andWhere(['user_post.user_id' => $userId])
            ->andWhere(['user_post_main.type' => 'Review'])
            ->andWhere(['user_post_main.is_publish' => true])
            ->cache(60)
            ->asArray()->one();

        if (!empty($modelUserPostMain)) {

            $modelUserPostMain['success'] = true;
            $modelUserPostMain['created_at'] = $modelUserPostMain['created_at'];
            $modelUserPostMain['comment_value'] = !empty($modelUserPostMain['userPostComments']) ? count($modelUserPostMain['userPostComments']) : 0;
            $modelUserPostMain['user_post_count'] = !empty($modelUserPostMain['user']['userPosts']) ? count($modelUserPostMain['user']['userPosts']) : 0;
            $modelUserPostMain['user_post_image_count'] = !empty($modelUserPostMain['userPostMains']) ? count($modelUserPostMain['userPostMains']) : 0;

            if (!empty($modelUserPostMain['userVotes'])) {

                $ratingComponentValue = [];
                $totalVoteValue = 0;

                foreach ($modelUserPostMain['userVotes'] as $i => $dataUserVote) {

                    if (!empty($dataUserVote['ratingComponent'])) {

                        $totalVoteValue += $dataUserVote['vote_value'];

                        $ratingComponentValue[$i]['name'] = $dataUserVote['name'];
                        $ratingComponentValue[$i]['vote_value'] = $dataUserVote['vote_value'];
                    }
                }

                $overallValue = !empty($totalVoteValue) && !empty($ratingComponentValue) ? ($totalVoteValue / count($ratingComponentValue)) : 0;

                $modelUserPostMain['dataUserVoteReview'] = [
                    'overall_value' => $overallValue,
                    'ratingComponent' => $ratingComponentValue
                ];
            }
        } else {

            $modelUserPostMain['success'] = false;

            $modelRatingComponent = RatingComponent::find()
                ->andWhere(['is_active' => true])
                ->asArray()->all();

            $ratingComponentValue = [];

            foreach ($modelRatingComponent as $i => $dataRatingComponent) {

                $ratingComponentValue[$i]['name'] = $dataRatingComponent['name'];
                $ratingComponentValue[$i]['vote_value'] = 0;
            }

            $modelUserPostMain['dataUserVoteReview'] = [
                'overall_value' => 0,
                'ratingComponent' => $ratingComponentValue
            ];
        }

        return $modelUserPostMain;
    }

    public function actionAllReview($businessId, $userId = null)
    {
        $modelUserPostMain = UserPostMain::find()
            ->select([
                'user_post_main.id', 'user_post_main.user_id', 'user.image as user_image', 'user.full_name',
                'user_post_main.text', 'user_post_main.love_value', 'user_post_main.created_at'
            ])
            ->joinWith([
                'user' => function ($query) {

                    $query->select(['user.id']);
                },
                'user.userPosts' => function ($query) {

                    $query->select(['user_post.id', 'user_post.user_id']);
                },
                'userPostMains child' => function ($query) {

                    $query->select(['child.parent_id', 'child.image'])
                        ->andOnCondition(['child.is_publish' => true])
                        ->andOnCondition(['child.type' => 'Photo']);
                },
                'userVotes' => function ($query) {

                    $query->select(['rating_component.name', 'user_vote.vote_value', 'user_vote.rating_component_id', 'user_vote.user_post_main_id'])
                        ->joinWith([
                            'ratingComponent' => function ($query) {

                                $query->select(['rating_component.id'])
                                    ->andOnCondition(['rating_component.is_active' => true]);
                            }
                        ]);
                },
                'userPostLoves' => function ($query) use ($userId) {

                    $query->select(['user_post_love.id', 'user_post_love.user_post_main_id', 'user_post_love.user_id'])
                        ->andOnCondition(['user_post_love.is_active' => true])
                        ->andOnCondition(['user_post_love.user_id' => !empty($userId) ? $userId : null]);
                },
                'userPostComments' => function ($query) {

                    $query->select([
                        'user_post_comment.text', 'user_post_comment.user_post_main_id', 'user_post_comment.user_id',
                        'user_post_comment.created_at', 'user_comment.full_name', 'user_comment.image as user_image'
                    ])
                    ->joinWith([
                        'user user_comment' => function ($query) {

                            $query->select(['user_comment.id']);
                        }
                    ]);
                }
            ])
            ->andWhere(['user_post_main.parent_id' => null])
            ->andWhere(['user_post_main.business_id' => $businessId])
            ->andWhere(['user_post_main.type' => 'Review'])
            ->andWhere(['user_post_main.is_publish' => true])
            ->andFilterWhere(['<>', 'user_post_main.user_id' , !empty($userId) ? $userId : null])
            ->orderBy(['user_post_main.created_at' => SORT_DESC])
            ->cache(60)
            ->distinct()
            ->asArray();

        $provider = new ActiveDataProvider([
            'query' => $modelUserPostMain
        ]);

        $models = $provider->models;

        foreach ($models as $i => $dataUserPostMain) {

            $models[$i]['created_at'] = $dataUserPostMain['created_at'];
            $models[$i]['comment_value'] = !empty($dataUserPostMain['userPostComments']) ? count($dataUserPostMain['userPostComments']) : 0;
            $models[$i]['user_post_count'] = !empty($dataUserPostMain['user']['userPosts']) ? count($dataUserPostMain['user']['userPosts']) : 0;
            $models[$i]['user_post_image_count'] = !empty($dataUserPostMain['userPostMains']) ? count($dataUserPostMain['userPostMains']) : 0;

            if (!empty($dataUserPostMain['userVotes'])) {

                $ratingComponentValue = [];
                $totalVoteValue = 0;

                foreach ($dataUserPostMain['userVotes'] as $j => $dataUserVote) {

                    if (!empty($dataUserVote['ratingComponent'])) {

                        $totalVoteValue += $dataUserVote['vote_value'];

                        $ratingComponentValue[$j]['name'] = $dataUserVote['name'];
                        $ratingComponentValue[$j]['vote_value'] = $dataUserVote['vote_value'];
                    }
                }

                $overallValue = !empty($totalVoteValue) && !empty($ratingComponentValue) ? ($totalVoteValue / count($ratingComponentValue)) : 0;

                $models[$i]['dataUserVoteReview'] = [
                    'overall_value' => $overallValue,
                    'ratingComponent' => $ratingComponentValue
                ];
            }
        }

        $provider->setModels($models);

        return $provider;
    }

    public function actionCountMenuOrder($businessId)
    {
        $modelBusiness = Business::find()
            ->select(['business.id'])
            ->joinWith([
                'businessProducts' => function ($query) {

                    $query->select(['business_product.id', 'business_product.name', 'business_product.business_id'])
                        ->andOnCondition(['business_product.not_active' => false]);
                },
                'businessProducts.transactionItems' => function ($query) {

                    $query->select(['transaction_item.business_product_id', 'transaction_item.amount']);
                }
            ])
            ->andWhere(['business.id' => $businessId])
            ->asArray()->one();

        $idx = 0;
        $temp = [];

        foreach ($modelBusiness['businessProducts'] as $dataBusinessProduct) {

            $temp[$idx]['menu'] = $dataBusinessProduct['name'];

            $orderCounter = 0;

            foreach ($dataBusinessProduct['transactionItems'] as $dataTransactionItem) {

                $orderCounter += $dataTransactionItem['amount'];
            }

            $temp[$idx]['order_count'] = $orderCounter;

            $idx++;
        }

        uksort($temp, function ($a, $b) use ($temp) {

            return $temp[$b]['order_count'] - $temp[$a]['order_count'];
        });

        $idx = 0;
        $result = [];

        foreach ($temp as $dataTemp) {

            if ($dataTemp['order_count'] != 0) {

                $result[$idx]['menu'] = $dataTemp['menu'];
                $result[$idx]['order_count'] = $dataTemp['order_count'];

                $idx++;

                if ($idx >= 5) {

                    break;
                }
            } else {

                break;
            }
        }

        return $result;
    }
}