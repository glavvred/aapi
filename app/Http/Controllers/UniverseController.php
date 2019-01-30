<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\User;
use App\Planet;
use Illuminate\Http\Request;

class UniverseController extends BaseController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMyGalaxy(Request $request)
    {
        $myGalaxy = Planet::select('galaxy')->where('owner_id', $request->auth->id)->first();
        return response()->json(Planet::select('coordinateX as x', 'coordinateY as y', 'type as image')
                                ->where('galaxy', $myGalaxy)
                                ->groupBy('coordinateX', 'coordinateY')
                                ->orderBy('coordinateX', 'ASC')
                                ->orderBy('coordinateY', 'ASC')
                                ->orderBy('orbit', 'ASC')
                                ->get());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneGalaxy(Request $request, $galaxyId)
    {
        return response()->json(Planet::select('coordinateX as x', 'coordinateY as y', 'type as image')
                                ->groupBy('coordinateX', 'coordinateY')
                                ->orderBy('coordinateX', 'ASC')
                                ->orderBy('coordinateY', 'ASC')
                                ->orderBy('orbit', 'ASC')
                                ->get());
    }
}
