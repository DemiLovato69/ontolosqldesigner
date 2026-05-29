<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SupportRequest;
use App\Jobs\SendSupportEmail;
use Illuminate\Http\JsonResponse;

class SupportController extends Controller
{
    public function send(SupportRequest $request): JsonResponse
    {
        $data = $request->validated();

        SendSupportEmail::dispatch($data['message'], $data['email'] ?? null);

        return $this->success(['status' => true]);
    }
}
