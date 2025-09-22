<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\SessionKey;
use App\Enums\ViewPaths\Vendor\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\LoginRequest;
use App\Repositories\VendorWalletRepository;
use App\Services\VendorService;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{


    public function __construct(
        private readonly VendorRepositoryInterface $vendorRepo,
        private readonly VendorService             $vendorService,
        private readonly VendorWalletRepository    $vendorWalletRepo,

    )
    {
        $this->middleware('guest:seller', ['except' => ['logout']]);
    }



    public function getLoginView(): View
    {
        return view(Auth::VENDOR_LOGIN[VIEW]);
    }

    public function login(LoginRequest $request): JsonResponse
    {

        $vendor = $this->vendorRepo->getFirstWhere(['identity' => $request['email']]);
        $passwordCheck = Hash::check($request['password'],$vendor['password']);
        if (!$vendor){
            return response()->json(['error'=>translate('credentials_doesnt_match').'!']);
        }
        if ($passwordCheck && $vendor['status'] !== 'approved') {
            return response()->json(['status' => $vendor['status']]);
        }
        if ($this->vendorService->isLoginSuccessful($request->email, $request->password, $request->remember)) {
            if ($this->vendorWalletRepo->getFirstWhere(params:['id'=>auth('seller')->id()]) === false) {
                $this->vendorWalletRepo->add($this->vendorService->getInitialWalletData(vendorId:auth('seller')->id()));
            }
            Toastr::info(translate('welcome_to_your_dashboard').'.');
            return response()->json([
                'success' =>translate('login_successful') . '!',
                'redirectRoute'=>route('vendor.dashboard.index'),
            ]);
        }else{
            return response()->json(['error'=>translate('credentials_doesnt_match').'!']);

        }
    }

    public function logout(): RedirectResponse
    {
        $this->vendorService->logout();
        Toastr::success(translate('logged_out_successfully').'.');
        return redirect()->route('vendor.auth.login');
    }
}
