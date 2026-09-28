<?php

namespace App\Http\Controllers;

use App\Models\TailoredResume;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TailoredResumeController extends Controller
{
    public function destroy(TailoredResume $tailoredResume): RedirectResponse
    {
        abort_unless($tailoredResume->user_id === Auth::id(), 403);

        $tailoredResume->delete();

        return back()->with('status', 'CV adaptado eliminado.');
    }
}
