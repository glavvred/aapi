<?php

namespace App\Http\Controllers;

use App\Planet;
use App\Wreckage;

/**
 * Class CommentsController
 * @package App\Http\Controllers
 */
class WreckagesController extends Controller
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
     * Show wreckage over the planet
     * @param Planet $coordinates
     * @return array
     */
    public function showWreckageOverPlanet(Planet $coordinates)
    {

        $res = Wreckage::with('location')
            ->where('coordinate_id', $coordinates->id)
            ->first();

        return ['metal' => $res['metal'],
            'crystal' => $res['crystal']];
    }
}
