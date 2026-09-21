<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ApiKey;
use App\Models\Customer;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

trait ResolvesApiCustomer
{
    protected function apiCustomer(Request $request): Customer
    {
        $customer = $request->attributes->get('api_customer');

        if (! $customer instanceof Customer) {
            throw new UnauthorizedHttpException('Bearer', 'Missing or invalid API key.');
        }

        return $customer;
    }

    protected function apiKey(Request $request): ApiKey
    {
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            throw new UnauthorizedHttpException('Bearer', 'Missing or invalid API key.');
        }

        return $key;
    }
}
