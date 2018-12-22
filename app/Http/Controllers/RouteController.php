<?php

namespace App\Http\Controllers;

use App\Fleet;
use App\Planet;
use App\Route;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Lumen\Routing\Controller as BaseController;

class RouteController extends BaseController
{
    public static function ladder(Request $request, Fleet $fleet, Planet $destination, int $order, $param = '')
    {
        $origin = $fleet->coordinate()->first();

        if (($origin->coordinateX != $destination->coordinateX) || ($origin->coordinateX != $destination->coordinateX))
            MessagesController::i18n('planets_system_differ', $request->auth->language);

        // todo                   ==
        $reverse = $origin->orbit > $destination->orbit;

        $parent = null;
        $previous = $origin;
        $startTime = Carbon::now()->format('Y-m-d H:i:s');

        for ($i = $origin->orbit; $reverse ? ($i > $destination->orbit - 1) : ($i < $destination->orbit + 1); $reverse ? $i-- : $i++) {
            $currentStep = Planet::where('coordinateX', $origin->coordinateX)
                ->where('coordinateY', $origin->coordinateY)
                ->where('orbit', $i)
                ->firstOrNew([
                    'coordinateX' => $origin->coordinateX,
                    'coordinateY' => $origin->coordinateY,
                    'orbit' => $i,
                ]);

            if (empty($currentStep->name)) {
                $currentStep->name = $origin->coordinateX . ':' . $origin->coordinateY . ':' . $i;
                $currentStep->slots = 0;
                $currentStep->temperature = 0;
                $currentStep->diameter = 0;
                $currentStep->density = 0;
                $currentStep->galaxy = 1;
                $currentStep->type = 1;
                $currentStep->metal = 0;
                $currentStep->crystal = 0;
                $currentStep->gas = 0;
                $currentStep->created_at = Carbon::now();
                $currentStep->save();
                $currentStep->refresh();
            }

            if ($previous->id == $currentStep->id) {
                $previous = $currentStep;
                continue;
            }
            $startTime = Carbon::now()->addSeconds(Config::get('constants.time.interplanetary') * $i)->format('Y-m-d H:i:s');
            $parent = RouteController::add($fleet, $startTime, $previous, $currentStep, $order, $parent, $param);
            $previous = $currentStep;

        }

        die;

        $originSolarSystem = Planet::where('coordinateX', $origin->coordinateX)
            ->where('coordinateY', $origin->coordinateY)
            ->pluck('id');
    }

    //ladder inside one system

    public static function add(Fleet $fleet, $startTime = null, Planet $origin, Planet $destination, int $order, Route $parent = null, $param = '')
    {
        if (empty($startTime))
            $startTime = Carbon::now()->format('Y-m-d H:i:s');

        $route = new Route();
        $route->fleet_id = $fleet->id;
        $route->coordinate_id = $origin->id;
        $route->destination_id = $destination->id;
        if (!empty($parent))
            $route->parent_id = $parent->id;
        if (!empty($param))
            $route->order_param = $param;
        $route->order_id = $order;
        $route->start_time = $startTime;

        $route->save();
        $route->refresh();

        return $route;
    }

    public function getByFleet(Fleet $fleet)
    {

    }

    public function getByCoordinate(Planet $destination)
    {

    }

    public function update(Request $request)
    {
        $routesToUpdate = Route::where('start_time', '<',
            Carbon::now()
                ->addSeconds(Config::get('constants.time.interplanetary'))
                ->format('Y-m-d H:i:s'))
            ->orderBy('start_time', 'DESC')
            ->get();

        foreach ($routesToUpdate as $route) {
            var_dump($route->destination_id);
            $collision = $this->getCollisions($route, $route->destination());
            if ($collision)
                var_dump($collision);
            else
                echo " no collision" . "\r\n";
        }

        die;
    }

    /**
     * Get fleet of foreign players over given coordinate
     * @param Route $route
     * @param Planet $planet
     * @return array|null
     */
    public function getCollisions(Route $route, Planet $planet)
    {
        $myAlliance = $route->fleet()->owner()->alliance()->first();
        $allianceGroup = app('App\Http\Controllers\UserController')
            ->metaAlliance($myAlliance);

        $allianceIds = [];

        foreach ($allianceGroup as $alliance) {
            $allianceIds[] = $alliance->id;
        }

        $foreignFleets = Fleet::whereNotIn('owner_id', $allianceIds)
            ->where('coordinate_id', $planet->id)
            ->get();

        $collisions = [];
        foreach ($foreignFleets as $foreignFleet) {
            //foreign fleets with certain battle orders (type == 2)
            var_dump($foreignFleet->order()->name);
//            if (($foreignFleet->order_type == 1) ||
//                ($route->fleet()->first()->order_type == 4)) {
//                $collisions[] = $foreignFleet->id;
//            }
            $collisions[] = $foreignFleet->id;
        }
        return !empty($collisions) ? $collisions : null;
    }


}