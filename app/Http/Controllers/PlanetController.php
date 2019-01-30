<?php

namespace App\Http\Controllers;

use App\Comments;
use App\Planet;
use App\User;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

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

    public function chooseUnoccupied($xc, $yc)
    {
        $planet = Planet::where('coordinateX', $xc)
            ->where('coordinateY', $yc)
            ->whereNull('owner_id')
            ->where('diameter', '!=', 0)
            ->inRandomOrder()
            ->limit(1)
            ->first();

        return $planet;
    }

    /**
     * Seed empty solar system
     * @param $xc
     * @param $yc
     */
    public function seedSolarSystem($xc, $yc)
    {
        //seedOrbits first
        for ($orbit = Config::get('constants.galaxy.dimensions.orbit.min');
             $orbit < Config::get('constants.galaxy.dimensions.orbit.min_inhabited');
             $orbit++) {
            $this->newOrbit($xc, $yc, $orbit);
        }

        //seedPlanets
        for ($orbit = Config::get('constants.galaxy.dimensions.orbit.min_inhabited');
             $orbit <= Config::get('constants.galaxy.dimensions.orbit.max_inhabited');
             $orbit++) {
            $faker = Factory::create();
            if ($faker->boolean(75)) {
                $this->newPlanet($xc, $yc, $orbit);
            } else {
                $this->newOrbit($xc, $yc, $orbit);
            }
        }

        //seedOrbits last
        for ($orbit > Config::get('constants.galaxy.dimensions.orbit.max_inhabited');
             $orbit <= Config::get('constants.galaxy.dimensions.orbit.max');
             $orbit++) {
            $this->newOrbit($xc, $yc, $orbit);
        }
    }

    /**
     * New orbit coordinate create
     * @param $xc
     * @param $yc
     * @param $orbit
     * @return mixed
     */
    public function newOrbit($xc, $yc, $orbit)
    {
        $planet = Planet::where('coordinateX', $xc)
            ->where('coordinateY', $yc)
            ->where('orbit', $orbit)
            ->firstOrNew([
                'coordinateX' => $xc,
                'coordinateY' => $yc,
                'orbit' => $orbit,
            ]);

        if (empty($planet->name)) {
            $planet->name = $xc . ':' . $yc . ':' . $orbit;
            $planet->slots = 0;
            $planet->temperature = 0;
            $planet->diameter = 0;
            $planet->density = 0;
            $planet->galaxy = 1;
            $planet->type = 0;
            $planet->metal = 0;
            $planet->crystal = 0;
            $planet->gas = 0;
            $planet->created_at = Carbon::now();
            $planet->save();
            $planet->refresh();
        }

        return $planet;
    }

    /**
     * New planet coordinate create
     * @param $xc
     * @param $yc
     * @param $orbit
     * @return mixed
     */
    public function newPlanet($xc, $yc, $orbit)
    {
        $planet = Planet::where('coordinateX', $xc)
            ->where('coordinateY', $yc)
            ->where('orbit', $orbit)
            ->firstOrNew([
                'coordinateX' => $xc,
                'coordinateY' => $yc,
                'orbit' => $orbit,
            ]);

        if (empty($planet->name)) {
            $faker = Factory::create();

            $planet->name = $faker->colorName . ' ' . $faker->randomNumber(3);
            $planet->slots = rand(
                Config::get('constants.galaxy.planet.slots.min'),
                Config::get('constants.galaxy.planet.slots.max')
            );
            $planet->temperature = rand(
                Config::get('constants.galaxy.planet.temperature.min'),
                Config::get('constants.galaxy.planet.temperature.max')
            );
            $planet->diameter = rand(
                Config::get('constants.galaxy.planet.diameter.min'),
                Config::get('constants.galaxy.planet.diameter.max')
            );
            $planet->density = rand(
                Config::get('constants.galaxy.planet.density.min'),
                Config::get('constants.galaxy.planet.density.max')
            );
            $planet->galaxy = 1;
            $planet->type = 1;
            $planet->metal = 0;
            $planet->crystal = 0;
            $planet->gas = 0;
            $planet->created_at = Carbon::now();
            $planet->save();
            $planet->refresh();
        }

        return $planet;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSolarSystem(Request $request, $gid, $xc, $yc)
    {
        $planetList = Planet::where('coordinateX', $xc)->where('coordinateY', $yc)->get();
        foreach ($planetList as $planet) {
            if ($request->auth->id != $planet->owner_id) {
                $planet->makeHidden(['metal', 'crystal', 'gas', 'created_at', 'updated_at']);
            }
        }
        return response()->json($planetList);
    }

    /**
     * Show my planets
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMyPlanets(Request $request)
    {
        $planets = Planet::with('buildings')
            ->where('owner_id', $request->auth->id)
            ->orderBy('id', 'ASC')
            ->get();
        $res = [];

        foreach ($planets as $planet) {
            $refreshed = app(BuildingController::class)->refreshPlanet($request, $planet);
            $ships = (!empty($refreshed['ships'])) ? $refreshed['ships'] : ['shipStartTime' => 0, 'shipQuantityQued' => 0, 'shipOneTimeToBuild' => 0];
            $defence = (!empty($refreshed['defences'])) ? $refreshed['defences'] : ['defenceStartTime' => 0, 'defenceQuantityQued' => 0, 'defenceOneTimeToBuild' => 0];

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
                    "defences" => [
                        "startTime" => $defence['defenceStartTime'],
                        "defenceQued" => $defence['defenceQuantityQued'],
                        "timeToBuild" => $defence['defenceQuantityQued'] * $defence['defenceOneTimeToBuild'],
                    ],
                ],
                "profiler" => $refreshed['profile'],
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
            'coordinateX',
            $planet->coordinateX
        )
            ->where('coordinateY', $planet->coordinateY)
            ->where('orbit', $planet->orbit)
            ->where('share_with_alliance', 0)
            ->where('owner_id', $request->auth->id)
            ->get(['id', 'owner_id', 'comment', 'description', 'share_with_alliance', 'created_at', 'updated_at']);

        //my guild comments - shared
        $comments['alliance'] = Comments::where('coordinateX', $planet->coordinateX)
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
     * Rename planet
     *
     * @param Request $request
     * @param $pid
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function rename(Request $request, $pid)
    {
        $this->validate($request, [
            'name' => 'filled'
        ]);

        $planet = Planet::find($pid);

        if (empty($planet)) {
            return response()->json(['status' => 'error',
                'message' => MessagesController::i18n('planet_not_found', $request->auth->language),
            ], 403);
        }


        if ($planet->owner()->id != $request->auth->id) {
            return response()->json(['status' => 'error',
                'message' => MessagesController::i18n('planet_not_yours', $request->auth->language),
            ], 403);
        }


        if ($planet->fill($request->all())->save()) {
            return response()->json(['status' => 'success',
                'message' => MessagesController::i18n('planet_renamed', $request->auth->language),
            ], 200);
        }

        return response()->json(['status' => 'error',
            'message' => 'something is not right, planet controller 218',
        ], 200);
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
