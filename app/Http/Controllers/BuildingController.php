<?php

namespace App\Http\Controllers;

use App\Building;
use App\Planet;
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
     * @param $id Planet
     * @param $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneBuilding($id, $bid)
    {
        $planet = Planet::find($id);
        $ref = $this->refreshPlanet($id);
        $buildings = $planet->buildings->find($bid);
        return response()->json($buildings);
    }

    /**
     * @param $id Planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllBuildings(Request $request, $id)
    {
        $planet = Planet::find($id);
        $ref = $this->refreshPlanet($planet);

        $buildings = Building::where('race', $request->auth->race)->get();
        foreach ($buildings as $building) {
            $bap = $planet->buildings()
                ->wherePivot('planet_id', $id)
                ->wherePivot('building_id', $building->id)
                ->first();

            if ($bap) {
                $building->actual = ['level' => $bap->pivot->level,
                                     'startTime' =>$bap->pivot->startTime,
                                     'timeToBuild' => $bap->pivot->timeToBuild,
                                     'updated_at' => $bap->pivot->updated_at,
                                     'destroying' => $bap->destroying
                ];
            }
        }
        return response()->json($buildings);
    }

    /**
     * @param Planet $planet
     * @return array
     */
    public function refreshPlanet(Planet $planet)
    {
        $timeRemain = 0;
        $status = 0;

        $overallMetalPH = 0;
        $overallCrystalPH = 0;
        $overallGasPH = 0;
        $overallEnergyAvailable = 0;
        $overallEnergyUsed = 0;

        foreach ($planet->buildings as $building) {
            $buildingByPivot = $planet->buildings()->where('building_id', $building->id)->first()->pivot;

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
            $endTime = Carbon::parse($buildingByPivot->startTime)->addSecond($buildingByPivot->timeToBuild);

            if (Carbon::now()->diffInSeconds($endTime, false) <= 0) {
                $status = 0;

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
                $status = 1;
            }
        }

        //update resources
        $diff = Carbon::now()->diffInSeconds(Carbon::parse($planet->updated_at));
        $planet->increment('metal', $overallMetalPH * ($diff / 60));
        $planet->increment('crystal', $overallCrystalPH * ($diff / 60));
        $planet->increment('gas', $overallGasPH * ($diff / 60));
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
            'buildingQued' => $status,
            'queTimeRemain' => $timeRemain];

    }

    /**
     * @param $id Planet
     * @param $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeBuilding($id, $bid)
    {
        //нашли планету
        $planet = Planet::find($id);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        $ref = $this->refreshPlanet($planet);

        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'no slots available'], 403);

        //если нет на планете - создали с уровнем 0

        $buildingAtPlanet = $planet->buildings()->where('building_id', $bid)->first();

        if (is_null($buildingAtPlanet)) {
            $planet->buildings()->attach($bid);
        }
        $planet = Planet::find($id);

        $buildingAtPlanet = $planet->buildings->find($bid);

        $level = !empty($buildingAtPlanet->level) ? $buildingAtPlanet->level : 0;

        //resources check
        $resources = ['metal' => $buildingAtPlanet->cost_metal, 'crystal' => $buildingAtPlanet->cost_crystal, 'gas' => $buildingAtPlanet->cost_gas];
        $resourcesAtLevel = $this->calcLevelResourceCost($level + 1, $resources);

        if (!$this->checkResourcesAvailable($id, $resourcesAtLevel))
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
     * @param $level
     * @param array $levelOneResources
     * @return mixed
     */
    public function calcLevelResourceCost($level, array $levelOneResources)
    {
        $res['metal'] = round($levelOneResources['metal'] * pow(1.55, $level));
        $res['crystal'] = round($levelOneResources['crystal'] * pow(1.55, $level));
        $res['gas'] = round($levelOneResources['gas'] * pow(1.55, $level));
        return $res;
    }

    /**
     * @param $planetId
     * @param array $resourcesToCheck
     * @return bool
     */
    public function checkResourcesAvailable($planetId, array $resourcesToCheck)
    {
        $planet = Planet::find($planetId);

        if ($planet &&
            ($resourcesToCheck['metal'] <= $planet->metal) &&
            ($resourcesToCheck['crystal'] <= $planet->crystal) &&
            ($resourcesToCheck['gas'] <= $planet->gas)
        )
            return true;
        return false;
    }

    /**
     * @param Planet $planet
     * @param array $resources
     * @return bool
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
            DB::rollBack(); //rollback the phone selling data
            var_dump($exception->getMessage());
        }
        return true;
    }

    /**
     * @param $level
     * @param $levelOneTimeCost
     * @return float
     */
    public function calcLevelTimeCost($level, $levelOneTimeCost)
    {
        return round($levelOneTimeCost * pow(1.55, $level));
    }

    /**
     * @param $id
     * @param $bid
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgradeBuilding($id, $bid)
    {
        $planet = Planet::find($id);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);

        $ref = $this->refreshPlanet($planet);
        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'no slots available'], 403);

        $buildingAtPlanet = $planet->buildings()->where('building_id', $bid)->first();
        if (!$buildingAtPlanet || ($buildingAtPlanet->pivot->level <= 0))
            return response()->json(['status' => 'error', 'message' => 'building is lvl 0'], 403);

        //resources refund
        $resources = ['metal' => -$buildingAtPlanet->cost_metal, 'crystal' => -$buildingAtPlanet->cost_crystal, 'gas' => -$buildingAtPlanet->cost_gas];
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

    public function calcLevelResourcePh($level, $formula)
    {

    }

}
