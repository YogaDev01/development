@extends('layouts.app')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <!-- TITLE -->
            <div class="col-sm-6">

                <h1 class="m-0 font-weight-bold">

                    Dashboard

                </h1>

            </div>

            <!-- BREADCRUMB -->
            <div class="col-sm-6">

                <ol class="breadcrumb float-sm-right">

                    <li class="breadcrumb-item active">

                        Dashboard

                    </li>

                </ol>

            </div>

        </div>

    </div>

</div>

<!-- MAIN CONTENT -->
<div class="content">

</div>

@endsection

@push('styles')

<style>

    /* DARK BACKGROUND */
    .content-wrapper,
    .content-header {
        background: #ffffff !important;
    }

    .content-header h1,
    .breadcrumb-item,
    .breadcrumb-item.active {
        color: #1e272e !important;
    }

    .breadcrumb-item a {
        color: #1e272e !important;
    }

    /* SMALL BOX RESPONSIVE */
    @media (max-width: 768px) {

        .small-box {
            margin-bottom: 15px;
        }

        .small-box .inner h3 {
            font-size: 32px;
        }

        .small-box .inner p {
            font-size: 14px;
        }

        .small-box .icon {
            font-size: 60px;
        }

    }

</style>

@endpush