<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Inertia\Inertia;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // ទាញយកហ្គេមដែល Active ព្រមជាមួយកញ្ចប់ពេជ្រ (Products) របស់វា
        $games = Game::with(['products' => function ($query) {
            $query->where('is_active', true)->orderBy('selling_price', 'asc');
        }])->where('is_active', true)->get();

        return Inertia::render('Home', [
            'games' => $games,
        ]);
    }
}