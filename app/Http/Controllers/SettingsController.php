<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request, AiManager $ai)
    {
        $provider = $ai->provider();

        return view('settings.index', [
            'aiConfigured' => $provider->isConfigured() && config('services.ai.key'),
            'aiProvider' => config('services.ai.key') ? 'external' : 'rule_based',
            'aiModel' => config('services.ai.model'),
            'limits' => [
                'max_pages' => \App\Services\Crawler\SiteCrawler::MAX_PAGES,
                'max_depth' => \App\Services\Crawler\SiteCrawler::MAX_DEPTH,
                'scans_per_hour' => 10,
                'competitors_per_project' => 2,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Settings saved.');
    }
}
