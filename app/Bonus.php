<?php

namespace App;

use http\Env\Request;

class Bonus
{
    public function technology(User $user)
    {
    }

    public function get(Request $request, int $planetId = null)
    {
        $user = User::find($request->auth->id)->first();
        if (!empty($planetId)) {
            $planet = Planet::find($planetId)->first();
        }

        $res = [
            'powerUps' => $this->powerUp($user),
            'buildings' => (isset($planet)) ? $this->building($user, $planet) : [],
            'technologies' => 1
        ];

        return response()->json($res, 200);
    }

    /**
     * @param User $user
     * @return array
     */
    public function powerUp(User $user)
    {
        return [];
    }

    public function building(User $user, Planet $planet)
    {
        return [];
    }
}
