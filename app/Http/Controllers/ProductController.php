<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Filter berdasarkan category_id jika parameter ada di URL
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter berdasarkan keyword pencarian nama produk
        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        // ambil semua hasil, urutkan dari terbaru
        $products = $query->latest()->get();

        // Tambahkan URL lengkap ke field image supaya FE bisa langsung pakai
        // sebelum = products/xxx.jpg
        // sesudah = /storage/products/xxx.jpg
        $products->transform(function ($product) {
            $product->image_url = $product->image
                ? Storage::url($product->image)
                : null;

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil',
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validasi semua input yang masuk
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id', // harus ada di tabel categories
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', // maks 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // Proses upload foto kalau ada file yang dikirim
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Simpan ke storage/app/public/products/
            // Laravel otomatis buat nama file unik
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // Buat produk baru dengan data yang sudah divalidasi
        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image' => $imagePath, // null kalau tidak ada foto
        ]);

        // Load relasi category untuk ditampilkan di response
        $product->load('category');

        // Tambahkan image_url ke response
        $product->image_url = $product->image
            ? Storage::url($product->image)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // cari produk beserta data kategorinya
        $product = Product::with('category')->find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
                'data' => null,
            ], 404);
        }

        // Tambahkan URL lengkap ke field image
        $product->image_url = $product->image
            ? Storage::url($product->image)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil diambil',
            'data' => $product,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Cari produk yang mau diedit, kalau tidak ada return 404
        $product = Product::find($id);
        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
                'data' => null,
            ], 404);
        }

        // Validasi input — semua field optional saat update (sometimes)
        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // Kalau ada foto baru yang dikirim
        if ($request->hasFile('image')) {
            // Hapus foto lama dulu biar storage tidak numpuk
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            // Simpan foto baru
            $product->image = $request->file('image')->store('products', 'public');
        }

        // Update field yang dikirim sekaligus pakai fill()
        $product->fill($request->only(['category_id', 'name', 'description', 'price', 'stock']));

        // Simpan semua perubahan ke database
        $product->save();

        // Load relasi category dan tambah image_url untuk response
        $product->load('category');

        $product->image_url = $product->image
            ? Storage::url($product->image)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan',
                'data'    => null,
            ], 404);
        }

        // Hapus foto dari storage kalau ada, sebelum hapus data
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        // Hapus produk dari database
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
            'data' => null,
        ]);
    }
}
