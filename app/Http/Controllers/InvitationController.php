<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationMail;

class InvitationController extends Controller
{
    public function index()
    {
        return Invitation::all();
    }

    public function store(StoreInvitationRequest $request)
    {
        $validated = $request->validated();
        $invitation = Invitation::create($validated);

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        return response()->json($invitation, 201);
    }

    public function accept(AcceptInvitationRequest $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation || $invitation->accepted_at || $invitation->cancelled_at || $invitation->expires_at->isPast()) {
            return response()->json([
                'message' => 'This invitation link is invalid or has expired.',
            ], 410);
        }

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => $validated['password'],
            'company_id' => $invitation->company_id,
            'role' => $invitation->role,
        ]);

        $invitation->accepted_at = now();
        $invitation->save();

        $authToken = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $authToken,
        ], 201);
    }

    public function cancel(Invitation $invitation)
    {
        if ($invitation->accepted_at) {
            return response()->json([
                'message' => 'This invitation has already been accepted and cannot be cancelled.',
            ], 409);
        }

        if ($invitation->cancelled_at) {
            return response()->json([
                'message' => 'This invitation has already been cancelled.',
            ], 409);
        }

        $invitation->cancelled_at = now();
        $invitation->save();

        return response()->json($invitation, 200);
    }
}
