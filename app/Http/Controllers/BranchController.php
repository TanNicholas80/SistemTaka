<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::all();
        return view('branch.select', compact('branches'));
    }

    public function create()
    {
        return redirect()->route('branch.select');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255', 'unique:branches,customer_id'],
            'photo'       => ['nullable', 'image', 'max:2048'],
            'url_accurate' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('branches', 'public');
        }

        $branch = Branch::create($validated);

        Log::info('Toko dibuat', ['branch' => $branch->id]);

        return redirect()->route('branch.select')->with('success', 'Toko berhasil ditambahkan.');
    }

    public function edit(Branch $branch)
    {
        return redirect()->route('branch.select');
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255', 'unique:branches,customer_id,' . $branch->id],
            'photo'       => ['nullable', 'image', 'max:2048'],
            'url_accurate' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('photo')) {
            if ($branch->photo) {
                Storage::disk('public')->delete($branch->photo);
            }
            $validated['photo'] = $request->file('photo')->store('branches', 'public');
        }

        $branch->update($validated);

        Log::info('Toko diperbarui', ['branch' => $branch->id]);

        return redirect()->route('branch.select')->with('success', 'Toko berhasil diupdate.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->photo) {
            Storage::disk('public')->delete($branch->photo);
        }

        $branch->delete();

        Log::warning('Toko dihapus', ['branch' => $branch->id]);

        return redirect()->route('branch.select')->with('success', 'Toko berhasil dihapus.');
    }

    public function select(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'super_admin' || $user->role === 'owner') {
            $branches = Branch::all();
        } else {
            $branches = $user->branches;
        }

        return view('branch.select', compact('branches'));
    }

    public function choose(Request $request)
    {
        $branchId = $request->branch_id;
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'owner'])) {
            if (!$user->branches->pluck('id')->contains($branchId)) {
                return back()->with('error', 'Anda tidak punya akses ke cabang ini');
            }
        }

        $branch = Branch::find($branchId);
        if ($branch) {
            session([
                'active_branch' => $branchId,
                'active_branch_name' => $branch->name,
                'url_accurate' => $branch->url_accurate,
            ]);
            $successMessage = 'Berhasil memilih Toko ' . $branch->name . '.';
        } else {
            $successMessage = 'Toko tidak ditemukan.';
        }

        return redirect()->route('barang_master.index')->with('success', $successMessage);
    }
}
