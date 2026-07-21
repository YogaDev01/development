@extends('layouts.app')

@section('content')

<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <div class="col-sm-6">

                <h1 class="m-0">

                    Project Monitoring

                </h1>

            </div>

        </div>

    </div>

</div>

<div class="content">

    <div class="container-fluid">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">

                    Active Projects

                </h3>

            </div>

            <div class="card-body">

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>Project</th>
                            <th>PO</th>
                            <th>Current Stage</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($projects as $project)

                        <tr>

                            <td>{{ $project['project'] }}</td>

                            <td>{{ $project['po'] }}</td>

                            <td>

                                <span class="badge badge-warning">

                                    {{ $project['stage'] }}

                                </span>

                            </td>

                            <td>

                                {{ $project['progress'] }}%

                            </td>

                            <td>

                                <span class="badge badge-success">

                                    {{ $project['status'] }}

                                </span>

                            </td>

                            <td>

                                <a
                                    href="{{ route('project-monitoring.show', $project['id']) }}"
                                    class="btn btn-info btn-sm"
                                >

                                    <i class="fas fa-eye"></i>

                                </a>

                            </td>

                        </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection