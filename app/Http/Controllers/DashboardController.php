<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TrackingNumber;
use App\Models\Office;
use App\Models\Action;
use App\Models\PaperTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class DashboardController extends Controller
{
    public function reports(Request $request) {
        if (Auth::user()->role == 0) {

            $search = $request->input('search');
            $category = $request->input('category');
            $order = $request->input('order');

            $query = User::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('AvgProcessTime', 'LIKE', "%{$search}%");
                });
            }


            if ($category) {
                $query->orderBy($category, $order);
            }

            $users = $query->paginate(10);

            foreach ($users as $user) {
                $user->documents_created_count = Document::where('author', $user->name)->count();
                $user->documents_received_count = Document::where('received_by', $user->id)->count();
                $user->documents_released_count = Document::where('released_by', $user->id)->count();
                $user->documents_terminal_count = Document::where('terminal_by', $user->id)->count();
            }


            return view('admin.reports', compact('users'));
        }
    }

    public function dashboard(Request $request) {
        if (Auth::user()->role == 1) {
            $user = auth()->user();
            $userOffice = auth()->user()->office->id;
            $unusedTrackingNumbers = TrackingNumber::where('user_id', $user->id)
                                           ->where('status', 'Unused')
                                           ->first();

            $currentUserOfficeId = $user->office_id?? null;
            $forReceive = Document::whereHas('paperTrails', function ($query) use ($currentUserOfficeId) {
                $query->where('designated_office', '=', $currentUserOfficeId)
                      ->where('status', '=', 'released');
            })->get();


            $forRelease = Document::whereHas('paperTrails', function ($query) use ($currentUserOfficeId) {
                $query->where('designated_office', '=', $currentUserOfficeId)
                      ->where('status', '=', 'received');
            })->get();

            return view('user.dashboard', compact('unusedTrackingNumbers','forReceive','forRelease'));
        }
    }

    public function receive(Request $request){
        $user = auth()->user();
        try {
            $tracking_number = $request->input('tracking_number');
            $document = Document::where('tracking_number', $tracking_number)->firstOrFail();
            $paperTrails = PaperTrail::where('document_id', $document->id)->orderBy('created_at', 'desc')->get();

            // Check if the document has already been received by the user's office
            if ($document->status == 'received' && $document->current_office == $user->office->code) {
                return back()->with('error', "This document has already been received by your office.");
            }

            // Check if the document is designated to the current user's office
            if ($document->designated_office != $user->office_id) {
                return back()->with('error', "This document is not designated to your office.");
            }

            // Update the document's current office to the receiving office
            $document->current_office = $request->user()->office->code;
            $document->status = 'received';
            $document->received_by = $user->id;
            $document->save();

            return view('user.receive',compact('document','paperTrails','tracking_number'))->with('success',$document->title.' - '.$document->tracking_number.' ,has been received successfully. Tag as Terminal, If your office is the end of its paper trail.');
        } catch (ModelNotFoundException $e) {
            return back()->with('error',"We're sorry, but the request is Invalid Input.");
        }
    }

    public function release(Request $request){
        $user = auth()->user();
        try {
            $tracking_number = $request->input('tracking_number');
            $document = Document::where('tracking_number', $tracking_number)->firstOrFail();

            // Check if the document is designated to the current user's office
            if ($document->designated_office != $user->office_id) {
                return back()->with('error', "This document is not designated to your office.");
            }

            $offices = Office::all();
            $actions = Action::all();

            return view('user.release',compact('offices', 'actions','document','tracking_number'));
        } catch (ModelNotFoundException $e) {
            return back()->with('error',"We're sorry, but the request is Invalid Input.");
        }
    }

    public function tag(Request $request){
        $user = auth()->user();
        try {
            $tracking_number = $request->input('tracking_number');
            $document = Document::where('tracking_number', $tracking_number)->firstOrFail();

            // Check if the document is designated to the current user's office
            if ($document->designated_office != $user->office_id) {
                return back()->with('error', "This document is not designated to your office.");
            }

            return view('user.tag',compact('document','tracking_number'));
        } catch (ModelNotFoundException $e) {
            return back()->with('error',"We're sorry, but the request is Invalid Input.");
        }
    }

    public function track(Request $request){
        $user = auth()->user();
        try {
            $tracking_number = $request->input('tracking_number');
            $document = Document::where('tracking_number', $tracking_number)->firstOrFail();
            $paperTrails = PaperTrail::where('document_id', $document->id)->orderBy('created_at', 'desc')->get();

            $office = $user->office;

            // Get all user IDs in the office
            $officeUserIds = $office->users()->pluck('id');

            // Start building the query to retrieve the document
            $query = Document::query();

            // Filter documents that have been processed by any user in the office
            $query->where(function ($q) use ($officeUserIds) {
                $q->whereIn('received_by', $officeUserIds)
                ->orWhereIn('released_by', $officeUserIds)
                ->orWhereIn('terminal_by', $officeUserIds);
            });
            $query->orWhere('author', $user->name);

            // Add the tracking number filter
            $query->where('tracking_number', $tracking_number);

            // Retrieve the document
            $documents = $query->firstOrFail();

            if($documents != $document) {
                return back()->with('error', "This document is not processed in your office.");
            }

            return view('documents.track',compact('document','paperTrails','tracking_number'))->with('success',$document->title.' - '.$document->tracking_number.' ,has been track successfully.');
        } catch (ModelNotFoundException $e) {
            return back()->with('error',"We're sorry, but the request is Invalid Input.");
        }
    }
}
