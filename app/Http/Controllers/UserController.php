<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
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
            'accurate_api_token' => 'nullable|required_if:role,super_admin,owner,kepala_toko,akunting',
            'accurate_signature_secret' => 'nullable|required_if:role,super_admin,owner,kepala_toko,akunting',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->role = $request->role;
        $user->username = $request->username;
        $user->password = Hash::make($request->password);
        // Memanggil mutator model User untuk enkripsi credential Accurate
        $user->accurate_api_token = $request->accurate_api_token;
        $user->accurate_signature_secret = $request->accurate_signature_secret;
        $user->save();

        $user->branches()->sync($request->branches ?? []);

        return redirect()->route('user.index')->with('success', 'User berhasil ditambahkan');
    }

    public function edit($id)
    {
        $user = User::with('branches')->findOrFail($id);
        $branches = Branch::all();
        return view('user.edit', compact('user', 'branches'));
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
            'accurate_api_token' => 'nullable|required_if:role,super_admin,owner,kepala_toko,akunting',
            'accurate_signature_secret' => 'nullable|required_if:role,super_admin,owner,kepala_toko,akunting',
        ]);

        $user->name = $request->name;
        $user->role = $request->role;
        $user->username = $request->username;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        // Memanggil mutator model User untuk enkripsi credential Accurate
        $user->accurate_api_token = $request->accurate_api_token;
        $user->accurate_signature_secret = $request->accurate_signature_secret;
        $user->save();
        $user->branches()->sync($request->branches ?? []);

        return redirect()->route('user.index')->with('success', 'User berhasil diperbarui');
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
