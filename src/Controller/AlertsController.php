<?php
namespace App\Controller;

use App\DataModel\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AlertsController
{
    /** @var User $userStore */
    private $repo;
    /** @var \GDS\Entity $currentUser */
    private $currentUser;
    private $errors = ['error' => 'Something went wrong', 'code' => 500];

    function __construct(User $userStore)
    {
        $this->repo = $userStore;
    }

    public function indexAction(Request $request)
    {
        if (!$this->validateToken($request->get('secretToken'))) {
            return new JsonResponse($this->errors, $this->errors['code']);
        }
        return new JsonResponse([
            'isEnabled' => $this->currentUser->notificationsEnabled,
            'phrases' => $this->currentUser->alertPhrases ?: []
        ]);
    }

    private function validateToken($secretToken)
    {
        if (!$secretToken) {
            $this->errors['error'] = 'Parameter `secretToken` is required.';
            $this->errors['code'] = 400;
            return false;
        }
        $user = $this->repo->findBySecretToken($secretToken);
        if (!$user) {
            $this->errors['error'] = 'Parameter `secretToken` is invalid.';
            $this->errors['code'] = 404;
            return false;
        }
        $this->currentUser = $user;
        return true;
    }

    public function addPhraseAction(Request $request, $phrase)
    {
        if (!$this->validateToken($request->get('secretToken')) || !$this->validatePhrase($phrase)) {
            return new JsonResponse($this->errors, $this->errors['code']);
        }
        $phrases = $this->currentUser->alertPhrases;
        if (!in_array($phrase, $phrases)) {
            $phrases[] = $phrase;
            $this->currentUser->alertPhrases = $phrases;
            $this->repo->update($this->currentUser);
        }
        return new JsonResponse([
            'isEnabled' => $this->currentUser->notificationsEnabled,
            'phrases' => $this->currentUser->alertPhrases ?: []
        ], 201);
    }

    private function validatePhrase($phrase)
    {
        $phrase = trim($phrase);
        if (empty($phrase)) {
            $this->errors['error'] = 'Parameter `phrase` is required.';
            $this->errors['code'] = 400;
            return false;
        }
        if (strlen($phrase) < 3) {
            $this->errors['error'] = 'Parameter `phrase` is too short.';
            $this->errors['code'] = 400;
            return false;
        }
        return true;
    }

    public function delPhraseAction(Request $request, $phrase)
    {
        if (!$this->validateToken($request->get('secretToken'))) {
            return new JsonResponse($this->errors, $this->errors['code']);
        }
        $phrases = $this->currentUser->alertPhrases;
        if (in_array($phrase, $phrases)) {
            $keys = array_keys($phrases, $phrase);
            foreach ($keys as $key) unset($phrases[$key]);
            $this->currentUser->alertPhrases = $phrases;
            $this->repo->update($this->currentUser);
        }
        return new JsonResponse([
            'isEnabled' => $this->currentUser->notificationsEnabled,
            'phrases' => $this->currentUser->alertPhrases ?: []
        ]);
    }

    public function updateAction(Request $request)
    {
        if (!$this->validateToken($request->get('secretToken'))) {
            return new JsonResponse($this->errors, $this->errors['code']);
        }
        $isEnabled = filter_var($request->get('isEnabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->currentUser->notificationsEnabled = $isEnabled;
        $this->repo->update($this->currentUser);
        return new JsonResponse([
            'isEnabled' => $this->currentUser->notificationsEnabled,
            'phrases' => $this->currentUser->alertPhrases ?: []
        ]);
    }
}
