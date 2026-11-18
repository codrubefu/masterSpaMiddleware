<?php

namespace App\Http\Requests\Orders;

class HotelOrderRequest extends OrderRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'custom_info' => ['required', 'array'],
            'custom_info.start_date' => ['required', 'date'],
            'custom_info.end_date' => ['required', 'date', 'after:custom_info.start_date'],
            'items.*.product_meta_input._hotel_id.0' => ['required'],
            'items.*.product_meta_input._hotel_room_number.0' => ['required'],
            'items.*.product_meta_input._hotel_room_type_long.0' => ['required', 'string'],
            'items.*.product_meta_input._hotel_room_type' => ['required'],
            // Package name (e.g. "... mic dejun ... single").
            'items.*.meta_data.0.value' => ['required', 'string'],
        ];
    }
}
