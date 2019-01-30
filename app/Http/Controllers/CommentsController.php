<?php

namespace App\Http\Controllers;

use App\Planet;
use App\User;
use Illuminate\Http\Request;
use App\Comments;

/**
 * Class CommentsController
 * @package App\Http\Controllers
 */
class CommentsController extends Controller
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
     * Show all my comments
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllMyComments($request)
    {
        $authId = $request->auth->id;
        $res = Comments::where('owner_id', $authId)
            ->get();
        return response()->json($res, 200);
    }

    /**
     * Show comments over the planet
     * @param User $user
     * @param Planet $planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showCommentsForPlanet(User $user, Planet $planet)
    {
        $res = Comments::where(function ($query) use ($user) {
            $query->where('owner_id', '=', $user->id);
        })
            ->where(function ($query) use ($user) {
                $query->where('alliance_id', '=', $user->alliance_id)
                    ->orWhere('share_with_alliance', '=', 'true');
            })
            ->andWhere('coordinateX', $planet->coordinateX)
            ->andWhere('coordinateY', $planet->coordinateY)
            ->andWhere('orbit', $planet->orbit);

        return response()->json($res, 200);
    }

    /**
     * Show comments over solarSystem
     * @param User $user
     * @param $xc
     * @param $yc
     * @return \Illuminate\Http\JsonResponse
     */
    public function showCommentsOverSolarSystem(User $user, $xc, $yc)
    {

        $res = Comments::where('owner_id', '=', $user->id)
            ->orWhere(function ($query) use ($user) {
                $query->where('alliance_id', '=', $user->alliance_id)
                    ->orWhere('share_with_alliance', '=', 'true');
            })
            ->andWhere('coordinateX', $xc)
            ->andWhere('coordinateY', $yc)
            ->andWhere('orbit', null);

        return response()->json($res, 200);
    }
}
