<?php

namespace apps\common\models\forms;


use apps\common\models\users\BaseUsers;
use rock\base\Model;
use rock\base\ModelEvent;
use rock\helpers\ArrayHelper;
use rock\Rock;
use rock\validate\Validate;

class BaseLoginForm extends Model
{
    const EVENT_BEFORE_LOGIN = 'beforeLogin';
    const EVENT_AFTER_LOGIN = 'afterLogin';


    /** @var  string */
    public $email;
    /** @var  string */
    public $password;
    /** @var  string */
    public $_csrf;

    public $redirectUrl;
    public $isLogged = false;



    public function rules()
    {
        return [
            [
                self::RULE_VALIDATE, '_csrf', 'validateCSRF', 'one'
            ],
            [
                self::RULE_SANITIZE, ['email', 'password'], 'trim'
            ],
            [
                self::RULE_VALIDATE, ['email', 'password'], 'required',
            ],
            [
                self::RULE_VALIDATE, 'email', 'length' => [4, 80, true], 'email'
            ],
            [
                self::RULE_VALIDATE, 'password', 'length' => [6, 20, true], 'regex' => ['/^[a-z\d\-\_\.]+$/i']
            ],
            [
                self::RULE_SANITIZE, 'email', 'lowercase'
            ],
            [
                self::RULE_SANITIZE, ['email', 'password'], 'removeTags'
            ],
            [
                self::RULE_VALIDATE, 'password', 'validatePassword', 'validateStatus'
            ],
        ];
    }

    public function safeAttributes()
    {
        return ['email', 'password', $this->Rock->csrf->csrfParam];
    }


    public function attributeLabels()
    {
        return [
            'email' => Rock::t('email'),
            'password'=> Rock::t('password')
        ];
    }


    protected $_users;

    /**
     * Finds user by `email`
     *
     * @return BaseUsers
     */
    public function getUsers()
    {
        if (!isset($this->_users)) {
            if (!$this->_users = BaseUsers::findOneByEmail($this->email, null, false)) {
                $this->addErrorAsPlaceholder(Rock::t('notExistsUser'), 'e_login');
            }
        }

        return $this->_users;
    }


    public function validate(array $attributes = NULL, $clearErrors = true)
    {
        if (!$this->beforeLogin() || !parent::validate()) {
            return false;
        }

        $users = $this->getUsers();
        $users->login_last = $this->Rock->date->isoDatetime();
        if (!$users->save()) {
            $this->addErrorAsPlaceholder(Rock::t('failLogin'), 'e_login');
            return false;
        }

        $this->isLogged = true;
        $data = $users->toArray();
        $this->Rock->user->addMulti(ArrayHelper::intersectByKeys($data, ['id', 'username', 'url']));
        $this->Rock->user->login();

        $this->afterLogin($data);

        //$this->redirect();
        return true;
    }


    /**
     * @param string|null $url
     */
    public function redirect($url = null)
    {
        if (!$this->isLogged) {
            return;
        }
        $response = $this->Rock->response;
        if (!isset($url) && isset($this->redirectUrl)) {
            $url = $this->redirectUrl;
        }
        if (!isset($url)) {
            $response->refresh()->send(true);
        }
        $response->redirect($url)->send(true);
    }

    public function beforeLogin()
    {
        $event = new ModelEvent();
        $this->trigger(self::EVENT_BEFORE_LOGIN, $event);
        return $event->isValid;
    }

    public function afterLogin($result)
    {
        $event = new ModelEvent();
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_LOGIN, $event);
    }



    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     */
    protected function validatePassword($password)
    {
        if ($this->hasErrors()) {
            return true;
        }
        if (!$user = $this->getUsers()) {
            return false;
        }
        if (!$user->validatePassword($password)) {
            $this->addErrorAsPlaceholder(Rock::t('invalidPasswordOrEmail'), 'e_login');
            return false;
        }
        return true;

    }

    protected function validateCSRF($input)
    {
        $v = Validate::required()->csrf()->placeholders(['name' => 'CSRF-token']);
        if (!$v->validate($input)) {
            $this->addErrorAsPlaceholder($v->getFirstError(), 'e_login');
            return false;
        }

        return true;
    }

    protected function validateStatus()
    {
        if ($this->hasErrors()) {
            return true;
        }
        if (!$user = $this->getUsers()) {
            return false;
        }

        if ($user->status !== BaseUsers::STATUS_ACTIVE) {
            $this->addErrorAsPlaceholder(Rock::t('notActivatedUser'), 'e_login');
            return false;
        }
        return true;
    }

}