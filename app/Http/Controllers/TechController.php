<?php

namespace App\Http\Controllers;

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

        if ($planet->owner_id != $user->id)
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);

        $techAvailableList = Technology::where('race', $user->race)->get();

        $result = [];

        foreach ($techAvailableList as $technology) {
            $baseCost = [
                'metal' => $technology->cost_metal,
                'crystal' => $technology->cost_crystal,
                'gas' => $technology->cost_gas,
                'dark_matter' => $technology->dark_matter_cost,
                'time' => $technology->cost_time,
            ];

            $res = [
                'id' => $technology->id,
                'name' => $technology->name,
                'description' => $technology->description,
                'type' => $technology->type,
                'race' => $technology->race,
                'resources' => [
                    'base' => $baseCost,
                    'base_per_hour' => $baseCost,
                ]
            ];

            $techAtUser = $user->technologies()
                ->wherePivot('owner_id', $user->id)
                ->wherePivot('technology_id', $technology->id)
                ->first();


            if (!empty($techAtUser)) {
                $currentPrice = app('App\Http\Controllers\BuildingController')
                    ->calcLevelResourceCost($techAtUser->pivot->level, $baseCost);

                $res['level'] = $techAtUser->pivot->level;
                $res['startTime'] = $techAtUser->pivot->startTime;
                $res['timeToBuild'] = $techAtUser->pivot->timeToBuild;
                $res['created_at'] = Carbon::parse($techAtUser->pivot->created_at)->format('Y-m-d H:i:s');
                $res['updated_at'] = Carbon::parse($techAtUser->pivot->updated_at)->format('Y-m-d H:i:s');
                $res['resources']['current'] = $currentPrice;
                $res['resources']['current_per_hour'] = $currentPrice;
            }
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

        if ($planet->owner_id != $user->id)
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);

        $techAtUser = $user->technologies->find($techId);

        //race check
        if ($user->race != $tech->race) {
            $res[] = [
                'id' => $tech->id,
                'name' => $tech->name,
                'description' => $tech->description,
                'type' => $tech->type,
                'race' => $tech->race,
                'resources' => [
                    'metal' => $tech->cost_metal,
                    'crystal' => $tech->cost_crystal,
                    'gas' => $tech->cost_gas,
                    'dark_matter' => $tech->dark_matter_cost,
                    'time' => $tech->cost_time,
                ],
                'level' => 0,
            ];
            return response()->json(['status' => 'error', 'message' => 'race mismatch', 'info' => $res], 200);
        }

        $res = [];

        if (!empty($techAtUser)) {
            $currentPrice = app('App\Http\Controllers\BuildingController')
                ->calcLevelResourceCost($techAtUser->pivot->level,
                    [
                        'metal' => $tech->cost_metal,
                        'crystal' => $tech->cost_crystal,
                        'gas' => $tech->cost_gas,
                        'dark_matter' => $tech->dark_matter_cost,
                        'time' => $tech->cost_time,
                    ]);

            $res[] = [
                'id' => $tech->id,
                'name' => $tech->name,
                'description' => $tech->description,
                'type' => $tech->type,
                'race' => $tech->race,
                'resources' => $currentPrice,
                'level' => $techAtUser->pivot->level,
                'startTime' => $techAtUser->pivot->startTime,
                'timeToBuild' => $techAtUser->pivot->timeToBuild,
                'created_at' => $techAtUser->pivot->created_at,
                'updated_at' => $techAtUser->pivot->updated_at,
            ];
            return response()->json($res, 200);
        } else //level 0 tech
            return response()->json([
                'id' => $tech->id,
                'name' => $tech->name,
                'description' => $tech->description,
                'type' => $tech->type,
                'race' => $tech->race,
                'resources' => [
                    'metal' => $tech->cost_metal,
                    'crystal' => $tech->cost_crystal,
                    'gas' => $tech->cost_gas,
                    'dark_matter' => $tech->dark_matter_cost,
                    'time' => $tech->cost_time,
                ],
                'level' => 0,
                'startTime' => null,
                'timeToBuild' => null,
                'created_at' => null,
                'updated_at' => null,
            ], 200);
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

        if ($planet->owner_id != $user->id)
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        $tech = Technology::all()->find($tid);

        if ($user->race != $tech->race) {
            return response()->json(['status' => 'error', 'message' => 'race mismatch'], 403);
        }

        //slots check
        if (!empty($ref['techQued']) && ($ref['techQueTimeRemain'] > 0))
            return response()->json([
                'status' => 'error',
                'message' => 'no slots available',
                'time_remain' => $ref['techQueTimeRemain']
            ], 403);

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
        $resources = [
            'metal' => $techAtUser->cost_metal,
            'crystal' => $techAtUser->cost_crystal,
            'gas' => $techAtUser->cost_gas,
            'dark_matter' => $techAtUser->cost_dark_matter,
            'time' => $techAtUser->cost_time,
        ];
        $resourcesAtLevel = app('App\Http\Controllers\BuildingController')->calcLevelResourceCost($level + 1, $resources);

        if (!app('App\Http\Controllers\BuildingController')->checkResourcesAvailable($planet, $resourcesAtLevel))
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);

        app('App\Http\Controllers\BuildingController')->buy($planet, $resourcesAtLevel);

        $user->technologies()->updateExistingPivot($techAtUser->id, [
            'level' => $level,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $resourcesAtLevel['time'],
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);


        return response()->json(['status' => 'success',
            'level' => $level,
            'time' => $resourcesAtLevel['time']], 200);
    }

}