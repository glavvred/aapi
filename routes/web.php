<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/db-test', function () {
    try {
        DB::connection()->getPdo();
    } catch (\Exception $e) {
        die("Could not connect to the database.  Please check your configuration. error:" . $e);
    }
});

$router->group(['prefix' => 'api'], function () use ($router) {

    //auth
    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->get('login', 'AuthController@authenticate');
        $router->get('refresh', 'AuthController@refresh');
        $router->group(['middleware' => 'jwt.auth'], function () use ($router) {
            $router->get('logout', 'AuthController@logout');
        });
    });

    //no auth
    $router->post('users', ['uses' => 'UserController@create']);

    //auth + scope
    $router->group(['middleware' => ['jwt.auth', 'astrality.scope']], function () use ($router) {
        //users
        $router->get('users', ['uses' => 'UserController@showAllUsers']);
        $router->get('users/{id}', ['uses' => 'UserController@showOneUser']);
        $router->put('users/{id}', ['uses' => 'UserController@update']);
        $router->delete('users/{id}', ['uses' => 'UserController@delete']);

        //galaxy
        $router->group(['prefix' => 'galaxy'], function () use ($router) {
            $router->get('', ['uses' => 'UniverseController@showMyGalaxy']);
            $router->get('{gid}', ['uses' => 'UniverseController@showOneGalaxy']);

            $router->group(['prefix' => '{gid}'], function () use ($router) {

                //solar systems
                $router->get('{xc}/{yc}', ['uses' => 'PlanetController@showSolarSystem']);

                //planets
                $router->group(['prefix' => '{xc}/{yc}'], function () use ($router) {
                    $router->get('{oid}', ['uses' => 'UniverseController@showPlanetByRoute']);
                });
            });
        });

        //shortcuts
        $router->get('planets', ['uses' => 'PlanetController@showMyPlanets']);

        $router->get('planets/{id}', ['uses' => 'PlanetController@showOnePlanet']);
        $router->get('planets/{id}/buildings', ['uses' => 'BuildingController@showAllBuildings']);
        $router->get('planets/{id}/buildings/{bid}', ['uses' => 'BuildingController@showOneBuilding']);
        $router->put('planets/{id}/buildings/{bid}/upgrade', ['uses' => 'BuildingController@upgradeBuilding']);
        $router->put('planets/{id}/buildings/{bid}/downgrade', ['uses' => 'BuildingController@downgradeBuilding']);

        //tech
        $router->get('technologies', ['uses' => 'TechController@showMyTech']); //shortcut
        $router->get('planets/{id}/technologies', ['uses' => 'TechController@showTechByPlanet']);
        $router->get('planets/{id}/technologies/{tid}', ['uses' => 'TechController@showOneTech']);
        $router->put('planets/{id}/technologies/{tid}/upgrade', ['uses' => 'TechController@upgradeTech']);

        $router->get('fleet', ['uses' => 'ShipController@showMyFleet']);

        $router->get('planets/{id}/fleet', ['uses' => 'ShipController@showFleetAtPlanet']);
        $router->get('planets/{id}/fleet/{fid}', ['uses' => 'ShipController@showShipProperties']);
        $router->put('planets/{id}/fleet/{fid}/build', ['uses' => 'ShipController@build']);


        //comments
        $router->get('comments', ['uses' => 'CommentsController@showAllMyComments']);

        //governors
        $router->get('planets/{id}/governors', ['uses' => 'GovernorController@showGovernorAtPlanet']);

    });


});