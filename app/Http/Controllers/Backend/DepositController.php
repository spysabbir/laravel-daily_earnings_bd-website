<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class DepositController extends Controller
{
    public function depositRequest(Request $request)
    {
        if ($request->ajax()) {
            $query = Deposit::where('status', 'Pending');

            $query->select('deposits.*')->orderBy('created_at', 'desc');

            $pendingRequest = $query->get();

            return DataTables::of($pendingRequest)
                ->addIndexColumn()
                ->editColumn('user_name', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->user->name . '</span>
                        ';
                })
                ->editColumn('user_email', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->user->email . '</span>
                        ';
                })
                ->editColumn('created_at', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . date('F j, Y  H:i:s A', strtotime($row->created_at)) . '</span>
                        ';
                })
                ->addColumn('action', function ($row) {
                    $btn = '
                    <button type="button" data-id="' . $row->id . '" class="btn btn-primary btn-xs viewBtn" data-bs-toggle="modal" data-bs-target=".viewModal">View</button>
                    ';
                return $btn;
                })
                ->rawColumns(['user_name', 'user_email', 'created_at', 'action'])
                ->make(true);
        }

        return view('backend.deposit.index');
    }

    public function depositRequestShow(string $id)
    {
        $deposit = Deposit::where('id', $id)->first();
        return view('backend.deposit.show', compact('deposit'));
    }

    public function depositRequestStatusChange(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'error' => $validator->errors()->toArray()
            ]);
        } else {
            $deposit = Deposit::findOrFail($id);
            $deposit->update([
                'status' => $request->status,
                'remarks' => $request->remarks,
                'rejected_by' => $request->status == 'Rejected' ? auth()->user()->id : NULL,
                'rejected_at' => $request->status == 'Rejected' ? now() : NULL,
                'approved_by' => $request->status == 'Approved' ? auth()->user()->id : NULL,
                'approved_at' => $request->status == 'Approved' ? now() : NULL,
            ]);

            $user = User::where('id', $deposit->user_id)->first();
            if ($request->status == 'Approved') {
                $user->update([
                    'deposit_balance' => $user->deposit_balance + $deposit->amount
                ]);
            }

            return response()->json([
                'status' => 200,
            ]);
        }
    }

    public function depositRequestRejected(Request $request)
    {
        if ($request->ajax()) {
            $query = Deposit::where('status', 'Rejected');

            $query->select('deposits.*')->orderBy('rejected_at', 'desc');

            $rejectedData = $query->get();

            return DataTables::of($rejectedData)
                ->addIndexColumn()
                ->editColumn('user_email', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->user->email . '</span>
                        ';
                })
                ->editColumn('rejected_by', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->rejectedBy->name . '</span>
                        ';
                })
                ->editColumn('rejected_at', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . date('F j, Y  H:i:s A', strtotime($row->rejected_at)) . '</span>
                        ';
                })
                ->addColumn('action', function ($row) {
                    $btn = '
                    <button type="button" data-id="' . $row->id . '" class="btn btn-danger btn-xs deleteBtn">Delete</button>
                    ';
                return $btn;
                })
                ->rawColumns(['user_email', 'rejected_by', 'rejected_at', 'action'])
                ->make(true);
        }

        return view('backend.deposit.index');
    }

    public function depositRequestApproved(Request $request)
    {
        if ($request->ajax()) {
            $query = Deposit::where('status', 'Approved');

            $query->select('deposits.*')->orderBy('approved_at', 'desc');

            $approvedData = $query->get();

            return DataTables::of($approvedData)
                ->addIndexColumn()
                ->editColumn('user_name', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->user->name . '</span>
                        ';
                })
                ->editColumn('user_email', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->user->email . '</span>
                        ';
                })
                ->editColumn('approved_by', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . $row->approvedBy->name . '</span>
                        ';
                })
                ->editColumn('approved_at', function ($row) {
                    return '
                        <span class="badge text-dark bg-light">' . date('F j, Y  H:i:s A', strtotime($row->approved_at)) . '</span>
                        ';
                })
                ->rawColumns(['user_name', 'user_email', 'approved_by', 'approved_at'])
                ->make(true);
        }

        return view('backend.deposit.approved');
    }

    public function depositRequestDelete(string $id)
    {
        $deposit = Deposit::findOrFail($id);

        $deposit->delete();
    }
}
