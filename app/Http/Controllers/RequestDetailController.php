<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\RequestDetail;
use App\Models\BudgetRequest;

class RequestDetailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $details = RequestDetail::with([
            'budgetRequest'
        ])->latest()->get();

        return view('requestdetail.index', compact(
            'details'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $budgetRequests = BudgetRequest::all();

        return view('requestdetail.create', compact(
            'budgetRequests'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'budget_request_id' =>
                'required|exists:budget_request,id',

            'description' =>
                'required',

            'amount' =>
                'required|numeric|min:0',

            'qty' =>
                'required|numeric|min:1'

        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE DETAIL
        |--------------------------------------------------------------------------
        */

        RequestDetail::create([

            'budget_request_id' =>
                $request->budget_request_id,

            'description' =>
                strtoupper($request->description),

            'amount' =>
                $request->amount,

            'qty' =>
                $request->qty,

            'total' =>
                $request->amount * $request->qty,

            'notes' =>
                $request->notes

        ]);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/request-detail')
            ->with(
                'success',
                'Request detail berhasil dibuat'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $detail = RequestDetail::with([
            'budgetRequest'
        ])->findOrFail($id);

        return view('requestdetail.show', compact(
            'detail'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $detail = RequestDetail::findOrFail($id);

        $budgetRequests = BudgetRequest::all();

        return view('requestdetail.edit', compact(
            'detail',
            'budgetRequests'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $detail = RequestDetail::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'budget_request_id' =>
                'required|exists:budget_request,id',

            'description' =>
                'required',

            'amount' =>
                'required|numeric|min:0',

            'qty' =>
                'required|numeric|min:1'

        ]);

        /*
        |--------------------------------------------------------------------------
        | UPDATE DETAIL
        |--------------------------------------------------------------------------
        */

        $detail->update([

            'budget_request_id' =>
                $request->budget_request_id,

            'description' =>
                strtoupper($request->description),

            'amount' =>
                $request->amount,

            'qty' =>
                $request->qty,

            'total' =>
                $request->amount * $request->qty,

            'notes' =>
                $request->notes

        ]);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/request-detail')
            ->with(
                'success',
                'Request detail berhasil diupdate'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $detail = RequestDetail::findOrFail($id);

        $detail->delete();

        return redirect('/request-detail')
            ->with(
                'success',
                'Request detail berhasil dihapus'
            );
    }
}