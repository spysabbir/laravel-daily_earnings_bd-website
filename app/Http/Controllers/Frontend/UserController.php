<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NidVerification;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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

    public function nidVerification(Request $request)
    {
        $nidVerification = NidVerification::where('user_id', $request->user()->id)->first();
        $user = $request->user();
        return view('frontend.nid-verification', compact('user', 'nidVerification'));
    }

    public function nidVerificationStore(Request $request)
    {
        $request->validate([
            'nid_number' => 'required|string|max:255|unique:nid_verifications,nid_number,'.$request->user()->id.',user_id',
            'nid_date_of_birth' => 'required|date|before:today',
            'nid_front_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'nid_with_face_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $nidVerification = NidVerification::where('user_id', $request->user()->id)->first();

        if ($nidVerification) {
            unlink(base_path("public/uploads/nid_verification_photo/").$nidVerification->nid_front_image);
            unlink(base_path("public/uploads/nid_verification_photo/").$nidVerification->nid_with_face_image);
        }

        $manager = new ImageManager(new Driver());
        // nid_front_image
        $nid_front_image_name = $request->user()->id."-nid_front_image".".". $request->file('nid_front_image')->getClientOriginalExtension();
        $image = $manager->read($request->file('nid_front_image'));
        $image->toJpeg(80)->save(base_path("public/uploads/nid_verification_photo/").$nid_front_image_name);
        // nid_with_face_image
        $nid_with_face_image_name = $request->user()->id."-nid_with_face_image".".". $request->file('nid_with_face_image')->getClientOriginalExtension();
        $image = $manager->read($request->file('nid_with_face_image'));
        $image->toJpeg(80)->save(base_path("public/uploads/nid_verification_photo/").$nid_with_face_image_name);

        if ($nidVerification) {
            $nidVerification->update([
                'nid_number' => $request->nid_number,
                'nid_date_of_birth' => $request->nid_date_of_birth,
                'nid_front_image' => $nid_front_image_name,
                'nid_with_face_image' => $nid_with_face_image_name,
                'status' => 'Pending',
            ]);

            $notification = array(
                'message' => 'NID Verification request updated successfully.',
                'alert-type' => 'success'
            );

            return back()->with($notification);
        }

        NidVerification::create([
            'user_id' => $request->user()->id,
            'nid_number' => $request->nid_number,
            'nid_date_of_birth' => $request->nid_date_of_birth,
            'nid_front_image' => $nid_front_image_name,
            'nid_with_face_image' => $nid_with_face_image_name,
        ]);

        $notification = array(
            'message' => 'NID Verification request submitted successfully.',
            'alert-type' => 'success'
        );

        return back()->with($notification);
    }

}
