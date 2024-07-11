<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Verification;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function dashboard()
    {
        return view('frontend/dashboard');
    }

    public function profileEdit(Request $request)
    {
        $user = $request->user();
        return view('profile.edit', compact('user'));
    }

    public function profileSetting(Request $request)
    {
        $user = $request->user();
        return view('profile.setting', compact('user'));
    }

    public function verification(Request $request)
    {
        $verification = Verification::where('user_id', $request->user()->id)->first();
        $user = $request->user();
        return view('frontend.verification.index', compact('user', 'verification'));
    }

    public function verificationStore(Request $request)
    {
        $request->validate([
            'id_type' => 'required|in:NID,Passport,Driving License',
            'id_number' => 'required|string|max:255|unique:verifications,id_number,'.$request->user()->id.',user_id',
            'id_front_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'id_with_face_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $verification = Verification::where('user_id', $request->user()->id)->first();

        if ($verification) {
            unlink(base_path("public/uploads/verification_photo/").$verification->id_front_image);
            unlink(base_path("public/uploads/verification_photo/").$verification->id_with_face_image);
        }

        $manager = new ImageManager(new Driver());
        // id_front_image
        $id_front_image_name = $request->user()->id."-id_front_image".".". $request->file('id_front_image')->getClientOriginalExtension();
        $image = $manager->read($request->file('id_front_image'));
        $image->toJpeg(80)->save(base_path("public/uploads/verification_photo/").$id_front_image_name);
        // id_with_face_image
        $id_with_face_image_name = $request->user()->id."-id_with_face_image".".". $request->file('id_with_face_image')->getClientOriginalExtension();
        $image = $manager->read($request->file('id_with_face_image'));
        $image->toJpeg(80)->save(base_path("public/uploads/verification_photo/").$id_with_face_image_name);

        if ($verification) {
            $verification->update([
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
                'id_front_image' => $id_front_image_name,
                'id_with_face_image' => $id_with_face_image_name,
                'status' => 'Pending',
            ]);

            $notification = array(
                'message' => 'Id Verification request updated successfully.',
                'alert-type' => 'success'
            );

            return back()->with($notification);
        }

        Verification::create([
            'user_id' => $request->user()->id,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'id_front_image' => $id_front_image_name,
            'id_with_face_image' => $id_with_face_image_name,
        ]);

        $notification = array(
            'message' => 'Id Verification request submitted successfully.',
            'alert-type' => 'success'
        );

        return back()->with($notification);
    }

    public function deposit(Request $request)
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            if ($request->ajax()) {
                $query = Deposit::select('deposits.*');

                if ($request->status) {
                    $query->where('deposits.status', $request->status);
                }

                $query->orderBy('created_at', 'desc');

                $deposits = $query->get();

                return DataTables::of($deposits)
                    ->addIndexColumn()
                    ->editColumn('status', function ($row) {
                        if ($row->status == 'Pending') {
                            $status = '
                            <span class="badge bg-success">' . $row->status . '</span>
                            ';
                        } else if ($row->status == 'Approved') {
                            $status = '
                            <span class="badge text-white bg-info">' . $row->status . '</span>
                            ';
                        } else {
                            $status = '
                            <span class="badge bg-danger">' . $row->status . '</span>
                            ';
                        }
                        return $status;
                    })
                    ->rawColumns(['status'])
                    ->make(true);
            }

            $total_deposit = Deposit::where('user_id', $request->user()->id)->where('status', 'Approved')->sum('amount');

            return view('frontend.deposit.index', compact('total_deposit'));
        }
    }

    public function depositStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string|max:255',
            'number' => 'required|string|max:255',
            'transaction_id' => 'required|string|max:255',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => 400,
                'error'=> $validator->errors()->toArray()
            ]);
        }else{
            if ($request->amount < get_default_settings('min_deposit_amount') || $request->amount > get_default_settings('max_deposit_amount')) {
                return response()->json([
                    'status' => 401,
                    'error'=> 'The amount must be between '.get_default_settings('site_currency_symbol') .get_default_settings('min_deposit_amount').' and '.get_default_settings('site_currency_symbol') .get_default_settings('max_deposit_amount') .' to deposit'
                ]);
            }else {
                Deposit::create([
                    'user_id' => $request->user()->id,
                    'amount' => $request->amount,
                    'method' => $request->method,
                    'number' => $request->number,
                    'transaction_id' => $request->transaction_id,
                    'status' => 'Pending',
                ]);

                return response()->json([
                    'status' => 200,
                ]);
            }
        }
    }

    public function withdraw(Request $request)
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            if ($request->ajax()) {
                $query = Withdraw::select('withdraws.*');

                if ($request->status) {
                    $query->where('withdraws.status', $request->status);
                }

                $query->orderBy('created_at', 'desc');

                $withdraws = $query->get();

                return DataTables::of($withdraws)
                    ->addIndexColumn()
                    ->editColumn('status', function ($row) {
                        if ($row->status == 'Pending') {
                            $status = '
                            <span class="badge bg-success">' . $row->status . '</span>
                            ';
                        } else if ($row->status == 'Approved') {
                            $status = '
                            <span class="badge text-white bg-info">' . $row->status . '</span>
                            ';
                        } else {
                            $status = '
                            <span class="badge bg-danger">' . $row->status . '</span>
                            ';
                        }
                        return $status;
                    })
                    ->rawColumns(['status'])
                    ->make(true);
            }

            $total_withdraw = Withdraw::where('user_id', $request->user()->id)->where('status', 'Approved')->sum('amount');

            return view('frontend.withdraw.index', compact('total_withdraw'));
        }
    }

    public function withdrawStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string|max:255',
            'number' => 'required|string|max:255',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => 400,
                'error'=> $validator->errors()->toArray()
            ]);
        }else{
            if ($request->amount < get_default_settings('min_withdraw_amount') || $request->amount > get_default_settings('max_withdraw_amount')) {
                return response()->json([
                    'status' => 401,
                    'error'=> 'The amount must be between '.get_default_settings('site_currency_symbol') .get_default_settings('min_withdraw_amount').' and '.get_default_settings('site_currency_symbol') .get_default_settings('max_withdraw_amount').' to withdraw'
                ]);
            }else {
                if ($request->amount > $request->user()->withdraw_balance) {
                    return response()->json([
                        'status' => 402,
                        'error'=> 'Insufficient balance in your account to withdraw '.get_default_settings('site_currency_symbol') .$request->amount .' . Your current balance is '.get_default_settings('site_currency_symbol') .$request->user()->withdraw_balance
                    ]);
                }else {
                    Withdraw::create([
                        'user_id' => $request->user()->id,
                        'amount' => $request->amount,
                        'method' => $request->method,
                        'number' => $request->number,
                        'status' => 'Pending',
                    ]);

                    return response()->json([
                        'status' => 200,
                    ]);
                }
            }
        }
    }

    public function findWorks()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.find_works.index');
        }
    }

    public function workDetails()
    {
        return view('frontend.find_works.view');
    }

    public function workApplyStore()
    {
        return response()->json([
            'status' => 200,
        ]);
    }

    public function workListPending()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.work_list.pending');
        }
    }

    public function workListApproved()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.work_list.approved');
        }
    }

    public function workListRejected()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.work_list.rejected');
        }
    }

    public function postJob()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.post_job.create');
        }
    }

    public function postJobStore()
    {
        return response()->json([
            'status' => 200,
        ]);
    }

    public function jobListRunning()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.job_list.running');
        }
    }

    public function jobListCompleted()
    {
        $user = User::findOrFail(Auth::id());
        $hasVerification = $user->hasVerification('Approved');

        if (!$hasVerification) {
            return redirect()->route('verification')->with('error', 'Please verify your account first.');
        } else if ($user->status == 'Blocked' || $user->status == 'Banned') {
            return redirect()->route('dashboard')->with('error', 'Your account is blocked or banned.');
        } else {
            return view('frontend.job_list.completed');
        }
    }

    public function notifications()
    {
        return view('frontend.notifications.index');
    }

    public function refferal()
    {
        return view('frontend.refferal.index');
    }
}
