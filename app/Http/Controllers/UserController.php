<?php

namespace App\Http\Controllers;

use App\Alliance;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {

        $validator = $this->validate($request, [
            'name' => 'required|alphadash',
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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name' => 'alphadash|filled',
            'email' => 'email|unique:users'
        ]);

        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json('no user exists with id:' . $id, 404);
        }

        $user->update($request->all());

        return response()->json($user, 200);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function delete($id)
    {
        try {
            User::findOrFail($id)->delete();
        } catch (ModelNotFoundException $e) {
            return response()->json('no user exists with id:' . $id, 404);
        }

        return response()->json('Deleted Successfully', 200);
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

    public function metaAlliance(Alliance $alliance)
    {
        if (empty($alliance->parent_id)) {
            $meta = Alliance::where('parent_id', $alliance->id)->get()
                ->prepend($alliance);  //add top alliance
        } else {
            $top = Alliance::find($alliance->parent_id);
            $meta = Alliance::where('parent_id', $alliance->parent_id)->get()
                ->prepend($top);  //add top alliance
        }

        $meta->each(function($row)
        {
            $row->setHidden(['created_at', 'updated_at']);
        });

        return $meta;
    }

    public function showAllAlliances(){
        $topAlliances = Alliance::where('parent_id', null)->get();

        $res = [];

        foreach ($topAlliances as $topAlliance) {
            $res[] = $this->metaAlliance($topAlliance);
        }

        return $res;
    }
}


