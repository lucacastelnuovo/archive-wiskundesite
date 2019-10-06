<?php

namespace App\Http\Controllers;

use App\Helpers\HttpStatusCodes;
use App\Models\User;
use App\Validators\ValidatesAccountsRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountsController extends Controller
{
    use ValidatesAccountsRequests;

    /**
     * View user account.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        return response()->json(
            $user,
            HttpStatusCodes::SUCCESS_OK
        );
    }

    /**
     * Update user account.
     *
     * @param Request $request
     *
     * @throws
     *
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        $this->validateUpdate($request, $user);

        $user->update([
            'name' => $request->get('name', $user->name),
            'email' => $request->get('email', $user->email),
            'password' => $request->has('password') ? Hash::make($request->input('password')) : $user->password,
        ]);

        $user->save();

        // If user changes password revoke all refresh_tokens
        if ($request->has('password')) {
            Session::where('user_id', $user->id)->delete();
        }

        return response()->json(
            $user,
            HttpStatusCodes::SUCCESS_OK
        );
    }

    /**
     * Delete user account.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function delete(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        Session::where('user_id', $user->id)->delete();

        $user->delete();

        // TODO: Send delete account email

        return response()->json(
            null,
            HttpStatusCodes::SUCCESS_NO_CONTENT
        );
    }

    /**
     * Show all sessions.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function showSessions(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $refresh_tokens = $user->refreshTokens();

        return response()->json(
            $refresh_tokens->get(),
            HttpStatusCodes::SUCCESS_OK
        );
    }

    /**
     * Revoke the refresh_token.
     *
     * @param Request $request
     *
     * @throws
     *
     * @return JsonResponse
     */
    public function revoke(Request $request)
    {
        $this->validateRevoke($request);

        $session_uuid = $request->get('session_uuid');

        if ($session_uuid === $request->session_uuid) {
            return response()->json(
                [
                    'error' => "you can't revoke current session, please use logout endpoint",
                ],
                HttpStatusCodes::CLIENT_ERROR_BAD_REQUEST
            );
        }

        $session = Session::findOrFail($request->session_uuid);

        if (!$session || $session->user_id !== $request->user_id) {
            return $this->respondError(
                'session not found',
                HttpStatusCodes::CLIENT_ERROR_UNAUTHORIZED
            );
        }

        $session->delete();

        return $this->respondSuccess(
            'session_revoked',
            HttpStatusCodes::SUCCESS_OK
        );
    }
}
