<?php

namespace App\Http\Controllers;

use App\Building;
use App\Planet;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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
     * @uses i18n
     * @param Request $request
     * @param int $id Planet
     * @param int $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneBuilding(Request $request, int $id, int $bid)
    {
        $user = User::find($request->auth->id);
        $language = $user->language;

        $building = Building::where('id', $bid)->first();

        //building info
        $res = [
            'id' => $building->id,
            'name' => $building->i18n($user->language)->name,
            'image' => imagePath($building),
            'description' => $building->i18n($user->language)->description,
            'type' => $building->type,
            'race' => $building->race,
        ];

        $planet = Planet::find($id);
        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        if (empty($planet->owner_id))
            return response()->json(['status' => 'success',
                'message' => MessagesController::i18n('planet_uninhabited', $language)], 200);

        $ref = $this->refreshPlanet($request, $planet);
        //fresh buildings data
        $buildingAtUser = $planet->buildings->find($bid);

        $resources = [];

        if (empty($buildingAtUser->pivot->level))
            $level = 1;
        else
            $level = $buildingAtUser->pivot->level;

        if (!empty($building->resources) || !empty($building->requirements) || !empty($building->upgrades)) {
            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $building, $level, $id);
        }

        $res['resources'] = [
            'current' => $resources['cost'],
            'energy' => $resources['production']['energy'],
            'current_per_hour' => [
                'metal' => $resources['production']['metal'],
                'crystal' => $resources['production']['crystal'],
                'gas' => $resources['production']['gas'],
            ],
            'upgrades' => $resources['upgradesCurrent'],
        ];

        //actual building at planet
        if (!empty($buildingAtUser)) {
            $res['level'] = $buildingAtUser->pivot->level;
            $res['startTime'] = $buildingAtUser->pivot->startTime;
            $res['timeToBuild'] = $buildingAtUser->pivot->timeToBuild;
            $res['destroying'] = $buildingAtUser->pivot->destroying;
            $res['updated_at'] = $buildingAtUser->pivot->updated_at;
        }
        return response()->json($res);
    }

    /**
     * Refresh planet data
     * Counts ques, updates buildings, technologies if it is done
     * Counts resources from buildings, updates planet resources by time delta * resources per hour
     *
     * @uses i18n
     * @param $request Request
     * @param Planet $planet
     * @return array
     */
    public function refreshPlanet(Request $request, Planet $planet)
    {
        $start = microtime(true);
        $language = $request->auth->language;

        if (empty($planet))
            return response()->json([
                'status' => 'error',
                'message' => MessagesController::i18n('planet_not_found', $language)
            ], 200);

        $owner = User::find($planet->owner_id);

        $buildingStartTime = $buildingTimeToBuild = $buildingTimeRemain = $buildingsQued = 0;

        $techStartTime = $techTimeToBuild = $techQueTimeRemain = $techStatus = 0;

        $shipTime = $defenceTime = [];

        $overallMetalPH = 0;
        $overallCrystalPH = 0;
        $overallGasPH = 0;
        $overallEnergyAvailable = 0;
        $overallEnergyUsed = 0;
        $storage = Config::get('constants.galaxy.planet.capacity');

        if (!empty($owner)) {
            $energyEfficiency = $this->energyBalance($owner, $planet->buildings);

            //строения
            foreach ($planet->buildings as $building) {
                $buildingByPivot = $building->pivot;

                $resources = app('App\Http\Controllers\ResourceController')
                    ->parseAll($owner, $building, $buildingByPivot->level, $planet->id);

                $overallMetalPH += $resources['production']['metal'] * $energyEfficiency;
                $overallCrystalPH += $resources['production']['crystal'] * $energyEfficiency;
                $overallGasPH += $resources['production']['gas'] * $energyEfficiency;
                $energy = $resources['production']['energy'];

                if (!empty($resources['storage']['metal']))
                    $storage['metal'] += $resources['storage']['metal'];
                if (!empty($resources['storage']['metal']))
                    $storage['crystal'] += $resources['storage']['crystal'];
                if (!empty($resources['storage']['gas']))
                    $storage['gas'] += $resources['storage']['gas'];

                if ($energy > 0)
                    $overallEnergyAvailable += $energy;
                else
                    $overallEnergyUsed += $energy;

                //update ques
                if (!empty($buildingByPivot->startTime)) {
                    $endTime = Carbon::parse($buildingByPivot->startTime)
                        ->addSecond($buildingByPivot->timeToBuild);

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
                        $buildingStartTime = $buildingByPivot->startTime;
                        $buildingTimeToBuild = $buildingByPivot->timeToBuild;
                        $buildingTimeRemain = Carbon::now()->diffInSeconds($endTime);
                        $buildingsQued++;
                    }
                }
            }

            //технологии
            foreach ($owner->technologies as $technology) {
                $techByPivot = $technology->pivot;
                if ($techByPivot->planet_id == $planet->id) {
                    if (!empty($techByPivot->startTime) && !(empty($techByPivot->timeToBuild))) {
                        //update ques
                        $techEndTime = Carbon::parse($techByPivot->startTime)->addSecond($techByPivot->timeToBuild);

                        if (Carbon::now()->diffInSeconds($techEndTime, false) <= 0) {
                            $techStatus = 0;

                            //что то достроилось
                            $owner->technologies()->updateExistingPivot($technology->id, [
                                'level' => $techByPivot->level + 1,
                                'planet_id' => null,
                                'startTime' => null,
                                'timeToBuild' => null,
                                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                        } else {
                            $techStartTime = $techByPivot->startTime;
                            $techTimeToBuild = $techByPivot->timeToBuild;
                            $techQueTimeRemain = Carbon::now()->diffInSeconds($techEndTime);
                            $techStatus = 1;
                        }
                    }
                }
            }

            //корабли
            $ships = $planet->ships()->get();
            foreach ($ships as $ship) {
                $shipByPivot = $ship->pivot;
                if (!empty($shipByPivot->startTime) && !(empty($shipByPivot->timeToBuildOne))) {

                    $diff = Carbon::parse($shipByPivot->updated_at)
                        ->subSeconds($shipByPivot->passedFromLastOne)
                        ->diffInSeconds(Carbon::now(), true);

                    //количество целых за текущий отрезок
                    $quantityBuilt = floor($diff / $shipByPivot->timeToBuildOne);
                    if ($quantityBuilt > $shipByPivot->quantityQued)
                        $quantityBuilt = $shipByPivot->quantityQued;

                    //остаток от деления
                    $timePassed = $diff % $shipByPivot->timeToBuildOne;

                    $fleet = $planet->fleets()->where('owner_id', $request->auth->id)->first();
                    //нет флота игрока на планете
                    if (is_null($fleet)) {
                        DB::table('fleets')->insert([
                            'owner_id' => $request->auth->id,
                            'coordinate_id' => $planet->id,
                            'origin_id' => $planet->id,
                            'overall_speed' => 0,
                            'overall_capacity' => 0,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                        $fleet = $planet->fleets()->where('owner_id', $request->auth->id)->first();
                    }

                    $fs = $fleet->ships->where('ship_id', $ship->id)->first();

                    //нет заданных кораблей в флоте на планете
                    if (is_null($fs)) {
                        DB::table('fleet_ships')->insert([
                            'fleet_id' => $fleet->id,
                            'ship_id' => $ship->id,
                            'quantity' => 0,
                        ]);
                        //refresh
                        $fleet = $fleet->refresh();
                        $fs = $fleet->ships->where('ship_id', $ship->id)->first();
                    }

                    //добавим к первому флоту на этой планете количество построенного
                    $fs->quantity = $fs->quantity + $quantityBuilt;
                    $fs->save();

                    if ($shipByPivot->quantity > $quantityBuilt) {
                        //что то достроилось, но еще остались корабли в очереди

                        $planet->ships()->updateExistingPivot($ship->id, [
                            'quantity' => $shipByPivot->quantity - $quantityBuilt,
                            'passedFromLastOne' => $timePassed,
                            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

                        $shipStartTime = $shipByPivot->startTime;
                        $shipQuantityQued = $shipByPivot->quantityQued;
                        $shipTimePassedFromLast = $timePassed;

                        $shipTimeRemain = Carbon::parse($shipByPivot->startTime)
                            ->addSeconds($shipQuantityQued * $shipByPivot->timeToBuildOne)
                            ->diffInSeconds(Carbon::now(), false);
                        $shipQuantityRemain = $shipByPivot->quantity - $quantityBuilt;

                        $shipTime = [
                            'shipId' => $ship->id,
                            'planetId' => $planet->id,
                            'shipStartTime' => $shipStartTime,
                            'shipOneTimeToBuild' => $shipByPivot->timeToBuildOne,
                            'shipTimeRemain' => $shipTimeRemain,
                            'shipTimePassedFromLast' => $shipTimePassedFromLast,
                            'shipQuantityQued' => $shipQuantityQued,
                            'shipQuantityRemain' => $shipQuantityRemain,
                        ];
                    } else {
                        //все достроилось
                        $shipTime = [
                            'shipId' => null,
                            'planetId' => null,
                            'shipStartTime' => null,
                            'shipTimeRemain' => null,
                            'shipOneTimeToBuild' => null,
                            'shipTimePassedFromLast' => null,
                            'shipQuantityQued' => null,
                            'shipQuantityRemain' => null,
                        ];

                        $planet->ships()->detach($ship->id);
                    }
                }
            }

            //оборона
            $defences = $planet->defencesBuildingNow();
            foreach ($defences as $defence) {
                if (!empty($defence->startTime) && !(empty($defence->timeToBuildOne))) {

                    $diff = Carbon::parse($defence->updated_at)
                        ->subSeconds($defence->passedFromLastOne)
                        ->diffInSeconds(Carbon::now(), true);

                    //количество целых за текущий отрезок
                    $quantityBuilt = floor($diff / $defence->timeToBuildOne);
                    if ($quantityBuilt > $defence->quantityQued)
                        $quantityBuilt = $defence->quantityQued;

                    //остаток от деления
                    $timePassed = $diff % $defence->timeToBuildOne;

                    $fleet = $planet
                        ->fleets()
                        ->where('owner_id', $request->auth->id)
                        ->orderBy('id', 'ASC')
                        ->first();

                    //нет флотов игрока на планете
                    if (is_null($fleet)) {
                        DB::table('fleets')->insert([
                            'owner_id' => $request->auth->id,
                            'coordinate_id' => $planet->id,
                            'origin_id' => $planet->id,
                            'overall_speed' => 0,
                            'overall_capacity' => 0,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                        $fleet = $planet
                            ->fleets()
                            ->where('owner_id', $request->auth->id)
                            ->orderBy('id', 'ASC')
                            ->first();
                    }

                    $defenceFleet = $fleet
                        ->defences()
                        ->where('defence_id', $defence->defence_id)
                        ->first();

                    //нет заданных оборонных юнитов в флоте на планете
                    if (is_null($defenceFleet)) {
                        DB::table('fleet_defences')->insert([
                            'fleet_id' => $fleet->id,
                            'defence_id' => $defence->defence_id,
                            'quantity' => 0,
                        ]);

                        $defenceFleet = $fleet
                            ->defences()
                            ->where('defence_id', $defence->defence_id)
                            ->first();
                    }

                    //добавим к первому флоту на этой планете количество построенного
                    $defenceFleet->quantity = $defenceFleet->quantity + $quantityBuilt;
                    $defenceFleet->save();

                    if ($defence->quantity > $quantityBuilt) {
                        //что то достроилось, но еще остались оборонки в очереди

                        DB::table('planet_defence')
                            ->where([
                                'planet_id' => $planet->id,
                                'defence_id' => $defence->defence_id,
                            ])
                            ->update([
                                'quantity' => $defence->quantity - $quantityBuilt,
                                'passedFromLastOne' => $timePassed,
                                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
                            ]);

                        $defenceStartTime = $defence->startTime;
                        $defenceQuantityQued = $defence->quantityQued;
                        $defenceTimePassedFromLast = $timePassed;

                        $defenceTimeRemain = Carbon::parse($defence->startTime)
                            ->addSeconds($defenceQuantityQued * $defence->timeToBuildOne)
                            ->diffInSeconds(Carbon::now(), false);
                        $defenceQuantityRemain = $defence->quantity - $quantityBuilt;

                        $defenceTime = [
                            'defenceId' => $defence->defence_id,
                            'planetId' => $planet->id,
                            'defenceStartTime' => $defenceStartTime,
                            'defenceOneTimeToBuild' => $defence->timeToBuildOne,
                            'defenceTimeRemain' => $defenceTimeRemain,
                            'defenceTimePassedFromLast' => $defenceTimePassedFromLast,
                            'defenceQuantityQued' => $defenceQuantityQued,
                            'defenceQuantityRemain' => $defenceQuantityRemain,
                        ];
                    } else {
                        //все достроилось
                        $defenceTime = [
                            'defenceId' => null,
                            'planetId' => null,
                            'defenceStartTime' => null,
                            'defenceOneTimeToBuild' => null,
                            'defenceTimeRemain' => null,
                            'defenceTimePassedFromLast' => null,
                            'defenceQuantityQued' => null,
                            'defenceQuantityRemain' => null,
                        ];

                        DB::table('planet_defence')
                            ->where([
                                'planet_id' => $planet->id,
                                'defence_id' => $defence->defence_id,
                            ])
                            ->update([
                                'quantity' => null,
                                'passedFromLastOne' => null,
                                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
                            ]);
                    }
                }
            }

            $diff = Carbon::now()->diffInSeconds(Carbon::parse($planet->updated_at));

            $resourcesToUpdate = $this->resourcesBalance(
                [
                    'metal' => $overallMetalPH,
                    'crystal' => $overallCrystalPH,
                    'gas' => $overallGasPH,
                ],
                $diff, $planet, $storage);
            $overallMetalPH = $resourcesToUpdate['metalPH'];
            $overallCrystalPH = $resourcesToUpdate['crystalPH'];
            $overallGasPH = $resourcesToUpdate['gasPH'];

            $planet->update(['metal' => $resourcesToUpdate['metal']]);
            $planet->update(['crystal' => $resourcesToUpdate['crystal']]);
            $planet->update(['gas' => $resourcesToUpdate['gas']]);

            $planet->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $planet->save();

            $endTime = microtime(true);
        }
        return [
            'resources' => [
                'metal' => $planet->metal,
                'crystal' => $planet->crystal,
                'gas' => $planet->gas,
                'storage' => $storage,
                'metalPh' => $overallMetalPH,
                'crystalPh' => $overallCrystalPH,
                'gasPh' => $overallGasPH,
                'energyAvailable' => $overallEnergyAvailable,
                'energyUsed' => $overallEnergyUsed,
            ],
            'buildingStartTime' => $buildingStartTime,
            'buildingQued' => $buildingsQued,
            'buildingTimeToBuild' => $buildingTimeToBuild,
            'buildingTimeRemain' => $buildingTimeRemain,

            'technologyTimeToBuild' => $techTimeToBuild,
            'techQueTimeRemain' => $techQueTimeRemain,
            'techQued' => $techStatus,
            'techStartTime' => $techStartTime,

            'ships' => $shipTime,
            'defences' => $defenceTime,
            'profile' => [
                'full' => $endTime - $start,
            ],
        ];
    }

    /**
     * Passivisation coefficient for lack of energy
     * @param User $owner
     * @param Collection $buildingsWithPivot
     * @return float
     */
    public function energyBalance(User $owner, Collection $buildingsWithPivot): float
    {
        $energyHave = $energyNeed = 1;

        foreach ($buildingsWithPivot as $building) {
            $resources = app('App\Http\Controllers\ResourceController')->parseAll($owner, $building, $building->pivot->level, $building->pivot->planet_id);
            if ($resources['production']['energy'] < 0)
                $energyNeed += $resources['production']['energy'];
            else
                $energyHave += $resources['production']['energy'];
        }

        if (($energyHave / abs($energyNeed)) > 1)
            $eff = 1;
        else
            $eff = $energyHave / abs($energyNeed);

        return $eff;
    }

    public function resourcesBalance($resourcesPH, $time, Planet $planet, Array $storage): array
    {
        //добыто за промежуток
        $metalMined = $resourcesPH['metal'] * ($time / 60);
        $crystalMined = $resourcesPH['crystal'] * ($time / 60);
        $gasMined = $resourcesPH['gas'] * ($time / 60);

        //металл
        //хватает складов
        if ($planet->metal < $storage['metal']) {
            $metalPH = $resourcesPH['metal'];
            //склады заполнены за этот промежуток
            if ($planet->metal + $metalMined > $storage['metal']) {
                $metalMined = $storage['metal'] - $planet->metal;
                $metalPH = 0;
            }
        } else {
            //не хватает складов, добыча в минус
            $metalMined = ($storage['metal'] - $planet->metal) * ($time / 60) / Config::get('constants.galaxy.planet.resources_overflow_divider');
            $metalPH = ($storage['metal'] - $planet->metal) / Config::get('constants.galaxy.planet.resources_overflow_divider');

            //склады освобождены за этот промежуток
            if ($planet->metal + $metalMined <= $storage['metal']) {
                $metalMined = $planet->metal - $storage['metal'];
                $metalPH = 0;
            }
        }

        //кристалл
        //хватает складов
        if ($planet->crystal < $storage['crystal']) {
            $crystalPH = $resourcesPH['crystal'];
            //склады заполнены за этот промежуток
            if ($planet->crystal + $crystalMined > $storage['crystal']) {
                $crystalMined = $storage['crystal'] - $planet->crystal;
                $crystalPH = 0;
            }
        } else {
            //не хватает складов, добыча в минус
            $crystalMined = ($storage['crystal'] - $planet->crystal) * ($time / 60) / Config::get('constants.galaxy.planet.resources_overflow_divider');
            $crystalPH = ($storage['crystal'] - $planet->crystal) / Config::get('constants.galaxy.planet.resources_overflow_divider');

            //склады освобождены за этот промежуток
            if ($planet->crystal + $crystalMined <= $storage['crystal']) {
                $crystalMined = $planet->crystal - $storage['crystal'];
                $crystalPH = 0;
            }
        }

        //газ
        //хватает складов
        if ($planet->gas < $storage['gas']) {
            $gasPH = $resourcesPH['gas'];
            //склады заполнены за этот промежуток
            if ($planet->gas + $gasMined > $storage['gas']) {
                $gasMined = $storage['gas'] - $planet->gas;
                $gasPH = 0;
            }
        } else {
            //не хватает складов, добыча в минус
            $gasMined = ($storage['gas'] - $planet->gas) * ($time / 60) / Config::get('constants.galaxy.planet.resources_overflow_divider');
            $gasPH = ($storage['gas'] - $planet->gas) / Config::get('constants.galaxy.planet.resources_overflow_divider');

            //склады освобождены за этот промежуток
            if ($planet->gas + $gasMined <= $storage['gas']) {
                $gasMined = $planet->gas - $storage['gas'];
                $gasPH = 0;
            }
        }

        return [
            'metal' => $planet->metal + $metalMined,
            'crystal' => $planet->crystal + $crystalMined,
            'gas' => $planet->gas + $gasMined,
            'metalPH' => $metalPH,
            'crystalPH' => $crystalPH,
            'gasPH' => $gasPH,
        ];
    }

    /**
     * Show buildings list by planetId
     *
     * @uses i18n
     * @param Request $request
     * @param int $planetId Planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllBuildings(Request $request, int $planetId)
    {
        $language = $request->auth->language;
        $planet = Planet::find($planetId);

        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        if (empty($planet->owner_id))
            return response()->json(['status' => 'success',
                'message' => MessagesController::i18n('planet_uninhabited', $language)], 200);

        $user = User::find($planet->owner_id);

        $bap = DB::table('buildings-with-lang as buildings')
            ->select('planets.id',
                'planet_building.planet_id as pivot_planet_id',
                'planet_building.building_id as pivot_building_id',
                'planet_building.level as pivot_level',
                'planet_building.startTime as pivot_startTime',
                'planet_building.timeToBuild as pivot_timeToBuild',
                'planet_building.updated_at as pivot_updated_at',
                'planet_building.destroying as pivot_destroying',
                'buildings.*')
            ->leftJoin(DB::raw(
                "(select * from `planet_building` where `planet_building`.`planet_id` = $planetId) planet_building"),
                function ($join) {
                    $join->on('planet_building.building_id', '=', 'buildings.id');
                })
            ->leftJoin('planets', 'planets.id', '=', 'planet_building.planet_id')
            ->where('buildings.race', '=', $user->race)
            ->where('buildings.language', '=', $user->language)
            ->get();

        //актуализация данных по планете
        $ref = $this->refreshPlanet($request, $planet);

        $res = [];

        foreach ($bap as $building) {
            //building info
            $res[$building->id] = [
                'id' => $building->id,
                'name' => $building->name,
                'image' => imagePath($building, 'building'),
                'description' => $building->description, //->i18n($user->language)
                'type' => $building->type,
                'race' => $building->race,
            ];

            if (empty($building->pivot_level))
                $building->pivot_level = 0;

            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $building, $building->pivot_level, $planetId);
            $resourcesNextLevel = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $building, $building->pivot_level + 1, $planetId);

            $res[$building->id]['resources'] = [
                'current' => $resources['cost'],
                'storage' => $resources['storage'],
                'storage_next_level' => $resourcesNextLevel['storage'],
                'energy' => $resources['production']['energy'],
                'current_per_hour' => [
                    'metal' => $resources['production']['metal'],
                    'crystal' => $resources['production']['crystal'],
                    'gas' => $resources['production']['gas'],
                    'energy' => $resources['production']['energy'],
                ],
                'next_per_hour' => [
                    'metal' => $resourcesNextLevel['production']['metal'],
                    'crystal' => $resourcesNextLevel['production']['crystal'],
                    'gas' => $resourcesNextLevel['production']['gas'],
                    'energy' => $resourcesNextLevel['production']['energy'],
                ],
            ];

            $upgradesArray = [];
            foreach ($resources['upgrades'] as $categoryName => $category) {
                foreach ($category as $key => $bonus) {
                    $upgradesArray[] = [
                        'name' => $key,
                        'type' => $categoryName,
                        'name_i18n' => MessagesController::skills_i18n($key, $request->auth->language),
                        'current' => $resources['upgrades'][$categoryName][$key],
                        'next' => $resourcesNextLevel['upgrades'][$categoryName][$key],
                    ];
                }
            }

//            $res[$building->id]['upgrades'] = $resources['upgradesCurrent'];
            $res[$building->id]['upgrades'] = $upgradesArray;

            $res[$building->id]['requirements'] = $resources['requirements'];

            //actual building at planet
            if (!is_null($building->pivot_level)) {
                $res[$building->id]['level'] = $building->pivot_level;
                $res[$building->id]['startTime'] = $building->pivot_startTime;
                $res[$building->id]['timeToBuild'] = $building->pivot_timeToBuild;
                $res[$building->id]['destroying'] = $building->pivot_destroying;
//                $res[$building->id]['updated_at'] = $building->pivot_updated_at;
            }
        }

        return response()->json($res);
    }

    /**
     * Add one level to given building
     * Add level 0 if no building exists
     * Resource, slots check
     *
     * @uses i18n
     * @param $request Request
     * @param int $planetId Planet
     * @param int $buildingId Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $language = $request->auth->language;

        //нашли планету
        $planet = Planet::find($planetId);

        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        $user = User::find($planet->owner_id);

        $ref = $this->refreshPlanet($request, $planet);

        //que check
        if ($ref['buildingQued'] && ($ref['buildingTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_que_not_empty', $language), 'time_remain' => $ref['buildingTimeRemain']], 403);

        $slots = $this->slotsAvailable($planet);

        if (!$slots['canBuild'])
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_slots_insufficient', $language), 'time_remain' => $ref['buildingTimeRemain']], 403);

        $building = Building::find($buildingId);

        //если нет на планете - создали с уровнем 0
        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();

        if (is_null($buildingAtPlanet)) {
            $planet->buildings()->attach($buildingId);
            $buildingAtPlanet = $planet->buildings()->find($buildingId);
        }

        $level = !empty($buildingAtPlanet->pivot->level) ? $buildingAtPlanet->pivot->level : 0;

        //resources check
        $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($user, $building, $level, $planetId);

        if (!$this->checkResourcesAvailable($planet, $resourcesAtLevel['cost']))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('no_resources', $language)], 403);

        $this->buy($planet, $resourcesAtLevel['cost']);

        $timeToBuild = $resourcesAtLevel['cost']['time'];

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
        return ['available' => $planet->slots, 'occupied' => $occupied, 'canBuild' => ($planet->slots > $occupied)];
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
     * @param bool $refund
     * @return void
     */
    public function buy(Planet $planet, array $resources, $refund = false)
    {
        if (empty($resources['metal']))
            $resources['metal'] = 0;
        if (empty($resources['crystal']))
            $resources['crystal'] = 0;
        if (empty($resources['gas']))
            $resources['gas'] = 0;

        if ($refund) {
            $resources['metal'] = -$resources['metal'];
            $resources['crystal'] = -$resources['crystal'];
            $resources['gas'] = -$resources['gas'];
        }

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

    /**
     * Cancel building request
     *
     * @uses i18n
     * @param Request $request
     * @param int $bid
     * @param int $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelBuilding(Request $request, int $bid, int $planetId)
    {
        $language = $request->auth->language;

        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);

        $user = User::find($request->auth->id);
        $owner = User::find($planet->owner_id);

        if ($user != $owner)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_yours', $language)], 403);

        $ref = $this->refreshPlanet($request, $planet);

        //slots check
        if (!$ref['buildingQued'] || ($ref['buildingTimeRemain'] == 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_que_empty', $language)], 403);

        $building = Building::find($bid);
        $buildingAtPlanet = $planet->buildings()->find($bid);

        //resources refund
        $resources = app('App\Http\Controllers\ResourceController')
            ->parseAll($owner, $buildingAtPlanet, $buildingAtPlanet->pivot->level, $planetId);

        $fullCost = [
            'metal' => $resources['cost']['metal'],
            'crystal' => $resources['cost']['crystal'],
            'gas' => $resources['cost']['gas'],
        ];

        $refund = app('App\Http\Controllers\ResourceController')
            ->refund($planet, $fullCost, false);

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'startTime' => null,
            'timeToBuild' => null,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        $planet = Planet::find($planetId);

        $ref = $this->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => MessagesController::i18n('building_removed_from_que', $language),
            'building' => [
                'id' => $building->id,
                'name' => $building->i18n($user->language)->name,
                'current_level' => $buildingAtPlanet->pivot->level,
            ],
            'buildingQued' => $ref['buildingQued'],
            'queTimeRemain' => $ref['buildingTimeRemain'],

            'refunded' => $refund,
        ], 200);

    }

    /**
     * Remove one level from given building
     * Message if building current level is 0
     * Slots check, resources refund
     *
     * @uses i18n
     * @param $request Request
     * @param int $planetId
     * @param int $buildingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $language = $request->auth->language;

        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);
        $user = User::find($planet->owner_id);

        $ref = $this->refreshPlanet($request, $planet);
        //slots check
        if ($ref['buildingQued'] && (!empty($ref['queTimeRemain'])))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_que_not_empty', $language), 'time_remain' => $ref['queTimeRemain']], 403);

        $building = Building::find($buildingId);

        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();
        if (!$buildingAtPlanet || ($buildingAtPlanet->pivot->level <= 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_level_0', $language)], 403);

        //resources refund
        $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($user, $building, $buildingAtPlanet->pivot->level - 1, $planetId);

        $this->buy($planet, $resourcesAtLevel, true);

        $timeToBuild = $resourcesAtLevel['cost']['time'];

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $buildingAtPlanet->pivot->level - 1,
            'destroying' => 1,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $buildingAtPlanet->pivot->level - 1,
            'timeToBuild' => $timeToBuild
        ], 200);
    }
}
