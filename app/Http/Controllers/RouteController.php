<?php

namespace App\Http\Controllers;

use App\Fleet;
use App\Planet;
use App\Route;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Lumen\Routing\Controller as BaseController;

class RouteController extends BaseController
{
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

    //ladder inside one system
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

    public function getCollisions($route)
    {
//        var_dump($route->id);

        $originSolarSystem = Planet::where('coordinateX', $route->origin()->coordinateX)
            ->where('coordinateY', $route->origin()->coordinateY)
            ->pluck('id');

        $routesForeign = Route::whereIn("coordinate_id", $originSolarSystem)
//            ->where()
            ->get();

        foreach ($routesForeign as $routeForeign) {
            var_dump($routeForeign->id);
        }

        //get foreign routes over xy
        //get least timer of route
        //get other timers, substract least
        //get collisions, (x,t), (x1, t1)
        //collision radius t = 5

    }

    public function getByFleet(Fleet $fleet)
    {

    }

    public function getByCoordinate(Planet $destination)
    {

    }

    public function test()
    {
        echo 'go';
        declare(ticks=1) {
            while (1) sleep(1);
        }
        register_tick_function(array(&$this, 'tick'));
        $this->tick('--start--');
    }

    public function tick($str = '')
    {
        list($sec, $usec) = explode(' ', microtime());
        printf("[%.4f] Tick.%s\n", $sec + $usec, $str);
    }


}