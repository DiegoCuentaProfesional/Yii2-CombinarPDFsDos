<?php

namespace app\assets;

use yii\web\AssetBundle;

class CompresorAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap',
        'css/compresor.css',
        'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
    ];
    public $js = [
        'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
        'js/compresor.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];

    public $jsOptions = [
        'position' => \yii\web\View::POS_END,
    ];

    public function init()
    {
        parent::init();

        // Añade la marca de tiempo solo a los archivos locales en CSS
        foreach ($this->css as &$css) {
            if (!preg_match('/^https?:\/\//', $css)) {
                $css = $css . '?v=' . time(); // Usar time() para forzar recarga
            }
        }

        // Añade la marca de tiempo solo a los archivos locales en JS
        foreach ($this->js as &$js) {
            if (is_array($js)) {
                if (!preg_match('/^https?:\/\//', $js[0])) {
                    $js[0] = $js[0] . '?v=' . filemtime($this->basePath . '/' . $js[0]);
                }
            } else {
                if (!preg_match('/^https?:\/\//', $js)) {
                    $js = $js . '?v=' . filemtime($this->basePath . '/' . $js);
                }
            }
        }
    }
}