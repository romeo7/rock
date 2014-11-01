<?php

namespace rock\widgets;


use rock\captcha\CaptchaInterface;
use rock\helpers\Html;
use rock\Rock;
use rock\url\Url;

class Captcha extends InputWidget implements CaptchaInterface
{
    /**
     * @var string the route of the action that generates the CAPTCHA images.
     * The action represented by this route must be an action of @see \rock\captcha\Captcha .
     */
    public $captchaAction = '/captcha/';
    /**
     * Display format (default: data-uri)
     * @var int
     */
    public $output = self::BASE64;
    /**
     * @var array HTML attributes to be applied to the CAPTCHA image tag.
     * @see Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $imageOptions = [];
    /**
     * @var string the template for arranging the CAPTCHA image tag and the text input tag.
     * In this template, the token `{image}` will be replaced with the actual image tag,
     * while `{input}` will be replaced with the text input tag.
     */
    public $template = '{image} {input}';
    /**
     * @var array the HTML attributes for the input tag.
     * @see Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'form-control'];

    /**
     * Initializes the widget.
     */
    public function init()
    {
        parent::init();

        $this->checkRequirements();
        if (!isset($this->imageOptions['id'])) {
            $this->imageOptions['id'] = $this->options['id'] . '-image';
        }
        if (!isset($this->imageOptions['data-ng-click'])) {
            $this->imageOptions['data-ng-click'] = 'reloadCaptcha("/ajax/captcha/", $event)';
        }
    }

    /**
     * Renders the widget.
     */
    public function run()
    {
        if ($this->hasModel()) {
            $this->calculateClientInputOption();
            $input = Html::activeTextInput($this->model, $this->attribute, $this->options);
        } else {
            $input = Html::textInput($this->name, $this->value, $this->options);
        }

        if ($this->output === self::BASE64) {
            $captcha = $this->Rock->captcha;
            $src = $captcha->getDataUri();
        } else {
            /** @var Url $urlBuilder */
            $urlBuilder = Rock::factory($this->captchaAction, Url::className());
            $src = $urlBuilder->addArgs(['v' => uniqid()])->getAbsoluteUrl(true);
        }

        $image = Html::img(
            $src,
            $this->imageOptions
        );
        echo strtr(
            $this->template,
            [
              '{input}' => $input,
              '{image}' => $image,
            ]
        );
    }

    protected function calculateClientInputOption()
    {
        $formName = $this->model->formName();
        if (!isset($this->options['data-ng-model'])) {
            $this->options['data-ng-model'] = "{$formName}.values.{$this->attribute}";
        }

        if (!isset($this->options['data-ng-class'])) {
            $this->options['data-ng-class'] = 'showHighlightError("'.$formName.'['.$this->attribute.']")';
        }

        if (isset($this->options['value']) && empty($this->options['value']) && !isset($this->options['data-rock-reset-field'])) {
            $this->options['data-rock-reset-field'] = '';
        }
    }

    /**
     * Checks if there is graphic extension available to generate CAPTCHA images.
     * This method will check the existence of ImageMagick and GD extensions.
     * @return string the name of the graphic extension, either "imagick" or "gd".
     * @throws Exception if neither ImageMagick nor GD is installed.
     */
    public static function checkRequirements()
    {
        if (extension_loaded('imagick')) {
            $imagick = new \Imagick();
            $imagickFormats = $imagick->queryFormats('PNG');
            if (in_array('PNG', $imagickFormats)) {
                return 'imagick';
            }
        }
        if (extension_loaded('gd')) {
            $gdInfo = gd_info();
            if (!empty($gdInfo['FreeType Support'])) {
                return 'gd';
            }
        }
        throw new Exception(Exception::CRITICAL, 'GD with FreeType or ImageMagick PHP extensions are required.');
    }
} 