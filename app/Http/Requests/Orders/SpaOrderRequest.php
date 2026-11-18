<?php

namespace App\Http\Requests\Orders;

class SpaOrderRequest extends OrderRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            // Used to build the voucher numbers.
            'id' => ['required'],
        ];
    }
}
