@extends('layouts.app')

@section('content')

<div class="content-header">

    <div class="container-fluid">

        <h1>

            Project Monitoring

        </h1>

    </div>

</div>

<div class="content">

<div class="container-fluid">

<div class="card">

<div class="card-body p-0">

<div class="row no-gutters">

    <!-- LEFT -->

    <div class="col-md-4 border-right">

        <div class="p-3 border-bottom">

            <h5 class="mb-0">

                BALITOWER OVERHEAD

            </h5>

            <small class="text-muted">

                BALITOWER BATCH 2025

            </small>

        </div>

        <div class="list-group list-group-flush">

            <a href="#"
               class="list-group-item active">

                Documentation

                <span class="float-right badge badge-warning">

                    Progress

                </span>

            </a>

            <a href="#"
               class="list-group-item">

                Survey

                <span class="float-right badge badge-success">

                    Done

                </span>

            </a>

            <a href="#"
               class="list-group-item">

                Implementation

                <span class="float-right badge badge-success">

                    Done

                </span>

            </a>

            <a href="#"
               class="list-group-item">

                BAST

                <span class="float-right badge badge-secondary">

                    Waiting

                </span>

            </a>

        </div>

    </div>

    <!-- RIGHT -->

    <div class="col-md-8">

        <div class="p-3 border-bottom">

            <h5>

                Documentation

            </h5>

        </div>

        <div class="p-3"
             style="height:500px;overflow-y:auto;">

            <div class="mb-3">

                <div class="small text-muted">

                    18 Jun 2026 - Yoga

                </div>

                <div class="alert alert-light">

                    Documentation Started

                </div>

            </div>

            <div class="mb-3">

                <div class="small text-muted">

                    19 Jun 2026 - Ketut

                </div>

                <div class="alert alert-info">

                    Customer Requested Revision

                </div>

            </div>

            <div class="mb-3">

                <div class="small text-muted">

                    20 Jun 2026 - Yoga

                </div>

                <div class="alert alert-success">

                    Document Updated

                </div>

            </div>

        </div>

        <div class="border-top p-3">

            <div class="input-group">

                <input
                    type="text"
                    class="form-control"
                    placeholder="Tulis aktivitas..."
                >

                <div class="input-group-append">

                    <button
                        class="btn btn-primary"
                    >

                        Kirim

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

</div>

</div>

</div>

</div>

@endsection