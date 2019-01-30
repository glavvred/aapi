<?php

namespace App\Http\Controllers;

use App\Quest;
use App\User;
use App\UserQuest;
use Illuminate\Http\Request;

/**
 * Class DefenceController
 * @package App\Http\Controllers
 */
class QuestController extends Controller
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
     * Get quest list, current user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(Request $request)
    {
        $user = User::find($request->auth->id)->first();

        $quests = $this->getQuests($user);

        $res = [
            'storyline' => [
                'available' => $quests->storyline->available,
                'done' => $quests->storyline->done,
            ],
            'daily' => [
                'available' => $quests->daily->available,
                'done' => $quests->daily->done,
            ],
            'tutorial' => [
                'available' => $quests->tutorial->available,
                'done' => $quests->tutorial->done,
            ],
        ];

        return response()->json(['status' => 'success', 'quests' => $res], 200);
    }


    /**
     * Get quest list by user
     * @param User $user
     * @return mixed
     */
    public function getQuests(User $user)
    {
        $quests = Quest::where('race', '=', $user->auth->race)
            ->leftJoin('user_quest', 'user_quests.quest_id', '=', 'quests.id')
            ->leftJoin('quests_lang', 'quests.name', '=', 'quests_lang.name')
            ->where('user_quests.user_id', $user->id)
            ->where('language', $user->auth->language)
            ->get();


        return $quests;
    }

    public function daily()
    {
    }

    /**
     * Error / Messages translation
     * @param $questName
     * @param $language
     * @return string
     */
    public static function i18n($questName, $language)
    {
        $messages = Quest::where('name', $questName)
            ->leftJoin('quests_lang', 'quests.name', '=', 'quests_lang.name')
            ->where('language', $language)
            ->first();

        if (empty($messages)) {
            return 'Error is not yet translated. please tell administration: '. $questName. ' with language: '.$language;
        }

        return $messages->text;
    }
}
