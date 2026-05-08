<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReviewController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $reviewed = Review::where('user_id', $request->user()->id)->exists();

        return response()->json(['reviewed' => $reviewed]);
    }

    public function store(ReviewRequest $request): JsonResponse
    {
        Review::create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['status' => true]);
    }
}
