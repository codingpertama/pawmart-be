<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->role == 'admin') {
            // admin dapat semua pesanan beserta data user dan itemnya
            $orders = Order::with(['user', 'orderItems.product'])->latest()->get();
        } else {
            // user hanya dapat melihat pesanan milik sendiri
            $orders = Order::with(['orderItems.product'])->where('user_id', Auth::id())->latest()->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar pesanan berhasil diambi',
            'data' => $orders,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validasi input alamat pengiriman
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // Ambil semua item cart milik user yang sedang login
        $carts = Cart::with('product')->where('user_id', Auth::id())->get();

        // tidak bisa checkout kalau cart kosong
        if ($carts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart masih kosong, tidak bisa checkout',
                'data' => null,
            ], 422);
        }

        // Validasi stok SEMUA produk sebelum mulai transaksi
        foreach ($carts as $cart) {
            if ($cart->product->stock < $cart->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok "'.$cart->product->name.'" tidak mencukupi. '.'Stok tersedia: '.$cart->product->stock.', dibutuhkan: '.$cart->quantity,
                    'data' => null,
                ], 422);
            }
        }

        try {
            $order = DB::transaction(function () use ($carts, $request) {
                // hitung total harga dari semua item di cart
                $totalPrice = $carts->sum(function ($cart) {
                    return $cart->quantity * $cart->product->price;
                });

                // 1. Buat record order baru
                $order = Order::create([
                    'user_id' => Auth::id(),
                    'total_price' => $totalPrice,
                    'status' => 'diproses', // default status saat checkout
                    'shipping_address' => $request->shipping_address,
                    'payment_proof' => null, // bukti bayar dikirim terpisah
                ]);

                // 2. Loop setiap item cart, buat order_item & kurangi stok
                foreach ($carts as $cart) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cart->product_id,
                        'quantity' => $cart->quantity,
                        'price' => $cart->product->price,
                    ]);

                    // Kurangi stok produk sesuai quantity yang dibeli
                    $cart->product->decrement('stock', $cart->quantity);
                }

                // 3. Hapus semua cart user setelah checkout berhasil
                Cart::where('user_id', Auth::id())->delete();

                return $order;
            });

            // Load relasi untuk response
            $order->load(['orderItems.product']);

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil! Silakan upload bukti pembayaran.',
                'data' => $order,
            ], 201);
        } catch (Exception $e) {
            // Kalau ada error apapun di dalam transaction,
            // semua perubahan otomatis di-rollback oleh Laravel
            return response()->json([
                'success' => false,
                'message' => 'Checkout gagal: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $order = Order::with(['user', 'orderItems.product'])->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
                'data' => null,
            ], 404);
        }

        // User biasa tidak boleh lihat pesanan orang lain
        if (Auth::user()->role !== 'admin' && $order->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke pesanan ini',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pesanan berhasil diambil',
            'data' => $order,
        ]);
    }

    // upload bukti pembayaran untuk pesanan tertentu
    public function uploadPayment(Request $request, $id)
    {
        // cari pesanan milik user yang login
        $order = Order::where('id', $id)->where('user_id', Auth::id())->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
                'data' => null,
            ], 404);
        }

        // Tidak bisa upload bukti kalau pesanan sudah selesai
        if ($order->status === 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan sudah selesai, tidak bisa upload bukti bayar',
                'data' => null,
            ], 422);
        }

        // Validasi file bukti bayar
        $validator = Validator::make($request->all(), [
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png|max:2048', // maks 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        // Hapus bukti bayar lama kalau ada (user re-upload)
        if ($order->payment_proof) {
            Storage::disk('public')->delete($order->payment_proof);
        }

        // Simpan file bukti bayar ke storage/app/public/payments/
        $path = $request->file('payment_proof')->store('payments', 'public');

        // Update kolom payment_proof di tabel orders
        $order->update(['payment_proof' => $path]);

        // Tambahkan URL lengkap untuk ditampilkan
        $order->payment_proof_url = Storage::url($path);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload',
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
                'data' => null,
            ], 404);
        }

        // Validasi status hanya boleh 'diproses' atau 'selesai'
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:diproses,selesai',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        $order->update(['status' => $request->status]);
        $order->load(['user', 'orderItems.product']);

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui menjadi '.$request->status,
            'data' => $order,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
