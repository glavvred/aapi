<?php

namespace App\Http\Controllers;

use App\Message;
use Laravel\Lumen\Routing\Controller as BaseController;

class MessagesController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Error / Messages translation
     * @param $messageName
     * @param $language
     * @return string
     */
    public static function i18n($messageName, $language)
    {
        $messages = Message::where('name', $messageName)
            ->where('language', $language)->first();

        if (empty($messages))
            return 'Error is not yet translated. please tell administration: '. $messageName. ' with language: '.$language;

        return $messages->text;
    }

}
