<?php

namespace App\Http\Controllers;

use App\I18n;
use App\Planet;
use App\Technology;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Class PlanetController
 * @package App\Http\Controllers
 */
class TechController extends Controller
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
     * Show list of technologies at given planet
     * For technologies level 1+ shows current level with calculated bonus
     *
     * @param Request $request
     * @param int $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showTechByPlanet(Request $request, int $planetId)
    {
        $user = User::find($request->auth->id);
        $planet = Planet::find($planetId);

        if ($planet->owner_id != $user->id) {
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);
        }


        $ref = app('App\Http\Controllers\BuildingController')
            ->refreshPlanet($request, $planet);

        $techAvailableList = Technology::where('race', $user->race)->get();
        $allTechAtUser = $user->technologies()
            ->wherePivot('owner_id', $user->id)
            ->get();

        $result = [];

        foreach ($techAvailableList as $technology) {
            $techAtUser = $allTechAtUser->find($technology->id);

            $level = !empty($techAtUser) ? $techAtUser->pivot->level : 0;

            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $technology, $level + 1, $planetId);

            $upgradesArray = [];

            foreach ($resources['upgrades'] as $categoryName => $category) {
                foreach ($category as $key => $bonus) {
                    $upgradesArray[] = [
                        'name_i18n' => $technology->i18n($request->auth->language)->name,
                        'name' => $technology->name,
                        'current' => $resources['upgradesCurrent'][$categoryName][$key],
                        'next' => $bonus,
                    ];
                }
            }

            $res = [
                'id' => $technology->id,
                'name' => $technology->i18n($request->auth->language)->name,
                'image' => imagePath($technology),
                'description' => $technology->i18n($request->auth->language)->description,
                'type' => $technology->type,
                'race' => $technology->race,
                'level' => $level,
                'resources' => [
                    'cost' => $resources['cost'],
                    'production' => $resources['production'],
                ],
                'requirements' => $resources['requirements'],
                //'upgrades' => $resources['upgrades'],
                'upgrades' => $upgradesArray,
                'startTime' => !empty($techAtUser) ? $techAtUser->pivot->startTime : null,
                'timeToBuild' => !empty($techAtUser) ? $techAtUser->pivot->timeToBuild : null,
                'planet_id' => !empty($techAtUser) ? $techAtUser->pivot->planet_id : null,
                'created_at' => !empty($techAtUser) ? Carbon::parse($techAtUser->pivot->created_at)->format('Y-m-d H:i:s') : null,
                'updated_at' => !empty($techAtUser) ? Carbon::parse($techAtUser->pivot->updated_at)->format('Y-m-d H:i:s') : null,
            ];
            $result[] = $res;
        }

        return response()->json($result, 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMyTech(Request $request)
    {
        $userId = $request->auth->id;
        $res = User::find($userId)->technologies;

        return response()->json($res, 200);
    }

    /**
     * @param Request $request
     * @param $planetId Planet
     * @param $techId Technology
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneTech(Request $request, $planetId, $techId)
    {
        $user = User::find($request->auth->id);
        $planet = Planet::find($planetId);
        $tech = Technology::find($techId);

        if ($planet->owner_id != $user->id) {
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);
        }

        //race check skipped - cannot be not your race

        $techAtUser = $user->technologies->find($techId);

        $level = !empty($techAtUser) ? $techAtUser->pivot->level : 0;

        $resources = app('App\Http\Controllers\ResourceController')
            ->parseAll($user, $tech, $level + 1, $planetId);

        if (!empty($techAtUser)) {
            $res[] = [
                'id' => $tech->id,
                'name' => $tech->i18n($request->auth->language)->name,
                'image' => imagePath($tech),
                'description' => $tech->description,
                'type' => $tech->type,
                'race' => $tech->race,
                'level' => $techAtUser->pivot->level,
                'resources' => [
                    'cost' => $resources['cost'],
                    'production' => $resources['production'],
                ],
                'requirements' => $resources['requirements'],
                'upgrades' => $resources['upgrades'],
                'startTime' => $techAtUser->pivot->startTime,
                'timeToBuild' => $techAtUser->pivot->timeToBuild,
                'planet_id' => $techAtUser->pivot->planet_id,
                'created_at' => Carbon::parse($techAtUser->pivot->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($techAtUser->pivot->updated_at)->format('Y-m-d H:i:s'),
            ];
            return response()->json($res, 200);
        } else { //level 0 tech
            return response()->json([
                'id' => $tech->id,
                'name' => $tech->i18n($request->auth->language)->name,
                'image' => imagePath($tech),
                'description' => $tech->description,
                'type' => $tech->type,
                'race' => $tech->race,
                'resources' => [
                    'cost' => $resources['cost'],
                    'production' => $resources['production'],
                ],
                'requirements' => $resources['requirements'],
                'upgrades' => $resources['upgrades'],
                'level' => 0,
            ], 200);
        }
    }

    /**
     * @param Request $request
     * @param $id Planet id
     * @param $tid Technology id
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeTech(Request $request, $id, $tid)
    {
        $user = User::find($request->auth->id);
        $planet = Planet::find($id);

        if ($planet->owner_id != $user->id) {
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);
        }

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        $tech = Technology::find($tid);

        if ($user->race != $tech->race) {
            return response()->json(['status' => 'error', 'message' => 'race mismatch'], 403);
        }
        //slots check
        if (!empty($ref['techQued'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'no slots available',
                'time_remain' => $ref['techQueTimeRemain']
            ], 403); //
        }

        //если нет у юзера - создали с уровнем 0
        $techAtUser = $user->technologies()->where('technology_id', $tid)->first();

        if (is_null($techAtUser)) {
            $user->technologies()->attach($tid, ['level' => 0]);
        }

        //рефрешнули
        $techAtUser = $user->technologies()->find($tid);
        $techAtUserPivot = $techAtUser->pivot;

        $level = !empty($techAtUserPivot->level) ? $techAtUserPivot->level : 0;

        //resources check
        $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($user, $tech, $level + 1, $planet->id);


        if (!app('App\Http\Controllers\BuildingController')->checkResourcesAvailable($planet, $resourcesAtLevel['cost'])) {
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);
        }

        app('App\Http\Controllers\BuildingController')->buy($planet, $resourcesAtLevel['cost']);

        $timeToBuild = $resourcesAtLevel['cost']['time'];
        if ($timeToBuild < 1) {
            $timeToBuild = 1;
        }

        $user->technologies()->updateExistingPivot($techAtUser->id, [
            'level' => $level,
            'planet_id' => $planet->id,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $level,
            'time' => $timeToBuild], 200);
    }

    /**
     * Cancel technology building request
     *
     * @uses i18n
     * @param Request $request
     * @param int $tid
     * @param int $id
     * @param bool $isPaid
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelBuilding(Request $request, int $tid, int $id, bool $isPaid = false)
    {
        $language = $request->auth->language;

        $planet = Planet::find($id);
        if (empty($planet)) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);
        }

        $user = User::find($request->auth->id);
        $owner = User::find($planet->owner_id);

        if ($user != $owner) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_yours', $language)], 403);
        }

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        $technology = Technology::find($tid);
        $userTechnology = $user->technologies()->find($tid);

        //que check
        if (empty($ref['techStartTime']) || empty($ref['techQued'])) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('tech_que_empty', $language)], 403);
        }

        //resources refund
        $resources = app('App\Http\Controllers\ResourceController')
            ->parseAll($owner, $userTechnology, 1, $id);

        $fullCost = [
            'metal' => $resources['cost']['metal'],
            'crystal' => $resources['cost']['crystal'],
            'gas' => $resources['cost']['gas'],
        ];

        $refund = app('App\Http\Controllers\ResourceController')
            ->refund($planet, $fullCost, $isPaid);

        $user->technologies()->detach($userTechnology->id);

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => MessagesController::i18n('tech_removed_from_que', $language),
            'refunded' => [
                'metal' => $refund['metal'],
                'crystal' => $refund['crystal'],
                'gas' => $refund['gas'],
            ],
        ], 200);
    }
}
