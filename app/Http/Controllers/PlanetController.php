<?php

namespace App\Http\Controllers;

use App\Comments;
use App\Planet;
use App\User;
use Illuminate\Http\Request;

/**
 * Class PlanetController
 * @package App\Http\Controllers
 */
class PlanetController extends Controller
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSolarSystem(Request $request, $gid, $xc, $yc)
    {
        $planetList = Planet::where('coordinateX', $xc)->where('coordinateY', $yc)->get();
        foreach ($planetList as $planet) {
            if ($request->auth->id != $planet->owner_id)
                $planet->makeHidden(['metal', 'crystal', 'gas', 'created_at', 'updated_at']);
        }
        return response()->json($planetList);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMyPlanets(Request $request)
    {
        $planets = Planet::with('buildings')
            ->where('owner_id', $request->auth->id)
            ->get();
        $res = [];

        foreach ($planets as $planet) {
            $refreshed = app(BuildingController::class)->refreshPlanet($request, $planet);
            $ships = (!empty($refreshed['ships'])) ?  $refreshed['ships'] : ['shipStartTime' => 0, 'shipQuantityQued' => 0, 'shipOneTimeToBuild' => 0];

            $plan = [
                "id" => $planet['id'],
                "owner_id" => $planet['owner_id'],
                "name" => $planet['name'],
                "slots" => $planet['slots'],
                "temperature" => $planet['temperature'],
                "diameter" => $planet['diameter'],
                "density" => $planet['density'],
                "galaxy" => $planet['galaxy'],
                "coordinateX" => $planet['coordinateX'],
                "coordinateY" => $planet['coordinateY'],
                "orbit" => $planet['orbit'],
                "type" => $planet['type'],
                "created_at" => $planet['created_at'],
                "updated_at" => $planet['updated_at'],
                "resources" => $refreshed['resources'],
                "ques" => [
                    "buildings" => [
                        "startTime" => $refreshed['buildingStartTime'],
                        "buildingQued" => $refreshed['buildingQued'],
                        "timeToBuild" => $refreshed['buildingTimeToBuild'],
                    ],
                    "technologies" => [
                        "startTime" => $refreshed['techStartTime'],
                        "techQued" => $refreshed['techQued'],
                        "timeToBuild" => $refreshed['technologyTimeToBuild'],
                    ],
                    "ships" => [
                        "startTime" => $ships['shipStartTime'],
                        "shipQued" => $ships['shipQuantityQued'],
                        "timeToBuild" => $ships['shipQuantityQued'] * $ships['shipOneTimeToBuild'],
                    ],
                ],
            ];

            foreach ($planet['buildings'] as $building) {
                $plan['buildings'][] = [
                    'id' => $building['id'],
                    'name' => $building->i18n($request->auth->language)->name,
                    'description' => $building->i18n($request->auth->language)->description,
                    'type' => $building['type'],
                    'level' => $building['pivot']['level'],
                    'startTime' => $building['pivot']['startTime'],
                    'timeToBuild' => $building['pivot']['timeToBuild'],
                    'destroying' => $building['pivot']['destroying'],
                ];
            }
            $res[] = $plan;

        }
        return response()->json($res);
    }

    /**
     * @param Request $request
     * @param $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOnePlanet(Request $request, $planetId)
    {
        $planet = Planet::where('id', $planetId)->first();
        $planet->makeHidden(['buildings']);


        if (!empty($planet->owner_id)) {
            //моя планета
            if ($planet->owner_id == $request->auth->id) {
                $ref = app(BuildingController::class)->refreshPlanet($request, Planet::where('id', $planetId)->first());
                $userData = User::find($request->auth->id)->first(['id', 'name', 'userimage']);
                $ref['slots'] = app(BuildingController::class)->slotsAvailable($planet);
            } //не моя планета
            else {
                $ref = [];
                $planet->makeHidden(['metal', 'crystal', 'gas', 'created_at', 'updated_at']);
                $userData = User::find($planet->owner_id)->first(['id', 'name', 'userimage']);
            }
        } else {
            $userData = [];
            $ref = [];
        }

        $myAlliance = User::where('alliance_id', $request->auth->alliance_id)->pluck('id')->toArray();

        //my comments not shared
        $comments['own'] = Comments::where(
            'coordinateX', $planet->coordinateX)
            ->where('coordinateY', $planet->coordinateY)
            ->where('orbit', $planet->orbit)
            ->where('share_with_alliance', 0)
            ->where('owner_id', $request->auth->id)
            ->get(['id', 'owner_id', 'comment', 'description', 'share_with_alliance', 'created_at', 'updated_at']);

        //my guild comments - shared
        $comments['alliance'] = Comments::where(
            'coordinateX', $planet->coordinateX)
            ->where('coordinateY', $planet->coordinateY)
            ->where('orbit', $planet->orbit)
            ->where('share_with_alliance', 1)
            ->whereIn('owner_id', $myAlliance)
            ->get(['id', 'owner_id', 'comment', 'description', 'share_with_alliance', 'created_at', 'updated_at']);

        return response()->json([
            'userData' => $userData,
            'planet' => $planet,
            'ownData' => $ref,
            'comments' => $comments,
            'wreckage' => app(WreckagesController::class)->showWreckageOverPlanet($planet),
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'location' => 'required',
            'type' => 'required'
        ]);

        if (Auth::user()->planets()->Create($request->all())) {
            return response()->json(['status' => 'success']);
        } else {
            return response()->json(['status' => 'fail']);
        }

    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'filled',
            'location' => 'filled',
            'type' => 'filled'
        ]);
        $planet = Planet::find($id);
        if ($planet->fill($request->all())->save()) {
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'fail']);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        if (Planet::destroy($id)) {
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'fail']);
    }
}