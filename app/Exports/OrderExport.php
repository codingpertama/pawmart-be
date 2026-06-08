<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderExport implements FromCollection, WithHeadings, WithMapping
{
    private $rowNumber = 0;
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Order::with(['user', 'orderItems.product'])->latest()->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Pembeli',
            'Email',
            'Total Harga',
            'Status',
            'Alamat Pengiriman',
            'Jumlah Item',
            'Tanggal Pesan',
        ];
    }

    public function map($order): array
    {
        return [
            ++$this->rowNumber,
            $order->user->name,
            $order->user->email,
            'Rp. ' . number_format($order->total_price, 0, ',', '.'),
            $order->status,
            $order->shipping_address,
            $order->orderItems->count() . ' item',
            $order->created_at->format('d/m/Y H:i'),
        ];
    }
}
