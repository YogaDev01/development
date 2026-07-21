<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\RequestAttachment;

class RequestAttachmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $attachments = RequestAttachment::with([
            'budgetRequest'
        ])->latest()->get();

        return view('requestattachment.index', compact(
            'attachments'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $attachment = RequestAttachment::findOrFail($id);

        return response()->download(
            storage_path(
                'app/public/' . $attachment->path
            ),
            $attachment->original_name
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $attachment = RequestAttachment::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | DELETE FILE
        |--------------------------------------------------------------------------
        */

        if (
            Storage::disk('public')->exists($attachment->path)
        ) {

            Storage::disk('public')->delete(
                $attachment->path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE DATABASE
        |--------------------------------------------------------------------------
        */

        $attachment->delete();

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return back()->with(
            'success',
            'Attachment berhasil dihapus'
        );
    }
}