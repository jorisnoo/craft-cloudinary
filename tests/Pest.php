<?php

// Make the global Yii and Craft classes available so code under test can log
// without a fully bootstrapped Craft app.
require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';
