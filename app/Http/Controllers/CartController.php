<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $carts = Cart::with('product.category')->where('user_id', Auth::id())->latest()->get();

        // hitung total harga seluruh item di cart
        $totalPrice = $carts->sum(function ($cart) {
            return $cart->quantity * $cart->product->price;
        });

        return response()->json([
            'success' => true,
            'message' => 'isi Cart berhasil diambil',
            'data' => [
                'items' => $carts,
                'total_price' => $totalPrice,
                'total_items' => $carts->count(),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     * Kalau produk sudah ada di cart, quantity-nya ditambah (bukan buat baris baru)
     */
    public function store(Request $request)
    {
        // validasi input
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id', // produk harus ada di database
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // cek stok produk cukup atau tidak
        $product = Product::find($request->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' =>  false,
                'message' => 'Stok produk tidak mencukupi. Stok tersedia: ' . $product->stock,
                'data' => null,
            ], 422);
        }

        // cek apakah produk ini sudah ada di cart user yang sama
        $existingCart = Cart::where('user_id',  Auth::id())->where('product_id', $request->product_id)->first();

        if ($existingCart) {
            // kalau sudah ada => hitung total quantity baru
            $newQuantity = $existingCart->quantity + $request->quantity;

            // cek lagi apakah total quantity tidak melebihi stok
            if ($product->stock < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total quantity melebihi stok. Stok tersedia: ' . $product->stock . ', sudah di cart: ' . $existingCart->quantity,
                    'data' => null,
                ], 422);
            }

            // update quantity cart yang sudah ada
            $existingCart->update(['quantity' =>  $newQuantity]);
            $cart = $existingCart;
            $message = 'quantity cart berhasil diperbarui';
        } else {
            // kalau belom ada => buat data baru di cart
            $cart = Cart::create([
                'user_id'    => Auth::id(),
                'product_id' => $request->product_id,
                'quantity'   => $request->quantity,
            ]);
            $message = 'Produk berhasil ditambahkan ke cart';
        }

        // Load relasi untuk response
        $cart->load('product.category');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $cart,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // cari cart, pastikan milik user yang login
        $cart = Cart::where('id', $id)
            ->where('user_id', Auth::id()) // user tidak bisa edit cart orang lain
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'message' => 'item cart tidak ditemukan',
                'data' =>  null,
            ], 404);
        }

        // validasi quantity baru
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' =>  false,
                'message' =>  'validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // Cek stok produk untuk quantity baru
        $product = Product::find($cart->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' =>  false,
                'message' => 'Stok produk tidak mencukupi. Stok tersedia: ' . $product->stock,
                'data' =>  null,
            ], 422);
        }

        // Update quantity
        $cart->update(['quantity' => $request->quantity]);
        $cart->load('product.category');

        return response()->json([
            'success' => true,
            'message' => 'Quantity berhasil diperbarui',
            'data'    => $cart,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Cari cart sekaligus pastikan milik user yang login
        $cart = Cart::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Item cart tidak ditemukan',
                'data'    => null,
            ], 404);
        }

        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus dari cart',
            'data'    => null,
        ]);
    }

    public function clear() {
        // Hapus semua baris cart milik user yang login
        Cart::where('user_id', Auth::id())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart berhasil dikosongkan',
            'data'    => null,
        ]);
    }
}
