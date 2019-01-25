<?php

namespace App;

use http\Env\Request;

class Quest
{

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

    public function getQuests(User $user)
    {


        return [];
    }

}


