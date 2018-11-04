<?php

namespace App\Http\Controllers;

use App\Governor;
use App\Planet;

/**
 * Class CommentsController
 * @package App\Http\Controllers
 */
class GovernorController extends Controller
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
     * Губернатор на планете
     * @param $coordinate
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function showGovernorAtPlanet($coordinate)
    {
        $planet = Planet::find($coordinate);

        $governors = $planet->governors;

        return $governors;
    }


}