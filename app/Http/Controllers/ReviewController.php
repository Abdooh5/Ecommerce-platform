<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store_ReviewRequest;
use App\Http\Requests\Update_ReviewRequest;
use App\Models\Review;
use finfo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class ReviewController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $this->authorize('viewAny', Review::class);
        $reviews = Review::latest()->paginate(25);
        return response()->json($reviews);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Store_ReviewRequest $request)
    {
        $this->authorize('create', Review::class);
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['approved'] = false;
        // Check if the user has already reviewed the product
        $check_is_exist = Review::where('product_id', $validated['product_id'])->where('user_id', $validated['user_id'])->first();
        if ($check_is_exist) {
            return response()->json(['error' => 'You have already reviewed this product'], 400);
        }
        $review = Review::create($validated);
        return response()->json($review);
    }

    /**
     * Display the specified resource.
     */
    public function show(Review $review)
    {
        $this->authorize('view', $review);
        try {
            $review = Review::findOrFail($review->id);
            return response()->json($review);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Review not found'], 404);
        }


    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update_ReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);
        try {
            $review = Review::findOrFail($review->id);
            $validated = $request->validated();
            $validated['approved'] = $validated['approved'] ?? false;
            $review->update($validated);
            return response()->json($review);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Review not found'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        try {
            $review = Review::findOrFail($review->id);
            $review->delete();
            return response()->json(['message' => 'Review deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Review not found'], 404);
        }
    }
    public function approveReview($id)
    {

        try {
            $review = Review::findOrFail($id);
            $this->authorize('approve', $review);
            $review->approved = true;
            $review->save();
            return response()->json(['message' => 'Review approved successfully', 'review' => $review]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Review not found'], 404);
        }
    }
}
