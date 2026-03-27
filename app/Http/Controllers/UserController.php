<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\UserAccurateAPI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('branches')->get();
        return view('user.index', compact('users'));
    }


    public function create()
    {
        $branches = Branch::all();
        return view('user.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'role' => 'required|in:super_admin,owner,kepala_toko,akunting,marketing',
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'branches' => 'array',
            'branches.*' => 'exists:branches,id',
            'accurate_credentials' => 'array',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->role = $request->role;
        $user->username = $request->username;
        $user->password = Hash::make($request->password);
        $user->save();

        $branchIds = array_map('intval', $request->branches ?? []);
        $user->branches()->sync($branchIds);
        $this->syncAccurateCredentials($request, $user, $branchIds);

        return redirect()->route('user.index')->with('success', 'User berhasil ditambahkan');
    }

    public function edit($id)
    {
        $user = User::with(['branches', 'accurateApis'])->findOrFail($id);
        $branches = Branch::all();
        $accurateApisByBranch = $user->accurateApis->keyBy('branch_id');

        return view('user.edit', compact('user', 'branches', 'accurateApisByBranch'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'role' => 'required|in:super_admin,owner,kepala_toko,akunting,marketing',
            'username' => 'required|unique:users,username,' . $user->id,
            'password' => 'nullable|min:6',
            'branches' => 'array',
            'branches.*' => 'exists:branches,id',
            'accurate_credentials' => 'array',
        ]);

        $user->name = $request->name;
        $user->role = $request->role;
        $user->username = $request->username;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();
        $branchIds = array_map('intval', $request->branches ?? []);
        $user->branches()->sync($branchIds);
        $this->syncAccurateCredentials($request, $user, $branchIds);

        return redirect()->route('user.index')->with('success', 'User berhasil diperbarui');
    }

    private function syncAccurateCredentials(Request $request, User $user, array $branchIds): void
    {
        $credentialsInput = $request->input('accurate_credentials', []);
        $requiresCredential = in_array($request->role, ['super_admin', 'owner', 'kepala_toko', 'akunting'], true);

        if (!empty($branchIds) && $requiresCredential) {
            $rules = [];
            foreach ($branchIds as $branchId) {
                $rules["accurate_credentials.$branchId.customer_id"] = 'required|string|max:255';
                $rules["accurate_credentials.$branchId.accurate_api_token"] = 'required|string';
                $rules["accurate_credentials.$branchId.accurate_signature_secret"] = 'required|string';
            }
            $request->validate($rules);
        }

        $user->accurateApis()->whereNotIn('branch_id', $branchIds)->delete();

        foreach ($branchIds as $branchId) {
            $branchCredential = $credentialsInput[$branchId] ?? [];
            $customerId = $branchCredential['customer_id'] ?? null;
            $apiToken = $branchCredential['accurate_api_token'] ?? null;
            $signatureSecret = $branchCredential['accurate_signature_secret'] ?? null;

            $hasValue = filled($customerId) || filled($apiToken) || filled($signatureSecret);
            if (!$requiresCredential && !$hasValue) {
                $user->accurateApis()->where('branch_id', $branchId)->delete();
                continue;
            }

            UserAccurateAPI::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                ],
                [
                    'customer_id' => $customerId,
                    'accurate_api_token' => $apiToken,
                    'accurate_signature_secret' => $signatureSecret,
                ]
            );
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('user.index')->with('success', 'User berhasil dihapus');
    }

    public function editProfile()
    {
        $user = Auth::user();
        return view('user.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = User::find(Auth::user()->id);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'password' => 'nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->route('user.profile')->withErrors($validator)->withInput();
        }

        $user->name = $request->input('name');
        $user->username = $request->input('username');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return redirect()->route('user.profile')->with('success', 'Profil berhasil diperbarui.');
    }
}
