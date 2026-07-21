<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BudgetRequest;

class DashboardController extends Controller
{
    public function index()
    {
        // Calculate date range (29th rule)
        $today = now();
        $currentDay = $today->day;
        
        if ($currentDay > 29) {
            $fromDate = $today->copy()->day(30);
            $toDate = $today->copy()->addMonth()->day(29);
        } else {
            $fromDate = $today->copy()->subMonth()->day(30);
            $toDate = $today->copy()->day(29);
        }

        // Get statistics
        $statistics = [
            'pending' => BudgetRequest::where('status', 'pending')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count(),
            'approved' => BudgetRequest::where('status', 'approved')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count(),
            'rejected' => BudgetRequest::where('status', 'rejected')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count(),
            'draft' => BudgetRequest::where('status', 'draft')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count(),
        ];

        return view('dashboard.index', compact('statistics'));
    }
}