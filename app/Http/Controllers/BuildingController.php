<?php

namespace App\Http\Controllers;

use App\Building;
use App\Planet;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingController extends Controller
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
     * Show building details
     * For level 1+ show current building stats
     *
     * @param Request $request
     * @param int $id Planet
     * @param int $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneBuilding(Request $request, int $id, int $bid)
    {
        $user = User::find($request->auth->id);
        $building = Building::where('id', $bid)->first();

        //building info
        $res = [
            'id' => $building->id,
            'name' => $building->name,
            'description' => $building->description,
            'type' => $building->type,
            'race' => $building->race,
            'resources' => [
                'base' => [
                    'metal' => $building->cost_metal,
                    'crystal' => $building->cost_crystal,
                    'gas' => $building->cost_gas,
                    'time' => $building->cost_time,
                    'dark_matter' => $building->dark_matter_cost,
                ],
                'base_per_hour' => [
                    'metal' => $building->metal_ph,
                    'crystal' => $building->crystal_ph,
                    'gas' => $building->gas_ph,
                ],
            ],
        ];

        //race check
        if ($user->race != $building->race) {
            return response()->json(['status' => 'error', 'message' => 'race mismatch', 'info' => $res], 200);
        }

        $planet = Planet::find($id);
        $ref = $this->refreshPlanet($request, $planet);
        //fresh buildings data
        $buildingAtUser = $planet->buildings->find($bid);

        //actual building at planet
        if (!empty($buildingAtUser)) {
            $resourcesCurrent = $this->calcLevelResourceCost($buildingAtUser->pivot->level, $res['resources']['base']);

            $res['resources']['current'] = $resourcesCurrent;
            $res['resources']['current_per_hour'] = $resourcesCurrent;

            $res['level'] = $buildingAtUser->pivot->level;
            $res['startTime'] = $buildingAtUser->pivot->startTime;
            $res['timeToBuild'] = $buildingAtUser->pivot->timeToBuild;
            $res['destroying'] = $buildingAtUser->pivot->destroying;
            $res['updated_at'] = $buildingAtUser->pivot->updated_at;
        }
        return response()->json($res);
    }

    /**
     * Refresh planet data, ques, resources
     *
     * @param $request Request
     * @param Planet $planet
     * @return array
     */
    public function refreshPlanet(Request $request, Planet $planet)
    {
        $owner = $request->auth->id;
        $user = User::find($owner);

        $timeRemain = 0;
        $buildingsQued = 0;
        $techQueTimeRemain = 0;
        $techStatus = 0;

        $overallMetalPH = 0;
        $overallCrystalPH = 0;
        $overallGasPH = 0;
        $overallEnergyAvailable = 0;
        $overallEnergyUsed = 0;

        //строения
        foreach ($planet->buildings as $building) {
            $buildingByPivot = $building->pivot;

            //todo: актуальная формула
            $overallMetalPH += round($building->metal_ph * pow(1.15, $buildingByPivot->level));
            $overallCrystalPH += round($building->crystal_ph * pow(1.15, $buildingByPivot->level));
            $overallGasPH += round($building->gas_ph * pow(1.15, $buildingByPivot->level));
            $energy = round($building->energy_ph * pow(1.15, $buildingByPivot->level));
            if ($energy > 0)
                $overallEnergyAvailable += $energy;
            else
                $overallEnergyUsed += $energy;

            //update ques
            if (!empty($buildingByPivot->startTime) && !(empty($buildingByPivot->timeToBuild))) {
                $endTime = Carbon::parse($buildingByPivot->startTime)->addSecond($buildingByPivot->timeToBuild);

                if (Carbon::now()->diffInSeconds($endTime, false) <= 0) {

                    //что то достроилось
                    $add = 0;
                    if ($buildingByPivot->startTime)
                        $add = ($buildingByPivot->destroying) ? '-1' : 1;

                    $planet->buildings()->updateExistingPivot($building->id, [
                        'level' => $buildingByPivot->level + ($add),
                        'startTime' => null,
                        'timeToBuild' => null,
                        'destroying' => 0,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

                } else {
                    $timeRemain = Carbon::now()->diffInSeconds($endTime);
                    $buildingsQued++;
                }
            }
        }

        //технологии
        foreach ($user->technologies as $technology) {
            $techByPivot = $technology->pivot;

            //update ques
            $techEndTime = Carbon::parse($techByPivot->startTime)->addSecond($techByPivot->timeToBuild);

            if (Carbon::now()->diffInSeconds($techEndTime, false) <= 0) {
                $techStatus = 0;

                //что то достроилось
                $user->technologies()->updateExistingPivot($technology->id, [
                    'level' => $techByPivot->level + 1,
                    'startTime' => null,
                    'timeToBuild' => null,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
            } else {
                $techQueTimeRemain = Carbon::now()->diffInSeconds($techEndTime);
                $techStatus = 1;
            }
        }

        //update resources
        $diff = Carbon::now()->diffInSeconds(Carbon::parse($planet->updated_at));
        $planet->increment('metal', round($overallMetalPH * ($diff / 60)));
        $planet->increment('crystal', round($overallCrystalPH * ($diff / 60)));
        $planet->increment('gas', round($overallGasPH * ($diff / 60)));
        $planet->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $planet->save();

        return ['resources' => [
            'metal' => $planet->metal,
            'crystal' => $planet->crystal,
            'gas' => $planet->gas,
            'metalPh' => $planet->metal_ph,
            'crystalPh' => $planet->crystal_ph,
            'gasPh' => $planet->gas_ph,
            'energyAvailable' => $overallEnergyAvailable,
            'energyUsed' => $overallEnergyUsed,
        ],
            'buildingQued' => $buildingsQued,
            'queTimeRemain' => $timeRemain,
            'techQued' => $techStatus,
            'techQueTimeRemain' => $techQueTimeRemain,
        ];

    }

    /**
     * TODO: move to Resource class
     * @param $level
     * @param array $levelOneResources
     * @return mixed
     */
    public function calcLevelResourceCost($level, array $levelOneResources)
    {
        $res['metal'] = round($levelOneResources['metal'] * pow(1.55, $level));
        $res['crystal'] = round($levelOneResources['crystal'] * pow(1.55, $level));
        $res['gas'] = round($levelOneResources['gas'] * pow(1.55, $level));
        $res['dark_matter'] = round($levelOneResources['dark_matter'] * pow(1.55, $level));
        $res['time'] = $this->calcLevelTimeCost($level, $levelOneResources['time']);
        return $res;
    }

    /**
     * TODO: move to Resource class
     *
     * @param $level
     * @param $levelOneTimeCost
     * @return float
     */
    public function calcLevelTimeCost($level, $levelOneTimeCost)
    {
        return round($levelOneTimeCost * pow(1.55, $level));
    }

    /**
     * Show buildings list by planetId
     *
     * @param Request $request
     * @param int $planetId Planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllBuildings(Request $request, int $planetId)
    {
        $planet = Planet::find($planetId);
        $user = User::find($request->auth->id);

        //актуализация данных по планете
        $ref = $this->refreshPlanet($request, $planet);

        $buildings = Building::where('race', $request->auth->race)->get();

        $res = [];

        foreach ($buildings as $building) {
            //building info
            $res[$building->id] = [
                'id' => $building->id,
                'name' => $building->name,
                'description' => $building->description,
                'type' => $building->type,
                'race' => $building->race,
                'resources' => [
                    'base' => [
                        'metal' => $building->cost_metal,
                        'crystal' => $building->cost_crystal,
                        'gas' => $building->cost_gas,
                        'time' => $building->cost_time,
                        'dark_matter' => $building->dark_matter_cost,
                    ],
                    'base_per_hour' => [
                        'metal' => $building->metal_ph,
                        'crystal' => $building->crystal_ph,
                        'gas' => $building->gas_ph,
                    ],
                ],
            ];

            $bap = $planet->buildings()
                ->wherePivot('planet_id', $planetId)
                ->wherePivot('building_id', $building->id)
                ->first();

            //actual building at planet
            if (!empty($bap)) {
                $resourcesCurrent = $this->calcLevelResourceCost($bap->pivot->level, $res[$building->id]['resources']['base']);

                $res[$building->id]['resources']['current'] = $resourcesCurrent;
                $res[$building->id]['resources']['current_per_hour'] = $resourcesCurrent;

                $res[$building->id]['level'] = $bap->pivot->level;
                $res[$building->id]['startTime'] = $bap->pivot->startTime;
                $res[$building->id]['timeToBuild'] = $bap->pivot->timeToBuild;
                $res[$building->id]['destroying'] = $bap->pivot->destroying;
                $res[$building->id]['updated_at'] = $bap->pivot->updated_at;
            }
        }
        return response()->json($res);
    }

    /**
     * Add one level to given building
     * Add level 0 if no building exists
     * Resource, slots check
     *
     * @param $request Request
     * @param int $id Planet
     * @param int $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeBuilding(Request $request, int $id, int $bid)
    {
        //нашли планету
        $planet = Planet::find($id);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        $ref = $this->refreshPlanet($request, $planet);

        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'no slots available', 'time_remain' => $ref['queTimeRemain']], 403);

        $building = Building::find($bid);

        //если нет на планете - создали с уровнем 0
        $buildingAtPlanet = $planet->buildings()->where('building_id', $bid)->first();

        if (is_null($buildingAtPlanet)) {
            $planet->buildings()->attach($bid);
            $buildingAtPlanet = $planet->buildings->find($bid);
        }

        $level = !empty($buildingAtPlanet->pivot->level) ? $buildingAtPlanet->pivot->level : 0;

        //resources check
        $resources = [
            'metal' => $building->cost_metal,
            'crystal' => $building->cost_crystal,
            'gas' => $building->cost_gas,
            'dark_matter' => $building->cost_dark_matter,
            'time' => $building->cost_time,
        ];
        $resourcesAtLevel = $this->calcLevelResourceCost($level + 1, $resources);

        if (!$this->checkResourcesAvailable($planet, $resourcesAtLevel))
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);

        $this->buy($planet, $resourcesAtLevel);

        $timeToBuild = $this->calcLevelTimeCost($level + 1, $buildingAtPlanet->cost_time);

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $level,
            'destroying' => 0,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $level + 1,
            'time' => $timeToBuild], 200);
    }

    /**
     * Simple resource checker
     *
     * @param $planet Planet
     * @param array $resourcesToCheck
     * @return bool
     * @throws \BadMethodCallException
     */
    public function checkResourcesAvailable(Planet $planet, array $resourcesToCheck)
    {
        if ($planet &&
            ($resourcesToCheck['metal'] <= $planet->metal) &&
            ($resourcesToCheck['crystal'] <= $planet->crystal) &&
            ($resourcesToCheck['gas'] <= $planet->gas)
        )
            return true;
        return false;
    }

    /**
     * Remove given amount of resources from given planet
     *
     * @param Planet $planet
     * @param array $resources
     * @return void
     */
    public function buy(Planet $planet, array $resources)
    {
        try {
            DB::beginTransaction();
            $planet = DB::table('planets')->where('id', $planet->id)->lockForUpdate()->first();
            DB::table('planets')->where('id', $planet->id)
                ->update([
                    'metal' => $planet->metal - $resources['metal'],
                    'crystal' => $planet->crystal - $resources['crystal'],
                    'gas' => $planet->gas - $resources['gas']
                ]);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            var_dump($exception->getMessage());
        }
    }

    public function cancelBuilding(Request $request, int $bid, int $planetId)
    {
        //нашли планету
        $planet = Planet::find($planetId);
        $user = User::find($request->auth->id);

        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        if ($planet->owner_id != $user->id)
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);

        $ref = $this->refreshPlanet($request, $planet);

        //slots check
        if (!$ref['buildingQued'] || ($ref['queTimeRemain'] == 0))
            return response()->json(['status' => 'error', 'message' => 'no buildings qued'], 403);

        $building = Building::find($bid);
        $buildingAtPlanet = $planet->buildings()->find($bid);

        //resources refund
        $resources = [
            'metal' => - $building->cost_metal,
            'crystal' => - $building->cost_crystal,
            'gas' => - $building->cost_gas,
            'time' => - $building->cost_time,
            'dark_matter' => - $building->cost_dark_matter,
        ];

        $refund = $this->calcLevelResourceCost($buildingAtPlanet->pivot->level + 1, $resources);

        $this->buy($planet, $refund);

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'startTime' => null,
            'timeToBuild' => null,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        $planet = Planet::find($planetId);

        $ref = $this->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => 'building removed from que',
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
                'current_level' => $buildingAtPlanet->pivot->level,
            ],
            'buildingQued' => $ref['buildingQued'],
            'queTimeRemain' => $ref['queTimeRemain'],

            'refunded' => [
                'metal' => $refund['metal'],
                'crystal' => $refund['crystal'],
                'gas' => $refund['gas'],

            ],
        ], 200);

    }

    /**
     * Remove one level from given building
     * Error if building current level is 0
     * Slots check, resources refund
     *
     * @param $request Request
     * @param int $planetId
     * @param int $buildingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);

        $ref = $this->refreshPlanet($request, $planet);
        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'no slots available', 'time_remain' => $ref['queTimeRemain']], 403);

        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();
        if (!$buildingAtPlanet || ($buildingAtPlanet->pivot->level <= 0))
            return response()->json(['status' => 'error', 'message' => 'building is lvl 0'], 403);

        //resources refund
        $resources = [
            'metal' => -$buildingAtPlanet->cost_metal,
            'crystal' => -$buildingAtPlanet->cost_crystal,
            'gas' => -$buildingAtPlanet->cost_gas,
            'dark_matter' => -$buildingAtPlanet->cost_dark_matter,
            'time' => -$buildingAtPlanet->cost_time,
        ];
        $resourcesAtLevel = $this->calcLevelResourceCost($buildingAtPlanet->pivot->level - 1, $resources);

        //todo: slots check

        $this->buy($planet, $resourcesAtLevel);

        $timeToBuild = $this->calcLevelTimeCost($buildingAtPlanet->pivot->level - 1, $buildingAtPlanet->cost_time);


        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $buildingAtPlanet->pivot->level - 1,
            'destroying' => 1,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $buildingAtPlanet->pivot->level - 1,
            'timeToBuild' => $this->calcLevelTimeCost($buildingAtPlanet->pivot->level - 1, $buildingAtPlanet->cost_time)
        ], 200);
    }

    /**
     * Slots checker by given planet
     * @param Planet $planet
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function slotsAvailable(Planet $planet)
    {

        $bap = $planet->buildings;
        $occupied = 0;
        foreach ($bap as $building) {
            $occupied += ($building->pivot->level);
        }
        return ['available' => $planet->slots, 'occupied' => $occupied];
    }

    /**
     * TODO: move to Resource class
     * @param $level
     * @param $formula
     */
    public function calcLevelResourcePh($level, $formula)
    {

    }

}
