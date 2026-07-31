<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarFeedService;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function show(string $token): Response
    {
        $user = User::where('calendar_token', $token)
            ->where('is_disabled', false)
            ->first();

        if (! $user) {
            abort(404);
        }

        $ics = (new CalendarFeedService($user))->get();

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="taskassist.ics"',
        ]);
    }
}
