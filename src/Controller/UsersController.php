<?php
namespace App\Controller;

use App\DataModel\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UsersController
{
    /** @var User $userStore */
    private $repo;

    function __construct(User $userStore)
    {
        $this->repo = $userStore;
    }

    public function registerAction(Request $request)
    {
        $deviceToken = $request->get('deviceToken');
        if (!$deviceToken) {
            return new JsonResponse([
                'error' => 'Parameter `deviceToken` is required.',
                'code' => 400
            ], 400);
        }
        $user = $this->repo->findByDeviceToken($deviceToken);
        if ($user) {
            return new JsonResponse([
                'error' => 'Provided `deviceToken` is already registered.',
                'code' => 400
            ], 400);
        }
        return new JsonResponse($this->repo->create($deviceToken));
    }

    public function unregisterAction(Request $request)
    {
        $deviceToken = $request->get('deviceToken');
        $secretToken = $request->get('secretToken');
        if (!$deviceToken || !$secretToken) {
            return new JsonResponse([
                'error' => 'Parameters `deviceToken` and `secretToken` are required.',
                'code' => 400
            ], 400);
        }
        $user = $this->repo->findByDeviceToken($deviceToken);
        if (!$user || $user->secretToken !== $secretToken) {
            return new JsonResponse([
                'error' => 'Parameter `deviceToken` or `secretToken` is invalid.',
                'code' => 400
            ], 400);
        }
        $this->repo->remove($user);
        return new JsonResponse([], 200);
    }
}
