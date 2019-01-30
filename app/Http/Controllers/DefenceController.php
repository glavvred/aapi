<?php

namespace App\Http\Controllers;

use App\Defence;
use App\Fleet;
use App\Planet;
use App\PlanetDefence;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class DefenceController
 * @package App\Http\Controllers
 */
class DefenceController extends Controller
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
     * Add defence (cannon) to building que
     * @param Request $request
     * @param int $quantity
     * @param int $planetId
     * @param int $defenceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function buildDefence(Request $request, int $quantity, int $planetId, int $defenceId)
    {
        //нашли планету
        $planet = Planet::find($planetId);
        if (!$planet) {
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        }

        //нашли оборону
        $defence = Defence::find($defenceId);
        if (!$defence) {
            return response()->json(['status' => 'error', 'message' => 'no ship found'], 403);
        }

        //check que
        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        if (!empty($ref['defences'])) {
            $ref = $ref['defences'];
        }

        $defenceDetails = app('App\Http\Controllers\ResourceController')->parseAll(User::find($planet->owner_id), $defence, 1, $planetId);

        //que check
        if (!empty($ref['defenceStartTime']) && !empty(($ref['defenceQuantityQued'] > 0))) {
            return response()->json(['status' => 'error',
                'message' => 'que is not empty',
                'defenceQuantityQued' => $ref['defenceQuantityQued'],
                'defenceQuantityRemain' => $ref['defenceQuantityRemain'],
                'oneDefenceBuildTime' => $ref['defenceOneTimeToBuild'],
                'defenceTimePassedFromLast' => $ref['defenceTimePassedFromLast'],
                'fullQueTimeRemain' => $ref['defenceOneTimeToBuild'] * $ref['defenceQuantityRemain'] - $ref['defenceTimePassedFromLast'],
            ], 403);
        }

        $planetDefence = PlanetDefence::where('planet_id', $planetId)
            ->where('defence_id', $defenceId)
            ->first();

        //no ship of given type at planetId
        if (empty($planetDefence->id)) {
            $newCannon = new PlanetDefence();
            $newCannon->defence_id = $defenceId;
            $newCannon->planet_id = $planetId;
            $newCannon->save();

            $planetDefence = PlanetDefence::where('planet_id', $planetId)
                ->where('defence_id', $defenceId)
                ->first();
        }

        $resources = [
            'metal' => $defenceDetails['cost']['metal'] * $quantity,
            'crystal' => $defenceDetails['cost']['crystal'] * $quantity,
            'gas' => $defenceDetails['cost']['gas'] * $quantity,
        ];

        if (!app('App\Http\Controllers\BuildingController')->checkResourcesAvailable($planet, $resources)) {
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);
        }

        app('App\Http\Controllers\BuildingController')->buy($planet, $resources);

        $timeToBuild = $defenceDetails['cost']['time'] * $quantity;

        $planetDefence->quantityQued = $quantity;
        $planetDefence->quantity = $quantity;
        $planetDefence->startTime = Carbon::now()->format('Y-m-d H:i:s');
        $planetDefence->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $planetDefence->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $planetDefence->timeToBuildOne = $defenceDetails['cost']['time'];
        $planetDefence->save();

        return response()->json(['status' => 'success',
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'quantity' => $quantity,
            'timeToBuild' => $timeToBuild,
        ], 200);
    }

    /**
     * Cancel defence building request
     *
     * @uses i18n
     * @param Request $request
     * @param int $sid
     * @param int $planetId
     * @param bool $isPaid
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelBuilding(Request $request, int $sid, int $planetId, bool $isPaid = false)
    {
        $language = $request->auth->language;

        $planet = Planet::find($planetId);
        if (empty($planet)) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);
        }

        $user = User::find($request->auth->id);
        $owner = User::find($planet->owner_id);

        if ($user != $owner) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_yours', $language)], 403);
        }

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);
        $ref = $ref['defences'];

        $planetDefences = $planet->defencesBuildingNow();
        foreach ($planetDefences as $check) {
            if ($check->defence_id == $sid) {
                $planetDefence = $check;
            }
        }
        //que check
        if (empty($ref['defenceStartTime']) || empty($planetDefence->quantityQued)) {
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('defence_que_empty', $language)], 403);
        }

        //resources refund
        $resources = app('App\Http\Controllers\ResourceController')
            ->parseAll($owner, $planetDefence, 1, $planetId);

        $fullCost = [
            'metal' => $resources['cost']['metal'] * $planetDefence->quantityQued,
            'crystal' => $resources['cost']['crystal'] * $planetDefence->quantityQued,
            'gas' => $resources['cost']['gas'] * $planetDefence->quantityQued,
        ];

        $refund = app('App\Http\Controllers\ResourceController')
            ->refund($planet, $fullCost, $isPaid);


        DB::table('planet_defence')
            ->where([
                'planet_id' => $planet->id,
                'defence_id' => $planetDefence->defence_id,
            ])
            ->delete();

        app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => MessagesController::i18n('defence_removed_from_que', $language),
            'refunded' => [
                'metal' => $refund['metal'],
                'crystal' => $refund['crystal'],
                'gas' => $refund['gas'],
            ],
        ], 200);
    }

    /**
     * Show defences by planet with respective quantity
     * @param Request $request
     * @param $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDefenceListByPlanet(Request $request, $planetId)
    {
        $res = [];

        $authId = $request->auth->id;
        $user = User::find($authId);

        //only my fleet counts
        $fleetList = Fleet::where('coordinate_id', $planetId)
            ->where('owner_id', $authId)
            ->get();

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, Planet::find($planetId));

        $defencesAtPlanet = [];

        foreach ($fleetList as $fleet) {
            foreach ($fleet->defences as $defence) {
                if (!empty($defencesAtPlanet[$defence->defence_id])) {
                    $defencesAtPlanet[$defence->defence_id] += $defence->quantity;
                } else {
                    $defencesAtPlanet[$defence->defence_id] = $defence->quantity;
                }
            }
        }

        $defencesAvailable = Defence::where('race', $request->auth->race)->get();

        foreach ($defencesAvailable as $defenceAvailable) {
            $defenceProperties = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $defenceAvailable, 1, $planetId);

            $upgradesArray = [];
            foreach ($defenceProperties['upgrades'] as $categoryName => $category) {
                foreach ($category as $key => $bonus) {
                    $upgradesArray[] = [
                        'name' => $key,
                        'type' => $categoryName,
                        'name_i18n' => MessagesController::skills_i18n($key, $request->auth->language),
                        'current' => $defenceProperties['upgrades'][$categoryName][$key]
                    ];
                }
            }

            $propertiesArray = [];
            foreach ($defenceProperties['properties'] as $categoryName => $category) {
                foreach ($category as $key => $bonus) {
                    $propertiesArray[] = [
                        'name' => $key,
                        'type' => $categoryName,
                        'name_i18n' => MessagesController::skills_i18n($key, $request->auth->language),
                        'current' => $defenceProperties['properties'][$categoryName][$key]
                    ];
                }
            }

            $re = [
                'defenceId' => $defenceAvailable->id,
                'name' => $defenceAvailable->i18n($user->language)->name,
                'image' => imagePath($defenceAvailable),
                'description' => $defenceAvailable->i18n($user->language)->description,
                'type' => $defenceAvailable->type,
                'race' => $defenceAvailable->race,
                'cost' => $defenceProperties['cost'],
                'requirements' => $defenceProperties['requirements'],
                'upgrades' => $upgradesArray,
                'properties' => $propertiesArray,
            ];

            if (!empty($ref['defences'])) {
                if ($ref['defences']['defenceId'] == $defenceAvailable->id) {
                    $re['startTime'] = $ref['defences']['defenceStartTime'];
                    $re['timeToBuild'] = $ref['defences']['defenceOneTimeToBuild'] * $ref['defences']['defenceQuantityQued'];
                    $re['shipQuantityQued'] = $ref['defences']['defenceQuantityQued'];
                    $re['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
                }
            }
            if (!empty($defencesAtPlanet[$defenceAvailable->id])) {
                $re['quantity'] = $defencesAtPlanet[$defenceAvailable->id];
            } else {
                $re['quantity'] = 0;
            }

            $res[] = $re;
        }

        return response()->json($res, 200);
    }
}
