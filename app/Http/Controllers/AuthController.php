<?php

namespace App\Http\Controllers;

use App\Alliance;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Routing\Controller as BaseController;

class AuthController extends BaseController
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Http\Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Authenticate a user and return the token if the provided credentials are correct.
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(User $user)
    {
        $this->validate($this->request, [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        // Find the user by email
        $user = User::where('email', $this->request->input('email'))
            ->first();
        if (!$user) {
            // You wil probably have some sort of helpers or whatever
            // to make sure that you have the same response format for
            // differents kind of responses. But let's return the
            // below respose for now.
            return response()->json(['error' => 'Email does not exist.'], 400);
        }

        $alliance = $user->alliance()->first();

        // Verify the password and generate the token
        if (Hash::check($this->request->input('password'), $user->password)) {
            $user->api_key = $this->jwt($user);
            $user->save();
            return response()->json(['token' => $this->jwt($user),
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'alliance' => $alliance,
                'image' => $user->userimage,
                'race' => $user->race,
            ], 200);
        }
        // Bad Request response
        return response()->json(['error' => 'Email or password is wrong.'], 400);
    }

    /**
     * Create a new token.
     *
     * @param  \App\User $user
     * @return string
     */
    protected function jwt(User $user)
    {
        $payload = [
            'iss' => "lumen-jwt", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => time(), // Time when JWT was issued.
            'exp' => time() + 60 * 60 // Expiration time
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    /**
     * @return string
     */
    public function refresh()
    {
        try {          //пробуем декодить
            $decoded = JWT::decode($this->request->input('token'), env('JWT_SECRET'), ['HS256']);
            //если получилось, обновили ему ключ
            $decoded->iat = time();
            $decoded->exp = time() + 60 * 60;  //поставили еще час и перевыпустили

            //ищем юзера с этим ключом
            $user = User::where('api_key', $this->request->input('token'))->first();
            if (!$user)
                throw new \Exception('no user found');

            $user->api_key = $this->jwt($user);
            //пишем новый ключ в базу
            $user->save();

            return response()->json(['token' => JWT::encode($decoded, env('JWT_SECRET'))], 200);

        } catch (\Firebase\JWT\ExpiredException $e) {
            //если не получается - пробуем декодить с допуском в 2 часа.
            JWT::$leeway = 7200000;
            try {
                $decoded = (array)JWT::decode($this->request->input('token'), env('JWT_SECRET'), ['HS256']);

            } catch (\Firebase\JWT\ExpiredException $e) {
                return response()->json(['error' => 'Expired, relogin', 307]);
            }

            // TODO: test if token is blacklisted
            $decoded['iat'] = time();
            $decoded['exp'] = time() + 60 * 60;  //поставили еще час и перевыпустили
            return response()->json(['token' => JWT::encode($decoded, env('JWT_SECRET'))], 200);
        } catch (\Exception $e) {
            //тут редирект на ре-логин. что то пошло не так
            return response()->json(['error' => $e->getMessage()], 307);
        }

    }

    /**
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(User $user)
    {
        $user = User::where('api_key', $this->request->input('token'))->first();
        if ($user) {
            $user->api_key = null;
            $user->save();
            return response()->json(['success' => 'logged out'], 200);
        } else
            return response()->json(['fail' => 'no user found with that token'], 200);
    }
}