<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesCustomer
{
    protected function currentCustomer(Request $request): Customer
    {
        /** @var User|null $user */
        $user = $request->user();
        $customer = $user?->customer;

        if ($customer === null) {
            throw new NotFoundHttpException('This account does not have a customer profile.');
        }

        return $customer;
    }
}
