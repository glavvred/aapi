<?php

namespace App\Http\Controllers;

use App\Message;
use App\Skill;
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

        if (empty($messages)) {
            return 'Error is not yet translated. please tell administration: '. $messageName. ' with language: '.$language;
        }

        return $messages->text;
    }

    /**
     * Skills translation
     * @param $messageName
     * @param $language
     * @return string
     */
    public static function skills_i18n($messageName, $language)
    {
        $messages = Skill::where('name', $messageName)
            ->where('language', $language)->first();

        if (empty($messages)) {
            return 'Skill/Property is not yet translated. please tell administration: '. $messageName. ' with language: '.$language;
        }

        return $messages->text;
    }
}
