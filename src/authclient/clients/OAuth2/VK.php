<?php

namespace rock\authclient\clients\OAuth2;


use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Client\StreamClient;
use rock\authclient\ClientInterface;
use rock\authclient\services\Vkontakte;
use rock\authclient\storages\Session;
use rock\base\BaseException;
use rock\components\ComponentsInterface;
use rock\helpers\Json;
use rock\helpers\JsonException;
use rock\log\Log;
use rock\request\Request;
use rock\url\Url;

class VK implements ComponentsInterface, ClientInterface
{
    use \rock\components\ComponentsTrait;

    public $clientId;

    public $clientSecret;

    public $redirectUrl;

    /**
     * @see https://vk.com/dev/api_requests
     * @var string
     */
    public $apiUrl = 'https://api.vk.com/method/users.get?fields=uid,first_name,last_name,nickname,sex,bdate';

    /** @var Vkontakte  */
    protected $service;
    public $scopes = ['email'];

    public function init()
    {
        if (isset($this->service)) {
            return;
        }
        //$serviceFactory = new ServiceFactory();
        // Session storage
        $storage = new Session();

        // Setup the credentials for the requests
        $credentials = new Credentials(
            $this->clientId,
            $this->clientSecret,
            Url::modify([$this->redirectUrl], Url::ABS)
        );

        $this->service = new Vkontakte($credentials, new StreamClient(), $storage, $this->scopes);
    }

    /**
     * @return Vkontakte
     */
    public function getService()
    {
        return $this->service;
    }


    /**
     * {@inheritdoc}
     */
    public function getAuthorizationUrl()
    {
        return $this->service->getAuthorizationUri()->getAbsoluteUri();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes($code = null)
    {
        if (!isset($code)) {
            $code = Request::get('code');
        }

        if (empty($code)) {
            return [];
        }
        // This was a callback request from google, get the token
        $extraAttributes = $this->service->requestAccessToken($code)->getExtraParams();
        // Send a request with it
        try {
            return array_merge(Json::decode($this->service->request($this->apiUrl)), ['extra' => $extraAttributes]);
        } catch (JsonException $e) {
            if (class_exists('\rock\log\Log')) {
                Log::err(BaseException::convertExceptionToString($e));
            }
        }

        return [];
    }
}