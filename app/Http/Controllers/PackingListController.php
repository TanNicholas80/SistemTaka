<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barcode;
use App\Models\PackingList;

class PackingListController extends Controller
{
    public function index()
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->get();

        return view('packing_list.index', compact('packingList'));
    }

    public function show($id)
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->findOrFail($id);

        $barcodes = $packingList->barcodes()
            ->forBranch($branchId)
            ->get();

        return view('packing_list.detail', [
            'data' => $packingList,
            'barcodes' => $barcodes
        ]);
    }

    public function create()
    {
        return view('packing_list.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'npl' => 'required|regex:/^\d{10}$/|unique:packing_list,npl',
        ], [
            'npl.regex' => 'No. Packing List harus terdiri dari 10 digit angka.',
            'npl.unique' => 'No. Packing List tersebut sudah terinput.',
        ]);

        $branchId = session('active_branch');
        $branch = \App\Models\Branch::find($branchId);
        if ($branch) {
            $validated['kode_customer'] = $branch->customer_id;
        }
        $validated['status'] = PackingList::STATUS_PENDING; // Status di-set sistem, default pending

        PackingList::create($validated);

        return redirect()->route('packing-list.create')
            ->with('success', 'Packing List berhasil ditambahkan');
    }

    public function edit($id)
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->findOrFail($id);

        return view('packing_list.edit', compact('packingList'));
    }

    public function update(Request $request, $id)
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->findOrFail($id);

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'npl' => 'required|regex:/^\d{10}$/',
        ], [
            'npl.regex' => 'No. Packing List harus terdiri dari 10 digit angka.',
        ]);

        // Status tidak bisa diubah user, hanya terupdate oleh sistem (matching barcode)
        $packingList->update($validated);

        return redirect()->route('packing-list.index')->with('success', 'Packing List berhasil diperbarui');
    }

    public function destroy($id)
    {
        $branchId = session('active_branch');
        $packingList = PackingList::forBranch($branchId)->findOrFail($id);

        $packingList->delete();

        return redirect()->route('packing-list.index')->with('success', 'Packing List berhasil dihapus');
    }
}
