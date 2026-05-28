<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // ambil semua kategori, urutkan dari yang terbaru
        $categories = Category::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Data Kategori berhasil diambil',
            'data' => $categories,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validasi input: nama wajib diisi dan harus unik di tabel categories
        $validator =  Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        // kalau validasi gagal, kembalikan error 422
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // buat kategori baru dari data yang sudah di validasi
        $category = Category::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data'    => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // cari kategori berdasarkan ID, kalau tidak ada 404
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' =>  false,
                'message' => 'kategori tidak ditemukaan',
                'data' =>  null,
            ], 404);
        }

        // Validasi: nama wajib diisi, unik KECUALI untuk kategori yang sedang diedit
        // Rule "unique:categories,name,{$id}" mengecualikan baris dengan id ini
        $validator = Validator::make($request->all(), [
            'name' => "required|string|max:255|unique:categories,name,{$id}",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data'    => $validator->errors(),
            ], 422);
        }

        // update nama kategori
        $category->update([

            'name' => $request->name,
        ]);

        return response()->json([
            'success' =>  true,
            'message' => 'kategori berhasil di update',
            'data' => $category,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // cari kategori berdasarkan id
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan',
                'data'    => null,
            ], 404);
        }

        // Hapus kategori dari database
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus',
            'data'    => null,
        ], 200);
    }
}
