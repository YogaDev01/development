<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProjectMonitoringController extends Controller
{
    public function index()
    {
        $projects = collect([
            [
                'id' => 1,
                'project' => 'BALITOWER OVERHEAD',
                'po' => 'BALITOWER BATCH 2025',
                'stage' => 'Documentation',
                'progress' => 75,
                'status' => 'On Progress'
            ],
            [
                'id' => 2,
                'project' => 'BTR ODC',
                'po' => 'BALITOWER BATCH 2025',
                'stage' => 'Implementation',
                'progress' => 50,
                'status' => 'On Progress'
            ]
        ]);

        return view(
            'project-monitoring.index',
            compact('projects')
        );
    }

    public function show($id)
    {
        return view(
            'project-monitoring.show'
        );
    }
}