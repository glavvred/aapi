<?php

namespace App\Http\Controllers;

use App\Fleet;
use App\Planet;
use App\Route;
use Carbon\Carbon;
use Laravel\Lumen\Routing\Controller as BaseController;

class RouteController extends BaseController
{
    public static function add(Fleet $fleet, Planet $destination, int $order, Route $parent = null, $param = '')
    {
        $route = new Route();
        $route->fleet_id = $fleet->id;
        $route->coordinate_id = $fleet->coordinate_id;
        $route->destination_id = $destination->id;
        if (!empty($parent))
            $route->parent_id = $parent->id;
        if (!empty($param))
            $route->order_param = $param;
        $route->order_id = $order;
        $route->start_time = Carbon::now()->format('Y-m-d H:i:s');

        $route->save();
        $route->refresh();

        return $route->id;
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

    public function test(){
        echo 'go';
        declare(ticks=1) {
            while(1) sleep(1);
        }
        register_tick_function(array(&$this,'tick'));
        $this->tick('--start--');
    }

    public function tick($str = '')
    {
        list($sec, $usec) = explode(' ', microtime());
        printf("[%.4f] Tick.%s\n", $sec + $usec, $str);
    }


}