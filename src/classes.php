<?php
use rock\base\Alias;
use rock\db\BatchQueryResult;
use rock\rbac\Permission;
use rock\rbac\Role;
use rock\Rock;
use rock\security\Security;
use rock\template\Template;

return array_merge(
    [
        'route' => [
            'class' => \rock\route\Route::className(),
        ],
        'access' => [
            'class' => \rock\access\Access::className(),
        ],
        'behavior' => [
            'class' => \rock\components\Behavior::className(),
        ],

        // Database
        'db' => [
            'class' => \rock\db\Connection::className(),
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
            'dsn' => 'mysql:host=localhost;dbname=rockdemo;charset=utf8',
            'tablePrefix' => 'spt_',
            'aliasSeparator' => '__',
        ],
        'BatchQueryResult' => [
            'class' => BatchQueryResult::className(),
        ],

        'template' => [
            'class' => Template::className(),
            'locale' =>  [\rock\LocaleProperties::className(), 'locale'],
            'autoEscape' => Template::ESCAPE | Template::TO_TYPE,
            'handlerLink' => function($link, Template $template, array $params = [])
            {
                if (!$link = Alias::getAlias("@{$link}", [], false)) {
                    return '#';
                }

                return $template->autoEscape(\rock\template\filters\BaseFilter::modifyUrl($link, $params));
            },

            'extensions' => [
                'cfg' => function (array $keys) {
                    return \rock\helpers\ArrayHelper::getValue(Rock::$config, $keys);
                },
                'user' => function (array $keys) {
                    if (current($keys) === 'isGuest') {
                        return Rock::$app->user->isGuest();
                    } elseif (in_array(current($keys), ['isLogged', 'isAuthenticated'], true)) {
                        return !Rock::$app->user->isGuest();
                    }
                    return \rock\helpers\ArrayHelper::getValue(Rock::$app->user->getAll(), $keys);
                },
                'call' => function (array $call, array $params = [], Template $template) {
                    if (!isset($call[1])) {
                        $call[1] = null;
                    }
                    list($class, $method) = $call;
                    if ($class === 'context') {
                        $object = $template->context;
                        $function = [$object, $method];
                    } elseif (function_exists($class) && !$class instanceof \Closure){
                        return call_user_func_array($class, $params);
                    } else {
                        $object = \rock\di\Container::load($class);
                        if (!method_exists($object, $method)) {
                            throw new \rock\base\BaseException(\rock\base\BaseException::UNKNOWN_METHOD, ['method' => "{$class}::{$method}"]);
                        }
                        $function = [$object, $method];
                    }

                    return call_user_func_array($function, $params);
                },
            ],
            'title' => 'Demo',
            'metaTags' => ['charset' => '<meta charset="'.Rock::$app->charset.'" />'],
            'linkTags' => ['favicon' => '<link rel="Shortcut Icon" type="image/x-icon" href="/favicon.ico?10">'],
            'snippets' => [

                'request.get' => [
                    'class'        => \rock\snippets\request\Get::className(),
                ],

                'request.post' => [
                    'class'        => \rock\snippets\request\Post::className(),
                ],

                'csrf' => [
                    'class'        => \rock\snippets\CSRF::className(),
                ],


                'captchaView' => [
                    'class'        => \rock\snippets\CaptchaView::className(),
                ],

                'activeForm' => [
                    'class' => \rock\snippets\ActiveForm::className(),
                ]
            ]
        ],

        'execute' => [
            'class' => \rock\execute\CacheExecute::className(),
        ],

        'i18n' => [
            'class' => \rock\i18n\i18n::className(),
            'pathsDicts' => [
                'ru' => [
                    '@rock/messages/ru/lang.php',
                    '@rock/messages/ru/validate.php',
                ],
                'en' => [
                    '@rock/messages/en/lang.php',
                    '@rock/messages/en/validate.php',
                ]
            ],
            'locale' =>  [\rock\LocaleProperties::className(), 'locale']
        ],
        'date' => [
            'class' => \rock\date\DateTime::className(),
            'locale' =>  [\rock\LocaleProperties::className(), 'locale'],
            'formats' => [
                'dmy'   => function(\rock\date\DateTime $dateTime){
                    $nowYear  = date('Y');
                    $lastYear = $dateTime->format('Y');

                    return $nowYear > $lastYear
                        ? $dateTime->format('j F Y')
                        : $dateTime->format('d F');
                },
                'dmyhm' => function(\rock\date\DateTime $dateTime){
                    $nowYear  = date('Y');
                    $lastYear = $dateTime->format('Y');
                    return $nowYear > $lastYear
                        ? $dateTime->format('j F Y H:i')
                        : $dateTime->format('j F H:i');
                },
            ]
        ],

        'mail' => [
            'class' => \rock\mail\Mail::className(),
            'From' => 'support@' . (new \rock\request\Request())->getHost(),
            'FromName' => 'Rock Framework',
        ],

        // Request
        'url' => [
            'class' => \rock\url\Url::className(),
        ],
        'request' => [
            'class' => \rock\request\Request::className(),
            'locale' => [\rock\LocaleProperties::className(), 'locale']
        ],

        // Response
        'response' => [
            'class' => \rock\response\Response::className(),
            //'singleton' => true,
            'locale' =>  [\rock\LocaleProperties::className(), 'locale']
        ],
        'htmlResponseFormatter' => [
            'class' => \rock\response\HtmlResponseFormatter::className(),
        ],
        'jsonResponseFormatter' => [
            'class' => \rock\response\JsonResponseFormatter::className(),
        ],
        'xmlResponseFormatter' => [
            'class' => \rock\response\XmlResponseFormatter::className(),
        ],
        'rssResponseFormatter' => [
            'class' => \rock\response\RssResponseFormatter::className(),
        ],

        // Session & Cookies
        'session' => [
            'class' => \rock\session\Session::className(),
            'cookieParams' => [
                'httponly' => true,
                'lifetime' => 60 * 60 * 24 * 60,
                'setUseCookies' => \rock\session\Session::USE_ONLY_COOKIES
            ],
        ],
        'cookie' => [
            'class' => \rock\cookie\Cookie::className(),
        ],

        // Security
        'security' => [
            'class' => Security::className(),
        ],
        'sanitize' => [
            'class' => \rock\sanitize\Sanitize::className(),
        ],
        'validate' => [
            'class' => \rock\validate\Validate::className(),
            'locale' =>  [\rock\LocaleProperties::className(), 'locale']
        ],
        'csrf' => [
            'class' => \rock\csrf\CSRF::className(),
        ],
        'captcha' => [
            'class' => \rock\captcha\Captcha::className(),
            // Captcha string length
            'length' => 0,
            // Noise white
            'whiteNoiseDensity' => 1 / 6,
            // Noise black
            'blackNoiseDensity' => 1 / 30,
        ],

        // user & RBAC
        'user' => [
            'class' => \rock\user\User::className(),
            'container' => 'user',
        ],
        'rbac' =>[
            'class' => \rock\rbac\DBManager::className(),
        ],


        'log' => [
            'class' => \rock\log\Log::className()
        ],

        Role::className() =>[
            'class' => Role::className(),
        ],
        Permission::className() =>[
            'class' => Permission::className(),
        ],
    ],
    require(__DIR__ . '/widgets.php')
);