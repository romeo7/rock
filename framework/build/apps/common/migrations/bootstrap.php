<?php

use apps\common\migrations\AccessItemsMigration;
use apps\common\migrations\AccessRolesItemsMigration;
use apps\common\migrations\AccessUsersItemsMigration;
use apps\common\migrations\UsersMigration;
use rock\Rock;

require(dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php');
$configs = require(dirname(dirname(__DIR__)) . '/common/configs/configs.php');

Rock::$app = new Rock();
Rock::$app->language = \rock\i18n\i18nInterface::EN;

\rock\base\Config::set($configs);

\rock\di\Container::addMulti($configs['_components']);

(new UsersMigration())->up();
(new  AccessItemsMigration())->up();
(new  AccessRolesItemsMigration())->up();
(new  AccessUsersItemsMigration())->up();