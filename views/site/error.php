<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

$this->title = $name; ?>

<section class="content-header">
    <h1><?= $name ?></h1>
</section>

<section class="content">

    <div class="site-error">
s
        <div class="alert alert-danger">
            <h3><?= $exception->getMessage() ?></h3>
        </div>

        <p>
            <?= Html::a('Back.', \Yii::$app->request->referrer); ?>
        </p>

    </div>
</section>
