<?php

namespace App\Http\Controllers;

use App\Alliance;
use App\Planet;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Faker\Factory;


/**
 * Class UserController
 * @package App\Http\Controllers
 */
class UserController extends Controller
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
    public function showAllUsers(Request $request)
    {
        $users = User::all();
        foreach ($users as $user) {
            if ($user->id != $request->auth->id)
                $user->makeHidden(['created_at', 'updated_at', 'remember_token', 'email']);
        }

        return response()->json($users);
    }


    /**
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneUser(Request $request, $id)
    {
        if ($id == $request->auth->id)
            return response()->json(User::find($id));
        else {
            $user = User::find($id);
            $user->makeHidden(['created_at', 'updated_at', 'remember_token', 'email']);
            return response()->json($user);
        }
    }

    public function lazyRegister(Request $request)
    {
        $newCoordinates = $this->getFirstRandomUnoccupiedSystem();

        if ($newCoordinates['count'] == 0)
            app(PlanetController::class)->seedSolarSystem($newCoordinates['x'], $newCoordinates['y']);

        $planet = app(PlanetController::class)->chooseUnoccupied($newCoordinates['x'], $newCoordinates['y']);

        $titles = [
            'Commander',
            'Admiral',
            'Vice Admiral',
            'Chief commander',
            'Leutenant',
            'Officer',
        ];

        $images = [
            'newUser1',
            'newUser2',
            'newUser3',
            'newUser4',
            'newUser5',
        ];

        $faker = Factory::create();

        $password = $faker->password(20,20);

        $email = $newCoordinates['x'].'.'.
            $newCoordinates['y'].'.'.
            $newCoordinates['o'].
            '@astrality.newuser';

        $owner = new User([
            'alliance_id' => null,
            'race' => 2,
            'language' => 'russian',
            'userimage' => $faker->randomElement($images),
            'name' => $faker->randomElement($titles). ' '.$faker->name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $owner->save();
        $owner->refresh();

        $planet->owner_id = $owner->id;

        $planet->metal = Config::get('constants.registration.resources.metal');
        $planet->crystal = Config::get('constants.registration.resources.crystal');
        $planet->gas = Config::get('constants.registration.resources.gas');

        $planet->save();
        $planet->refresh();

        $request->request->add(['email' => $email, 'password' => $password]);
        return app(AuthController::class)->authenticate($owner);
    }

    public function getFirstRandomUnoccupiedSystem()
    {
        $x = rand(0, Config::get('constants.galaxy.dimensions.x'));
        $y = rand(0, Config::get('constants.galaxy.dimensions.y'));
        $o = rand(Config::get('constants.galaxy.dimensions.orbit.min_inhabited'),
            Config::get('constants.galaxy.dimensions.orbit.max_inhabited')
        );

        $users = 5;

        while ($users >= Config::get('constants.galaxy.dimensions.user_per_solar_system')) {
            $users =  Planet::whereNotNull('owner_id')
                ->where('coordinateX', $x)
                ->where('coordinateY', $y)
                ->where('orbit', $o)
                ->distinct('owner_id')
                ->count();
        }

        return [
            'x' => $x,
            'y' => $y,
            'o' => $o,
            'count' => $users,
        ];

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {

        $validator = $this->validate($request, [
            'name' => 'required|regex:/^[\pL\d\s\-\_\.]+$/u',
            'email' => 'required|email|unique:users',
            'password' => 'required'
        ]);

        $user = User::create($request->all());

        return response()->json($user, 201);
    }

    /**
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $rules = [
            'name' => 'required|regex:/^[\pL\d\s\-]+$/u',
            'email' => 'email|unique:users'
        ];

        $response = array('response' => '', 'success' => false);
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            try {
                $user = User::findOrFail($id);
            } catch (ModelNotFoundException $e) {
                return response()->json('no user exists with id:' . $id, 404);
            }
        }

        $user->update($request->all());

        return response()->json($user, 200);
    }

    /**
     * User delete by id
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, $id)
    {
        //todo: check user roles

        try {
            User::findOrFail($id)->delete();
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error',
                'message' => MessagesController::i18n('no_user_exist', $request->auth->language),
                'id' => $id,
            ], 403);
        }

        return response()->json(['status' => 'success',
            'message' => MessagesController::i18n('user_deleted', $request->auth->language),
            'id' => $id,
        ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(Request $request)
    {
        $this->validate($request, [
            'email' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (Hash::check($request->input('password'), $user->password)) {
            $apikey = base64_encode(str_random(40));
            User::where('email', $request->input('email'))->update(['api_key' => "$apikey"]);
            return response()->json(['status' => 'success', 'api_key' => $apikey]);
        } else {
            return response()->json(['status' => 'fail'], 401);
        }
    }

    public function showAllAlliances()
    {
        $topAlliances = Alliance::where('parent_id', null)->get();

        $res = [];

        foreach ($topAlliances as $topAlliance) {
            $res[] = $this->metaAlliance($topAlliance);
        }

        return $res;
    }

    public function metaAlliance(Alliance $alliance, bool $flatten = false)
    {
        if (empty($alliance->parent_id)) {
            $meta = Alliance::where('parent_id', $alliance->id)->get()
                ->prepend($alliance);  //add top alliance
        } else {
            $top = Alliance::find($alliance->parent_id);
            $meta = Alliance::where('parent_id', $alliance->parent_id)->get()
                ->prepend($top);  //add top alliance
        }

        $meta->each(function ($row) {
            $row->setHidden(['created_at', 'updated_at']);
        });

        $flat = [];
        if ($flatten) {
            foreach ($meta as $alliance) {
                $flat[] = $alliance->id;
            }

            return $flat;
        }

        return $meta;
    }
}


