<?php

namespace App\Http\Controllers;

use App\Models\BarangMasuk;
use App\Models\Barcode;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BarangMasukController extends Controller
{
    public function index()
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        $branch = Branch::find(session('active_branch'));

        $barangMasuk = BarangMasuk::with(['barcode' => function ($query) use ($branch) {
            if ($branch) {
                $query->where('kode_customer', $branch->customer_id);
            }
        }])
            ->forBranch()
            ->orderByDesc('tanggal')
            ->get();

        return view('barang_masuk.index', compact('barangMasuk', 'branch'));
    }

    public function create()
    {
        return view('barang_masuk.create');
    }

    public function store(Request $request)
    {
        $activeBranchId = session('active_branch');
        if (!$activeBranchId) {
            return back()->with('error', 'Cabang belum dipilih.');
        }

        $branch = Branch::find($activeBranchId);
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        // Ambil hanya 10 karakter pertama dari barcode
        if ($request->filled('nbrg')) {
            $barcode = explode(';', $request->input('nbrg'))[0];
            $request->merge(['nbrg' => substr(trim($barcode), 0, 10)]);
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'nbrg' => [
                'required',
                'string',
                'max:20',
                'unique:barang_masuk,nbrg',
                Rule::exists('barcodes', 'barcode')->where('kode_customer', $branch->customer_id),
            ],
        ], [
            'nbrg.unique' => 'No. Barang tersebut sudah terinput.',
            'nbrg.exists' => 'No. Barang tidak ditemukan di data Barcode untuk cabang ini.',
        ]);

        // Simpan data dengan kode_customer cabang aktif
        BarangMasuk::create([
            'tanggal' => $validated['tanggal'],
            'nbrg' => $validated['nbrg'],
            'kode_customer' => $branch->customer_id,
        ]);

        return redirect()->route('barang-masuk.create')->with('success', 'Barang Masuk berhasil ditambahkan');
    }

    public function edit(BarangMasuk $barangMasuk)
    {
        return view('barang_masuk.edit', compact('barangMasuk'));
    }

    public function update(Request $request, $id)
    {
        $barangMasuk = BarangMasuk::findOrFail($id);
        $branch = Branch::find(session('active_branch'));
        if (!$branch) {
            return back()->with('error', 'Cabang tidak valid.');
        }

        if ($request->filled('nbrg')) {
            $barcode = explode(';', $request->input('nbrg'))[0];
            $request->merge(['nbrg' => substr(trim($barcode), 0, 10)]);
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'nbrg' => [
                'required',
                'string',
                'max:20',
                Rule::unique('barang_masuk', 'nbrg')->ignore($barangMasuk->id),
                Rule::exists('barcodes', 'barcode')->where('kode_customer', $branch->customer_id),
            ],
        ], [
            'nbrg.unique' => 'No. Barang tersebut sudah terinput.',
            'nbrg.exists' => 'No. Barang tidak ditemukan di data Barcode untuk cabang ini.',
        ]);

        $barangMasuk->update($validated);

        return redirect()->route('barang-masuk.index')->with('success', 'Barang Masuk berhasil diperbarui');
    }

    public function destroy(BarangMasuk $barangMasuk)
    {
        $barangMasuk->delete();
        return redirect()->route('barang-masuk.index')->with('success', 'Barang Masuk berhasil dihapus');
    }
}
