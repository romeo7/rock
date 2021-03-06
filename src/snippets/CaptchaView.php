<?php
namespace rock\snippets;



use rock\base\Alias;
use rock\captcha\Captcha;
use rock\di\Container;
use rock\helpers\FileHelper;

/**
 * @see Captcha
 */
class CaptchaView extends Snippet
{
    /** @var  Captcha */
    protected $captcha = 'captcha';

    public function init()
    {
        parent::init();
        $this->captcha = Container::load($this->captcha);
    }

    public function get()
    {
        if (!$dataImage = $this->captcha->get()) {
            return '#';
        }

        if ($dataImage['mime_type'] === 'image/x-png') {
            $ext = '.png';
        } elseif ($dataImage['mime_type'] === 'image/jpeg') {
            $ext = '.jpg';
        } else {
            $ext = '.gif';
        }

        $uniq = uniqid();
        $path = Alias::getAlias('@assets') . DS . 'cache' . DS . 'captcha' . DS . $uniq . $ext;

        if (FileHelper::create($path, $dataImage['image'])) {
            return Alias::getAlias('@web') . '/cache/captcha/' . $uniq . $ext;
        }

        return '#';
    }
}