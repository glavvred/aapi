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

    //test
    $router->get('test/resources/', 'ResourceController@defaultJsonResources');
    $router->get('test/requirements', 'ResourceController@defaultJsonRequirements');
    $router->get('test/upgrades', 'ResourceController@defaultJsonUpgrades');
    $router->get('test/properties', 'ResourceController@defaultJsonProperties');



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
    $router->get('users/lazy_register', 'UserController@lazyRegister');

    //auth + scope
    $router->group(['middleware' => ['jwt.auth', 'astrality.scope']], function () use ($router) {

        //test
        $router->get('test/{pid}/building/{bid}/level/{level}', 'ResourceController@test');
        $router->get('test/{pid}/building/{bid}', 'ResourceController@testMany');
        $router->get('test/{pid}/technology/{bid}', 'ResourceController@testManyTech');
        $router->get('test/{pid}/ship/{bid}', 'ResourceController@testShip');

        $router->get('alliance/test/{aid}', ['uses' => 'UserController@showAllAlliances']);

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
        $router->post('planets/{id}/rename', ['uses' => 'PlanetController@rename']);

        $router->get('planets/{id}', ['uses' => 'PlanetController@showOnePlanet']);
        $router->get('planets/{id}/buildings', ['uses' => 'BuildingController@showAllBuildings']);
        $router->get('planets/{id}/buildings/{bid}', ['uses' => 'BuildingController@showOneBuilding']);
        $router->put('planets/{id}/buildings/{bid}/upgrade', ['uses' => 'BuildingController@upgradeBuilding']);
        $router->put('planets/{id}/buildings/{bid}/downgrade', ['uses' => 'BuildingController@downgradeBuilding']);
        $router->put('planets/{id}/buildings/{bid}/cancel', ['uses' => 'BuildingController@cancelBuilding']);

        //tech
        $router->get('technologies', ['uses' => 'TechController@showMyTech']); //shortcut
        $router->get('planets/{id}/technologies', ['uses' => 'TechController@showTechByPlanet']);
        $router->get('planets/{id}/technologies/{tid}', ['uses' => 'TechController@showOneTech']);
        $router->put('planets/{id}/technologies/{tid}/upgrade', ['uses' => 'TechController@upgradeTech']);
        $router->put('planets/{id}/technologies/{tid}/cancel', ['uses' => 'TechController@cancelBuilding']);

        //ships
        $router->get('planets/{id}/ships', ['uses' => 'ShipController@showShipListByPlanet']);
        $router->put('planets/{id}/ships/{sid}/build/{quantity}', ['uses' => 'ShipController@buildShip']);
        $router->put('planets/{planetId}/ships/{sid}/cancel', ['uses' => 'ShipController@cancelBuilding']);

        //defence
        $router->get('planets/{id}/defences', ['uses' => 'DefenceController@showDefenceListByPlanet']);
        $router->put('planets/{id}/defences/{sid}/build/{quantity}', ['uses' => 'DefenceController@buildDefence']);
        $router->put('planets/{planetId}/defences/{sid}/cancel', ['uses' => 'DefenceController@cancelBuilding']);

        //fleet
        $router->get('fleet', ['uses' => 'ShipController@showMyFleet']);
        $router->get('fleet/update', ['uses' => 'RouteController@update']);
        $router->get('planets/{id}/fleet/{fid}/test', ['uses' => 'ShipController@loadFleet']);
        $router->get('planets/{id}/fleet', ['uses' => 'ShipController@showFleetAtPlanet']);
        $router->get('planets/{id}/fleet/{fid}', ['uses' => 'ShipController@showOneFleet']);

        //fleet actions
        $router->put('planets/{id}/fleet/{fleetId}/cargo', ['uses' => 'ShipController@transferResourcesToFleet']);
        $router->put('planets/{id}/fleet/{fleetId}/transfer', ['uses' => 'ShipController@transferShipsToFleet']);

        $router->get('planets/{id}/fleet/{fleetId}/move/{destination}/order/{order}', ['uses' => 'ShipController@moveToOrbit']);
        $router->put('planets/{id}/fleet/{fid}/build', ['uses' => 'ShipController@build']);


        //comments
        $router->get('comments', ['uses' => 'CommentsController@showAllMyComments']);

        //governors
        $router->get('planets/{id}/governors', ['uses' => 'GovernorController@showGovernorAtPlanet']);

    });


});